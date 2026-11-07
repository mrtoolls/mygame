<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\CoinVendor;
use App\Models\Message;
use App\Models\SupportAgent;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Users can message vendors/agents, and vendors/agents can message any user - but two plain users
 * can never start a conversation with each other. The permission rule is symmetric and checked
 * only at conversation-creation time (see startConversation()); once a conversation exists, either
 * side may reply freely through sendMessage() - there's nothing left to gate, since its mere
 * existence already proves at least one side was privileged when it was created.
 */
class MessagingService
{
    /** An agent is always privileged; a 'user' is privileged only while linked to an active CoinVendor. */
    public function isPrivileged(string $type, int $id): bool
    {
        if ($type === 'agent') {
            return SupportAgent::where('id', $id)->where('is_active', true)->exists();
        }
        if ($type === 'user' && Schema::hasTable('coin_vendors')) {
            return CoinVendor::where('user_id', $id)->where('status', 'active')->exists();
        }
        return false;
    }

    public function startConversation(string $typeA, int $idA, string $typeB, int $idB, string $body): Conversation
    {
        if ($typeA === $typeB && $idA === $idB) {
            throw new InvalidArgumentException('You cannot message yourself.');
        }
        if (!$this->isPrivileged($typeA, $idA) && !$this->isPrivileged($typeB, $idB)) {
            throw new InvalidArgumentException('Direct messaging is only available with a Coin Vendor, a support agent, or someone already messaging you.');
        }

        $conversation = Conversation::between($typeA, $idA, $typeB, $idB);
        $this->sendMessage($conversation, $typeA, $idA, $body);

        return $conversation->fresh();
    }

    public function sendMessage(Conversation $conversation, string $senderType, int $senderId, string $body): Message
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Message cannot be empty.');
        }
        if (!$conversation->isParticipant($senderType, $senderId)) {
            throw new InvalidArgumentException('You are not a participant in this conversation.');
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'body' => $body,
        ]);

        $conversation->last_message_at = $message->created_at;
        $conversation->last_message_preview = mb_substr($body, 0, 255);
        $conversation->save();

        [$recipientType, $recipientId] = $conversation->otherParticipant($senderType, $senderId);
        $this->notifyRecipient($recipientType, $recipientId, $senderType, $senderId, $body, $conversation);

        return $message;
    }

    public function markRead(Conversation $conversation, string $readerType, int $readerId): void
    {
        Message::where('conversation_id', $conversation->id)
            ->where('sender_type', '!=', $readerType)
            ->unread()
            ->update(['read_at' => now()]);
    }

    public function unreadCountFor(string $type, int $id): int
    {
        return Message::whereHas('conversation', fn ($q) => $q->forParticipant($type, $id))
            ->where('sender_type', '!=', $type)
            ->unread()
            ->count();
    }

    /** Only 'user' recipients get a real push/in-app notification - an agent has no User row to notify(), and sees new messages via their own portal's live unread count instead (same as the existing ticket system). */
    private function notifyRecipient(string $recipientType, int $recipientId, string $senderType, int $senderId, string $body, Conversation $conversation): void
    {
        if ($recipientType !== 'user') {
            return;
        }
        $user = User::find($recipientId);
        if (!$user) {
            return;
        }
        $senderName = $this->displayNameFor($senderType, $senderId);
        notify($user, 'NEW_CHAT_MESSAGE', [
            'sender' => $senderName,
            'preview' => mb_substr($body, 0, 120),
            'notif_icon' => 'chat-circle-dots',
            'notif_url' => route('user.messages.show', $conversation->id),
            'notif_actor_name' => $senderName,
        ], ['push', 'inapp'], false);
    }

    public function displayNameFor(string $type, int $id): string
    {
        if ($type === 'agent') {
            return SupportAgent::find($id)?->name ?? 'Agent';
        }
        return User::find($id)?->username ?? 'User';
    }
}
