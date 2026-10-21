<?php

namespace App\Services;

use App\Models\SupportBotTraining;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SupportBot
{
    public function welcome(): array
    {
        return [
            'text' => 'Welcome to Winxpot Support! 👋 I’m your support assistant. I can help with your account, deposits, withdrawals, bonuses, verification, and games. What can I help you with today?',
            'suggestions' => ['My account or login', 'Deposit help', 'Withdrawal help', 'Bonuses and VIP', 'Verification', 'Games and gameplay', 'Chat with a live agent'],
        ];
    }

    public function respond(string $message): array
    {
        $text = mb_strtolower(trim($message));
        $text = preg_replace('/[^\pL\pN\s]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        if ($this->matches($text, ['human', 'live agent', 'talk to agent', 'speak to agent', 'real person', 'chat with agent', 'chat with a person', 'live support', 'talk to someone'])) {
            return ['handoff' => true];
        }

        // Admin-trained responses take priority over the built-in rules below, so a trainer
        // can sharpen or override the default behavior without touching code.
        $trained = $this->trainedResponse($text);
        if ($trained !== null) {
            return $trained;
        }

        if ($this->matches($text, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'])) {
            return ['text' => 'Hi there! 👋 What can I help you with?', 'suggestions' => ['Account or login', 'Deposits', 'Withdrawals', 'Bonuses', 'Talk to an agent']];
        }

        if ($this->matches($text, ['login', 'log in', 'password', 'forgot', 'account', 'sign in', 'register', 'registration', 'username', 'email'])) {
            return ['text' => 'For sign-in trouble, check that your username or email and password are correct. If you forgot your password, use “Forgot password?” on the login page and follow the email instructions. Never share your password or verification codes in chat.', 'suggestions' => ['I forgot my password', 'I cannot access my email', 'Create an account', 'Chat with a live agent']];
        }

        if ($this->matches($text, ['deposit', 'fund', 'payment', 'paid', 'top up', 'topup', 'crypto', 'transaction pending'])) {
            return ['text' => 'To add funds, sign in and open Deposit, choose an available payment method, and follow its instructions. Processing time depends on the method. If a completed payment is missing, keep the transaction reference and contact an agent; please do not send card details or private keys here.', 'suggestions' => ['My deposit is missing', 'Payment is pending', 'Payment methods', 'Chat with a live agent']];
        }

        if ($this->matches($text, ['withdraw', 'cash out', 'payout', 'withdrawal'])) {
            return ['text' => 'Open Withdraw from your account to review the available methods and submit a request. Requests may require completed verification and are processed according to the selected method. For a specific pending withdrawal, an agent can check your account.', 'suggestions' => ['Withdrawal is pending', 'Withdrawal was rejected', 'Verification help', 'Chat with a live agent']];
        }

        if ($this->matches($text, ['bonus', 'promo', 'promotion', 'referral', 'rakeback', 'vip', 'code'])) {
            return ['text' => 'Open Bonuses to review active offers, referral rewards, and any available rakeback. Each offer can have its own eligibility and wagering terms, so check those details before activating it. An agent can help with a specific promotion.', 'suggestions' => ['How do I use a bonus code?', 'Referral rewards', 'Rakeback or VIP', 'Chat with a live agent']];
        }

        if ($this->matches($text, ['kyc', 'verify', 'verification', 'identity', 'documents', 'document'])) {
            return ['text' => 'Open Verification in your account and submit the requested information and clear, valid documents. You can return to the same page to check your status. For your privacy, upload documents only through the secure verification page, not in chat.', 'suggestions' => ['Check verification status', 'My verification was rejected', 'Chat with a live agent']];
        }

        if ($this->matches($text, ['game', 'play', 'demo', 'not loading', 'crash', 'spin', 'slot', 'balance'])) {
            return ['text' => 'Try refreshing the game and checking your connection. Demo mode does not use your real balance; real play uses the amount shown before you confirm. If a game freezes during a round, note the game name and approximate time, then ask an agent to review it.', 'suggestions' => ['A game is not loading', 'I have a game result question', 'Demo mode', 'Chat with a live agent']];
        }

        if ($this->matches($text, ['security', 'scam', 'fraud', 'responsible', 'close account', 'self exclude'])) {
            return ['text' => 'Keep your password and one-time codes private. If you are concerned about account security or want help managing your play, contact a live agent so the team can guide you through the appropriate account options.', 'suggestions' => ['Account security', 'Responsible play help', 'Chat with a live agent']];
        }

        return ['text' => 'I’m still learning about that topic. I can help with account access, deposits, withdrawals, bonuses, verification, and games. You can choose a topic below or connect with a live support agent.', 'suggestions' => ['Account or login', 'Deposits', 'Withdrawals', 'Bonuses', 'Verification', 'Games', 'Chat with a live agent']];
    }

    private function matches(string $message, array $terms): bool
    {
        foreach ($terms as $term) {
            if (str_contains($message, $term)) {
                return true;
            }
        }

        return false;
    }

    private function trainedResponse(string $text): ?array
    {
        if (!Schema::hasTable('support_bot_trainings')) {
            return null;
        }

        $trainings = Cache::remember('support_bot_trainings', 300, function () {
            return SupportBotTraining::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        });

        foreach ($trainings as $training) {
            foreach ($training->triggerList() as $phrase) {
                $phrase = mb_strtolower($phrase);
                if ($phrase !== '' && str_contains($text, $phrase)) {
                    return ['text' => $training->response, 'suggestions' => []];
                }
            }
        }

        return null;
    }
}
