<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\TableGames\TableGameRules;
use App\Games\TableGames\TableGameSettlementMath;

$rules = new TableGameRules();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$xo = $rules->move('tic_tac_toe', ['board' => [0, 0, null, 1, 1, null, null, null, null], 'turn' => 0, 'moves' => 4], 0, ['cell' => 2]);
$assert($xo['winner'] === 0, 'X and O win detection failed.');

$connect = ['board' => array_fill(0, 6, array_fill(0, 7, null)), 'turn' => 1, 'moves' => 6];
foreach ([5, 4, 3] as $row) $connect['board'][$row][2] = 1;
$assert($rules->move('four_in_a_row', $connect, 1, ['column' => 2])['winner'] === 1, 'Four in a Row win detection failed.');

$sea = $rules->start('sea_battle', 2);
$shipCell = null;
for ($r = 0; $r < 10 && $shipCell === null; $r++) for ($c = 0; $c < 10; $c++) if ($sea['boards'][1][$r][$c] !== null) { $shipCell = [$r, $c]; break; }
$shot = $rules->move('sea_battle', $sea, 0, ['row' => $shipCell[0], 'col' => $shipCell[1]]);
$assert($shot['state']['shots'][0][0]['hit'] === true && $shot['state']['turn'] === 1, 'Sea Battle server board or hit handling failed.');
try { $rules->move('sea_battle', $shot['state'], 1, ['row' => 10, 'col' => 0]); throw new RuntimeException('Sea Battle accepted an invalid target action.'); }
catch (InvalidArgumentException) {}

$blackjack = $rules->start('blackjack', 2);
$assert(count($blackjack['hands'][0]) === 2 && count($blackjack['hands'][1]) === 2, 'Blackjack initial deal was invalid.');
$blackjack = $rules->move('blackjack', $blackjack, 0, ['type' => 'stand']);
$assert($blackjack['state']['turn'] === 1, 'Blackjack did not advance to the next player.');
$blackjack = $rules->move('blackjack', $blackjack['state'], 1, ['type' => 'stand']);
$assert($blackjack['winner'] !== null || $blackjack['draw'] === true, 'Blackjack did not resolve after all hands stood.');

$rummy = $rules->start('rummy', 2);
$assert(count($rummy['hands'][0]) === 7 && count($rummy['hands'][1]) === 7 && count($rummy['stock']) === 37, 'Rummy deal or stock count was invalid.');
$rummy = $rules->move('rummy', $rummy, 0, ['type' => 'draw_stock']);
$assert($rummy['state']['phase'] === 'discard' && count($rummy['state']['hands'][0]) === 8, 'Rummy did not draw a card before discard.');
$winningRummy = ['hands' => [[['rank'=>'9','suit'=>'S'],['rank'=>'9','suit'=>'H'],['rank'=>'9','suit'=>'D'],['rank'=>'3','suit'=>'C'],['rank'=>'4','suit'=>'C'],['rank'=>'5','suit'=>'C'],['rank'=>'6','suit'=>'C']], [['rank'=>'2','suit'=>'S']]], 'stock' => [['rank'=>'7','suit'=>'D']], 'discard' => [['rank'=>'8','suit'=>'S']], 'turn' => 0, 'phase' => 'draw', 'moves' => 0];
$winningRummy = $rules->move('rummy', $winningRummy, 0, ['type'=>'draw_stock']);
$winningRummy = $rules->move('rummy', $winningRummy['state'], 0, ['type'=>'discard','card'=>7]);
$assert($winningRummy['winner'] === 0, 'Rummy failed to validate a complete run-and-set hand.');

$whot = ['hands'=>[[['shape'=>'Circle','number'=>14],['shape'=>'Star','number'=>7]],[['shape'=>'Square','number'=>2]], [['shape'=>'Cross','number'=>3]]], 'stock'=>[['shape'=>'Square','number'=>9],['shape'=>'Cross','number'=>4]], 'discard'=>[['shape'=>'Triangle','number'=>14]], 'active_shape'=>'Triangle', 'turn'=>0, 'moves'=>0];
$whot = $rules->move('whot', $whot, 0, ['type'=>'play','card'=>0]);
$assert(count($whot['state']['hands'][1]) === 2 && count($whot['state']['hands'][2]) === 2 && $whot['state']['turn'] === 0, 'Whot General Market/turn rule failed.');
$wildWhot = ['hands'=>[[['shape'=>'Whot','number'=>20],['shape'=>'Star','number'=>7]],[['shape'=>'Square','number'=>2]]], 'stock'=>[], 'discard'=>[['shape'=>'Circle','number'=>5]], 'active_shape'=>'Circle', 'turn'=>0, 'moves'=>0];
$wildWhot = $rules->move('whot', $wildWhot, 0, ['type'=>'play','card'=>0,'shape'=>'Star']);
$assert($wildWhot['state']['active_shape'] === 'Star' && $wildWhot['state']['turn'] === 1, 'Whot wild shape choice failed.');

$backgammon = $rules->start('backgammon', 2);
$assert(count($backgammon['points']) === 24 && $backgammon['points'][0] === 2 && $backgammon['points'][23] === -2, 'Backgammon opening layout was invalid.');
$moveState = ['points'=>$backgammon['points'],'bar'=>[0,0],'off'=>[0,0],'dice'=>[1],'turn'=>0,'moves'=>0];
$backgammonMove = $rules->move('backgammon', $moveState, 0, ['type'=>'move','from'=>0,'die'=>1]);
$assert($backgammonMove['state']['points'][0] === 1 && $backgammonMove['state']['points'][1] === 1 && $backgammonMove['state']['turn'] === 1, 'Backgammon checker move failed.');
$bearOff = ['points'=>array_replace(array_fill(0,24,0),[23=>1]),'bar'=>[0,0],'off'=>[14,0],'dice'=>[1],'turn'=>0,'moves'=>0];
$assert($rules->move('backgammon', $bearOff, 0, ['type'=>'move','from'=>23,'die'=>1])['winner'] === 0, 'Backgammon did not detect bearing off all checkers.');

$holdem = ['private_hands'=>[[['rank'=>'A','suit'=>'S'],['rank'=>'K','suit'=>'S']],[['rank'=>'2','suit'=>'C'],['rank'=>'2','suit'=>'D']]],'board'=>[['rank'=>'Q','suit'=>'S'],['rank'=>'J','suit'=>'S'],['rank'=>'10','suit'=>'S'],['rank'=>'3','suit'=>'H'],['rank'=>'4','suit'=>'D']],'deck'=>[],'street'=>'river','checked'=>[],'turn'=>0,'moves'=>0];
$holdem = $rules->move('texas_holdem', $holdem, 0, ['type'=>'check']);
$holdem = $rules->move('texas_holdem', $holdem['state'], 1, ['type'=>'check']);
$assert($holdem['winner'] === 0, 'Texas Hold’em showdown ranking failed to recognize a royal flush.');
$dealt = $rules->start('texas_holdem', 3);
$assert(count($dealt['private_hands']) === 3 && count($dealt['board']) === 0, 'Texas Hold’em server deal failed.');

$spadesDeal = $rules->start('spades', 4);
$assert($spadesDeal['phase'] === 'bid' && array_sum(array_map('count',$spadesDeal['hands'])) === 52, 'Spades did not deal a complete four-player hand.');
for ($seat=0;$seat<4;$seat++) $spadesDeal=$rules->move('spades',$spadesDeal['state']??$spadesDeal,$seat,['type'=>'bid','bid'=>1]);
$assert($spadesDeal['state']['phase']==='play' && $spadesDeal['state']['turn']===$spadesDeal['state']['first_player'], 'Spades bidding did not start the first trick.');
$trickState=['hands'=>[[['rank'=>'2','suit'=>'C']],[['rank'=>'3','suit'=>'C']],[['rank'=>'4','suit'=>'C']],[['rank'=>'5','suit'=>'C']]],'phase'=>'play','bids'=>[1,1,1,1],'trick'=>[],'tricks_won'=>[0,0,0,0],'scores'=>[0,0],'turn'=>0,'first_player'=>0,'hearts_broken'=>false,'spades_broken'=>false,'moves'=>0];
foreach ([0,1,2,3] as $seat) $trickState=$rules->move('spades',$trickState['state']??$trickState,$seat,['type'=>'play','card'=>0]);
$assert($trickState['draw']===true, 'Spades did not resolve a tied contract score.');
$heartsState=['hands'=>[[['rank'=>'2','suit'=>'C']],[['rank'=>'3','suit'=>'C']],[['rank'=>'4','suit'=>'C']],[['rank'=>'5','suit'=>'C']]],'phase'=>'play','bids'=>[null,null,null,null],'trick'=>[],'tricks_won'=>[0,0,0,0],'scores'=>[0,0,0,0],'turn'=>0,'first_player'=>0,'hearts_broken'=>false,'spades_broken'=>false,'moves'=>0];
foreach ([0,1,2,3] as $seat) $heartsState=$rules->move('hearts',$heartsState['state']??$heartsState,$seat,['type'=>'play','card'=>0]);
$assert($heartsState['draw']===true, 'Hearts did not resolve a tied low-score result.');

$chess = $rules->start('chess', 2);
$assert($rules->move('chess', $chess, 0, ['from' => 'e2', 'to' => 'e4'])['state']['turn'] === 1, 'Chess legal move failed.');

$draughts = ['board' => array_fill(0, 8, array_fill(0, 8, null)), 'turn' => 0, 'moves' => 0, 'continuing_piece' => null];
$draughts['board'][5][0] = 'w'; $draughts['board'][4][1] = 'b';
$capture = $rules->move('draughts', $draughts, 0, ['from' => 'a3', 'to' => 'c5']);
$assert($capture['state']['board'][3][2] === 'w' && $capture['state']['board'][4][1] === null, 'Draughts capture failed.');

$ludo = $rules->move('ludo', $rules->start('ludo', 2), 0, ['roll' => true]);
$assert($ludo['state']['last_roll']['value'] >= 1 && $ludo['state']['last_roll']['value'] <= 6, 'Ludo server die failed.');
$snakes = $rules->move('snakes_and_ladders', $rules->start('snakes_and_ladders', 2), 0, []);
$assert($snakes['state']['roll']['value'] >= 1 && $snakes['state']['roll']['value'] <= 6, 'Snakes and Ladders server die failed.');

$oware = $rules->move('oware', $rules->start('oware', 2), 0, ['pit' => 0]);
$assert(array_sum($oware['state']['pits']) + array_sum($oware['state']['scores']) === 48, 'Oware seed count changed.');
$dominoes = ['hands' => [[[6,5],[2,1]], [[3,3]]], 'stock' => [], 'chain' => [[2,3]], 'ends' => [2,3], 'turn' => 0, 'passes' => 0, 'moves' => 1];
$dominoMove = $rules->move('dominoes', $dominoes, 0, ['type' => 'play', 'tile' => 1, 'end' => 'left']);
$assert($dominoMove['state']['ends'] === [1,3] && $dominoMove['state']['chain'][0] === [1,2], 'Domino placement validation failed.');
$crazyState = ['hands' => [[['rank'=>'8','suit'=>'S'],['rank'=>'3','suit'=>'H']],[['rank'=>'4','suit'=>'H']]], 'stock' => [], 'discard' => [['rank'=>'5','suit'=>'D']], 'top' => ['rank'=>'5','suit'=>'D'], 'current_suit' => 'D', 'turn' => 0, 'passes' => 0, 'moves' => 0];
$crazyMove = $rules->move('crazy_eights', $crazyState, 0, ['type'=>'play','card'=>0,'suit'=>'H']);
$assert($crazyMove['state']['current_suit'] === 'H' && $crazyMove['state']['turn'] === 1, 'Crazy Eights wild suit selection failed.');
$pot = TableGameSettlementMath::payout(['1.10000000', '2.20000000', '3.30000000'], '5.00');
$assert($pot['winner_amount'] === '6.27000000' && $pot['commission'] === '0.33000000', 'Exact pot settlement failed.');
$refunds = TableGameSettlementMath::refunds([12 => '1.25000000', 19 => '4.00000000']);
$assert(array_sum(array_map('floatval', $refunds)) === 5.25, 'Stake refund list failed.');

fwrite(STDOUT, "Table game rules and settlement smoke checks passed.\n");
