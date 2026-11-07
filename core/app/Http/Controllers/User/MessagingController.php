<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CoinVendor;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SupportAgent;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class MessagingController extends Controller
{
    /** Single unified inbox page (sidebar + active thread) - index() and show() both render it, show() just preselects a conversation. */
    public function index(MessagingService $messaging)
    {
        return $this->render(null, $messaging);
    }

    public function show(int $conversationId, MessagingService $messaging)
    {
        $userId = auth()->id();
        $conversation = Conversation::where('id', $conversationId)->forParticipant('user', $userId)->firstOrFail();
        $messaging->markRead($conversation, 'user', $userId);

        return $this->render($conversation, $messaging);
    }

    private function render(?Conversation $active, MessagingService $messaging)
    {
        $userId = auth()->id();
        $conversations = Conversation::forParticipant('user', $userId)
            ->orderByDesc('last_message_at')->orderByDesc('id')->get()
            ->map(fn (Conversation $conversation) => $this->presentConversation($conversation, $userId, $messaging));

        $vendors = Schema::hasTable('coin_vendors')
            ? CoinVendor::with('user:id,username')->where('status', 'active')->where('user_id', '!=', $userId)->get()
                ->filter(fn ($vendor) => $vendor->user)->map(fn ($vendor) => ['type' => 'user', 'id' => (int) $vendor->user_id, 'name' => $vendor->user->username, 'avatar' => $vendor->user->avatarUrl(), 'role' => 'Coin Vendor'])
            : collect();
        $agents = SupportAgent::where('is_active', true)->where('is_chat_agent', true)->get()
            ->map(fn ($agent) => ['type' => 'agent', 'id' => (int) $agent->id, 'name' => $agent->name, 'avatar' => $agent->photo ? asset('assets/images/support-agents/' . basename($agent->photo)) : null, 'role' => 'Support Agent']);
        $contacts = $vendors->concat($agents)->values();

        $activeData = null;
        $activeMessages = collect();
        if ($active) {
            [$otherType, $otherId] = $active->otherParticipant('user', $userId);
            $activeData = array_merge(['id' => $active->id, 'type' => $otherType], $this->participantInfo($otherType, $otherId), [
                'contact_methods' => $this->contactMethodsFor($otherType, $otherId),
            ]);
            $activeMessages = $active->messages()->get()->map(fn (Message $message) => $this->presentMessage($message, 'user', $userId));
        }

        $pageTitle = $active ? 'Conversation with ' . $activeData['name'] : 'Messages';
        return view('Template::user.messages.index', compact('pageTitle', 'conversations', 'contacts', 'activeData', 'activeMessages'));
    }

    /** Lightweight JSON refresh of the sidebar conversation list (used by the page's own polling, so switching/new messages update without a reload). */
    public function list(MessagingService $messaging)
    {
        $userId = auth()->id();
        $conversations = Conversation::forParticipant('user', $userId)
            ->orderByDesc('last_message_at')->orderByDesc('id')->get()
            ->map(fn (Conversation $conversation) => $this->presentConversation($conversation, $userId, $messaging));

        return response()->json(['conversations' => $conversations]);
    }

    public function start(Request $request, MessagingService $messaging)
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:user,agent'],
            'target_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $conversation = $messaging->startConversation('user', auth()->id(), $data['target_type'], (int) $data['target_id'], $data['body']);
        } catch (InvalidArgumentException $exception) {
            if ($request->expectsJson()) return response()->json(['message' => $exception->getMessage()], 422);
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        if ($request->expectsJson()) return response()->json(['id' => $conversation->id]);
        return to_route('user.messages.show', $conversation->id);
    }

    public function reply(Request $request, int $conversationId, MessagingService $messaging)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $userId = auth()->id();
        $conversation = Conversation::where('id', $conversationId)->forParticipant('user', $userId)->firstOrFail();

        try {
            $message = $messaging->sendMessage($conversation, 'user', $userId, $data['body']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => $this->presentMessage($message, 'user', $userId)]);
    }

    public function attachment(Request $request, int $conversationId, MessagingService $messaging)
    {
        $data = $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:15360'],
        ]);
        $userId = auth()->id();
        $conversation = Conversation::where('id', $conversationId)->forParticipant('user', $userId)->firstOrFail();

        try {
            $message = $messaging->sendAttachment($conversation, 'user', $userId, $data['attachment']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => $this->presentMessage($message, 'user', $userId)], 201);
    }

    public function downloadAttachment(int $conversationId, int $messageId)
    {
        $conversation = Conversation::where('id', $conversationId)->forParticipant('user', auth()->id())->firstOrFail();
        $message = $conversation->messages()->whereKey($messageId)->whereIn('kind', ['image', 'video'])->firstOrFail();
        abort_unless($message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Content-Type' => $message->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function poll(int $conversationId, MessagingService $messaging)
    {
        $userId = auth()->id();
        $conversation = Conversation::where('id', $conversationId)->forParticipant('user', $userId)->firstOrFail();
        $messaging->markRead($conversation, 'user', $userId);
        [$otherType, $otherId] = $conversation->otherParticipant('user', $userId);

        $messages = $conversation->messages()->get()->map(fn (Message $message) => $this->presentMessage($message, 'user', $userId));

        return response()->json([
            'conversation' => array_merge(['id' => $conversation->id], $this->participantInfo($otherType, $otherId)),
            'messages' => $messages,
        ]);
    }

    public function ring(int $conversationId, MessagingService $messaging)
    {
        $userId = auth()->id();
        $conversation = Conversation::where('id', $conversationId)->forParticipant('user', $userId)->firstOrFail();

        try {
            $rung = $messaging->ring($conversation, 'user', $userId);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['ok' => $rung]);
    }

    private function presentConversation(Conversation $conversation, int $userId, MessagingService $messaging): array
    {
        [$otherType, $otherId] = $conversation->otherParticipant('user', $userId);
        $unread = Message::where('conversation_id', $conversation->id)->where('sender_type', '!=', 'user')->unread()->count();

        return array_merge([
            'id' => $conversation->id,
            'preview' => $conversation->last_message_preview,
            'time' => $conversation->last_message_at?->diffForHumans(),
            'unread' => $unread,
        ], $this->participantInfo($otherType, $otherId));
    }

    private function participantInfo(string $type, int $id): array
    {
        if ($type === 'agent') {
            $agent = SupportAgent::find($id);
            return [
                'name' => $agent?->name ?? 'Agent',
                'role' => 'Support Agent',
                'avatar' => $agent?->photo ? asset('assets/images/support-agents/' . basename($agent->photo)) : null,
            ];
        }
        $user = User::find($id);
        return [
            'name' => $user?->username ?? 'User',
            'role' => Schema::hasTable('coin_vendors') && CoinVendor::where('user_id', $id)->where('status', 'active')->exists() ? 'Coin Vendor' : 'Member',
            'avatar' => $user?->avatarUrl(),
        ];
    }

    private function contactMethodsFor(string $type, int $id): array
    {
        $methods = [];
        if ($type === 'agent') {
            if (Schema::hasColumn('support_agents', 'contact_methods')) {
                $methods = SupportAgent::find($id)?->contact_methods ?? [];
            }
        } elseif (Schema::hasTable('coin_vendors') && Schema::hasColumn('coin_vendors', 'contact_methods')) {
            $methods = CoinVendor::where('user_id', $id)->where('status', 'active')->first()?->contact_methods ?? [];
        }

        return collect(['phone', 'whatsapp', 'telegram', 'email'])
            ->mapWithKeys(function ($channel) use ($methods) {
                $method = $methods[$channel] ?? [];
                $value = trim((string) ($method['value'] ?? ''));
                return !empty($method['enabled']) && $value !== '' ? [$channel => $value] : [];
            })->all();
    }

    private function presentMessage(Message $message, string $viewerType, int $viewerId): array
    {
        return [
            'id' => $message->id,
            'mine' => $message->sender_type === $viewerType && (int) $message->sender_id === $viewerId,
            'body' => $message->body,
            'time' => $message->created_at?->format('H:i'),
            'kind' => $message->kind ?: 'text',
            'attachment_name' => $message->attachment_name,
            'attachment_url' => $message->attachment_path ? route('user.messages.attachment', [$message->conversation_id, $message->id]) : null,
        ];
    }
}
