<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\TableGameMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserGiftInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Buy/send/cash-out for the 2D gift economy. Mirrors the locking and bcmath conventions already
 * proven in TableGameService::creditPlayer()/debit()/ledger() - lock the User row, mutate balance
 * with bcadd/bcsub, write a ledgered Transaction row - rather than inventing a new pattern.
 */
class GiftService
{
    public function buy(User $user, Gift $gift, int $quantity): UserGiftInventory
    {
        if ($quantity < 1) throw new InvalidArgumentException('Choose at least one gift.');
        if (!$gift->status) throw new InvalidArgumentException('This gift is not available right now.');

        return DB::transaction(function () use ($user, $gift, $quantity) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $unitPrice = $this->quoteInWalletCurrency((string) $gift->buy_price, $lockedUser);
            $totalPrice = bcmul($unitPrice, (string) $quantity, 8);
            if (bccomp((string) $lockedUser->balance, $totalPrice, 8) < 0) {
                throw new InvalidArgumentException('Your available balance is too low to buy this gift.');
            }
            $lockedUser->balance = bcsub((string) $lockedUser->balance, $totalPrice, 8);
            $lockedUser->save();
            $this->ledger($lockedUser, $totalPrice, '-', 'gift_purchase', "Bought {$quantity}x {$gift->name}");

            $inventory = UserGiftInventory::firstOrCreate(['user_id' => $lockedUser->id, 'gift_id' => $gift->id], ['quantity' => 0]);
            $inventory->increment('quantity', $quantity);

            GiftTransaction::create([
                'type' => 'purchase', 'gift_id' => $gift->id, 'from_user_id' => null, 'to_user_id' => $lockedUser->id,
                'quantity' => $quantity, 'unit_buy_price' => $gift->buy_price, 'unit_cashout_value' => $gift->cashout_value,
            ]);

            return $inventory->fresh();
        });
    }

    public function send(User $sender, Gift $gift, User $recipient, int $quantity, ?string $tableGameId = null): ?TableGameMessage
    {
        if ($quantity < 1) throw new InvalidArgumentException('Choose at least one gift.');
        if ((int) $sender->id === (int) $recipient->id) throw new InvalidArgumentException('You cannot send a gift to yourself.');

        return DB::transaction(function () use ($sender, $gift, $recipient, $quantity, $tableGameId) {
            $senderInventory = UserGiftInventory::where('user_id', $sender->id)->where('gift_id', $gift->id)->lockForUpdate()->first();
            if (!$senderInventory || $senderInventory->quantity < $quantity) {
                throw new InvalidArgumentException('You do not have enough of this gift to send.');
            }
            $senderInventory->decrement('quantity', $quantity);

            $recipientInventory = UserGiftInventory::firstOrCreate(['user_id' => $recipient->id, 'gift_id' => $gift->id], ['quantity' => 0]);
            $recipientInventory->increment('quantity', $quantity);

            GiftTransaction::create([
                'type' => 'send', 'gift_id' => $gift->id, 'from_user_id' => $sender->id, 'to_user_id' => $recipient->id,
                'quantity' => $quantity, 'unit_buy_price' => $gift->buy_price, 'unit_cashout_value' => $gift->cashout_value,
                'table_game_id' => $tableGameId,
            ]);

            if (!$tableGameId) return null;

            // A chat message is purely the live "announcement" everyone watching already polls for -
            // the money/ownership truth lives entirely in user_gift_inventory/gift_transactions above.
            $message = TableGameMessage::create([
                'table_game_id' => $tableGameId, 'user_id' => $sender->id, 'kind' => 'gift',
                'body' => json_encode([
                    'gift_id' => $gift->id, 'gift_name' => $gift->name, 'gift_image' => $gift->imageUrl(),
                    'quantity' => $quantity, 'recipient_id' => $recipient->id, 'recipient_name' => $recipient->username,
                ]),
            ]);
            return $message->load('user:id,username,image');
        });
    }

    public function cashOut(User $user, Gift $gift, int $quantity): UserGiftInventory
    {
        if ($quantity < 1) throw new InvalidArgumentException('Choose at least one gift.');

        return DB::transaction(function () use ($user, $gift, $quantity) {
            $inventory = UserGiftInventory::where('user_id', $user->id)->where('gift_id', $gift->id)->lockForUpdate()->first();
            if (!$inventory || $inventory->quantity < $quantity) {
                throw new InvalidArgumentException('You do not have enough of this gift to cash out.');
            }
            $inventory->decrement('quantity', $quantity);

            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $unitValue = $this->quoteInWalletCurrency((string) $gift->cashout_value, $lockedUser);
            $totalValue = bcmul($unitValue, (string) $quantity, 8);
            $lockedUser->balance = bcadd((string) $lockedUser->balance, $totalValue, 8);
            $lockedUser->save();
            $this->ledger($lockedUser, $totalValue, '+', 'gift_cashout', "Cashed out {$quantity}x {$gift->name}");

            GiftTransaction::create([
                'type' => 'cashout', 'gift_id' => $gift->id, 'from_user_id' => $user->id, 'to_user_id' => null,
                'quantity' => $quantity, 'unit_buy_price' => $gift->buy_price, 'unit_cashout_value' => $gift->cashout_value,
            ]);

            return $inventory->fresh();
        });
    }

    /**
     * Converts a USD-denominated amount (how every gift is priced) into the user's own wallet
     * currency, using the same Currency::rate_to_usd pivot TableGameService::walletStakeQuoteFor()
     * already uses to convert table stakes between players' different wallet currencies.
     */
    private function quoteInWalletCurrency(string $usdAmount, User $user): string
    {
        if (!$user->currency_id) throw new InvalidArgumentException('A supported wallet currency is required to use gifts.');
        $walletCurrency = Currency::find($user->currency_id);
        if (!$walletCurrency?->enabled) throw new InvalidArgumentException('Gifts require an enabled wallet currency.');

        $rate = $walletCurrency->rate_to_usd;
        if (!$rate || !$walletCurrency->last_updated_at || $walletCurrency->last_updated_at->lt(now()->subHours(48))) {
            throw new InvalidArgumentException('A current exchange rate is not available for your wallet currency. Please try again after the daily rate refresh.');
        }

        $precision = max(12, (int) $walletCurrency->decimals + 2);
        $walletAmount = bcdiv($usdAmount, (string) $rate, $precision);
        $decimals = min(8, max(0, (int) $walletCurrency->decimals));
        $halfUnit = '0.' . str_repeat('0', $decimals) . '5';
        $rounded = bcadd($walletAmount, $halfUnit, max(12, $decimals + 1));
        return bcadd($rounded, '0', $decimals);
    }

    private function ledger(User $user, string $amount, string $type, string $remark, string $details): void
    {
        $transaction = new Transaction();
        $transaction->user_id = $user->id;
        $transaction->amount = $amount;
        $transaction->charge = 0;
        $transaction->trx_type = $type;
        $transaction->details = $details;
        $transaction->remark = $remark;
        $transaction->trx = strtoupper(Str::random(12));
        $transaction->post_balance = $user->balance;
        if (Schema::hasColumn('transactions', 'currency_id')) {
            $transaction->currency_id = $user->currency_id;
        }
        $transaction->save();
    }
}
