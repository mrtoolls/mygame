<?php

namespace App\Http\Controllers;

use App\Models\AgentTransactionSpeedLog;
use App\Models\Deposit;
use App\Models\NewsPost;
use App\Models\SupportAgent;
use App\Models\SupportChatNotification;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Constants\Status;
use App\Http\Controllers\Gateway\PaymentController;
use App\Support\AgentSpeed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupportAgentController extends Controller
{
    public function loginForm()
    {
        if (Auth::guard('support_agent')->check()) {
            return redirect()->route('support-agent.dashboard');
        }

        return view('support-agent.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:100'],
        ]);
        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $agent = SupportAgent::where($field, $credentials['login'])->where('is_active', true)->first();

        if (!$agent || !Hash::check($credentials['password'], $agent->password)) {
            return back()->withErrors(['login' => 'Those sign-in details were not recognized.'])->onlyInput('login');
        }

        Auth::guard('support_agent')->login($agent);
        $request->session()->regenerate();
        $agent->forceFill(['last_seen_at' => now()])->save();

        return redirect()->intended(route('support-agent.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('support_agent')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('support-agent.login');
    }

    public function dashboard()
    {
        return view('support-agent.dashboard', ['agent' => Auth::guard('support_agent')->user()]);
    }

    public function conversations()
    {
        $agent = Auth::guard('support_agent')->user();
        $agent->forceFill(['last_seen_at' => now()])->save();
        $conversations = SupportTicket::with(['user:id,username,email'])
            ->withCount(['supportMessage as unread_count' => fn ($query) => $query->where('sender_type', 'user')->where('is_read', false)])
            ->with(['supportMessage' => fn ($query) => $query->latest('id')->limit(1)])
            ->where('channel', 'live')->where('status', '!=', Status::TICKET_CLOSE)
            ->where(function ($query) use ($agent) {
                $query->where('support_agent_id', $agent->id)->orWhereNull('support_agent_id');
            })
            ->orderByDesc('last_reply')->limit(100)->get();

        return response()->json([
            'agent' => ['name' => $agent->name, 'photo' => $agent->photo ? asset('assets/images/support-agents/' . $agent->photo) : null],
            'conversations' => $conversations->map(fn ($conversation) => [
                'uuid' => $conversation->chat_uuid,
                'ticket' => $conversation->ticket,
                'name' => $conversation->user?->username ?: $conversation->name ?: 'Website visitor',
                'email' => $conversation->user?->email ?: $conversation->email,
                'assigned' => (int) $conversation->support_agent_id === (int) $agent->id,
                'channel' => $conversation->channel,
                'unread' => $conversation->unread_count,
                'last_message' => $conversation->supportMessage->first()?->message ?: 'New support conversation',
                'updated_at' => $conversation->updated_at?->toIso8601String(),
            ]),
            'unread_notifications' => SupportChatNotification::where('recipient_type', 'agent')
                ->where('recipient_id', $agent->id)->whereNull('read_at')->count(),
        ]);
    }

    public function claim(string $uuid)
    {
        $agent = Auth::guard('support_agent')->user();
        $conversation = SupportTicket::where('chat_uuid', $uuid)->where('status', '!=', Status::TICKET_CLOSE)->firstOrFail();

        if ($conversation->support_agent_id && (int) $conversation->support_agent_id !== (int) $agent->id) {
            return response()->json(['message' => 'This conversation is already assigned to another agent.'], 409);
        }

        $conversation->support_agent_id = $agent->id;
        $conversation->channel = 'live';
        $conversation->save();

        return response()->json(['ok' => true]);
    }

    public function messages(string $uuid)
    {
        $agent = Auth::guard('support_agent')->user();
        $conversation = SupportTicket::where('chat_uuid', $uuid)
            ->where(function ($query) use ($agent) {
                $query->where('support_agent_id', $agent->id)->orWhereNull('support_agent_id');
            })->firstOrFail();

        $messages = SupportMessage::where('support_ticket_id', $conversation->id)->orderBy('id')->get();
        SupportMessage::where('support_ticket_id', $conversation->id)->where('sender_type', 'user')->where('is_read', false)->update(['is_read' => true]);
        SupportChatNotification::where('recipient_type', 'agent')->where('recipient_id', $agent->id)
            ->where('support_ticket_id', $conversation->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'conversation' => [
                'uuid' => $conversation->chat_uuid,
                'ticket' => $conversation->ticket,
                'name' => $conversation->user?->username ?: $conversation->name ?: 'Website visitor',
                'email' => $conversation->user?->email ?: $conversation->email,
                'assigned' => (int) $conversation->support_agent_id === (int) $agent->id,
                'status' => $conversation->status == Status::TICKET_CLOSE ? 'closed' : 'open',
            ],
            'messages' => $messages->map(fn ($message) => [
                'id' => $message->id,
                'sender' => $message->sender_type ?: ($message->admin_id ? 'admin' : 'user'),
                'body' => $message->message,
                'time' => $message->created_at?->format('H:i'),
            ]),
        ]);
    }

    public function reply(Request $request, string $uuid)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $body = trim($data['message']);
        abort_if($body === '', 422, 'Please enter a message.');
        $agent = Auth::guard('support_agent')->user();
        $conversation = SupportTicket::where('chat_uuid', $uuid)
            ->where('support_agent_id', $agent->id)->where('status', '!=', Status::TICKET_CLOSE)->firstOrFail();

        $message = new SupportMessage();
        $message->support_ticket_id = $conversation->id;
        $message->admin_id = 0;
        $message->support_agent_id = $agent->id;
        $message->sender_type = 'agent';
        $message->message = $body;
        $message->is_read = false;
        $message->save();
        $conversation->last_reply = now();
        $conversation->status = Status::TICKET_ANSWER;
        $conversation->channel = 'live';
        $conversation->save();

        if ($conversation->user_id) {
            SupportChatNotification::create([
                'recipient_type' => 'user', 'recipient_id' => $conversation->user_id,
                'support_ticket_id' => $conversation->id, 'support_message_id' => $message->id,
                'title' => "Reply from {$agent->name}", 'body' => $body,
            ]);
        }

        $email = $conversation->user?->email ?: $conversation->email;
        if ($email) {
            try {
                Mail::raw("{$agent->name} replied to your Winxpot Support chat:\n\n{$body}\n\nReturn to Winxpot to continue the conversation.", function ($mail) use ($email) {
                    $mail->to($email)->subject('New reply from Winxpot Support');
                });
            } catch (\Throwable $exception) {
                Log::warning('Live support user email notification failed.', ['conversation_id' => $conversation->id, 'error' => $exception->getMessage()]);
            }
        }

        return response()->json(['message' => [
            'id' => $message->id, 'sender' => 'agent', 'body' => $message->message, 'time' => $message->created_at?->format('H:i'),
        ]]);
    }

    public function close(string $uuid)
    {
        $agent = Auth::guard('support_agent')->user();
        $conversation = SupportTicket::where('chat_uuid', $uuid)->where('support_agent_id', $agent->id)->firstOrFail();
        $conversation->status = Status::TICKET_CLOSE;
        $conversation->save();

        return response()->json(['ok' => true]);
    }

    public function paymentQueue()
    {
        $agent = Auth::guard('support_agent')->user();
        $deposits = Deposit::where('support_agent_id', $agent->id)
            ->where('status', Status::PAYMENT_PENDING)
            ->with('user:id,username,email')
            ->latest('id')->get();
        $speedService = app(AgentSpeed::class);
        $speedScore = $speedService->scoreFor($agent);
        $fastStreakCount = $speedService->fastStreakCountThisMonth($agent);
        $notifications = \App\Models\AgentNotification::unreadFor('agent', $agent->id)->latest('id')->limit(10)->get();

        return view('support-agent.payments.queue', compact('agent', 'deposits', 'speedScore', 'fastStreakCount', 'notifications'));
    }

    public function paymentNotificationsRead()
    {
        $agent = Auth::guard('support_agent')->user();
        \App\Models\AgentNotification::unreadFor('agent', $agent->id)->update(['read_at' => now()]);

        return redirect()->route('support-agent.payments.queue');
    }

    public function paymentShow($id)
    {
        $agent = Auth::guard('support_agent')->user();
        $deposit = Deposit::where('id', $id)->where('support_agent_id', $agent->id)->with('user:id,username,email')->firstOrFail();

        return view('support-agent.payments.show', compact('agent', 'deposit'));
    }

    public function paymentConfirm($id)
    {
        $agent = Auth::guard('support_agent')->user();
        $deposit = Deposit::where('id', $id)->where('support_agent_id', $agent->id)->where('status', Status::PAYMENT_PENDING)->firstOrFail();

        try {
            PaymentController::userDataUpdate($deposit, true, 'agent');
        } catch (\RuntimeException $exception) {
            // Coin mode deliberately refuses to mint off an agent's own unverified "I received
            // this" attestation (see PaymentController::userDataUpdate()) - that is real-money-
            // backed minting authority an agent must never hold alone. The deposit itself is left
            // exactly as it was (the throw happens before any state changes), so it's still sitting
            // in the admin's normal pending-deposits queue, not stuck or lost - just redirect the
            // agent back with a clear explanation instead of a raw server error, and flag an admin
            // so someone actually goes and finishes it.
            $adminNotification = new \App\Models\AdminNotification();
            $adminNotification->user_id = $deposit->user_id;
            $adminNotification->title = 'Agent ' . $agent->name . ' confirmed receipt for ' . ($deposit->user->username ?? 'a user') . ', but coin mode requires an admin to approve this deposit directly before it mints.';
            $adminNotification->click_url = urlPath('admin.deposit.details', $deposit->id);
            $adminNotification->save();

            return redirect()->route('support-agent.payments.queue')->with('error', 'Receipt noted, but coin mode requires an admin to review and approve this deposit directly before coins can be minted - an admin has been notified.');
        }

        $deposit->refresh();
        if ((int) $deposit->status === Status::PAYMENT_SUCCESS && !AgentTransactionSpeedLog::where('deposit_id', $deposit->id)->exists()) {
            $slaMinutes = (int) ($deposit->confirmation_sla_minutes ?: 15);
            $startedAt = $deposit->confirmation_started_at ?: $deposit->created_at;
            $elapsedSeconds = max(0, now()->diffInSeconds($startedAt));
            $metFastThreshold = $elapsedSeconds <= ($slaMinutes * 60 * 0.5);

            AgentTransactionSpeedLog::create([
                'support_agent_id' => $agent->id,
                'deposit_id' => $deposit->id,
                'user_id' => $deposit->user_id,
                'confirmed_at' => now(),
                'sla_minutes_snapshot' => $slaMinutes,
                'elapsed_seconds' => $elapsedSeconds,
                'met_fast_threshold' => $metFastThreshold,
            ]);

            $adminNotification = new \App\Models\AdminNotification();
            $adminNotification->user_id = $deposit->user_id;
            $adminNotification->title = 'Agent ' . $agent->name . ' confirmed a deposit for ' . $deposit->user->username;
            $adminNotification->click_url = urlPath('admin.deposit.details', $deposit->id);
            $adminNotification->save();
        }

        return redirect()->route('support-agent.payments.queue')->with('status', 'Deposit confirmed and credited to the user.');
    }

    public function paymentReject(Request $request, $id)
    {
        $agent = Auth::guard('support_agent')->user();
        $deposit = Deposit::where('id', $id)->where('support_agent_id', $agent->id)->where('status', Status::PAYMENT_PENDING)->firstOrFail();

        $data = $request->validate(['message' => 'required|string|max:255']);

        $deposit->admin_feedback = $data['message'];
        $deposit->status = Status::PAYMENT_REJECT;
        $deposit->save();

        notify($deposit->user, 'DEPOSIT_REJECT', [
            'method_name'       => $deposit->methodName(),
            'method_currency'   => $deposit->method_currency,
            'method_amount'     => showAmount($deposit->final_amount, currencyFormat: false),
            'amount'            => showAmount($deposit->amount, currencyFormat: false),
            'charge'            => showAmount($deposit->charge, currencyFormat: false),
            'rate'              => showAmount($deposit->rate, currencyFormat: false),
            'trx'               => $deposit->trx,
            'rejection_message' => $data['message'],
        ]);

        return redirect()->route('support-agent.payments.queue')->with('status', 'Deposit rejected.');
    }

    public function paymentSettings()
    {
        $agent = Auth::guard('support_agent')->user();
        $speedService = app(AgentSpeed::class);
        $speedScore = $speedService->scoreFor($agent);
        $fastStreakCount = $speedService->fastStreakCountThisMonth($agent);

        return view('support-agent.payments.settings', compact('agent', 'speedScore', 'fastStreakCount'));
    }

    public function paymentSettingsUpdate(Request $request)
    {
        $agent = Auth::guard('support_agent')->user();
        $data = $request->validate([
            'accepted_payment_types'   => 'nullable|array',
            'accepted_payment_types.*' => 'string|max:40',
        ]);

        $agent->accepted_payment_types = array_values(array_filter(array_map('trim', $data['accepted_payment_types'] ?? [])));
        $agent->save();

        return redirect()->route('support-agent.payments.settings')->with('status', 'Payment types updated.');
    }

    public function newsIndex()
    {
        $agent = Auth::guard('support_agent')->user();
        $posts = NewsPost::orderBy('id', 'desc')->paginate(15);
        return view('support-agent.news.index', compact('agent', 'posts'));
    }

    public function newsCreate()
    {
        $agent = Auth::guard('support_agent')->user();
        return view('support-agent.news.form', ['agent' => $agent, 'post' => null]);
    }

    public function newsStore(Request $request)
    {
        $agent = Auth::guard('support_agent')->user();
        $request->validate([
            'title'       => 'required|string|max:160',
            'badge_label' => 'nullable|string|max:40',
            'excerpt'     => 'nullable|string|max:500',
            'body'        => 'required|string',
            'image'       => 'required|image|mimes:png,jpg,jpeg,webp|max:4096',
        ]);

        $post                  = new NewsPost();
        $post->title           = $request->title;
        $post->slug            = NewsPost::uniqueSlug($request->title);
        $post->badge_label     = $request->badge_label;
        $post->excerpt         = $request->excerpt;
        $post->body            = $request->body;
        $post->status          = Status::ENABLE;
        $post->posted_by_type  = 'agent';
        $post->posted_by_id    = $agent->id;
        $post->posted_by_name  = $agent->name;
        if ($request->hasFile('image')) {
            $post->image = fileUploader($request->file('image'), 'assets/images/news', '1200x600');
        }
        $post->save();

        return redirect()->route('support-agent.news.index')->with('status', 'News post published.');
    }

    public function newsEdit($id)
    {
        $agent = Auth::guard('support_agent')->user();
        $post  = NewsPost::where('posted_by_type', 'agent')->where('posted_by_id', $agent->id)->findOrFail($id);
        return view('support-agent.news.form', compact('agent', 'post'));
    }

    public function newsUpdate(Request $request, $id)
    {
        $agent = Auth::guard('support_agent')->user();
        $post  = NewsPost::where('posted_by_type', 'agent')->where('posted_by_id', $agent->id)->findOrFail($id);

        $request->validate([
            'title'       => 'required|string|max:160',
            'badge_label' => 'nullable|string|max:40',
            'excerpt'     => 'nullable|string|max:500',
            'body'        => 'required|string',
            'image'       => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
        ]);

        if ($post->title !== $request->title) {
            $post->slug = NewsPost::uniqueSlug($request->title, $post->id);
        }
        $post->title       = $request->title;
        $post->badge_label = $request->badge_label;
        $post->excerpt     = $request->excerpt;
        $post->body        = $request->body;
        if ($request->hasFile('image')) {
            $post->image = fileUploader($request->file('image'), 'assets/images/news', '1200x600', $post->image ?: null);
        }
        $post->save();

        return redirect()->route('support-agent.news.index')->with('status', 'News post updated.');
    }

    public function newsDestroy($id)
    {
        $agent = Auth::guard('support_agent')->user();
        $post  = NewsPost::where('posted_by_type', 'agent')->where('posted_by_id', $agent->id)->findOrFail($id);
        if ($post->image) {
            @unlink('assets/images/news/' . $post->image);
        }
        $post->delete();
        return redirect()->route('support-agent.news.index')->with('status', 'News post deleted.');
    }

    public function arenaQueue()
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_moderator, 403);
        $posts = \App\Models\ArenaPost::pending()->with('user')->latest()->paginate(15);
        return view('support-agent.arena.queue', compact('agent', 'posts'));
    }

    public function arenaApprove($id)
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_moderator, 403);
        $post = \App\Models\ArenaPost::where('id', $id)->where('status', 'pending')->firstOrFail();
        $post->status            = 'approved';
        $post->moderated_by_type = 'agent';
        $post->moderated_by_id   = $agent->id;
        $post->save();

        \App\Models\ArenaNotification::fire($post->user_id, 'post_approved', null, 'post', $post->id, 'Your Arena post was approved and is now live.');

        return redirect()->route('support-agent.arena.queue')->with('status', 'Post approved.');
    }

    public function arenaReject(Request $request, $id)
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_moderator, 403);
        $request->validate(['message' => 'required|string|max:255']);

        $post = \App\Models\ArenaPost::where('id', $id)->where('status', 'pending')->firstOrFail();
        $post->status            = 'rejected';
        $post->rejection_reason  = $request->message;
        $post->moderated_by_type = 'agent';
        $post->moderated_by_id   = $agent->id;
        $post->save();

        \App\Models\ArenaNotification::fire($post->user_id, 'post_rejected', null, 'post', $post->id, 'Your Arena post was rejected: ' . $request->message);

        return redirect()->route('support-agent.arena.queue')->with('status', 'Post rejected.');
    }
}
