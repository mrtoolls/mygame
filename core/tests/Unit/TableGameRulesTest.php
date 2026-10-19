<?php

namespace Tests\Unit;

use App\Games\TableGames\TableGameRules;
use App\Games\TableGames\TableGameSettlementMath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

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
