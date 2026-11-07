<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\CoinVendor;
use App\Models\AgentNotification;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
                    'role' => $otherType === 'agent' ? 'Support Agent' : (Schema::hasTable('coin_vendors') && CoinVendor::where('user_id', $otherId)->where('status', 'active')->exists() ? 'Coin Vendor' : 'Member'),
                    'avatar' => $otherType === 'user' ? User::find($otherId)?->avatarUrl() : null,
                    'preview' => $conversation->last_message_preview,
                    'time' => $conversation->last_message_at?->diffForHumans(),
                    'unread' => $unread,
                ];
            });

        $pageTitle = 'Direct Messages';
        $activeData = null;
        $activeMessages = collect();
        return view('support-agent.messages.index', compact('pageTitle', 'agent', 'conversations', 'activeData', 'activeMessages'));
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
        $activeData = [
            'id' => $conversation->id,
            'type' => $otherType,
            'name' => $otherName,
            'role' => $otherType === 'agent' ? 'Support Agent' : (Schema::hasTable('coin_vendors') && CoinVendor::where('user_id', $otherId)->where('status', 'active')->exists() ? 'Coin Vendor' : 'Member'),
            'avatar' => $otherType === 'user' ? User::find($otherId)?->avatarUrl() : null,
            'contact_methods' => $otherType === 'user' ? $this->contactMethodsFor($otherId) : [],
        ];
        $activeMessages = $conversation->messages()->get()->map(fn (Message $message) => $this->presentMessage($message, $agent->id));
        $conversations = Conversation::forParticipant('agent', $agent->id)
            ->orderByDesc('last_message_at')->orderByDesc('id')->get()
            ->map(function (Conversation $item) use ($agent, $messaging) {
                [$type, $id] = $item->otherParticipant('agent', $agent->id);
                return [
                    'id' => $item->id,
                    'name' => $messaging->displayNameFor($type, $id),
                    'role' => $type === 'agent' ? 'Support Agent' : (Schema::hasTable('coin_vendors') && CoinVendor::where('user_id', $id)->where('status', 'active')->exists() ? 'Coin Vendor' : 'Member'),
                    'avatar' => $type === 'user' ? User::find($id)?->avatarUrl() : null,
                    'preview' => $item->last_message_preview,
                    'time' => $item->last_message_at?->diffForHumans(),
                    'unread' => Message::where('conversation_id', $item->id)->where('sender_type', '!=', 'agent')->unread()->count(),
                ];
            });
        return view('support-agent.messages.index', compact('pageTitle', 'agent', 'conversations', 'activeData', 'activeMessages'));
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

    public function attachment(Request $request, int $conversationId, MessagingService $messaging)
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_chat_agent, 403);
        $data = $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:15360'],
        ]);
        $conversation = Conversation::where('id', $conversationId)->forParticipant('agent', $agent->id)->firstOrFail();
        try {
            $message = $messaging->sendAttachment($conversation, 'agent', $agent->id, $data['attachment']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        return response()->json(['message' => $this->presentMessage($message, $agent->id)], 201);
    }

    public function downloadAttachment(int $conversationId, int $messageId)
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_chat_agent, 403);
        $conversation = Conversation::where('id', $conversationId)->forParticipant('agent', $agent->id)->firstOrFail();
        $message = $conversation->messages()->whereKey($messageId)->whereIn('kind', ['image', 'video'])->firstOrFail();
        abort_unless($message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404);
        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Content-Type' => $message->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function ring(int $conversationId, MessagingService $messaging)
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_chat_agent, 403);
        $conversation = Conversation::where('id', $conversationId)->forParticipant('agent', $agent->id)->firstOrFail();
        try {
            $sent = $messaging->ring($conversation, 'agent', $agent->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        return response()->json(['ok' => $sent]);
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

    public function messageAlerts()
    {
        $agent = Auth::guard('support_agent')->user();
        abort_unless($agent->is_chat_agent, 403);

        $alerts = Schema::hasTable('agent_notifications')
            ? AgentNotification::unreadFor('agent', $agent->id)
                ->whereIn('title', ['Someone is trying to reach you', 'New direct message'])
                ->latest('id')->limit(5)->get(['id', 'title', 'body', 'click_url'])
            : collect();

        if ($alerts->isNotEmpty()) {
            AgentNotification::whereIn('id', $alerts->pluck('id'))->update(['read_at' => now()]);
        }

        return response()->json(['alerts' => $alerts]);
    }

    private function presentMessage(Message $message, int $agentId): array
    {
        return [
            'id' => $message->id,
            'mine' => $message->sender_type === 'agent' && (int) $message->sender_id === $agentId,
            'body' => $message->body,
            'time' => $message->created_at?->format('H:i'),
            'kind' => $message->kind ?: 'text',
            'attachment_name' => $message->attachment_name,
            'attachment_url' => $message->attachment_path ? route('support-agent.messages.attachment', [$message->conversation_id, $message->id]) : null,
        ];
    }

    private function contactMethodsFor(int $userId): array
    {
        if (!Schema::hasTable('coin_vendors') || !Schema::hasColumn('coin_vendors', 'contact_methods')) return [];
        $methods = CoinVendor::where('user_id', $userId)->where('status', 'active')->first()?->contact_methods ?? [];
        return collect(['phone', 'whatsapp', 'telegram', 'email'])->mapWithKeys(function ($channel) use ($methods) {
            $method = $methods[$channel] ?? [];
            $value = trim((string) ($method['value'] ?? ''));
            return !empty($method['enabled']) && $value !== '' ? [$channel => $value] : [];
        })->all();
    }
}
