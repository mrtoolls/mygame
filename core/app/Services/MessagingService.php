<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\CoinVendor;
use App\Models\AgentNotification;
use App\Models\Message;
use App\Models\SupportAgent;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
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

        $this->finishMessage($conversation, $message, $senderType, $senderId, $body);
        return $message;
    }

    public function sendAttachment(Conversation $conversation, string $senderType, int $senderId, UploadedFile $file): Message
    {
        if (!$conversation->isParticipant($senderType, $senderId)) {
            throw new InvalidArgumentException('You are not a participant in this conversation.');
        }

        $mime = strtolower((string) $file->getMimeType());
        $kind = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : null);
        if (!$kind) {
            throw new InvalidArgumentException('Attach an image or video file.');
        }

        $path = $file->store('direct-messages/' . $conversation->id, 'local');
        try {
            $label = $kind === 'image' ? 'Photo' : 'Video';
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'body' => $label,
                'kind' => $kind,
                'attachment_path' => $path,
                'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'attachment_mime' => $mime,
                'attachment_size' => $file->getSize(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        $this->finishMessage($conversation, $message, $senderType, $senderId, $label);
        return $message;
    }

    private function finishMessage(Conversation $conversation, Message $message, string $senderType, int $senderId, string $preview): void
    {
        $conversation->last_message_at = $message->created_at;
        $conversation->last_message_preview = mb_substr($preview, 0, 255);
        $conversation->save();
        [$recipientType, $recipientId] = $conversation->otherParticipant($senderType, $senderId);
        $this->notifyRecipient($recipientType, $recipientId, $senderType, $senderId, $preview, $conversation);
    }

    /** Send a lightweight online ping to the other participant; this is not a call. */
    public function ring(Conversation $conversation, string $ringerType, int $ringerId): bool
    {
        if (!$conversation->isParticipant($ringerType, $ringerId)) {
            throw new InvalidArgumentException('You are not a participant in this conversation.');
        }
        [$recipientType, $recipientId] = $conversation->otherParticipant($ringerType, $ringerId);
        $ringerName = $this->displayNameFor($ringerType, $ringerId);
        if ($recipientType === 'user') {
            $user = User::find($recipientId);
            if (!$user) return false;
            notify($user, 'RING_ONLINE', [
                'ringer' => $ringerName,
                'notif_icon' => 'bell-ringing',
                'notif_url' => route('user.messages.show', $conversation->id),
                'notif_actor_name' => $ringerName,
            ], ['push', 'inapp'], false);
            return true;
        }
        if ($recipientType === 'agent' && SupportAgent::where('id', $recipientId)->where('is_active', true)->exists()
            && Schema::hasTable('agent_notifications')) {
            AgentNotification::create([
                'recipient_type' => 'agent',
                'recipient_id' => $recipientId,
                'title' => 'Someone is trying to reach you',
                'body' => $ringerName . ' is hoping to catch you online for a chat.',
                'click_url' => route('support-agent.messages.show', $conversation->id),
            ]);
            return true;
        }

        return false;
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

    /** Users receive app notifications; support agents receive portal notifications because they authenticate outside the User model. */
    private function notifyRecipient(string $recipientType, int $recipientId, string $senderType, int $senderId, string $body, Conversation $conversation): void
    {
        $senderName = $this->displayNameFor($senderType, $senderId);
        if ($recipientType === 'agent') {
            if (SupportAgent::where('id', $recipientId)->where('is_active', true)->exists()
                && Schema::hasTable('agent_notifications')) {
                AgentNotification::create([
                    'recipient_type' => 'agent',
                    'recipient_id' => $recipientId,
                    'title' => 'New direct message',
                    'body' => $senderName . ': ' . mb_substr($body, 0, 420),
                    'click_url' => route('support-agent.messages.show', $conversation->id),
                ]);
            }
            return;
        }
        $user = User::find($recipientId);
        if (!$user) return;
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
