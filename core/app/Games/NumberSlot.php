<?php

namespace App\Games;

use App\Constants\Status;
use App\Models\Transaction;

class NumberSlot extends Game {
    protected $alias                  = 'number_slot';
    protected $resultShowOnStart      = true;
    protected $hasCustomCompleteLogic = true;
    protected $extraValidationRule    = [
        'choose' => 'required|in:0,1,2,3,4,5,6,7,8,9',
    ];

    protected function gameResult() {
        $random = mt_rand(0, 100);
        if ($this->game->probable_win[0] > $random) {
            $result = $this->numberSlotResult(0, $this->request->choose);
            $win    = 0;
        } else if ($this->game->probable_win[0] + $this->game->probable_win[1] > $random) {
            $result = $this->numberSlotResult(1, $this->request->choose);
            $win    = 1;
        } else if ($this->game->probable_win[0] + $this->game->probable_win[1] + $this->game->probable_win[2] > $random) {
            $result = $this->numberSlotResult(2, $this->request->choose);
            $win    = 2;
        } else {
            $result = $this->numberSlotResult(3, $this->request->choose);
            $win    = 3;
        }
        $winLossData['win_status'] = $win;
        $winLossData['result']     = $result;
        return $winLossData;
    }

    protected function customCompleteLogic($gameLog) {
        $game   = $this->game;
        $user   = $this->user;
        $trx    = getTrx();
        $winner = 0;
        // Coin mode: this game's win bonus bypasses Game::end()'s own (already coin-aware) win
        // crediting entirely via should_return=true below, so it needs the exact same mint()-vs-
        // balance branch here, or a win while coin_mode is on would silently credit the frozen
        // real-currency balance instead of the coin wallet - the player would never see it.
        $coinMode = !$this->demoPlay && gs('coin_mode');
        foreach ($game->level as $key => $data) {
            if ($gameLog->win_status == $key + 1) {
                $winBon = $gameLog->invest * $game->level[$key] / 100;
                $amount = $winBon;

                if ($this->demoPlay) {
                    $this->adjustDemoBalance((string) $amount);
                } elseif ($coinMode) {
                    if (bccomp((string) $amount, '0', 8) > 0) {
                        app(\App\Services\CoinLedger::class)->mint(
                            'user',
                            $user->id,
                            (string) $amount,
                            $game->level[$key] . '% Win bonus of Number Slot Game ' . ($key + 1) . ' Time',
                            'game_win_bonus:' . $gameLog->id,
                            'GameLog',
                            $gameLog->id,
                            'user',
                            $user->id
                        );
                    }
                } else {
                    // $amount is derived entirely from $gameLog->invest, which is always
                    // site-default currency - convert at the one point it crosses into balance,
                    // same as Game::complete()'s own win-crediting fix.
                    $this->user->balance += convertAmountFromSiteDefault((string) $amount, $this->user->currency);
                    $this->user->save();
                }

                $gameLog->win_amo = $amount;
                $gameLog->save();

                $winner = 1;
                $lev    = $key + 1;

                if (!$this->demoPlay && !$coinMode) {
                    $transaction               = new Transaction();
                    $transaction->user_id      = $user->id;
                    $transaction->amount       = $winBon;
                    $transaction->charge       = 0;
                    $transaction->trx_type     = '+';
                    $transaction->details      = $game->level[$key] . '% Win bonus of Number Slot Game ' . $lev . ' Time';
                    $transaction->remark       = 'win_bonus';
                    $transaction->trx          = $trx;
                    $transaction->post_balance = $user->balance;
                    $transaction->save();
                }
            }
        }
        $balance            = $this->demoPlay
            ? $this->demoBalanceDisplay()
            : ($coinMode ? app(\App\Services\CoinLedger::class)->wallet('user', $user->id)->fresh()->available_balance : @$this->user->balance);
        $res['user_choose'] = $gameLog->user_select;
        // Demo play is never credited through the ledger (see above), but coin mode still
        // decides how this response's balance is LABELLED - gs('coin_mode') alone, not $coinMode
        // (which is false for demo), or a demo win would keep showing a stale currency figure.
        $res['bal']         = gs('coin_mode') ? number_format((float) $balance, 0, '.', '') : showAmount($balance, currencyFormat: false);
        $res['result']      = $gameLog->result;
        if ($winner == 1) {
            $res['message']    = 'Yahoo! You Win for ' . $gameLog->win_status . ' Time !!!';
            $res['win_status'] = Status::WIN;
        } else {
            $res['message']    = 'Oops! You Lost!!';
            $res['win_status'] = Status::LOSS;
        }

        $gameLog->status = Status::ENABLE;
        $gameLog->save();

        return [
            'should_return' => true,
            'data'          => $res,
        ];
    }

    private function numberSlotResult($win, $num) {
        for ($i = 0; $i < $win; $i++) {
            $res[] = (int) $num;
        }

        $left = 3 - $win;
        while ($left > 0) {
            $newRand = mt_rand(0, 9);
            if ($newRand != $num) {
                $res[] = $newRand;
                $left--;
            }
        }
        shuffle($res);
        return $res;
    }
}
