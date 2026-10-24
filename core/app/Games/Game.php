<?php

namespace App\Games;

use App\Constants\Status;
use App\Models\Game as GameModel;
use App\Models\GameLog;
use App\Models\GameplayBonusLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CoinLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Throwable;

abstract class Game {
    private static array $columnCache = [];
    private $globalValidationRule = [
        'invest' => 'required|numeric|gt:0',
    ];
    protected $extraValidationRule    = [];
    protected $exceptValidationRule   = [];
    protected $extraEndValidationRule = [];
    protected $alias;
    protected $game;
    protected $request;
    protected $user;
    protected $resultShowOnStart    = true;
    protected $extraResponseOnStart = [];
    protected $extraResponseOnEnd   = [];
    protected $userSelect;
    protected $hasCustomCompleteLogic = false;
    protected $extraResponseOnWin     = [];
    public $demoPlay                  = false;
    public $fromApi                   = false;

    public function __construct() {
        $this->request    = request();
        $this->user       = auth()->user();
        $this->game       = GameModel::active()->where('alias', $this->alias)->first();
        $this->userSelect = @$this->request->choose;
    }

    public function play() {
        if (!$this->game) {
            if ($this->fromApi) {
                $notify[] = 'Game not found';
                return responseError('not_found', $notify);
            }
            return response()->json(['error' => 'Game not found']);
        }

        $validator = Validator::make($this->request->all(), $this->getValidationRules());
        if ($validator->fails()) {
            if ($this->fromApi) {
                return responseError('validation_error', $validator->errors()->all());
            }
            return response()->json(['errors' => $validator->errors()->all()]);
        }

        try {
            $playData = DB::transaction(function () {
                $lockedUser = User::whereKey($this->user->id)->lockForUpdate()->first();
                if (!$lockedUser) {
                    return ['error' => 'User account not found'];
                }

                $this->user = $lockedUser;
                $fallback = $this->fallback();
                if (@$fallback['error']) {
                    return $fallback;
                }

                $gameResult = $this->gameResult();
                $investment = $this->invest($gameResult);

                return ['game_result' => $gameResult, 'investment' => $investment];
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $message = 'The game could not be started. Your balance was not changed.';
            if ($this->fromApi) {
                return responseError('game_error', [$message]);
            }
            return response()->json(['error' => $message], 500);
        }

        if (@$playData['error']) {
            if ($this->fromApi) {
                return responseError('fallback_error', [$playData['error']]);
            }
            return response()->json($playData);
        }

        $gameResult = $playData['game_result'];
        $invest     = $playData['investment'];
        $balance            = $this->demoPlay ? @$this->user->demo_balance : @$this->user->balance;
        $res['game_log_id'] = @$invest['game_log']->id;
        $res['balance']     = showAmount($balance, currencyFormat: false);

        if ($this->resultShowOnStart) {
            $res['result'] = $gameResult['result'];
        }

        if ($this->extraResponseOnStart) {
            $res = array_merge($res, $this->extraResponseOnStart);
        }

        if ($this->fromApi) {
            $notify[] = $this->game->name . ' investment data';
            return responseSuccess('investment_data', $notify, $res);
        }

        return response()->json($res);
    }

    public function complete() {

        if ($this->extraEndValidationRule) {
            $validator = Validator::make($this->request->all(), $this->extraEndValidationRule);
            if ($validator->fails()) {
                if ($this->fromApi) {
                    return responseError('validation_error', $validator->errors()->all());
                }
                return response()->json(['errors' => $validator->errors()->all()]);
            }
        }

        if (!$this->game) {
            if ($this->fromApi) {
                $notify[] = 'Game not found';
                return responseError('not_found', $notify);
            }
            return response()->json(['error' => 'Game not found']);
        }

        try {
        DB::beginTransaction();
        $lockedUser = User::whereKey($this->user->id)->lockForUpdate()->first();
        if (!$lockedUser) {
            DB::rollBack();
            if ($this->fromApi) {
                return responseError('not_found', ['User account not found']);
            }
            return response()->json(['error' => 'User account not found']);
        }
        $this->user = $lockedUser;

        $gameLog = GameLog::where('user_id', $this->user->id)
            ->where('id', $this->request->game_log_id)
            ->lockForUpdate()
            ->first();
        if (!$gameLog) {
            DB::commit();
            if ($this->fromApi) {
                $notify[] = 'Game Logs not found';
                return responseError('not_found', $notify);
            }
            return response()->json(['error' => 'Game Logs not found']);
        }

        if ($gameLog->status == Status::YES) {
            DB::commit();
            if ($this->fromApi) {
                $notify[] = 'Game Log already completed';
                return responseError('game_completed', $notify);
            }
            return response()->json(['error' => 'Game Log already completed']);
        }
        $trx = getTrx();

        if ($this->hasCustomCompleteLogic) {

            $customCompleteLogic = $this->customCompleteLogic($gameLog);

            if ($customCompleteLogic['should_return']) {
                DB::commit();
                if ($this->fromApi) {
                    if (@$customCompleteLogic['data']['error']) {
                        $notify[] = @$customCompleteLogic['data']['error'];
                        return responseError('error', $notify);
                    }
                    $notify[] = @$customCompleteLogic['data']['message'] ?? 'Success Data';
                    return responseSuccess('success_data', $notify, $customCompleteLogic['data']);
                }

                return response()->json($customCompleteLogic['data']);
            }
        }

        if ($gameLog->win_status == Status::WIN) {
            $winBon = $gameLog->invest * $this->game->win / 100;
            $amount = $winBon;

            $investBack = 0;
            $coinMode = !$this->demoPlay && gs('coin_mode');

            if ($this->game->invest_back == Status::YES) {
                $investBack = $gameLog->invest;

                if ($this->demoPlay) {
                    $this->user->demo_balance += $gameLog->invest;
                    $this->user->save();
                } elseif (!$coinMode) {
                    $this->user->balance += $gameLog->invest;
                    $this->user->save();

                    $transaction               = new Transaction();
                    $transaction->user_id      = $this->user->id;
                    $transaction->amount       = $investBack;
                    $transaction->charge       = 0;
                    $transaction->trx_type     = '+';
                    $transaction->details      = 'Invest Back For ' . $this->game->name;
                    $transaction->remark       = 'invest_back';
                    $transaction->trx          = $trx;
                    $transaction->post_balance = $this->user->balance;
                    if ($this->hasCurrencyColumn('transactions')) $transaction->currency_id = $this->user->currency_id;
                    $transaction->save();
                } elseif (bccomp((string) $investBack, '0', 8) > 0) {
                    // Coin mode: the invest-back payout is the house (platform wallet) paying
                    // the player back, the same direction of coin movement as a win bonus or a
                    // promotional grant - mint(), not a plain credit(), so the platform wallet
                    // moves in lockstep and CoinLedger::reconcile() keeps balancing. A plain
                    // credit() here would create coins in the player's wallet out of nothing,
                    // without the matching platform-side debit mint() provides.
                    app(CoinLedger::class)->mint(
                        'user',
                        $this->user->id,
                        (string) $investBack,
                        'Invest Back For ' . $this->game->name,
                        'game_invest_back:' . $gameLog->id,
                        'GameLog',
                        $gameLog->id,
                        'user',
                        $this->user->id
                    );
                }

            }

            if ($this->demoPlay) {
                $this->user->demo_balance += $amount;
                $this->user->save();
            } elseif (!$coinMode) {
                $this->user->balance += $amount;
                $this->user->save();
            } elseif (bccomp((string) $amount, '0', 8) > 0) {
                // Coin mode: the win bonus is the house paying the player from the platform
                // wallet, so it mints (platform debit + player credit) rather than a plain
                // credit() - see the invest-back comment above for why that distinction matters
                // for CoinLedger::reconcile().
                app(CoinLedger::class)->mint(
                    'user',
                    $this->user->id,
                    (string) $amount,
                    'Win bonus of ' . $this->game->name,
                    'game_win:' . $gameLog->id,
                    'GameLog',
                    $gameLog->id,
                    'user',
                    $this->user->id
                );
            }

            $gameLog->win_amo = $amount;
            $gameLog->save();

            if (!$this->demoPlay && !$coinMode) {
                $transaction               = new Transaction();
                $transaction->user_id      = $this->user->id;
                $transaction->amount       = $winBon;
                $transaction->charge       = 0;
                $transaction->trx_type     = '+';
                $transaction->details      = 'Win bonus of ' . $this->game->name;
                $transaction->remark       = 'Win_Bonus';
                $transaction->trx          = $trx;
                $transaction->post_balance = $this->user->balance;
                if ($this->hasCurrencyColumn('transactions')) $transaction->currency_id = $this->user->currency_id;
                $transaction->save();
            }

            $res['message'] = 'Yahoo! You Win!!!';
            $res['type']    = 'success';
        } else {
            $res['message'] = 'Oops! You Lost!!';
            $res['type']    = 'danger';
        }

        if ($this->extraResponseOnEnd) {
            $res = array_merge($res, $this->extraResponseOnEnd);
        }

        $balance = $this->demoPlay ? @$this->user->demo_balance : @$this->user->balance;

        $res['result']      = in_array($this->game->alias, ['keno']) ? json_decode($gameLog->result) : $gameLog->result;
        $res['user_choose'] = in_array($this->game->alias, ['keno']) ? json_decode($gameLog->user_select) : $gameLog->user_select;
        $res['win_status']  = $gameLog->win_status;
        $res['win_amount']  = $gameLog->win_status == Status::WIN ? $winBon + $investBack : 0;
        $res['invest_amount'] = $gameLog->invest;
        $res['bal']         = showAmount($balance, currencyFormat: false);

        $gameLog->status = Status::GAME_FINISHED;
        $gameLog->save();

        $this->checkAndGiveBonus($this->user->id, $this->game->id);

        DB::commit();

        if ($this->fromApi) {
            $notify[] = $this->game->name . ' result data';
            return responseSuccess('result_data', $notify, $res);
        }

        if ($this->game->alias == 'pai_gow_poker') {
            $res['dealerCards'] = $customCompleteLogic['data']['dealer'];
        }

        return $res;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            report($exception);
            $message = 'The game result could not be saved. Please refresh your balance before retrying.';
            if ($this->fromApi) {
                return responseError('game_error', [$message]);
            }
            return response()->json(['error' => $message], 500);
        }
    }

    private function getValidationRules() {
        return array_merge($this->globalValidationRule, $this->extraValidationRule);
    }

    private function fallback() {
        // Coin mode never credits/debits $user->balance for real play any more (see invest()
        // below), so the sufficiency pre-check here has to read the coin wallet instead - the
        // legacy balance column would otherwise sit untouched at whatever it was before coin
        // mode was switched on and block every real play. The authoritative check is still
        // CoinLedger::debit()'s own re-check under lock inside invest(); this is only the
        // same early, friendlier rejection the legacy path already gave before even trying.
        $coinMode = !$this->demoPlay && gs('coin_mode');
        $balance = $this->demoPlay
            ? @$this->user->demo_balance
            : ($coinMode ? app(CoinLedger::class)->wallet('user', $this->user->id)->available_balance : @$this->user->balance);
        if ($this->request->invest > $balance) {
            return ['error' => 'Oops! You have no sufficient balance'];
        }
        $running = GameLog::where('status', 0)->where('user_id', @$this->user->id)->where('game_id', $this->game->id)->first();
        if ($running) {
            return ['error' => '1 game is in-complete. Please wait'];
        }
        if ($this->request->invest > $this->game->max_limit) {
            return ['error' => 'Please follow the maximum limit of invest'];
        }
        if ($this->request->invest < $this->game->min_limit) {
            return ['error' => 'Please follow the minimum limit of invest'];
        }
        return ['success'];
    }

    private function invest($gameResult) {
        $user = $this->user;
        $coinMode = !$this->demoPlay && gs('coin_mode');

        if ($this->demoPlay) {
            $user->demo_balance -= $this->request->invest;
            $user->save();
        } elseif (!$coinMode) {
            $user->balance -= $this->request->invest;
            $user->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $user->id;
            $transaction->amount       = $this->request->invest;
            $transaction->charge       = 0;
            $transaction->trx_type     = '-';
            $transaction->details      = 'Invest to ' . $this->game->name;
            $transaction->remark       = 'invest';
            $transaction->trx          = getTrx();
            $transaction->post_balance = $user->balance;
            if ($this->hasCurrencyColumn('transactions')) $transaction->currency_id = $user->currency_id;
            $transaction->save();
        }
        // Coin mode's stake debit happens below, after the GameLog row is created - its
        // idempotency key is derived from that row's id, so it can't be issued before the row
        // exists.

        $gameLog          = new GameLog();
        $gameLog->user_id = $user->id ?? 0;
        if ($this->hasCurrencyColumn('game_logs')) $gameLog->currency_id = $user->currency_id;
        $gameLog->game_id = $this->game->id;
        if ($this->demoPlay) {
            $gameLog->demo_play = Status::YES;
        }
        $gameLog->user_select = $this->userSelect;
        $gameLog->result      =
        in_array($this->game->alias, ['number_slot', 'roulette', 'keno', 'poker', 'blackjack', 'pai_gow_poker'])
        ? json_encode($gameResult['result'])
        : (
            $this->game->id == 4
            ? decrypt($gameResult['result'])
            : $gameResult['result']
        );

        $gameLog->status         = 0;
        $gameLog->win_status     = @$gameResult['win_status'] ?? 0;
        $gameLog->invest         = $this->request->invest;
        $gameLog->win_amo        = @$gameResult['win_amount'] ?? 0;
        $gameLog->mines          = @$this->request->mines ?? 0;
        $gameLog->mine_available = @$this->request->mines ?? 0;
        $gameLog->save();

        if ($coinMode) {
            // The GameLog row now exists, so its id can anchor a unique, replay-safe
            // idempotency key - a retried/duplicated invest() call for the same GameLog can
            // never double-debit the stake. The stake moves to the house (platform wallet), the
            // same direction as a deposit purchase or a float grant moving the other way -
            // burn() (player debit + platform credit), not a plain debit(), so the platform
            // wallet moves in lockstep and CoinLedger::reconcile() keeps balancing. A plain
            // debit() here would remove coins from circulation without a matching platform
            // credit, which reconcile() would then flag as a mismatch.
            app(CoinLedger::class)->burn(
                'user',
                $user->id,
                (string) $this->request->invest,
                'Invest to ' . $this->game->name,
                'game_invest:' . $gameLog->id,
                'GameLog',
                $gameLog->id,
                'user',
                $user->id
            );
        }

        return ['game_log' => $gameLog];
    }

    private function checkAndGiveBonus($userId, $gameId) {
        if (!gs('game_play')) {
            return;
        }

        $alreadyRewarded = GameplayBonusLog::where('user_id', $userId)
            ->where('game_id', $gameId)
            ->exists();

        if ($alreadyRewarded) {
            return;
        }

        // This already runs nested inside complete()'s own locked transaction on this same
        // user row, so a second concurrent complete() call for this user is already serialized
        // behind that lock. Taking the lock again here explicitly (rather than a plain find())
        // is defense in depth - it keeps this method safe on its own if it's ever called from
        // anywhere that isn't already holding that lock.
        $user = User::whereKey($userId)->lockForUpdate()->first();
        if (!$user) {
            return;
        }

        $requiredPlayCount = getAmount(gs('gameplay_number_for_bonus'));
        $bonusAmount       = getAmount(gs('gameplay_bonus'));
        $playCount         = $user->gameLogs()->where('game_id', $gameId)->where('demo_play', Status::NO)->count();

        if ($playCount >= $requiredPlayCount) {
            // Coin mode: the gameplay bonus becomes a flat coin amount minted through
            // CoinLedger instead of credited to real-currency balance (decision 9). The
            // alreadyRewarded check above, plus the user-row lock taken before this point,
            // already guarantee this fires at most once per user+game - the idempotency key
            // below is defense in depth on top of that, same spirit as the lock itself.
            if (gs('coin_mode')) {
                $idempotencyKey = 'gameplay_bonus:' . $userId . ':' . $gameId;
                app(CoinLedger::class)->mint(
                    'user',
                    $user->id,
                    (string) $bonusAmount,
                    'Gameplay Bonus',
                    $idempotencyKey,
                    'Game',
                    $gameId,
                    'system'
                );

                $bonusLog          = new GameplayBonusLog();
                $bonusLog->user_id = $userId;
                $bonusLog->game_id = $gameId;
                $bonusLog->amount  = $bonusAmount;
                if ($this->hasCurrencyColumn('gameplay_bonus_logs')) $bonusLog->currency_id = $user->currency_id;
                $bonusLog->save();

                // Coins are always whole numbers - showAmount()'s 2-decimal default would show
                // "500.00" for a coin bonus even with currencyFormat disabled, so these use a
                // plain whole-number format instead, same as every other coin-amount shortcode.
                notify($user, 'GAMEPLAY_BONUS', [
                    'amount'       => number_format((float) $bonusAmount, 0, '.', ''),
                    'trx'          => $idempotencyKey,
                    'post_balance' => number_format((float) app(CoinLedger::class)->wallet('user', $user->id)->fresh()->available_balance, 0, '.', ''),
                ]);
            } else {
                $user->balance = bcadd((string) $user->balance, (string) $bonusAmount, 8);
                $user->save();

                $transaction               = new Transaction();
                $transaction->user_id      = $user->id;
                $transaction->amount       = $bonusAmount;
                $transaction->charge       = 0;
                $transaction->trx_type     = '+';
                $transaction->details      = 'Gameplay Bonus';
                $transaction->remark       = 'gameplay_bonus';
                $transaction->trx          = getTrx();
                $transaction->post_balance = $user->balance;
                if ($this->hasCurrencyColumn('transactions')) $transaction->currency_id = $user->currency_id;
                $transaction->save();

                $bonusLog          = new GameplayBonusLog();
                $bonusLog->user_id = $userId;
                $bonusLog->game_id = $gameId;
                $bonusLog->amount  = $bonusAmount;
                if ($this->hasCurrencyColumn('gameplay_bonus_logs')) $bonusLog->currency_id = $user->currency_id;
                $bonusLog->save();

                notify($user, 'GAMEPLAY_BONUS', [
                    'amount'       => showAmount($transaction->amount, currencyFormat: false),
                    'trx'          => $transaction->trx,
                    'post_balance' => showAmount($user->balance, currencyFormat: false),
                ]);
            }
        }
    }

    private function hasCurrencyColumn(string $table): bool
    {
        return self::$columnCache[$table] ??= Schema::hasColumn($table, 'currency_id');
    }

    public function setRequest(Request $request) {
        $this->request = $request;
    }
}
