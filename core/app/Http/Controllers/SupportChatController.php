<?php

namespace App\Http\Controllers;

use App\Constants\Status;
use App\Models\SupportAgent;
use App\Models\SupportChatNotification;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\SupportBot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SupportChatController extends Controller
{
    public function active(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['conversation' => null, 'unread_count' => 0]);
        }

        $ticket = SupportTicket::where('user_id', $user->id)
            ->whereIn('channel', ['bot', 'live'])
            ->where('status', '!=', Status::TICKET_CLOSE)
            ->orderByDesc('last_reply')->first();

        if (!$ticket) {
            return response()->json(['conversation' => null, 'unread_count' => 0]);
        }

        return response()->json([
            'conversation' => [
                'uuid' => $ticket->chat_uuid,
                'token' => null,
                'status' => 'open',
                'agent' => $ticket->supportAgent?->name,
                'ticket' => $ticket->ticket,
            ],
            'unread_count' => SupportMessage::where('support_ticket_id', $ticket->id)
                ->whereIn('sender_type', ['agent', 'admin', 'bot'])->where('is_read', false)->count(),
        ]);
    }

    public function agents()
    {
        $agents = SupportAgent::where('is_active', true)
            ->orderByDesc('last_seen_at')->orderBy('name')->limit(12)
            ->get(['id', 'name', 'photo', 'title', 'last_seen_at']);

        return response()->json(['agents' => $agents->map(fn ($agent) => [
            'id' => $agent->id,
            'name' => $agent->name,
            'title' => $agent->title ?: 'Winxpot Support',
            'photo' => $agent->photo ? asset('assets/images/support-agents/' . $agent->photo) : null,
            'online' => $agent->last_seen_at && $agent->last_seen_at->gt(now()->subMinutes(2)),
        ])]);
    }

    public function start(Request $request, SupportBot $bot)
    {
        $data = $request->validate([
            'agent_id' => ['nullable', 'integer', 'exists:support_agents,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);
        $agent = isset($data['agent_id'])
            ? SupportAgent::where('is_active', true)->findOrFail($data['agent_id'])
            : null;
        $user = $request->user();

        $ticket = new SupportTicket();
        $ticket->ticket = $this->uniqueTicketNumber();
        $ticket->user_id = $user?->id ?: 0;
        $ticket->admin_id = 0;
        $ticket->name = $user?->fullname ?: ($data['name'] ?? 'Website visitor');
        $ticket->email = $user?->email ?: ($data['email'] ?? '');
        $ticket->subject = 'Live Support Chat';
        $ticket->last_reply = now();
        $ticket->status = Status::TICKET_OPEN;
        $ticket->priority = Status::PRIORITY_MEDIUM;
        $ticket->chat_uuid = (string) Str::uuid();
        $ticket->visitor_token = $user ? null : Str::random(64);
        $ticket->support_agent_id = $agent?->id;
        $ticket->channel = $agent ? 'live' : 'bot';
        $ticket->save();

        $welcome = $bot->welcome();
        if ($agent) {
            $welcome['text'] = "Welcome to Winxpot Support! 👋 You’re in the queue for {$agent->name}. I can also help right away with your account, deposits, withdrawals, bonuses, verification, and games.";
            $welcome['suggestions'] = ['Account or login', 'Deposit help', 'Withdrawal help', 'Bonuses and VIP', 'Verification', 'Games and gameplay'];
        }
        $message = $this->createMessage($ticket, 'bot', $welcome['text'], ['suggestions' => $welcome['suggestions']], true);

        if ($agent) {
            $this->notifyAgents($ticket, $message, 'New live support chat', 'A visitor has started a chat and requested you.');
        }

        return response()->json([
            'conversation' => ['uuid' => $ticket->chat_uuid, 'token' => $ticket->visitor_token, 'status' => 'open', 'agent' => $agent?->name, 'ticket' => $ticket->ticket],
            'messages' => [$this->presentMessage($message)],
        ], 201);
    }

    public function messages(Request $request, string $uuid)
    {
        $ticket = $this->ownedTicket($request, $uuid);
        $after = max(0, (int) $request->query('after', 0));
        $messages = SupportMessage::where('support_ticket_id', $ticket->id)->where('id', '>', $after)->orderBy('id')->get();

        if ($request->boolean('mark_read')) {
            SupportMessage::where('support_ticket_id', $ticket->id)
                ->whereIn('sender_type', ['agent', 'admin', 'bot'])->where('is_read', false)->update(['is_read' => true]);
            if ($request->user()) {
                SupportChatNotification::where('recipient_type', 'user')->where('recipient_id', $request->user()->id)
                    ->where('support_ticket_id', $ticket->id)->whereNull('read_at')->update(['read_at' => now()]);
            }
        }

        return response()->json([
            'messages' => $messages->map(fn ($message) => $this->presentMessage($message)),
            'status' => $ticket->status == Status::TICKET_CLOSE ? 'closed' : 'open',
            'agent' => $ticket->supportAgent?->only(['name', 'title', 'photo']),
            'unread_count' => SupportMessage::where('support_ticket_id', $ticket->id)
                ->whereIn('sender_type', ['agent', 'admin', 'bot'])->where('is_read', false)->count(),
        ]);
    }

    public function send(Request $request, string $uuid, SupportBot $bot)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $body = trim($data['message']);
        if ($body === '') {
            return response()->json(['message' => 'Please enter a message.'], 422);
        }

        $ticket = $this->ownedTicket($request, $uuid);
        if ($ticket->status == Status::TICKET_CLOSE) {
            return response()->json(['message' => 'This chat is closed. Start a new conversation to contact support.'], 409);
        }

        $message = $this->createMessage($ticket, 'user', $body);
        $ticket->status = Status::TICKET_REPLY;
        $ticket->last_reply = now();
        $ticket->save();

        if ($ticket->channel === 'live' && $ticket->support_agent_id) {
            $this->notifyAgents($ticket, $message, 'New message in live support', Str::limit($body, 180));
            return response()->json(['messages' => [$this->presentMessage($message)]]);
        }

        $reply = $bot->respond($body);
        if (!empty($reply['handoff'])) {
            $agent = $ticket->supportAgent;
            if (!$agent || !$agent->is_active) {
                $agent = SupportAgent::where('is_active', true)->orderByDesc('last_seen_at')->first();
            }

            if ($agent) {
                $ticket->support_agent_id = $agent->id;
                $ticket->channel = 'live';
                $reply = ['text' => "I’m connecting you with {$agent->name}. Leave your message here and our team will join shortly.", 'suggestions' => []];
            } else {
                $ticket->channel = 'live';
                $reply = ['text' => 'Our support team is away right now, but your message is saved in the queue. If you share your email before closing this chat, we can follow up when an agent is available.', 'suggestions' => ['Account or login', 'Deposits', 'Withdrawals', 'Bonuses', 'Try an agent again']];
            }
        }

        $botMessage = $this->createMessage($ticket, 'bot', $reply['text'], ['suggestions' => $reply['suggestions'] ?? []], true);
        $ticket->last_reply = now();
        $ticket->save();

        if ($ticket->support_agent_id && $ticket->channel === 'live') {
            $this->notifyAgents($ticket, $message, 'New live support request', Str::limit($body, 180));
        }

        return response()->json(['messages' => [$this->presentMessage($message), $this->presentMessage($botMessage)]]);
    }

    private function ownedTicket(Request $request, string $uuid): SupportTicket
    {
        $ticket = SupportTicket::where('chat_uuid', $uuid)->firstOrFail();
        $user = $request->user();

        if ((int) $ticket->user_id > 0) {
            abort_unless($user && (int) $user->id === (int) $ticket->user_id, 404);
        } else {
            $token = (string) $request->header('X-Support-Token', $request->input('token', ''));
            abort_unless($token !== '' && hash_equals((string) $ticket->visitor_token, $token), 404);
        }

        return $ticket;
    }

    private function createMessage(SupportTicket $ticket, string $sender, string $body, ?array $metadata = null, bool $read = false): SupportMessage
    {
        $message = new SupportMessage();
        $message->support_ticket_id = $ticket->id;
        $message->admin_id = 0;
        $message->sender_type = $sender;
        $message->message = $body;
        $message->metadata = $metadata;
        $message->is_read = $read;
        $message->save();

        return $message;
    }

    private function presentMessage(SupportMessage $message): array
    {
        $sender = $message->sender_type ?: ($message->admin_id ? 'admin' : 'user');
        return [
            'id' => $message->id,
            'sender' => $sender,
            'sender_name' => $message->supportAgent?->name ?: ($sender === 'bot' ? 'Winxpot Assistant' : ($sender === 'agent' ? 'Support Agent' : 'You')),
            'sender_photo' => $message->supportAgent?->photo ? asset('assets/images/support-agents/' . $message->supportAgent->photo) : null,
            'body' => $message->message,
            'metadata' => $message->metadata ?? [],
            'time' => $message->created_at?->toIso8601String(),
        ];
    }

    private function notifyAgents(SupportTicket $ticket, SupportMessage $message, string $title, string $body): void
    {
        $agents = $ticket->support_agent_id
            ? SupportAgent::whereKey($ticket->support_agent_id)->where('is_active', true)->get()
            : SupportAgent::where('is_active', true)->get();
        foreach ($agents as $agent) {
            SupportChatNotification::create([
                'recipient_type' => 'agent', 'recipient_id' => $agent->id,
                'support_ticket_id' => $ticket->id, 'support_message_id' => $message->id,
                'title' => $title, 'body' => $body,
            ]);
            try {
                Mail::raw("{$body}\n\nSign in to your Winxpot Support Agent dashboard to reply.", function ($mail) use ($agent, $title) {
                    $mail->to($agent->email, $agent->name)->subject($title);
                });
            } catch (\Throwable $exception) {
                Log::warning('Live support email notification failed.', ['agent_id' => $agent->id, 'error' => $exception->getMessage()]);
            }
        }
    }

    private function uniqueTicketNumber(): int
    {
        do {
            $number = random_int(100000, 999999);
        } while (SupportTicket::where('ticket', $number)->exists());

        return $number;
    }
}
