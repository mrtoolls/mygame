<?php

namespace App\Http\Controllers\Api;

use App\Games\GamePlayer;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GuessBonus;
use Illuminate\Http\Request;

class PlayController extends Controller {
    public function playGame($alias, $isDemo = null) {
        $game = Game::active()->where('alias', $alias)->first();
        if (!$game) {
            $notify[] = 'Game not found';
            return responseError('not_found', $notify);
        }
        $user = auth()->user();
        if ($isDemo && $isDemo !== 'demo') {
            $notify[] = 'Invalid request';
            return responseError('not_found', $notify);
        }
        // $user->balance is frozen/zeroed once coin_mode is on (see showUserBalance()'s doc
        // comment) - the real, live number lives in the CoinLedger wallet instead. demo_balance
        // is deliberately untouched by coin mode either way - it is its own separate "Demo
        // Coins" concept, never minted as real coins and never mixed with the real wallet.
        $balance = ($isDemo === 'demo')
            ? @$user->demo_balance
            : (gs('coin_mode') ? app(\App\Services\CoinLedger::class)->wallet('user', $user->id)->available_balance : @$user->balance);

        $pokerImg           = null;
        $imagePath          = null;
        $winPercent         = [];
        $winChance          = null;
        $cardFindingImgName = [];
        $cardFindingImgPath = null;

        $gesBon = [];

        if ($game->alias == 'number_guess') {
            $gesBon = GuessBonus::where('alias', $game->alias)->get();
            foreach ($gesBon as $bon) {
                array_push($winPercent, $bon->percent);
                $winChance++;
            };
        }

        if ($game->alias == 'poker') {
            $gesBon = GuessBonus::where('alias', $game->alias)
                ->orderBy('chance', 'asc')
                ->pluck('percent')
                ->toArray();

            $pokerImg = [
                'royal_flush.png',
                'straight_flush.png',
                'four_kind.png',
                'full_house.png',
                'flash.png',
                'straight.png',
                'three_kind.png',
                'two_pair.png',
                'one_pair.png',
                'high_card.png',
            ];

            $imagePath = asset(activeTemplate(true) . 'images/poker');
        }

        if ($game->alias == 'card_finding') {
            $cardFindingImgPath = asset(activeTemplate(true) . 'images/play/cards');
            for ($i = 5; $i < 54; $i = $i + 5) {
                $cardFindingImgName[] = sprintf("%02d", $i);
            }
        }

        if ($game->alias == 'pai_gow_poker') {
            $imagePath = asset(activeTemplate(true) . 'images/cards');
        }

        $notify[] = $game->name . ' game data';
        return responseSuccess('game_data', $notify, [
            'game'               => $game,
            // Coins are always whole numbers with no currency formatting - the game client parses
            // this as a plain number either way, so coin mode just skips decimal places.
            'balance'            => ($isDemo !== 'demo' && gs('coin_mode')) ? number_format((float) $balance, 0, '.', '') : showAmount($balance, currencyFormat: false),
            'imagePath'          => $imagePath,
            'winChance'          => $winChance,
            'winPercent'         => $winPercent,
            'gesBon'             => $gesBon,
            'pokerImg'           => $pokerImg,
            'shortDesc'          => ($game->alias == 'blackjack' ? $game->short_desc : null),
            'cardFindingImgName' => $cardFindingImgName,
            'cardFindingImgPath' => $cardFindingImgPath,
            'isDemo'             => $isDemo,
        ]);
    }

    public function investGame(Request $request, $alias, $isDemo = null) {
        $gamePlayer = new GamePlayer($alias, $isDemo, true);
        return $gamePlayer->startGame();
    }

    public function gameEnd(Request $request, $alias, $isDemo = null) {
        $gamePlayer = new GamePlayer($alias, $isDemo, true);
        return $gamePlayer->completeGame();
    }

}
