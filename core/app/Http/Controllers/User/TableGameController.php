<?php

namespace App\Http\Controllers\User;

use App\Games\TableGames\TableGameRules;
use App\Games\TableGames\TableGameService;
use App\Http\Controllers\Controller;
use App\Models\TableGame;
use App\Models\TableGameMessage;
use App\Models\TableGameMessageReaction;
use App\Models\TableGamePlayer;
use App\Models\TableGameInvite;
use App\Models\User;
use App\Models\UserBlock;
use App\Constants\Status;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TableGameController extends Controller
{
    public function index(Request $request, TableGameService $service)
    {
        if (!Schema::hasTable('table_games') || !Schema::hasTable('table_game_settings')) {
            return view('Template::user.table_games.pending', ['pageTitle' => 'Live Games']);
        }
        return view('Template::user.table_games.index', [
            'pageTitle' => 'Live Games',
            'catalog' => $service->catalog(),
            'tables' => $service->liveTables($request->user()),
            'invites' => $service->pendingInvites($request->user()),
        ]);
    }

    public function show(Request $request, string $id, TableGameService $service)
    {
        $table = TableGame::with(['players.user', 'currency'])->findOrFail($id);
        abort_unless($table->players->contains('user_id', $request->user()->id), 403);
        $walletCurrency = $request->user()->walletCurrency;
        $walletStake = $table->players->firstWhere('user_id', $request->user()->id)?->wallet_stake ?? $table->stake;
        $setting = Schema::hasTable('table_game_settings') ? \App\Models\TableGameSetting::where('game_type', $table->game_type)->first() : null;
        $guideDefaults = \App\Games\TableGames\TableGameGuides::for($table->game_type);
        $guide = array_merge($guideDefaults, array_filter((array) ($setting->guide_json ?? []), fn ($value) => $value !== null && $value !== '' && $value !== []));
        return view('Template::user.table_games.show', [
            'pageTitle' => TableGameRules::GAMES[$table->game_type]['name'],
            'table' => $table,
            'walletCurrency' => $walletCurrency,
            'walletStake' => $walletStake,
            'walletStakeFormatted' => $walletCurrency && bccomp((string) $table->stake, '0', 8) > 0
                ? $walletCurrency->formatMinor((new \App\Support\Money())->toMinor((string) $walletStake, (int) $walletCurrency->decimals), true)
                : null,
            'guide' => $guide,
            'commissionPercent' => (string) ($setting->commission_percent ?? 5),
            'turnSeconds' => (int) ($setting->turn_seconds ?? 60),
            'snapshot' => $service->snapshot($request->user(), $table->id),
        ]);
    }

    public function create(Request $request, TableGameService $service)
    {
        $rules = [
            'game_type' => 'required|string|max:40',
            'player_limit' => 'required|integer|min:2|max:4',
            'play_mode' => 'required|in:friendly,staked',
            'idempotency_key' => 'required|string|alpha_dash|min:12|max:64',
        ];
        $rules['stake'] = $request->input('play_mode') === 'staked'
            ? 'required|numeric|gt:0|decimal:0,8'
            : 'nullable|numeric|decimal:0,8';
        $data = $request->validate($rules);
        $stake = $data['play_mode'] === 'friendly' ? '0.00000000' : (string) $data['stake'];
        return $this->run(fn () => $service->create($request->user(), $data['game_type'], (int) $data['player_limit'], $stake, $data['idempotency_key']));
    }

    public function join(Request $request, string $id, TableGameService $service)
    {
        if (!$request->expectsJson()) {
            try {
                $table = $service->join($request->user(), $id);
                return redirect()->route('user.tables.show', $table->id);
            } catch (InvalidArgumentException $exception) {
                return back()->withErrors(['table' => $exception->getMessage()]);
            }
        }
        return $this->run(fn () => $service->join($request->user(), $id));
    }

    public function invite(Request $request, string $id, TableGameService $service)
    {
        $data = $request->validate(['username' => 'required|string|min:2|max:40']);
        try {
            $invite = $service->invite($request->user(), $id, $data['username']);
            return response()->json(['success' => true, 'message' => 'Invitation sent.', 'expires_at' => $invite->expires_at?->toIso8601String()]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function searchPlayers(Request $request, string $id)
    {
        $query = trim((string) $request->query('q', ''));
        if ($query === '' || mb_strlen($query) > 40) return response()->json(['users' => []]);

        $table = TableGame::findOrFail($id);
        abort_unless($table->status === 'waiting' && (int) $table->creator_id === (int) $request->user()->id, 403);

        $excluded = TableGamePlayer::where('table_game_id', $id)->pluck('user_id')->all();
        $pending = TableGameInvite::where('table_game_id', $id)->where('status', 'pending')->where('expires_at', '>', now())->pluck('invitee_id')->all();
        $excluded = array_unique([...$excluded, ...$pending, (int) $request->user()->id]);
        $exact = User::query()->whereRaw('LOWER(username) = ?', [mb_strtolower($query)])
            ->where('status', Status::USER_ACTIVE)
            ->whereNotIn('id', $excluded)
            ->first(['id', 'username']);
        $prefix = addcslashes($query, "\\%_") . '%';
        $users = $exact ? collect([$exact]) : User::query()
            ->where('username', 'like', $prefix)
            ->where('status', Status::USER_ACTIVE)
            ->whereNotIn('id', $excluded)
            ->orderBy('username')->limit(8)->get(['id', 'username']);

        return response()->json(['users' => $users->map(fn (User $user) => ['id' => $user->id, 'username' => $user->username])->values()]);
    }

    public function acceptInvite(Request $request, int $invite, TableGameService $service)
    {
        return $this->run(fn () => $service->acceptInvite($request->user(), $invite));
    }

    public function start(Request $request, string $id, TableGameService $service)
    {
        return $this->run(fn () => $service->start($request->user(), $id));
    }

    public function move(Request $request, string $id, TableGameService $service)
    {
        $data = $request->validate([
            'action' => 'required|array',
            'version' => 'nullable|integer|min:0',
            'idempotency_key' => 'required|string|alpha_dash|min:12|max:64',
        ]);
        return $this->run(fn () => $service->move($request->user(), $id, $data['action'], $data['idempotency_key'], $data['version'] ?? null));
    }

    public function leave(Request $request, string $id, TableGameService $service)
    {
        return $this->run(fn () => $service->standUp($request->user(), $id));
    }

    public function snapshot(Request $request, string $id, TableGameService $service)
    {
        $version = $request->query('since');
        return response()->json($service->snapshot($request->user(), $id, is_numeric($version) ? (int) $version : null));
    }

    public function messages(Request $request, string $id)
    {
        $player = $this->authorizeSeat($request, $id);
        if (Schema::hasColumn('table_game_players', 'last_seen_at')) {
            $player->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
        $blockedIds = Schema::hasTable('user_blocks')
            ? UserBlock::where('blocker_id', $request->user()->id)->pluck('blocked_id')
            : collect();
        $messages = TableGameMessage::with(['user:id,username,image', 'replyTo:id,user_id,body,kind,attachment_name', 'replyTo.user:id,username'])
            ->where('table_game_id', $id)
            ->when($blockedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('user_id', $blockedIds))
            ->orderByDesc('id')->limit(40)->get()->sortBy('id')->values();
        $ids = $messages->pluck('id');
        $reactions = TableGameMessageReaction::whereIn('message_id', $ids)->get()->groupBy('message_id');
        $otherSeenAt = Schema::hasColumn('table_game_players', 'last_seen_at')
            ? TableGamePlayer::where('table_game_id', $id)->where('user_id', '!=', $request->user()->id)->pluck('last_seen_at')->filter()->map(fn ($value) => \Carbon\Carbon::parse($value))
            : collect();
        return response()->json(['messages' => $messages->map(fn ($message) => $this->messagePayload($message, $reactions->get($message->id, collect()), (int) $request->user()->id, $otherSeenAt))->values()]);
    }

    public function sendMessage(Request $request, string $id)
    {
        $this->authorizeSeat($request, $id);
        $request->validate([
            'body' => 'nullable|string|max:1000|required_without:attachment',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,txt,csv,zip,mp3,wav,ogg,m4a,weba,webm,mp4,3gp|required_without:body',
            'kind' => 'nullable|in:text,file,voice',
            'reply_to_id' => 'nullable|integer',
        ]);
        $body = trim((string) $request->input('body', ''));
        if (!$request->hasFile('attachment') && $body === '') return response()->json(['message' => 'Write a message or attach a file.'], 422);
        $file = $request->file('attachment');
        $kind = $file ? ($request->input('kind') === 'voice' ? 'voice' : 'file') : 'text';
        if ($kind === 'voice' && (!$file || !str_starts_with((string) $file->getMimeType(), 'audio/'))) return response()->json(['message' => 'Voice notes must be an audio recording.'], 422);
        $replyToId = null;
        if ($request->filled('reply_to_id') && Schema::hasColumn('table_game_messages', 'reply_to_id')) {
            $replyToId = TableGameMessage::where('table_game_id', $id)->whereKey($request->input('reply_to_id'))->value('id');
        }
        $path = $file?->storeAs('table-game-chat/' . $id, Str::uuid() . '.' . strtolower($file->getClientOriginalExtension() ?: 'bin'), 'local');
        try {
            $message = TableGameMessage::create([
                'table_game_id' => $id, 'user_id' => $request->user()->id, 'kind' => $kind,
                'body' => $body !== '' ? $body : null, 'attachment_path' => $path, 'reply_to_id' => $replyToId,
                'attachment_name' => $file ? basename($file->getClientOriginalName()) : null,
                'attachment_mime' => $file?->getMimeType(), 'attachment_size' => $file?->getSize(),
            ]);
        } catch (\Throwable $exception) {
            if ($path) Storage::disk('local')->delete($path);
            throw $exception;
        }
        $message->load(['user:id,username,image', 'replyTo:id,user_id,body,kind,attachment_name', 'replyTo.user:id,username']);
        return response()->json(['message' => $this->messagePayload($message, collect(), (int) $request->user()->id, collect())], 201);
    }

    public function react(Request $request, string $id, int $message)
    {
        $this->authorizeSeat($request, $id);
        $data = $request->validate(['emoji' => 'required|string|in:👍,❤️,😂,👏,😮,🎉,👎']);
        $record = DB::transaction(function () use ($id, $message, $request, $data) {
            $record = TableGameMessage::where('table_game_id', $id)->whereKey($message)->lockForUpdate()->firstOrFail();
            $existing = TableGameMessageReaction::where('message_id', $record->id)->where('user_id', $request->user()->id)->lockForUpdate()->first();
            if ($existing?->emoji === $data['emoji']) $existing->delete();
            else TableGameMessageReaction::updateOrCreate(['message_id' => $record->id, 'user_id' => $request->user()->id], ['emoji' => $data['emoji']]);
            return $record;
        }, 3);
        $reactionRows = TableGameMessageReaction::where('message_id', $record->id)->get();
        return response()->json(['reactions' => $this->reactionPayload($reactionRows, (int) $request->user()->id)]);
    }

    public function reportMessage(Request $request, string $id, int $message)
    {
        $this->authorizeSeat($request, $id);
        $data = $request->validate(['reason' => 'nullable|string|max:255']);
        $record = TableGameMessage::where('table_game_id', $id)->whereKey($message)->firstOrFail();
        if (!$record->reported_by) {
            $record->forceFill([
                'reported_by' => $request->user()->id,
                'reported_reason' => $data['reason'] ?? null,
                'reported_at' => now(),
            ])->save();
        }
        return response()->json(['message' => 'Thanks - our team will review this message.']);
    }

    public function blockUser(Request $request, string $id, int $user)
    {
        $this->authorizeSeat($request, $id);
        abort_if((int) $user === (int) $request->user()->id, 422, 'You cannot block yourself.');
        abort_unless(TableGamePlayer::where('table_game_id', $id)->where('user_id', $user)->exists(), 404);
        UserBlock::firstOrCreate(['blocker_id' => $request->user()->id, 'blocked_id' => $user]);
        return response()->json(['message' => 'Player blocked. You will no longer see their messages at this table or future ones.']);
    }

    public function unblockUser(Request $request, int $user)
    {
        UserBlock::where('blocker_id', $request->user()->id)->where('blocked_id', $user)->delete();
        return response()->json(['message' => 'Player unblocked.']);
    }

    public function attachment(Request $request, string $id, int $message)
    {
        $this->authorizeSeat($request, $id);
        $record = TableGameMessage::where('table_game_id', $id)->whereKey($message)->firstOrFail();
        abort_unless($record->attachment_path && Storage::disk('local')->exists($record->attachment_path), 404);
        $mime = (string) $record->attachment_mime;
        if (str_starts_with($mime, 'audio/') || in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return response()->file(Storage::disk('local')->path($record->attachment_path), ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff']);
        }
        return Storage::disk('local')->download($record->attachment_path, $record->attachment_name ?: 'table-attachment', ['X-Content-Type-Options' => 'nosniff']);
    }

    private function authorizeSeat(Request $request, string $id): TableGamePlayer
    {
        $player = TableGamePlayer::where('table_game_id', $id)->where('user_id', $request->user()->id)->first();
        abort_unless($player, 403);
        return $player;
    }

    private function messagePayload(TableGameMessage $message, $reactions, int $userId, $otherSeenAt = null): array
    {
        $status = null;
        if ($message->user_id === $userId && $otherSeenAt !== null && $otherSeenAt->isNotEmpty()) {
            $status = $otherSeenAt->contains(fn ($seenAt) => $seenAt->greaterThanOrEqualTo($message->created_at)) ? 'read' : 'sent';
        }
        $replyTo = $message->replyTo ? [
            'id' => $message->replyTo->id,
            'name' => $message->replyTo->user?->username,
            'preview' => $message->replyTo->body ?: ($message->replyTo->kind === 'voice' ? 'Voice note' : ($message->replyTo->attachment_name ?: 'Attachment')),
        ] : null;
        return ['id' => $message->id, 'user_id' => $message->user_id, 'name' => $message->user?->username, 'avatar' => $message->user?->avatarUrl(), 'kind' => $message->kind,
            'body' => $message->body, 'time' => $message->created_at?->toIso8601String(), 'status' => $status, 'reply_to' => $replyTo,
            'attachment_name' => $message->attachment_name, 'attachment_mime' => $message->attachment_mime, 'attachment_url' => $message->attachment_path ? route('user.tables.chat.attachment', [$message->table_game_id, $message->id]) : null,
            'reactions' => $this->reactionPayload($reactions, $userId)];
    }

    private function reactionPayload($rows, int $userId): array
    {
        return collect($rows)->groupBy('emoji')->map(fn ($group, $emoji) => ['emoji' => $emoji, 'count' => $group->count(), 'mine' => $group->contains(fn ($reaction) => (int) $reaction->user_id === $userId)])->values()->all();
    }

    private function run(callable $action)
    {
        try {
            $table = $action();
            return response()->json(['success' => true, 'table' => ['id' => $table->id, 'game_type' => $table->game_type, 'status' => $table->status, 'version' => $table->version]]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        } catch (\Throwable $exception) {
            // Keep AJAX clients on the JSON contract while recording the real server-side cause.
            Log::error('Live table request failed unexpectedly.', [
                'exception' => $exception,
                'user_id' => request()->user()?->id,
            ]);
            return response()->json(['success' => false, 'message' => 'The live game could not be created right now. Please try again or contact support.'], 500);
        }
    }
}
