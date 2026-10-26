<?php

namespace App\Http\Controllers;

use App\Models\CoinTrade;
use App\Models\CoinTradeMessage;
use App\Models\CoinVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

/**
 * Trade chat: visible only to the trade's two parties and marketplace supervisors, and
 * immutable (no edit/delete endpoints exist). Polling, not websockets - matching every other
 * "live" surface in this app (support chat, table-game chat). Attachment handling (private disk,
 * UUID filename, 10MB cap, mime allow-list, auth-gated retrieval) is a direct copy of
 * TableGameController::sendMessage()'s already-hardened pattern.
 */
class CoinTradeChatController extends Controller
{
    public function messages(Request $request, int $tradeId)
    {
        $trade = CoinTrade::findOrFail($tradeId);
        $this->authorizeParticipant($request, $trade);

        $messages = CoinTradeMessage::where('trade_id', $tradeId)->orderBy('created_at')->get()->map(fn ($message) => [
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sender_label' => $message->sender_type === 'user' && \Illuminate\Support\Facades\Schema::hasTable('coin_vendors') && CoinVendor::where('user_id', $message->sender_id)->exists() ? 'Vendor' : ucfirst($message->sender_type),
            'body' => $message->body,
            'attachment_url' => $message->attachment_path ? route('coin-trade.chat.attachment', [$tradeId, $message->id]) : null,
            'attachment_name' => $message->attachment_name,
            'created_at' => $message->created_at->toIso8601String(),
        ]);

        return response()->json(['messages' => $messages]);
    }

    public function send(Request $request, int $tradeId)
    {
        $trade = CoinTrade::findOrFail($tradeId);
        [$senderType, $senderId] = $this->authorizeParticipant($request, $trade);

        $request->validate([
            'body' => 'nullable|string|max:1000|required_without:attachment',
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
        ]);

        $attachmentPath = $attachmentName = $attachmentMime = $attachmentSize = null;
        if ($file = $request->file('attachment')) {
            $attachmentPath = $file->storeAs('coin-trade-chat/' . $tradeId, Str::uuid() . '.' . strtolower($file->extension() ?: 'bin'), 'local');
            $attachmentName = $file->getClientOriginalName();
            $attachmentMime = $file->getMimeType();
            $attachmentSize = $file->getSize();
        }

        $message = CoinTradeMessage::create([
            'trade_id' => $tradeId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'sender_label' => $senderType === 'user' && \Illuminate\Support\Facades\Schema::hasTable('coin_vendors') && CoinVendor::where('user_id', $senderId)->exists() ? 'Vendor' : ucfirst($senderType),
            'body' => $request->input('body'),
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
            'created_at' => now(),
        ]);

        return response()->json(['message' => [
            'id' => $message->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'body' => $message->body,
            'attachment_url' => $attachmentPath ? route('coin-trade.chat.attachment', [$tradeId, $message->id]) : null,
            'created_at' => $message->created_at->toIso8601String(),
        ]], 201);
    }

    public function attachment(Request $request, int $tradeId, int $messageId)
    {
        $trade = CoinTrade::findOrFail($tradeId);
        $this->authorizeParticipant($request, $trade);

        $message = CoinTradeMessage::where('trade_id', $tradeId)->findOrFail($messageId);
        abort_unless($message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array{0: string, 1: int} [actor_type, actor_id] */
    private function authorizeParticipant(Request $request, CoinTrade $trade): array
    {
        if ($user = $request->user('web')) {
            if ($trade->isParticipant('user', $user->id)) return ['user', $user->id];
        }
        if ($agent = $request->user('support_agent')) {
            if ($trade->isParticipant('agent', $agent->id) || $agent->is_marketplace_supervisor) return ['agent', $agent->id];
        }
        if ($request->user('admin')) {
            return ['admin', $request->user('admin')->id];
        }

        abort(403, 'You are not a party to this trade.');
    }
}
