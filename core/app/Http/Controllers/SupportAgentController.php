<?php

namespace App\Http\Controllers;

use App\Models\SupportAgent;
use App\Models\SupportChatNotification;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Constants\Status;
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
}
