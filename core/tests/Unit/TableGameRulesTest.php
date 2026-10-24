<?php

namespace Tests\Unit;

use App\Games\TableGames\TableGameRules;
use App\Games\TableGames\TableGameSettlementMath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DeterministicLudoRules extends TableGameRules
{
    public array $rolls = [];

    protected function rollLudoDie(): int
    {
        return array_shift($this->rolls) ?? 1;
    }
}

class TableGameRulesTest extends TestCase
{
    private TableGameRules $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = new TableGameRules();
    }

    public function test_x_and_o_win_and_full_board_draw_are_server_determined(): void
    {
        $state = ['board' => [0, 0, null, 1, 1, null, null, null, null], 'turn' => 0, 'moves' => 4];
        $result = $this->rules->move('tic_tac_toe', $state, 0, ['cell' => 2]);
        self::assertSame(0, $result['winner']);
        self::assertFalse($result['draw']);

        $draw = ['board' => [0, 1, 0, 0, 1, 1, 1, 0, null], 'turn' => 0, 'moves' => 8];
        $result = $this->rules->move('tic_tac_toe', $draw, 0, ['cell' => 8]);
        self::assertNull($result['winner']);
        self::assertTrue($result['draw']);
    }

    public function test_four_in_a_row_detects_a_vertical_win(): void
    {
        $state = ['board' => array_fill(0, 6, array_fill(0, 7, null)), 'turn' => 1, 'moves' => 6];
        foreach ([5, 4, 3] as $row) $state['board'][$row][2] = 1;
        $result = $this->rules->move('four_in_a_row', $state, 1, ['column' => 2]);
        self::assertSame(1, $result['winner']);
    }

    public function test_ludo_and_snakes_dice_are_generated_by_the_server(): void
    {
        $ludo = $this->rules->start('ludo', 2);
        $result = $this->rules->move('ludo', $ludo, 0, ['roll' => true]);
        self::assertGreaterThanOrEqual(1, $result['state']['pending_roll']);
        self::assertLessThanOrEqual(6, $result['state']['pending_roll']);
        self::assertSame(0, $result['state']['last_roll']['seat']);

        $snakes = $this->rules->start('snakes_and_ladders', 2);
        $result = $this->rules->move('snakes_and_ladders', $snakes, 0, []);
        self::assertGreaterThanOrEqual(1, $result['state']['roll']['value']);
        self::assertLessThanOrEqual(6, $result['state']['roll']['value']);
        self::assertSame(0, $result['state']['roll']['seat']);
    }

    public function test_ludo_v2_capture_gives_one_bonus_roll_even_when_multiple_tokens_are_captured(): void
    {
        $state = ['tokens' => [[4, -1, -1, -1], [44, 44, -1, -1]], 'starts' => [0, 13], 'turn' => 0, 'moves' => 0, 'pending_roll' => 1, 'last_roll' => ['seat' => 0, 'value' => 1]];
        $result = $this->rules->move('ludo', $state, 0, ['token' => 0], [], 2);

        self::assertSame(-1, $result['state']['tokens'][1][0]);
        self::assertSame(-1, $result['state']['tokens'][1][1]);
        self::assertSame(0, $result['state']['turn']);
        self::assertTrue($result['state']['bonus_roll_pending']);
        self::assertSame('Captured 2 tokens — roll again.', $result['message']);
    }

    public function test_ludo_v2_safe_square_does_not_capture_or_award_a_capture_bonus(): void
    {
        $state = ['tokens' => [[7, -1, -1, -1], [47, -1, -1, -1]], 'starts' => [0, 13], 'turn' => 0, 'moves' => 0, 'pending_roll' => 1, 'last_roll' => ['seat' => 0, 'value' => 1]];
        $result = $this->rules->move('ludo', $state, 0, ['token' => 0], [], 2);

        self::assertSame(47, $result['state']['tokens'][1][0]);
        self::assertSame(1, $result['state']['turn']);
        self::assertFalse($result['state']['bonus_roll_pending']);
        self::assertNull($result['message']);
    }

    public function test_ludo_v2_non_capture_move_does_not_award_a_bonus_roll(): void
    {
        $state = ['tokens' => [[4, -1, -1, -1], [-1, -1, -1, -1]], 'starts' => [0, 13], 'turn' => 0, 'moves' => 0, 'pending_roll' => 1, 'last_roll' => ['seat' => 0, 'value' => 1]];
        $result = $this->rules->move('ludo', $state, 0, ['token' => 0], [], 2);

        self::assertSame(1, $result['state']['turn']);
        self::assertFalse($result['state']['bonus_roll_pending']);
        self::assertNull($result['message']);
    }

    public function test_ludo_v2_home_entry_awards_bonus_but_finishing_the_last_token_does_not(): void
    {
        $rules = new DeterministicLudoRules();
        $state = ['tokens' => [[56, -1, -1, -1], [-1, -1, -1, -1]], 'starts' => [0, 13], 'turn' => 0, 'moves' => 0, 'pending_roll' => 1, 'last_roll' => ['seat' => 0, 'value' => 1]];
        $bonus = $rules->move('ludo', $state, 0, ['token' => 0], [], 2);
        self::assertSame(58, $bonus['state']['tokens'][0][0]);
        self::assertSame(0, $bonus['state']['turn']);
        self::assertTrue($bonus['state']['bonus_roll_pending']);
        self::assertSame('Token reached home — roll again.', $bonus['message']);

        $state['tokens'][0] = [56, 58, 58, 58];
        $win = $rules->move('ludo', $state, 0, ['token' => 0], [], 2);
        self::assertSame(0, $win['winner']);
        self::assertSame(58, $win['state']['tokens'][0][0]);
        self::assertNull($win['state']['pending_roll']);
        self::assertFalse($win['state']['bonus_roll_pending']);
    }

    public function test_ludo_v2_bonus_roll_without_a_legal_move_passes_even_on_a_six(): void
    {
        $rules = new DeterministicLudoRules();
        $rules->rolls = [6];
        $state = ['tokens' => [[58, 58, 58, 58], [-1, -1, -1, -1]], 'starts' => [0, 13], 'turn' => 0, 'moves' => 0, 'pending_roll' => null, 'last_roll' => null, 'bonus_roll_pending' => true];
        $result = $rules->move('ludo', $state, 0, ['roll' => true], [], 2);

        self::assertSame(1, $result['state']['turn']);
        self::assertNull($result['state']['pending_roll']);
        self::assertFalse($result['state']['bonus_roll_pending']);
        self::assertSame('No legal move on the bonus roll — turn passed.', $result['message']);
    }

    public function test_ludo_finished_token_remains_serialized_as_finished_and_old_rules_do_not_add_capture_bonus(): void
    {
        $state = ['tokens' => [[56, 58, 58, 58], [-1, -1, -1, -1]], 'starts' => [0, 13], 'turn' => 0, 'moves' => 0, 'pending_roll' => 1, 'last_roll' => ['seat' => 0, 'value' => 1]];
        $finished = $this->rules->move('ludo', $state, 0, ['token' => 0], [], 2)['state'];
        $reloaded = json_decode(json_encode($finished), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(58, $reloaded['tokens'][0][0]);

        $oldRulesState = ['tokens' => [[4, -1, -1, -1], [44, -1, -1, -1]], 'starts' => [0, 13], 'turn' => 0, 'moves' => 0, 'pending_roll' => 1, 'last_roll' => ['seat' => 0, 'value' => 1]];
        $oldRules = $this->rules->move('ludo', $oldRulesState, 0, ['token' => 0], [], 1);
        self::assertSame(1, $oldRules['state']['turn']);
        self::assertArrayNotHasKey('bonus_roll_pending', $oldRules['state']);
        self::assertSame(-1, $oldRules['state']['tokens'][1][0]);
    }

    public function test_ludo_bonus_chain_keeps_the_turn_for_a_six_then_passes_after_a_normal_move(): void
    {
        $rules = new DeterministicLudoRules();
        $rules->rolls = [6];
        $state = ['tokens' => [[4, -1, -1, -1], [49, -1, -1, -1]], 'starts' => [0, 13], 'turn' => 0, 'moves' => 0, 'pending_roll' => 6, 'last_roll' => ['seat' => 0, 'value' => 6], 'bonus_roll_pending' => false];
        $capture = $rules->move('ludo', $state, 0, ['token' => 0], [], 2);
        self::assertSame(0, $capture['state']['turn']);
        self::assertTrue($capture['state']['bonus_roll_pending']);
        $roll = $rules->move('ludo', $capture['state'], 0, ['roll' => true], [], 2);
        self::assertSame(6, $roll['state']['pending_roll']);
        self::assertSame(0, $roll['state']['turn']);
        $move = $rules->move('ludo', $roll['state'], 0, ['token' => 0], [], 2);
        self::assertSame(10, $move['state']['tokens'][0][0]);
        self::assertSame(1, $move['state']['turn']);
    }

    public function test_chess_validates_legal_moves_and_rejects_illegal_moves(): void
    {
        $state = $this->rules->start('chess', 2);
        $result = $this->rules->move('chess', $state, 0, ['from' => 'e2', 'to' => 'e4']);
        self::assertSame(1, $result['state']['turn']);
        $this->expectException(InvalidArgumentException::class);
        $this->rules->move('chess', $state, 0, ['from' => 'e2', 'to' => 'e5']);
    }

    public function test_draughts_requires_a_capture_when_available(): void
    {
        $state = ['board' => array_fill(0, 8, array_fill(0, 8, null)), 'turn' => 0, 'moves' => 0, 'continuing_piece' => null];
        $state['board'][5][0] = 'w';
        $state['board'][4][1] = 'b';
        $state['board'][5][4] = 'w';
        $this->expectException(InvalidArgumentException::class);
        $this->rules->move('draughts', $state, 0, ['from' => 'e3', 'to' => 'f4']);
    }

    public function test_oware_preserves_all_seeds_after_a_legal_move(): void
    {
        $state = $this->rules->start('oware', 2);
        $result = $this->rules->move('oware', $state, 0, ['pit' => 0]);
        self::assertSame(48, array_sum($result['state']['pits']) + array_sum($result['state']['scores']));
        self::assertSame(1, $result['state']['turn']);
    }

    // ── Ayo Olopon (oware rules_version 2) ──────────────────────────────────────────────

    public function test_ayo_v2_start_adds_a_no_capture_streak_counter_v1_does_not_have(): void
    {
        $v1 = $this->rules->start('oware', 2, 1);
        $v2 = $this->rules->start('oware', 2, 2);
        self::assertArrayNotHasKey('no_capture_streak', $v1);
        self::assertSame(0, $v2['no_capture_streak']);
        self::assertSame(48, array_sum($v1['pits']));
        self::assertSame(48, array_sum($v2['pits']));
    }

    public function test_ayo_v2_relay_sows_again_from_a_pit_that_already_had_seeds(): void
    {
        // Pit 0 (2 seeds) sows into pit 1 (0->1) then pit 2 (3->4). Landing on the mover's own
        // pit 2, now holding 4, isn't a capture (own side) and isn't empty - so it must relay:
        // pick that pit back up and keep sowing.
        $state = ['pits' => [2, 0, 3, 0, 0, 0, 4, 4, 4, 4, 4, 4], 'scores' => [0, 0], 'turn' => 0, 'moves' => 0, 'no_capture_streak' => 0];
        $result = $this->rules->move('oware', $state, 0, ['pit' => 0], [], 2);
        $relayEvents = array_filter($result['events'], fn ($e) => $e['type'] === 'relay');
        self::assertNotCount(0, $relayEvents);
        self::assertSame(29, array_sum($result['state']['pits']) + array_sum($result['state']['scores']));
    }

    public function test_ayo_v2_capture_takes_only_the_landing_pit_not_a_chain(): void
    {
        // Pit 4 (3 seeds) sows into pit 5, pit 6 (2->3, NOT the final landing), then pit 7
        // (1->2, the final landing - a capture). Pit 6 would also qualify under an old-style
        // chain-capture rule; v2 must leave it alone and take only pit 7.
        $state = ['pits' => [0, 0, 0, 0, 3, 0, 2, 1, 4, 4, 4, 4], 'scores' => [0, 0], 'turn' => 0, 'moves' => 0, 'no_capture_streak' => 0];
        $result = $this->rules->move('oware', $state, 0, ['pit' => 4], [], 2);
        self::assertSame(2, $result['state']['scores'][0]);
        self::assertSame(3, $result['state']['pits'][6]);
        self::assertSame(0, $result['state']['pits'][7]);
        self::assertCount(1, array_filter($result['events'], fn ($e) => $e['type'] === 'capture'));
    }

    public function test_ayo_v2_grand_slam_capture_is_allowed_but_takes_nothing(): void
    {
        // Pit 5 (1 seed) lands in pit 6, the opponent's only non-empty pit, making it 2 - a
        // capture that would leave the opponent's whole row at zero is blocked.
        $state = ['pits' => [0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0], 'scores' => [0, 0], 'turn' => 0, 'moves' => 0, 'no_capture_streak' => 0];
        $result = $this->rules->move('oware', $state, 0, ['pit' => 5], [], 2);
        self::assertSame(0, $result['state']['scores'][0]);
        self::assertSame(2, $result['state']['pits'][6]);
        self::assertCount(1, array_filter($result['events'], fn ($e) => $e['type'] === 'capture_blocked'));
        self::assertSame(1, $result['state']['no_capture_streak']);
    }

    public function test_ayo_v2_must_feed_rejects_a_move_that_ignores_a_feeding_alternative(): void
    {
        $state = ['pits' => [1, 0, 0, 0, 0, 2, 0, 0, 0, 0, 0, 0], 'scores' => [0, 0], 'turn' => 0, 'moves' => 0, 'no_capture_streak' => 0];
        $this->expectException(InvalidArgumentException::class);
        $this->rules->move('oware', $state, 0, ['pit' => 0], [], 2);
    }

    public function test_ayo_v2_must_feed_ends_the_round_when_no_move_can_feed_the_opponent(): void
    {
        $state = ['pits' => [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], 'scores' => [0, 0], 'turn' => 0, 'moves' => 0, 'no_capture_streak' => 0];
        $result = $this->rules->move('oware', $state, 0, ['pit' => 0], [], 2);
        self::assertSame(0, $result['winner']);
        self::assertFalse($result['draw']);
        self::assertSame(1, $result['state']['scores'][0]);
        self::assertSame(0, $result['state']['pits'][0], 'the swept row must be zeroed, not left showing seeds already credited to the score');
        self::assertCount(1, array_filter($result['events'], fn ($e) => $e['type'] === 'end' && $e['reason'] === 'no_seeds_left'));
    }

    public function test_ayo_v2_reaching_25_ends_the_game_and_sweeps_every_pit_to_zero(): void
    {
        $state = ['pits' => [0, 0, 0, 0, 0, 1, 2, 4, 4, 4, 4, 4], 'scores' => [24, 0], 'turn' => 0, 'moves' => 0, 'no_capture_streak' => 0];
        $result = $this->rules->move('oware', $state, 0, ['pit' => 5], [], 2);
        self::assertSame(0, $result['winner']);
        self::assertGreaterThanOrEqual(25, $result['state']['scores'][0]);
        self::assertSame(0, array_sum($result['state']['pits']), 'every pit must be swept to zero once its seeds are credited to a score');
        self::assertSame(array_sum($state['pits']) + array_sum($state['scores']), array_sum($result['state']['scores']));
    }

    public function test_ayo_v2_a_40_move_no_capture_streak_ends_the_round_by_repetition(): void
    {
        $state = ['pits' => [0, 0, 0, 0, 0, 1, 0, 4, 4, 4, 4, 4], 'scores' => [10, 12], 'turn' => 0, 'moves' => 39, 'no_capture_streak' => 39];
        $result = $this->rules->move('oware', $state, 0, ['pit' => 5], [], 2);
        self::assertTrue($result['winner'] !== null || $result['draw']);
        self::assertSame(0, array_sum($result['state']['pits']));
        self::assertCount(1, array_filter($result['events'], fn ($e) => $e['type'] === 'end' && $e['reason'] === 'repetition'));
    }

    public function test_ayo_v1_tables_keep_the_original_single_pass_rules_with_no_relay(): void
    {
        // Same board as the relay test above, but at rules_version 1 - a pre-existing table must
        // never gain the new relay behavior mid-game. Pit 0 (2 seeds) sows into pit 1 and pit 2
        // only, and stops there even though pit 2 (now 4) would relay under v2.
        $state = ['pits' => [2, 0, 3, 0, 0, 0, 4, 4, 4, 4, 4, 4], 'scores' => [0, 0], 'turn' => 0, 'moves' => 0];
        $result = $this->rules->move('oware', $state, 0, ['pit' => 0], [], 1);
        self::assertSame(1, $result['state']['pits'][1]);
        self::assertSame(4, $result['state']['pits'][2]);
        self::assertArrayNotHasKey('events', $result);
    }

    public function test_ayo_v2_random_games_always_conserve_all_48_seeds(): void
    {
        for ($game = 0; $game < 25; $game++) {
            $state = $this->rules->start('oware', 2, 2);
            for ($move = 0; $move < 300; $move++) {
                $seat = $state['turn'];
                $ownStart = $seat === 0 ? 0 : 6;
                $legal = [];
                for ($p = $ownStart; $p < $ownStart + 6; $p++) if ($state['pits'][$p] > 0) $legal[] = $p;
                if (!$legal) break;
                shuffle($legal);
                $result = null;
                foreach ($legal as $tryPit) {
                    try {
                        $result = $this->rules->move('oware', $state, $seat, ['pit' => $tryPit], [], 2);
                        break;
                    } catch (InvalidArgumentException) {
                        continue;
                    }
                }
                self::assertNotNull($result, 'a seated player must always have at least one legal pit to choose');
                $state = $result['state'];
                self::assertSame(48, array_sum($state['pits']) + array_sum($state['scores']), "game $game move $move broke seed conservation");
                if ($result['winner'] !== null || $result['draw']) break;
            }
        }
    }

    public function test_dominoes_deals_a_double_six_set_and_accepts_only_matching_ends(): void
    {
        $state = $this->rules->start('dominoes', 2);
        self::assertSame(28, array_sum(array_map('count', $state['hands'])) + count($state['stock']));
        self::assertSame(7, count($state['hands'][0]));

        $chain = ['hands' => [[[6,5],[2,1]], [[3,3]]], 'stock' => [], 'chain' => [[2,3]], 'ends' => [2,3], 'turn' => 0, 'passes' => 0, 'moves' => 1];
        $result = $this->rules->move('dominoes', $chain, 0, ['type' => 'play', 'tile' => 1, 'end' => 'left']);
        self::assertSame([1,3], $result['state']['ends']);
        self::assertSame([[1,2],[2,3]], $result['state']['chain']);

        $this->expectException(InvalidArgumentException::class);
        $this->rules->move('dominoes', $chain, 0, ['type' => 'play', 'tile' => 0, 'end' => 'right']);
    }

    public function test_crazy_eights_uses_a_server_deck_and_allows_suit_selection_for_eights(): void
    {
        $deal = $this->rules->start('crazy_eights', 2);
        self::assertSame(52, array_sum(array_map('count', $deal['hands'])) + count($deal['stock']) + count($deal['discard']));
        self::assertSame(7, count($deal['hands'][0]));

        $state = ['hands' => [[['rank'=>'8','suit'=>'S'],['rank'=>'3','suit'=>'H']],[['rank'=>'4','suit'=>'H']]], 'stock' => [], 'discard' => [['rank'=>'5','suit'=>'D']], 'top' => ['rank'=>'5','suit'=>'D'], 'current_suit' => 'D', 'turn' => 0, 'passes' => 0, 'moves' => 0];
        $result = $this->rules->move('crazy_eights', $state, 0, ['type'=>'play','card'=>0,'suit'=>'H']);
        self::assertSame('H', $result['state']['current_suit']);
        self::assertSame(1, $result['state']['turn']);
    }

    public function test_winning_pot_math_uses_exact_decimal_amounts(): void
    {
        $result = TableGameSettlementMath::payout(['1.10000000', '2.20000000', '3.30000000'], '5.00');
        self::assertSame('6.60000000', $result['pot']);
        self::assertSame('0.33000000', $result['commission']);
        self::assertSame('6.27000000', $result['winner_amount']);
    }

    public function test_draw_and_cancel_refunds_return_each_original_stake(): void
    {
        self::assertSame([
            12 => '1.25000000',
            19 => '4.00000000',
        ], TableGameSettlementMath::refunds([12 => '1.25000000', 19 => '4.00000000']));
    }
}
