<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * A separate "Direct Messages" inbox for agents, distinct from the existing SupportTicket inbox
 * (SupportAgentController::conversations/messages/reply) - that system is for inbound, user-
 * initiated support tickets; this one lets an agent reach out to ANY user proactively (e.g. about
 * a coin trade, a flagged account, a vendor application), and lets a user reply back or start one
 * with the agent. Same underlying Conversation/Message tables MessagingController (user-side) uses.
 */
class SupportAgentMessagingController extends Controller
{
    public function index(MessagingService $messaging)
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_chat_agent, 403);

        $conversations = Conversation::forParticipant('agent', $agent->id)
            ->orderByDesc('last_message_at')->orderByDesc('id')->get()
            ->map(function (Conversation $conversation) use ($agent, $messaging) {
                [$otherType, $otherId] = $conversation->otherParticipant('agent', $agent->id);
                $unread = Message::where('conversation_id', $conversation->id)->where('sender_type', '!=', 'agent')->unread()->count();
                return [
                    'id' => $conversation->id,
                    'name' => $messaging->displayNameFor($otherType, $otherId),
                    'preview' => $conversation->last_message_preview,
                    'time' => $conversation->last_message_at?->diffForHumans(),
                    'unread' => $unread,
                ];
            });

        $pageTitle = 'Direct Messages';
        return view('support-agent.messages.index', compact('pageTitle', 'agent', 'conversations'));
    }

    public function show(int $conversationId, MessagingService $messaging)
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_chat_agent, 403);
        $conversation = Conversation::where('id', $conversationId)->forParticipant('agent', $agent->id)->firstOrFail();
        [$otherType, $otherId] = $conversation->otherParticipant('agent', $agent->id);
        $messaging->markRead($conversation, 'agent', $agent->id);

        $pageTitle = 'Conversation with ' . $messaging->displayNameFor($otherType, $otherId);
        $otherName = $messaging->displayNameFor($otherType, $otherId);
        return view('support-agent.messages.show', compact('pageTitle', 'agent', 'conversation', 'otherName'));
    }

    public function start(Request $request, MessagingService $messaging)
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_chat_agent, 403);
        $data = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $user = User::where('username', $data['username'])->first();
        if (!$user) {
            return back()->withErrors(['username' => 'No user with that username was found.'])->withInput();
        }

        try {
            $conversation = $messaging->startConversation('agent', $agent->id, 'user', (int) $user->id, $data['body']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['username' => $exception->getMessage()])->withInput();
        }

        return to_route('support-agent.messages.show', $conversation->id);
    }

    public function reply(Request $request, int $conversationId, MessagingService $messaging)
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_chat_agent, 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $conversation = Conversation::where('id', $conversationId)->forParticipant('agent', $agent->id)->firstOrFail();

        try {
            $message = $messaging->sendMessage($conversation, 'agent', $agent->id, $data['body']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => $this->presentMessage($message, $agent->id)]);
    }

    public function poll(int $conversationId, MessagingService $messaging)
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_chat_agent, 403);
        $conversation = Conversation::where('id', $conversationId)->forParticipant('agent', $agent->id)->firstOrFail();
        $messaging->markRead($conversation, 'agent', $agent->id);

        $messages = $conversation->messages()->get()->map(fn (Message $message) => $this->presentMessage($message, $agent->id));

        return response()->json(['messages' => $messages]);
    }

    public function unreadCount()
    {
        $agent = Auth::guard('support_agent')->user();
        return response()->json(['count' => app(MessagingService::class)->unreadCountFor('agent', $agent->id)]);
    }

    private function presentMessage(Message $message, int $agentId): array
    {
        return [
            'id' => $message->id,
            'mine' => $message->sender_type === 'agent' && (int) $message->sender_id === $agentId,
            'body' => $message->body,
            'time' => $message->created_at?->format('H:i'),
        ];
    }
}
