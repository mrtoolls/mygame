<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CoinVendor;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SupportAgent;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class MessagingController extends Controller
{
    public function index(MessagingService $messaging)
    {
        $userId = auth()->id();
        $conversations = Conversation::forParticipant('user', $userId)
            ->orderByDesc('last_message_at')->orderByDesc('id')->get()
            ->map(fn (Conversation $conversation) => $this->presentConversation($conversation, $userId, $messaging));

        $vendors = Schema::hasTable('coin_vendors')
            ? CoinVendor::with('user:id,username')->where('status', 'active')->where('user_id', '!=', $userId)->get()
                ->filter(fn ($vendor) => $vendor->user)->map(fn ($vendor) => ['type' => 'user', 'id' => (int) $vendor->user_id, 'name' => $vendor->user->username, 'role' => 'Coin Vendor'])
            : collect();
        $agents = SupportAgent::where('is_active', true)->where('is_chat_agent', true)->get()
            ->map(fn ($agent) => ['type' => 'agent', 'id' => (int) $agent->id, 'name' => $agent->name, 'role' => 'Support Agent']);
        $contacts = $vendors->concat($agents)->values();

        $pageTitle = 'Messages';
        return view('Template::user.messages.index', compact('pageTitle', 'conversations', 'contacts'));
    }

    public function show(int $conversationId, MessagingService $messaging)
    {
        $userId = auth()->id();
        $conversation = Conversation::where('id', $conversationId)->forParticipant('user', $userId)->firstOrFail();
        [$otherType, $otherId] = $conversation->otherParticipant('user', $userId);
        $messaging->markRead($conversation, 'user', $userId);

        $pageTitle = 'Conversation with ' . $messaging->displayNameFor($otherType, $otherId);
        $otherName = $messaging->displayNameFor($otherType, $otherId);
        $otherRole = $otherType === 'agent' ? 'Support Agent' : 'Coin Vendor';

        return view('Template::user.messages.show', compact('pageTitle', 'conversation', 'otherName', 'otherRole'));
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
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

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

    public function poll(int $conversationId, MessagingService $messaging)
    {
        $userId = auth()->id();
        $conversation = Conversation::where('id', $conversationId)->forParticipant('user', $userId)->firstOrFail();
        $messaging->markRead($conversation, 'user', $userId);

        $messages = $conversation->messages()->get()->map(fn (Message $message) => $this->presentMessage($message, 'user', $userId));

        return response()->json(['messages' => $messages]);
    }

    private function presentConversation(Conversation $conversation, int $userId, MessagingService $messaging): array
    {
        [$otherType, $otherId] = $conversation->otherParticipant('user', $userId);
        $unread = Message::where('conversation_id', $conversation->id)->where('sender_type', '!=', 'user')->unread()->count();

        return [
            'id' => $conversation->id,
            'name' => $messaging->displayNameFor($otherType, $otherId),
            'role' => $otherType === 'agent' ? 'Support Agent' : 'Coin Vendor',
            'preview' => $conversation->last_message_preview,
            'time' => $conversation->last_message_at?->diffForHumans(),
            'unread' => $unread,
        ];
    }

    private function presentMessage(Message $message, string $viewerType, int $viewerId): array
    {
        return [
            'id' => $message->id,
            'mine' => $message->sender_type === $viewerType && (int) $message->sender_id === $viewerId,
            'body' => $message->body,
            'time' => $message->created_at?->format('H:i'),
        ];
    }
}
