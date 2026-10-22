<?php

namespace App\Games\TableGames;

use InvalidArgumentException;

/** Pure, server-authoritative rules for the introductory board games. */
class TableGameRules
{
    public const GAMES = [
        'ludo' => ['name' => 'Ludo', 'min' => 2, 'max' => 4],
        'whot' => ['name' => 'Whot', 'min' => 2, 'max' => 4],
        'chess' => ['name' => 'Chess', 'min' => 2, 'max' => 2],
        'draughts' => ['name' => 'Draughts', 'min' => 2, 'max' => 2],
        'tic_tac_toe' => ['name' => 'X and O', 'min' => 2, 'max' => 2],
        'four_in_a_row' => ['name' => 'Four in a Row', 'min' => 2, 'max' => 2],
        'snakes_and_ladders' => ['name' => 'Snakes and Ladders', 'min' => 2, 'max' => 4],
        'dominoes' => ['name' => 'Dominoes', 'min' => 2, 'max' => 4],
        'backgammon' => ['name' => 'Backgammon', 'min' => 2, 'max' => 2],
        'oware' => ['name' => 'Ayo / Oware', 'min' => 2, 'max' => 2],
        'sea_battle' => ['name' => 'Sea Battle', 'min' => 2, 'max' => 2],
        'blackjack' => ['name' => 'Blackjack', 'min' => 2, 'max' => 4],
        'texas_holdem' => ['name' => 'Texas Hold’em', 'min' => 2, 'max' => 4],
        'rummy' => ['name' => 'Rummy', 'min' => 2, 'max' => 4],
        'spades' => ['name' => 'Spades', 'min' => 4, 'max' => 4],
        'hearts' => ['name' => 'Hearts', 'min' => 4, 'max' => 4],
        'crazy_eights' => ['name' => 'Crazy Eights', 'min' => 2, 'max' => 4],
    ];

    public const AVAILABLE = ['ludo', 'chess', 'draughts', 'tic_tac_toe', 'four_in_a_row', 'snakes_and_ladders', 'oware', 'dominoes', 'crazy_eights', 'sea_battle', 'blackjack', 'rummy', 'whot', 'backgammon', 'texas_holdem', 'spades', 'hearts'];

    // Default icon glyph shown until an admin uploads a real card_image for that game
    // (Admin > Table Games). Every class here is confirmed present in line-awesome.min.css.
    public const GAME_ICONS = [
        'ludo' => 'la-dice-five',
        'whot' => 'la-id-card',
        'chess' => 'la-chess',
        'draughts' => 'la-circle',
        'tic_tac_toe' => 'la-th',
        'four_in_a_row' => 'la-th-large',
        'snakes_and_ladders' => 'la-flag-checkered',
        'dominoes' => 'la-grip-horizontal',
        'backgammon' => 'la-cubes',
        'oware' => 'la-dot-circle-o',
        'sea_battle' => 'la-ship',
        'blackjack' => 'la-coins',
        'texas_holdem' => 'la-money-bill-wave',
        'rummy' => 'la-layer-group',
        'spades' => 'la-id-card-alt',
        'hearts' => 'la-heart',
        'crazy_eights' => 'la-clone',
    ];

    // Each seat's own start square (0,13,26,39 - see 'starts' in start()) plus the classic star
    // square 8 ahead of every start. A token sitting on any of these shared-ring squares cannot
    // be captured, no matter how many other tokens land there with it.
    public const LUDO_SAFE_SQUARES = [0, 8, 13, 21, 26, 34, 39, 47];

    // Standard 54-card Whot deck (rules_version >= 2 - new tables only; a table created before
    // this shipped keeps the original 75-card deck for its whole lifetime, since mid-game rules
    // must never change). Circle/Triangle run 1-5,7,8,10-14 (12 cards each); Cross/Square run
    // 1,2,3,5,7,10,11,13,14 (9 cards each); Star runs 1-5,7,8 (7 cards) - plus 5 Whot(20) cards,
    // for 54 total.
    public const WHOT_STANDARD_NUMBERS = [
        'Circle' => [1, 2, 3, 4, 5, 7, 8, 10, 11, 12, 13, 14],
        'Triangle' => [1, 2, 3, 4, 5, 7, 8, 10, 11, 12, 13, 14],
        'Cross' => [1, 2, 3, 5, 7, 10, 11, 13, 14],
        'Square' => [1, 2, 3, 5, 7, 10, 11, 13, 14],
        'Star' => [1, 2, 3, 4, 5, 7, 8],
    ];

    public function start(string $game, int $playerCount, int $rulesVersion = 1): array
    {
        if (!isset(self::GAMES[$game]) || $playerCount < self::GAMES[$game]['min'] || $playerCount > self::GAMES[$game]['max']) {
            throw new InvalidArgumentException('Unsupported game or player count.');
        }

        return match ($game) {
            'ludo' => ['tokens' => array_map(fn () => array_fill(0, 4, -1), range(1, $playerCount)), 'starts' => [0, 13, 26, 39], 'turn' => 0, 'moves' => 0, 'pending_roll' => null, 'last_roll' => null],
            'chess' => ['board' => $this->chessStartingBoard(), 'turn' => 0, 'moves' => 0, 'castling' => ['K' => true, 'Q' => true, 'k' => true, 'q' => true], 'en_passant' => null, 'halfmove' => 0, 'draw_offer' => null],
            'draughts' => ['board' => $this->draughtsStartingBoard(), 'turn' => 0, 'moves' => 0, 'quiet_plies' => 0, 'continuing_piece' => null],
            'tic_tac_toe' => ['board' => array_fill(0, 9, null), 'turn' => 0, 'moves' => 0],
            'four_in_a_row' => ['board' => array_fill(0, 6, array_fill(0, 7, null)), 'turn' => 0, 'moves' => 0],
            'sea_battle' => $this->seaBattleStartingState(),
            'blackjack' => $this->blackjackStartingState($playerCount),
            'rummy' => $this->rummyStartingState($playerCount),
            'whot' => $this->whotStartingState($playerCount, $rulesVersion),
            'backgammon' => ['points' => [2,0,0,0,0,-5,0,-3,0,0,0,5,-5,0,0,0,3,0,5,0,0,0,0,-2], 'bar' => [0,0], 'off' => [0,0], 'dice' => null, 'turn' => 0, 'moves' => 0],
            'texas_holdem' => $this->holdemStartingState($playerCount),
            'spades', 'hearts' => $this->trickStartingState($game, $playerCount),
            'snakes_and_ladders' => ['positions' => array_fill(0, $playerCount, 0), 'turn' => 0, 'moves' => 0, 'roll' => null, 'snakes' => [16 => 6, 47 => 26, 49 => 11, 56 => 53, 62 => 19, 64 => 60, 87 => 24, 93 => 73, 95 => 75, 98 => 78], 'ladders' => [1 => 38, 4 => 14, 9 => 31, 21 => 42, 28 => 84, 36 => 44, 51 => 67, 71 => 91, 80 => 100]],
            'oware' => ['pits' => array_fill(0, 12, 4), 'scores' => [0, 0], 'turn' => 0, 'moves' => 0],
            'dominoes' => $this->dominoesStartingState($playerCount),
            'crazy_eights' => $this->crazyEightsStartingState($playerCount),
            default => throw new InvalidArgumentException('This game ruleset is not available yet.'),
        };
    }

    public function move(string $game, array $state, int $seat, array $action, array $activeSeats = [], int $rulesVersion = 1): array
    {
        if (($state['turn'] ?? null) !== $seat) {
            throw new InvalidArgumentException('It is not your turn.');
        }

        return match ($game) {
            'ludo' => $this->ludo($state, $seat, $action, $rulesVersion),
            'chess' => $this->chess($state, $seat, $action),
            'draughts' => $this->draughts($state, $seat, $action),
            'tic_tac_toe' => $this->ticTacToe($state, $seat, $action),
            'four_in_a_row' => $this->fourInARow($state, $seat, $action),
            'sea_battle' => $this->seaBattle($state, $seat, $action),
            'blackjack' => $this->blackjack($state, $seat, $action, $activeSeats),
            'rummy' => $this->rummy($state, $seat, $action),
            'whot' => $this->whot($state, $seat, $action, $rulesVersion),
            'backgammon' => $this->backgammon($state, $seat, $action),
            'texas_holdem' => $this->holdem($state, $seat, $action, $activeSeats),
            'spades', 'hearts' => $this->trickMove($game, $state, $seat, $action),
            'snakes_and_ladders' => $this->snakesAndLadders($state, $seat),
            'oware' => $this->oware($state, $seat, $action),
            'dominoes' => $this->dominoes($state, $seat, $action, $activeSeats),
            'crazy_eights' => $this->crazyEights($state, $seat, $action, $activeSeats),
            default => throw new InvalidArgumentException('This game ruleset is not available yet.'),
        };
    }

    private function trickStartingState(string $game, int $playerCount): array
    {
        if ($playerCount !== 4) throw new InvalidArgumentException('Spades and Hearts require four players.');
        $deck = $this->shuffledDeck(); $hands = [];
        foreach ([0,1,2,3] as $seat) $hands[$seat] = array_splice($deck, 0, 13);
        $first = 0;
        foreach ($hands as $seat => $hand) foreach ($hand as $card) if ($card['rank'] === '2' && $card['suit'] === 'C') $first = $seat;
        return ['hands'=>$hands,'phase'=>$game==='spades'?'bid':'play','bids'=>array_fill(0,4,null),'trick'=>[],'tricks_won'=>array_fill(0,4,0),'scores'=>array_fill(0,$game==='spades'?2:4,0),'turn'=>$game==='spades'?0:$first,'first_player'=>$first,'hearts_broken'=>false,'spades_broken'=>false,'moves'=>0];
    }

    private function trickMove(string $game, array $state, int $seat, array $action): array
    {
        if ($game==='spades' && $state['phase']==='bid') {
            if (($action['type']??'')!=='bid') throw new InvalidArgumentException('Submit your bid to begin play.');
            $bid=filter_var($action['bid']??null,FILTER_VALIDATE_INT);
            if ($bid===false||$bid<0||$bid>13) throw new InvalidArgumentException('Choose a bid from nil (0) to 13.');
            if ($state['bids'][$seat]!==null) throw new InvalidArgumentException('You already submitted a bid.');
            $state['bids'][$seat]=$bid;$state['moves']++;
            $next=($seat+1)%4;
            if (!in_array(null,$state['bids'],true)) {$state['phase']='play';$state['turn']=$state['first_player'];}
            else $state['turn']=$next;
            return ['state'=>$state,'winner'=>null,'draw'=>false];
        }
        if (($action['type']??'')!=='play') throw new InvalidArgumentException('Play a card from your hand.');
        $hand=$state['hands'][$seat];$index=filter_var($action['card']??null,FILTER_VALIDATE_INT);
        if ($index===false||$index<0||$index>=count($hand)) throw new InvalidArgumentException('Choose a card from your hand.');
        $card=$hand[$index];$lead=$state['trick'][0]['card']['suit']??null;
        if ($lead!==null&&$card['suit']!==$lead&&in_array($lead,array_column($hand,'suit'),true)) throw new InvalidArgumentException('Follow the suit that was led when you can.');
        if (!$state['trick']&&$seat===$state['first_player']&&$card['rank']!=='2'&&$card['suit']!=='C') throw new InvalidArgumentException('The first trick must open with the two of clubs.');
        if ($game==='spades'&&!$state['trick']&&!$state['spades_broken']&&$card['suit']==='S'&&count(array_filter($hand,fn($item)=>$item['suit']!=='S'))>0) throw new InvalidArgumentException('Do not lead spades until they are broken.');
        if ($game==='hearts'&&!$state['trick']&&!$state['hearts_broken']&&$card['suit']==='H'&&count(array_filter($hand,fn($item)=>$item['suit']!=='H'))>0) throw new InvalidArgumentException('Hearts cannot be led until broken.');
        if ($game==='hearts'&&$state['moves']<4&&($card['suit']==='H'||($card['suit']==='S'&&$card['rank']==='Q'))&&count(array_filter($hand,fn($item)=>$item['suit']!=='H'&&!($item['suit']==='S'&&$item['rank']==='Q')))>0) throw new InvalidArgumentException('Do not discard a penalty card on the opening trick unless you have no alternative.');
        array_splice($hand,$index,1);$state['hands'][$seat]=$hand;$state['trick'][]=['seat'=>$seat,'card'=>$card];$state['moves']++;
        if ($card['suit']==='S') $state['spades_broken']=true;
        if ($card['suit']==='H') $state['hearts_broken']=true;
        if (count($state['trick'])<4) {$state['turn']=($seat+1)%4;return ['state'=>$state,'winner'=>null,'draw'=>false];}
        $trick=$state['trick'];$leadSuit=$trick[0]['card']['suit'];$winning=$trick[0];
        foreach (array_slice($trick,1) as $play) {
            $suit=$play['card']['suit'];$winSuit=$winning['card']['suit'];
            $beats=($game==='spades'&&$suit==='S'&&$winSuit!=='S')||(($game!=='spades'||$suit==='S'||$winSuit!=='S')&&$suit===$winSuit&&$this->cardRank($play['card']['rank'])>$this->cardRank($winning['card']['rank']));
            if ($beats) $winning=$play;
        }
        $winner=(int)$winning['seat'];$state['tricks_won'][$winner]++;
        if ($game==='hearts') foreach ($trick as $play) {$card=$play['card'];$state['scores'][$winner]+=($card['suit']==='H'?1:($card['suit']==='S'&&$card['rank']==='Q'?13:0));}
        $state['last_trick']=$trick;$state['trick']=[];$state['turn']=$winner;
        $done=count(array_filter($state['hands'],fn($cards)=>count($cards)>0))===0;
        if ($done) {
            if ($game==='hearts') {$best=min($state['scores']);$winners=array_keys(array_filter($state['scores'],fn($score)=>$score===$best));return ['state'=>$state,'winner'=>count($winners)===1?(int)$winners[0]:null,'draw'=>count($winners)!==1];}
            $teamTricks=[(int)$state['tricks_won'][0]+(int)$state['tricks_won'][2],(int)$state['tricks_won'][1]+(int)$state['tricks_won'][3]];$teamBids=[(int)$state['bids'][0]+(int)$state['bids'][2],(int)$state['bids'][1]+(int)$state['bids'][3]];
            foreach ([0,1] as $team) {$contract=$teamBids[$team];$state['scores'][$team]=$contract&&$teamTricks[$team]<$contract?-10*$contract:($contract?10*$contract+max(0,$teamTricks[$team]-$contract):0);foreach ([$team,$team+2] as $player) if ((int)$state['bids'][$player]===0) $state['scores'][$team]+=$state['tricks_won'][$player]===0?100:-100;}
            if ($state['scores'][0]===$state['scores'][1]) return ['state'=>$state,'winner'=>null,'draw'=>true];
            $winnerTeam=$state['scores'][0]>$state['scores'][1]?0:1;
            return ['state'=>$state,'winner'=>$winnerTeam,'winners'=>[$winnerTeam, $winnerTeam + 2],'draw'=>false];
        }
        return ['state'=>$state,'winner'=>null,'draw'=>false];
    }

    private function cardRank(string $rank): int
    {
        return array_search($rank,['2','3','4','5','6','7','8','9','10','J','Q','K','A'],true)+2;
    }

    private function holdemStartingState(int $playerCount): array
    {
        $deck = $this->shuffledDeck(); $private = [];
        for ($seat = 0; $seat < $playerCount; $seat++) $private[$seat] = [array_pop($deck), array_pop($deck)];
        return ['private_hands' => $private, 'deck' => $deck, 'board' => [], 'street' => 'preflop', 'checked' => [], 'turn' => 0, 'moves' => 0];
    }

    private function holdem(array $state, int $seat, array $action, array $activeSeats = []): array
    {
        // Same reasoning as blackjack: a seat that stood up or timed out mid-hand can never
        // check again, so the round must only wait on seats that are still actually seated.
        $activeSeats = $activeSeats ?: array_keys($state['private_hands']);
        if (($action['type'] ?? '') !== 'check') throw new InvalidArgumentException('This fixed-stake table uses check-through rounds; no side bets are made.');
        if (in_array($seat, $state['checked'], true)) throw new InvalidArgumentException('You already checked this round.');
        $state['checked'][] = $seat; $state['moves']++;
        if (count($state['checked']) < count($activeSeats)) { $state['turn'] = ($seat + 1) % count($state['private_hands']); return ['state' => $state, 'winner' => null, 'draw' => false]; }
        $state['checked'] = []; $stage = ['preflop' => 'flop', 'flop' => 'turn', 'turn' => 'river', 'river' => 'showdown'][$state['street']];
        if ($stage === 'flop') for ($i = 0; $i < 3; $i++) $state['board'][] = array_pop($state['deck']);
        elseif ($stage === 'turn' || $stage === 'river') $state['board'][] = array_pop($state['deck']);
        else {
            $scores = [];
            foreach ($state['private_hands'] as $player => $cards) {
                if (in_array($player, $activeSeats, true)) $scores[$player] = $this->holdemBest(array_merge($cards, $state['board']));
            }
            $best = null; $winners = [];
            foreach ($scores as $player => $score) { $comparison = $best === null ? 1 : $this->comparePokerScores($score, $best); if ($comparison > 0) { $best = $score; $winners = [$player]; } elseif ($comparison === 0) $winners[] = $player; }
            return ['state' => $state, 'winner' => count($winners) === 1 ? (int) $winners[0] : null, 'draw' => count($winners) !== 1];
        }
        $state['street'] = $stage; $state['turn'] = 0;
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function holdemBest(array $cards): array
    {
        $best = null; $n = count($cards);
        for ($a=0;$a<$n-4;$a++) for ($b=$a+1;$b<$n-3;$b++) for ($c=$b+1;$c<$n-2;$c++) for ($d=$c+1;$d<$n-1;$d++) for ($e=$d+1;$e<$n;$e++) {
            $score = $this->pokerFive([$cards[$a],$cards[$b],$cards[$c],$cards[$d],$cards[$e]]);
            if ($best === null || $this->comparePokerScores($score, $best) > 0) $best = $score;
        }
        return $best ?? [0,0];
    }

    private function pokerFive(array $cards): array
    {
        $values = array_map(fn ($card) => array_search($card['rank'], ['2','3','4','5','6','7','8','9','10','J','Q','K','A'], true) + 2, $cards);
        rsort($values); $counts = array_count_values($values); arsort($counts); $groups = [];
        foreach ($counts as $value => $count) $groups[] = [(int) $value, $count];
        usort($groups, fn ($x,$y) => ($y[1] <=> $x[1]) ?: ($y[0] <=> $x[0]));
        $flush = count(array_unique(array_column($cards, 'suit'))) === 1; $unique = array_values(array_unique($values)); sort($unique); if (in_array(14,$unique,true)) $unique = array_merge([1],$unique);
        $straight = 0; for ($i=0;$i<=count($unique)-5;$i++) if ($unique[$i+4]-$unique[$i]===4) $straight=$unique[$i+4];
        if ($flush && $straight) return [8,$straight];
        if ($groups[0][1]===4) return [7,$groups[0][0],$groups[1][0]];
        if ($groups[0][1]===3 && $groups[1][1]===2) return [6,$groups[0][0],$groups[1][0]];
        if ($flush) return array_merge([5],$values);
        if ($straight) return [4,$straight];
        if ($groups[0][1]===3) return array_merge([3,$groups[0][0]],array_column(array_slice($groups,1),0));
        if ($groups[0][1]===2 && $groups[1][1]===2) return [2,$groups[0][0],$groups[1][0],$groups[2][0]];
        if ($groups[0][1]===2) return array_merge([1,$groups[0][0]],array_column(array_slice($groups,1),0));
        return array_merge([0],$values);
    }

    private function comparePokerScores(array $left, array $right): int
    {
        $length = max(count($left), count($right));
        for ($i=0;$i<$length;$i++) { $comparison = ($left[$i] ?? 0) <=> ($right[$i] ?? 0); if ($comparison !== 0) return $comparison; }
        return 0;
    }

    private function seaBattleStartingState(): array
    {
        $boards = [];
        foreach ([0, 1] as $seat) {
            $board = array_fill(0, 10, array_fill(0, 10, null));
            foreach ([5, 4, 3, 3, 2] as $ship) {
                $placed = false;
                for ($attempt = 0; $attempt < 300 && !$placed; $attempt++) {
                    $horizontal = random_int(0, 1) === 1;
                    $row = random_int(0, $horizontal ? 9 : 10 - $ship);
                    $col = random_int(0, $horizontal ? 10 - $ship : 9);
                    $cells = [];
                    for ($offset = 0; $offset < $ship; $offset++) $cells[] = [$row + ($horizontal ? 0 : $offset), $col + ($horizontal ? $offset : 0)];
                    $clear = true;
                    foreach ($cells as [$r, $c]) if ($board[$r][$c] !== null) { $clear = false; break; }
                    if ($clear) { foreach ($cells as [$r, $c]) $board[$r][$c] = $ship; $placed = true; }
                }
                if (!$placed) throw new \RuntimeException('The server could not place the Sea Battle fleet.');
            }
            $boards[$seat] = $board;
        }
        return ['boards' => $boards, 'shots' => [[], []], 'turn' => 0, 'moves' => 0, 'fleet_cells' => 17];
    }

    private function blackjackStartingState(int $playerCount): array
    {
        $deck = $this->shuffledDeck();
        $hands = [];
        for ($seat = 0; $seat < $playerCount; $seat++) $hands[$seat] = [array_pop($deck), array_pop($deck)];
        return ['hands' => $hands, 'deck' => $deck, 'stood' => array_fill(0, $playerCount, false), 'turn' => 0, 'moves' => 0];
    }

    private function rummyStartingState(int $playerCount): array
    {
        $deck = $this->shuffledDeck(); $hands = [];
        for ($seat = 0; $seat < $playerCount; $seat++) $hands[$seat] = array_splice($deck, 0, 7);
        $top = array_pop($deck);
        return ['hands' => $hands, 'stock' => $deck, 'discard' => [$top], 'turn' => 0, 'phase' => 'draw', 'moves' => 0];
    }

    private function whotStartingState(int $playerCount, int $rulesVersion = 1): array
    {
        $deck = [];
        if ($rulesVersion >= 2) {
            foreach (self::WHOT_STANDARD_NUMBERS as $shape => $numbers) foreach ($numbers as $number) $deck[] = ['shape' => $shape, 'number' => $number];
        } else {
            foreach (['Circle','Triangle','Cross','Square','Star'] as $shape) for ($number = 1; $number <= 14; $number++) $deck[] = ['shape' => $shape, 'number' => $number];
        }
        for ($i = 0; $i < 5; $i++) $deck[] = ['shape' => 'Whot', 'number' => 20];
        for ($i = count($deck) - 1; $i > 0; $i--) { $swap = random_int(0, $i); [$deck[$i], $deck[$swap]] = [$deck[$swap], $deck[$i]]; }
        $hands = [];
        for ($seat = 0; $seat < $playerCount; $seat++) $hands[$seat] = array_splice($deck, 0, 6);
        // Pop cards off until a non-Whot one turns up to open the discard pile - any Whot cards
        // drawn along the way go back into the stock instead of vanishing from the game, which
        // the previous version of this loop did (it kept re-popping over a Whot card without
        // ever returning it anywhere), silently shrinking the deck by one or more cards whenever
        // a Whot card happened to land at the exact spot being checked.
        $top = null; $setAside = [];
        while ($deck) {
            $candidate = array_pop($deck);
            if ($candidate['number'] === 20) { $setAside[] = $candidate; continue; }
            $top = $candidate;
            break;
        }
        if ($top === null) { $top = array_pop($setAside); } // Degenerate case: nothing but Whot cards left.
        foreach ($setAside as $card) $deck[] = $card;
        return ['hands' => $hands, 'stock' => $deck, 'discard' => [$top], 'active_shape' => $top['shape'], 'pending_penalty' => null, 'turn' => 0, 'moves' => 0];
    }

    private function backgammon(array $state, int $seat, array $action): array
    {
        if (($action['type'] ?? '') === 'roll') {
            if ($state['dice'] !== null) throw new InvalidArgumentException('Use the dice already rolled before rolling again.');
            $first = random_int(1, 6); $second = random_int(1, 6);
            $state['dice'] = $first === $second ? [$first,$first,$first,$first] : [$first,$second];
            $state['last_roll'] = [$first,$second]; $state['moves']++;
            if (!$this->backgammonHasMove($state, $seat)) { $state['dice'] = null; $state['turn'] = 1 - $seat; }
            return ['state' => $state, 'winner' => null, 'draw' => false];
        }
        if (($action['type'] ?? '') !== 'move' || !is_array($state['dice']) || !$state['dice']) throw new InvalidArgumentException('Roll the dice before moving a checker.');
        $from = filter_var($action['from'] ?? null, FILTER_VALIDATE_INT); $die = filter_var($action['die'] ?? null, FILTER_VALIDATE_INT);
        if ($from === false || $die === false || !in_array($die, $state['dice'], true)) throw new InvalidArgumentException('Choose one of the dice rolled.');
        $direction = $seat === 0 ? 1 : -1; $owner = $seat === 0 ? 1 : -1; $barIndex = $seat;
        if ($state['bar'][$barIndex] > 0) { if ($from !== -1) throw new InvalidArgumentException('Move a checker from the bar first.'); $to = $seat === 0 ? 24 - $die : $die - 1; }
        else {
            if ($from < 0 || $from > 23 || $state['points'][$from] * $owner <= 0) throw new InvalidArgumentException('Choose one of your checkers.');
            $to = $from + $direction * $die;
        }
        $bearingOff = $to < 0 || $to > 23;
        if ($bearingOff) {
            if (!$this->backgammonAllHome($state, $seat) || $from === -1) throw new InvalidArgumentException('All your checkers must be in your home board before bearing off.');
            if ($seat === 0 && $to > 23) { for ($i = 18; $i < $from; $i++) if ($state['points'][$i] > 0) throw new InvalidArgumentException('Use an exact die while a checker remains farther from the exit.'); }
            if ($seat === 1 && $to < 0) { for ($i = $from + 1; $i <= 5; $i++) if ($state['points'][$i] < 0) throw new InvalidArgumentException('Use an exact die while a checker remains farther from the exit.'); }
        } elseif ($state['points'][$to] * -$owner >= 2) throw new InvalidArgumentException('That point is blocked by two or more opposing checkers.');

        if (isset($state['last_roll'])) unset($state['last_roll']);
        if ($from === -1) $state['bar'][$barIndex]--; else $state['points'][$from] -= $owner;
        if (!$bearingOff && $state['points'][$to] === -$owner) { $state['points'][$to] = 0; $state['bar'][1 - $seat]++; }
        if ($bearingOff) $state['off'][$seat]++; else $state['points'][$to] += $owner;
        $dieIndex = array_search($die, $state['dice'], true); array_splice($state['dice'], $dieIndex, 1); $state['moves']++;
        if ($state['off'][$seat] >= 15) return ['state' => $state, 'winner' => $seat, 'draw' => false];
        if (!$state['dice'] || !$this->backgammonHasMove($state, $seat)) { $state['dice'] = null; $state['turn'] = 1 - $seat; }
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function backgammonAllHome(array $state, int $seat): bool
    {
        if ($state['bar'][$seat] > 0) return false;
        foreach ($state['points'] as $point => $count) if (($seat === 0 && $count > 0 && $point < 18) || ($seat === 1 && $count < 0 && $point > 5)) return false;
        return true;
    }

    private function backgammonHasMove(array $state, int $seat): bool
    {
        foreach ($state['dice'] ?? [] as $die) {
            $froms = $state['bar'][$seat] > 0 ? [-1] : array_keys(array_filter($state['points'], fn ($count) => $count * ($seat === 0 ? 1 : -1) > 0));
            foreach ($froms as $from) if ($this->backgammonMoveIsLegal($state, $seat, (int) $from, (int) $die)) return true;
        }
        return false;
    }

    private function backgammonMoveIsLegal(array $state, int $seat, int $from, int $die): bool
    {
        if ($from === -1) { if (!$state['bar'][$seat]) return false; $to = $seat === 0 ? 24 - $die : $die - 1; }
        else { $owner = $seat === 0 ? 1 : -1; if (($state['points'][$from] ?? 0) * $owner <= 0 || $state['bar'][$seat] > 0) return false; $to = $from + ($seat === 0 ? $die : -$die); }
        if ($to >= 0 && $to <= 23) return $state['points'][$to] * ($seat === 0 ? -1 : 1) < 2;
        return $this->backgammonAllHome($state, $seat) && $from !== -1;
    }

    /** Shuffle the discard pile (every card except its current top) back into the stock using
     *  the same secure RNG as the initial deal, when the stock has run dry. No-op if the stock
     *  still has cards, or if there is nothing worth reshuffling (fewer than 2 cards in the
     *  discard pile - that one card has to stay as the active top card). Mirrors the pattern
     *  already used by rummy() and crazyEights(). */
    private function reshuffleStockFromDiscard(array &$state): void
    {
        if ($state['stock'] || count($state['discard']) <= 1) return;
        $top = array_pop($state['discard']);
        $state['stock'] = array_values($state['discard']);
        for ($i = count($state['stock']) - 1; $i > 0; $i--) {
            $swap = random_int(0, $i);
            [$state['stock'][$i], $state['stock'][$swap]] = [$state['stock'][$swap], $state['stock'][$i]];
        }
        $state['discard'] = [$top];
    }

    /** v2 only: when the stock is exhausted and the discard pile has nothing left to reshuffle
     *  either, end the round by card total instead of forcing play to grind on with an empty
     *  draw pile. Each card counts as its printed number (a Whot card counts as 20). A single
     *  lowest total wins through the normal settlement; an exact tie among the lowest hands
     *  falls back to the existing draw/refund path (every stake returned, no payout) - the same
     *  mechanism every other draw in this file already uses, not a new one. */
    private function whotEndOnLowestHand(array $state): array
    {
        $totals = array_map(fn (array $hand) => array_sum(array_map(fn ($card) => (int) $card['number'], $hand)), $state['hands']);
        $lowest = min($totals);
        $winners = array_keys($totals, $lowest, true);
        return count($winners) === 1
            ? ['state' => $state, 'winner' => (int) $winners[0], 'draw' => false]
            : ['state' => $state, 'winner' => null, 'draw' => true];
    }

    private function whot(array $state, int $seat, array $action, int $rulesVersion = 1): array
    {
        $hand = $state['hands'][$seat] ?? null;
        if (!is_array($hand)) throw new InvalidArgumentException('Your hand is unavailable.');
        $top = $state['discard'][array_key_last($state['discard'])];
        // v2 only: while a Pick Two/Three is pending against the current player, the only
        // legal response is a card of the matching rank (another 2 defends a 2, another 5
        // defends a 5) - everything else, including a normal shape/number match or a Whot
        // card, is blocked until the penalty is defended or drawn away.
        $pending = $rulesVersion >= 2 ? ($state['pending_penalty'] ?? null) : null;
        $canDefend = false;
        if ($pending) foreach ($hand as $card) if ($card['number'] === $pending['rank']) { $canDefend = true; break; }

        if (($action['type'] ?? '') === 'draw') {
            if ($pending) {
                if ($canDefend) throw new InvalidArgumentException("You have a {$pending['rank']} - play it to pass the penalty along, or draw {$pending['amount']}.");
                $this->reshuffleStockFromDiscard($state);
                if (!$state['stock']) return $this->whotEndOnLowestHand($state); // Nothing left anywhere, not even for part of the penalty.
                for ($i = 0; $i < $pending['amount']; $i++) {
                    $this->reshuffleStockFromDiscard($state);
                    if (!$state['stock']) break; // The penalty itself exhausted what remained.
                    $hand[] = array_pop($state['stock']);
                }
                $state['hands'][$seat] = $hand;
                $state['pending_penalty'] = null;
                $state['turn'] = ($seat + 1) % count($state['hands']); $state['moves']++;
                return ['state' => $state, 'winner' => null, 'draw' => false];
            }
            foreach ($hand as $card) if ($card['number'] === 20 || $card['number'] === $top['number'] || $card['shape'] === $state['active_shape']) throw new InvalidArgumentException('Play a matching card before drawing.');
            $this->reshuffleStockFromDiscard($state);
            // Fewer than 2 cards left anywhere outside hands, so there is nothing left to
            // reshuffle either - end the round instead of erroring out. v2 settles by lowest
            // card total; v1 keeps its original plain draw/refund.
            if (!$state['stock']) return $rulesVersion >= 2 ? $this->whotEndOnLowestHand($state) : ['state' => $state, 'winner' => null, 'draw' => true];
            $hand[] = array_pop($state['stock']); $state['hands'][$seat] = $hand;
            $state['turn'] = ($seat + 1) % count($state['hands']); $state['moves']++;
            return ['state' => $state, 'winner' => null, 'draw' => false];
        }
        if (($action['type'] ?? '') !== 'play') throw new InvalidArgumentException('Play a matching Whot card or draw when blocked.');
        $index = filter_var($action['card'] ?? null, FILTER_VALIDATE_INT);
        if ($index === false || $index < 0 || $index >= count($hand)) throw new InvalidArgumentException('Choose a card from your hand.');
        $card = $hand[$index];
        if ($pending) {
            if ($card['number'] !== $pending['rank']) throw new InvalidArgumentException("A Pick {$pending['amount']} is pending - play a {$pending['rank']} or draw.");
        } elseif ($card['number'] !== 20 && $card['number'] !== $top['number'] && $card['shape'] !== $state['active_shape']) {
            throw new InvalidArgumentException('That card must match the active shape or card number.');
        }
        $shape = $card['number'] === 20 ? (string) ($action['shape'] ?? '') : $card['shape'];
        if (!in_array($shape, ['Circle','Triangle','Cross','Square','Star'], true)) throw new InvalidArgumentException('Choose a shape for the Whot card.');
        array_splice($hand, $index, 1); $state['hands'][$seat] = $hand; $state['discard'][] = $card; $state['active_shape'] = $shape; $state['moves']++;
        if (!$hand) { $state['pending_penalty'] = null; return ['state' => $state, 'winner' => $seat, 'draw' => false]; }
        $count = count($state['hands']); $next = ($seat + 1) % $count;
        if ($pending) {
            // Defended - the penalty stacks and moves on to the next player instead of
            // resolving now; nobody draws until someone can't (or won't) defend it.
            $state['pending_penalty'] = ['rank' => $card['number'], 'amount' => $pending['amount'] + ($card['number'] === 2 ? 2 : 3)];
        } elseif ($card['number'] === 1) {
            $next = $seat; // Hold On: the current player takes another turn.
        } elseif ($card['number'] === 2 || $card['number'] === 5) {
            $penalty = $card['number'] === 2 ? 2 : 3;
            if ($rulesVersion >= 2) {
                $state['pending_penalty'] = ['rank' => $card['number'], 'amount' => $penalty];
            } else {
                for ($i = 0; $i < $penalty; $i++) {
                    $this->reshuffleStockFromDiscard($state);
                    if (!$state['stock']) break; // Truly nothing left anywhere to deal out.
                    $state['hands'][$next][] = array_pop($state['stock']);
                }
                $next = ($next + 1) % $count;
            }
        } elseif ($card['number'] === 8) {
            $next = ($next + 1) % $count; // Suspension: skip the next player.
        } elseif ($card['number'] === 14) {
            for ($player = 0; $player < $count; $player++) {
                if ($player === $seat) continue;
                $this->reshuffleStockFromDiscard($state);
                if (!$state['stock']) break;
                $state['hands'][$player][] = array_pop($state['stock']);
            }
            $next = $seat; // General Market: other players draw; this player continues.
        }
        $state['turn'] = $next;
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function rummy(array $state, int $seat, array $action): array
    {
        if (($state['phase'] ?? 'draw') === 'draw') {
            $type = $action['type'] ?? '';
            if ($type === 'draw_discard') {
                if (count($state['discard']) < 2) throw new InvalidArgumentException('There is no discard card to pick up.');
                $card = array_pop($state['discard']);
            } elseif ($type === 'draw_stock') {
                $this->reshuffleStockFromDiscard($state);
                if (!$state['stock']) return ['state' => $state, 'winner' => null, 'draw' => true];
                $card = array_pop($state['stock']);
            } else throw new InvalidArgumentException('Draw from the stock or take the top discard.');
            $state['hands'][$seat][] = $card; $state['phase'] = 'discard'; $state['moves']++;
            return ['state' => $state, 'winner' => null, 'draw' => false];
        }

        if (($action['type'] ?? '') !== 'discard') throw new InvalidArgumentException('Discard one card to finish your turn.');
        $index = filter_var($action['card'] ?? null, FILTER_VALIDATE_INT);
        if ($index === false || $index < 0 || $index >= count($state['hands'][$seat])) throw new InvalidArgumentException('Choose a card from your hand.');
        $hand = $state['hands'][$seat]; $discarded = array_splice($hand, $index, 1)[0];
        $state['hands'][$seat] = $hand; $state['discard'][] = $discarded; $state['moves']++;
        if ($this->isRummyMeldedHand($hand)) return ['state' => $state, 'winner' => $seat, 'draw' => false];
        $state['phase'] = 'draw'; $state['turn'] = ($seat + 1) % count($state['hands']);
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function isRummyMeldedHand(array $hand): bool
    {
        if (!$hand) return true;
        $count = count($hand);
        for ($mask = 1; $mask < (1 << $count); $mask++) {
            $group = [];
            for ($i = 0; $i < $count; $i++) if (($mask & (1 << $i)) !== 0) $group[] = $hand[$i];
            if (count($group) < 3 || !$this->isRummyMeld($group)) continue;
            $remaining = [];
            for ($i = 0; $i < $count; $i++) if (($mask & (1 << $i)) === 0) $remaining[] = $hand[$i];
            if ($this->isRummyMeldedHand($remaining)) return true;
        }
        return false;
    }

    private function isRummyMeld(array $cards): bool
    {
        if (count($cards) >= 3 && count($cards) <= 4 && count(array_unique(array_column($cards, 'rank'))) === 1) return true;
        if (count(array_unique(array_column($cards, 'suit'))) !== 1) return false;
        $values = array_map(fn ($card) => array_search($card['rank'], ['2','3','4','5','6','7','8','9','10','J','Q','K','A'], true) + 2, $cards);
        sort($values);
        return count($values) >= 3 && count(array_unique($values)) === count($values) && $values === range($values[0], $values[count($values) - 1]);
    }

    private function shuffledDeck(): array
    {
        $deck = [];
        foreach (['S', 'H', 'D', 'C'] as $suit) foreach (['2','3','4','5','6','7','8','9','10','J','Q','K','A'] as $rank) $deck[] = ['rank' => $rank, 'suit' => $suit];
        for ($index = count($deck) - 1; $index > 0; $index--) { $swap = random_int(0, $index); [$deck[$index], $deck[$swap]] = [$deck[$swap], $deck[$index]]; }
        return $deck;
    }

    private function blackjackTotal(array $hand): int
    {
        $total = 0; $aces = 0;
        foreach ($hand as $card) {
            if ($card['rank'] === 'A') { $total += 11; $aces++; }
            elseif (in_array($card['rank'], ['J','Q','K'], true)) $total += 10;
            else $total += (int) $card['rank'];
        }
        while ($total > 21 && $aces > 0) { $total -= 10; $aces--; }
        return $total;
    }

    private function blackjack(array $state, int $seat, array $action, array $activeSeats = []): array
    {
        // A player who stands up (or times out) mid-hand is no longer 'seated', so their turn
        // can never come back around and their 'stood' flag can never be set to true by them -
        // without treating departed seats as already-done, the round could never complete.
        $activeSeats = $activeSeats ?: array_keys($state['stood']);
        $type = $action['type'] ?? '';
        if (!in_array($type, ['hit','stand'], true)) throw new InvalidArgumentException('Choose hit or stand.');
        if ($state['stood'][$seat] ?? true) throw new InvalidArgumentException('Your hand has already finished.');
        if ($type === 'hit') {
            if (!$state['deck']) throw new InvalidArgumentException('The deck is empty; stand to finish your hand.');
            $state['hands'][$seat][] = array_pop($state['deck']);
            if ($this->blackjackTotal($state['hands'][$seat]) >= 21) $state['stood'][$seat] = true;
        } else $state['stood'][$seat] = true;
        $state['moves']++;
        $allDone = true;
        foreach ($state['stood'] as $s => $stood) {
            if (in_array($s, $activeSeats, true) && !$stood) { $allDone = false; break; }
        }
        if ($allDone) {
            $scores = [];
            foreach ($state['hands'] as $s => $hand) {
                if (in_array($s, $activeSeats, true)) $scores[$s] = $this->blackjackTotal($hand);
            }
            $eligible = array_filter($scores, fn ($score) => $score <= 21);
            if (!$eligible) return ['state' => $state, 'winner' => null, 'draw' => true];
            $best = max($eligible); $winners = array_keys(array_filter($scores, fn ($score) => $score === $best));
            return ['state' => $state, 'winner' => count($winners) === 1 ? (int) $winners[0] : null, 'draw' => count($winners) !== 1];
        }
        for ($offset = 1; $offset <= count($state['stood']); $offset++) {
            $next = ($seat + $offset) % count($state['stood']);
            if (in_array($next, $activeSeats, true) && !$state['stood'][$next]) { $state['turn'] = $next; break; }
        }
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function seaBattle(array $state, int $seat, array $action): array
    {
        $row = filter_var($action['row'] ?? null, FILTER_VALIDATE_INT);
        $col = filter_var($action['col'] ?? null, FILTER_VALIDATE_INT);
        if ($row === false || $col === false || $row < 0 || $row > 9 || $col < 0 || $col > 9) throw new InvalidArgumentException('Choose a valid grid cell.');
        $key = $row . ':' . $col;
        foreach ($state['shots'][$seat] as $shot) if ($shot['cell'] === $key) throw new InvalidArgumentException('You already fired at that cell.');
        $opponent = 1 - $seat;
        $hit = $state['boards'][$opponent][$row][$col] !== null;
        $state['shots'][$seat][] = ['cell' => $key, 'hit' => $hit];
        $state['moves']++;
        $hits = count(array_filter($state['shots'][$seat], fn ($shot) => $shot['hit']));
        if ($hits >= $state['fleet_cells']) return ['state' => $state, 'winner' => $seat, 'draw' => false];
        $state['turn'] = $opponent;
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function chessStartingBoard(): array
    {
        return [
            ['r','n','b','q','k','b','n','r'], array_fill(0, 8, 'p'),
            array_fill(0, 8, null), array_fill(0, 8, null), array_fill(0, 8, null), array_fill(0, 8, null),
            array_fill(0, 8, 'P'), ['R','N','B','Q','K','B','N','R'],
        ];
    }

    private function dominoesStartingState(int $playerCount): array
    {
        $tiles = [];
        for ($left = 0; $left <= 6; $left++) for ($right = $left; $right <= 6; $right++) $tiles[] = [$left, $right];
        for ($index = count($tiles) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$tiles[$index], $tiles[$swap]] = [$tiles[$swap], $tiles[$index]];
        }
        $handSize = $playerCount === 2 ? 7 : 5;
        $hands = [];
        for ($seat = 0; $seat < $playerCount; $seat++) $hands[$seat] = array_splice($tiles, 0, $handSize);
        return ['hands' => $hands, 'stock' => $tiles, 'chain' => [], 'ends' => null, 'turn' => 0, 'passes' => 0, 'moves' => 0];
    }

    private function crazyEightsStartingState(int $playerCount): array
    {
        $deck = [];
        foreach (['S', 'H', 'D', 'C'] as $suit) foreach (['A','2','3','4','5','6','7','8','9','10','J','Q','K'] as $rank) $deck[] = ['rank' => $rank, 'suit' => $suit];
        for ($index = count($deck) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$deck[$index], $deck[$swap]] = [$deck[$swap], $deck[$index]];
        }
        $handSize = $playerCount === 2 ? 7 : 5;
        $hands = [];
        for ($seat = 0; $seat < $playerCount; $seat++) $hands[$seat] = array_splice($deck, 0, $handSize);
        $top = array_pop($deck);
        return ['hands' => $hands, 'stock' => $deck, 'discard' => [$top], 'top' => $top, 'current_suit' => $top['suit'], 'turn' => 0, 'passes' => 0, 'moves' => 0];
    }

    private function crazyEights(array $state, int $seat, array $action, array $activeSeats): array
    {
        $hand = $state['hands'][$seat] ?? null;
        if (!is_array($hand)) throw new InvalidArgumentException('Your card hand is unavailable.');
        $canPlay = static fn ($card) => $card['rank'] === '8' || $card['rank'] === $state['top']['rank'] || $card['suit'] === $state['current_suit'];

        if (($action['type'] ?? '') === 'draw') {
            if (array_filter($hand, $canPlay)) throw new InvalidArgumentException('You have a playable card.');
            $this->reshuffleStockFromDiscard($state);
            // Nothing anywhere left to draw - the existing 'pass' action (below) already handles
            // ending the round once every seat is blocked, so just let this draw be a no-op turn
            // pass rather than erroring; the client is expected to call 'pass' next.
            if (!$state['stock']) return ['state' => $state, 'winner' => null, 'draw' => false];
            $card = array_pop($state['stock']);
            $hand[] = $card;
            $state['hands'][$seat] = $hand;
            $state['moves']++;
            if ($canPlay($card)) { $state['passes'] = 0; return ['state' => $state, 'winner' => null, 'draw' => false]; }
            $state['passes']++;
            $activeSeats = $activeSeats ?: array_keys($state['hands']);
            if ($state['passes'] >= count($activeSeats)) return ['state' => $state, 'winner' => null, 'draw' => true];
            $state['turn'] = ($seat + 1) % count($state['hands']);
            return ['state' => $state, 'winner' => null, 'draw' => false];
        }

        if (($action['type'] ?? '') === 'pass') {
            if ($state['stock'] || count($state['discard']) > 1 || array_filter($hand, $canPlay)) throw new InvalidArgumentException('Pass only when no playable card or draw pile remains.');
            $state['passes']++;
            $state['moves']++;
            $activeSeats = $activeSeats ?: array_keys($state['hands']);
            if ($state['passes'] >= count($activeSeats)) return ['state' => $state, 'winner' => null, 'draw' => true];
            $state['turn'] = ($seat + 1) % count($state['hands']);
            return ['state' => $state, 'winner' => null, 'draw' => false];
        }

        if (($action['type'] ?? '') !== 'play') throw new InvalidArgumentException('Play a matching card or draw one card.');
        $index = filter_var($action['card'] ?? null, FILTER_VALIDATE_INT);
        if ($index === false || $index < 0 || $index >= count($hand)) throw new InvalidArgumentException('Choose a card from your hand.');
        $card = $hand[$index];
        if (!$canPlay($card)) throw new InvalidArgumentException('That card does not match the rank or suit in play.');
        if ($card['rank'] === '8') {
            $suit = strtoupper((string) ($action['suit'] ?? ''));
            if (!in_array($suit, ['S','H','D','C'], true)) throw new InvalidArgumentException('Choose a suit for the wild eight.');
            $state['current_suit'] = $suit;
        } else {
            $state['current_suit'] = $card['suit'];
        }
        array_splice($hand, $index, 1);
        $state['hands'][$seat] = $hand;
        $state['top'] = $card;
        $state['discard'][] = $card;
        $state['passes'] = 0;
        $state['moves']++;
        if (!$hand) return ['state' => $state, 'winner' => $seat, 'draw' => false];
        $state['turn'] = ($seat + 1) % count($state['hands']);
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function dominoes(array $state, int $seat, array $action, array $activeSeats): array
    {
        $hand = $state['hands'][$seat] ?? null;
        if (!is_array($hand)) throw new InvalidArgumentException('Your domino hand is unavailable.');
        $hasPlayable = static function (array $tiles, ?array $ends): bool {
            if ($ends === null) return count($tiles) > 0;
            foreach ($tiles as [$a, $b]) if ($a === $ends[0] || $b === $ends[0] || $a === $ends[1] || $b === $ends[1]) return true;
            return false;
        };

        if (($action['type'] ?? '') === 'draw') {
            if (!$state['stock']) throw new InvalidArgumentException('The draw pile is empty; pass if no tile can be played.');
            if ($hasPlayable($hand, $state['ends'])) throw new InvalidArgumentException('You have a playable tile; play it instead of drawing.');
            do {
                $hand[] = array_pop($state['stock']);
            } while ($state['stock'] && !$hasPlayable($hand, $state['ends']));
            $state['hands'][$seat] = $hand;
            $state['moves']++;
            if (!$hasPlayable($hand, $state['ends']) && !$state['stock']) {
                // A pass is still required to confirm the blocked seat and advance play.
                return ['state' => $state, 'winner' => null, 'draw' => false];
            }
            return ['state' => $state, 'winner' => null, 'draw' => false];
        }

        if (($action['type'] ?? '') === 'pass') {
            if ($state['stock']) throw new InvalidArgumentException('Draw until you find a playable tile or the stock is empty.');
            if ($hasPlayable($hand, $state['ends'])) throw new InvalidArgumentException('You have a playable tile.');
            $state['passes']++;
            $state['moves']++;
            $activeSeats = $activeSeats ?: array_keys($state['hands']);
            if ($state['passes'] >= count($activeSeats)) {
                $pipCounts = [];
                foreach ($activeSeats as $activeSeat) $pipCounts[$activeSeat] = array_sum(array_map(static fn ($tile) => $tile[0] + $tile[1], $state['hands'][$activeSeat] ?? []));
                $best = min($pipCounts);
                $winners = array_keys($pipCounts, $best, true);
                $winner = count($winners) === 1 ? (int) $winners[0] : null;
                return ['state' => $state, 'winner' => $winner, 'draw' => $winner === null];
            }
            $state['turn'] = ($seat + 1) % count($state['hands']);
            return ['state' => $state, 'winner' => null, 'draw' => false];
        }

        if (($action['type'] ?? '') !== 'play') throw new InvalidArgumentException('Choose a legal domino play, draw, or pass.');
        $tileIndex = filter_var($action['tile'] ?? null, FILTER_VALIDATE_INT);
        if ($tileIndex === false || $tileIndex < 0 || $tileIndex >= count($hand)) throw new InvalidArgumentException('Choose a tile from your hand.');
        [$a, $b] = $hand[$tileIndex];
        $end = $state['ends'] === null ? 'right' : ($action['end'] ?? '');
        if (!in_array($end, ['left', 'right'], true)) throw new InvalidArgumentException('Choose an open end of the chain.');
        if ($state['ends'] === null) {
            $oriented = [$a, $b];
            $state['ends'] = [$a, $b];
        } elseif ($end === 'right' && $a === $state['ends'][1]) {
            $oriented = [$a, $b]; $state['ends'][1] = $b;
        } elseif ($end === 'right' && $b === $state['ends'][1]) {
            $oriented = [$b, $a]; $state['ends'][1] = $a;
        } elseif ($end === 'left' && $b === $state['ends'][0]) {
            $oriented = [$a, $b]; $state['ends'][0] = $a;
        } elseif ($end === 'left' && $a === $state['ends'][0]) {
            $oriented = [$b, $a]; $state['ends'][0] = $b;
        } else {
            throw new InvalidArgumentException('That tile does not match the selected end.');
        }
        if ($end === 'left' && count($state['chain'])) array_unshift($state['chain'], $oriented);
        else $state['chain'][] = $oriented;
        array_splice($hand, $tileIndex, 1);
        $state['hands'][$seat] = $hand;
        $state['passes'] = 0;
        $state['moves']++;
        if (!$hand) return ['state' => $state, 'winner' => $seat, 'draw' => false];
        $state['turn'] = ($seat + 1) % count($state['hands']);
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function draughtsStartingBoard(): array
    {
        $board = array_fill(0, 8, array_fill(0, 8, null));
        for ($row = 0; $row < 3; $row++) for ($col = 0; $col < 8; $col++) if (($row + $col) % 2 === 1) $board[$row][$col] = 'b';
        for ($row = 5; $row < 8; $row++) for ($col = 0; $col < 8; $col++) if (($row + $col) % 2 === 1) $board[$row][$col] = 'w';
        return $board;
    }

    private function chess(array $state, int $seat, array $action): array
    {
        if ($state['draw_offer'] !== null) {
            if (($action['type'] ?? '') === 'accept_draw' && $seat !== $state['draw_offer']) return ['state' => $state, 'winner' => null, 'draw' => true];
            if (($action['type'] ?? '') === 'decline_draw' && $seat !== $state['draw_offer']) {
                $state['draw_offer'] = null;
                return ['state' => $state, 'winner' => null, 'draw' => false];
            }
            throw new InvalidArgumentException('Respond to the draw offer before moving.');
        }
        if (($action['type'] ?? '') === 'resign') return ['state' => $state, 'winner' => 1 - $seat, 'draw' => false];
        if (($action['type'] ?? '') === 'offer_draw') {
            $state['draw_offer'] = $seat;
            $state['turn'] = 1 - $seat;
            return ['state' => $state, 'winner' => null, 'draw' => false];
        }
        $from = $this->chessSquare($action['from'] ?? null);
        $to = $this->chessSquare($action['to'] ?? null);
        if (!$from || !$to) throw new InvalidArgumentException('Choose valid chess squares.');
        $piece = $state['board'][$from[0]][$from[1]];
        if (!$piece || ($seat === 0 ? strtoupper($piece) !== $piece : strtolower($piece) !== $piece)) throw new InvalidArgumentException('That piece does not belong to you.');
        $promotion = strtolower((string) ($action['promotion'] ?? 'q'));
        $move = ['from' => $from, 'to' => $to, 'promotion' => $promotion];
        $legalMoves = $this->chessLegalMoves($state, $seat);
        $found = false;
        foreach ($legalMoves as $legal) if ($legal['from'] === $from && $legal['to'] === $to && (($legal['promotion'] ?? null) === (isset($legal['promotion']) ? $promotion : null))) { $move = $legal; $found = true; break; }
        if (!$found) throw new InvalidArgumentException('That move is not legal.');
        $state = $this->chessApply($state, $move);
        $state['moves']++;
        $state['draw_offer'] = null;
        $state['turn'] = 1 - $seat;
        $nextMoves = $this->chessLegalMoves($state, 1 - $seat);
        $inCheck = $this->chessInCheck($state['board'], 1 - $seat);
        if (!$nextMoves) return $inCheck ? ['state' => $state, 'winner' => $seat, 'draw' => false] : ['state' => $state, 'winner' => null, 'draw' => true];
        if ($state['halfmove'] >= 100 || $this->chessInsufficientMaterial($state['board'])) return ['state' => $state, 'winner' => null, 'draw' => true];
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function chessSquare(mixed $square): ?array
    {
        if (!is_string($square) || !preg_match('/^[a-h][1-8]$/', $square)) return null;
        return [8 - (int) $square[1], ord($square[0]) - 97];
    }

    private function chessSquareName(array $square): string
    {
        return chr(97 + $square[1]) . (8 - $square[0]);
    }

    private function chessLegalMoves(array $state, int $seat): array
    {
        $moves = [];
        foreach ($state['board'] as $row => $line) foreach ($line as $col => $piece) {
            if (!$piece || ($seat === 0 ? strtoupper($piece) !== $piece : strtolower($piece) !== $piece)) continue;
            foreach ($this->chessPseudoMoves($state, $row, $col) as $move) {
                $next = $this->chessApply($state, $move);
                if (!$this->chessInCheck($next['board'], $seat)) $moves[] = $move;
            }
        }
        return $moves;
    }

    private function chessPseudoMoves(array $state, int $row, int $col): array
    {
        $board = $state['board']; $piece = $board[$row][$col]; $white = strtoupper($piece) === $piece; $kind = strtolower($piece); $moves = [];
        $push = function (int $r, int $c, array $extra = []) use (&$moves, $row, $col, $board, $white) {
            if ($r < 0 || $r > 7 || $c < 0 || $c > 7) return false;
            $target = $board[$r][$c];
            if ($target !== null && strtolower($target) === 'k') return false;
            if ($target !== null && (strtoupper($target) === $target) === $white) return false;
            $moves[] = ['from' => [$row, $col], 'to' => [$r, $c]] + $extra;
            return $target === null;
        };
        if ($kind === 'p') {
            $step = $white ? -1 : 1; $start = $white ? 6 : 1; $last = $white ? 0 : 7;
            $one = $row + $step;
            if ($one >= 0 && $one <= 7 && $board[$one][$col] === null) {
                if ($one === $last) foreach (['q','r','b','n'] as $choice) $push($one, $col, ['promotion' => $choice]);
                else $push($one, $col);
                $two = $row + 2 * $step;
                if ($row === $start && $board[$two][$col] === null) $push($two, $col);
            }
            foreach ([-1, 1] as $dc) {
                $r = $row + $step; $c = $col + $dc;
                if ($r < 0 || $r > 7 || $c < 0 || $c > 7) continue;
                $target = $board[$r][$c];
                if ($target !== null && (strtoupper($target) === $target) !== $white) {
                    if ($r === $last) foreach (['q','r','b','n'] as $choice) $push($r, $c, ['promotion' => $choice]);
                    else $push($r, $c);
                }
                elseif ($state['en_passant'] === [$r, $c]) $push($r, $c, ['en_passant' => true]);
            }
        } elseif ($kind === 'n') {
            foreach ([[2,1],[2,-1],[-2,1],[-2,-1],[1,2],[1,-2],[-1,2],[-1,-2]] as [$dr,$dc]) $push($row+$dr,$col+$dc);
        } elseif (in_array($kind, ['b','r','q'], true)) {
            $dirs = [];
            if (in_array($kind, ['b','q'], true)) $dirs = array_merge($dirs, [[1,1],[1,-1],[-1,1],[-1,-1]]);
            if (in_array($kind, ['r','q'], true)) $dirs = array_merge($dirs, [[1,0],[-1,0],[0,1],[0,-1]]);
            foreach ($dirs as [$dr,$dc]) for ($distance = 1; $distance < 8; $distance++) if (!$push($row+$dr*$distance,$col+$dc*$distance)) break;
        } else {
            foreach ([[1,1],[1,-1],[-1,1],[-1,-1],[1,0],[-1,0],[0,1],[0,-1]] as [$dr,$dc]) $push($row+$dr,$col+$dc);
            $home = $white ? 7 : 0; $rights = $state['castling'];
            if ($row === $home && $col === 4 && !$this->chessInCheck($board, $white ? 0 : 1)) {
                $ks = $white ? 'K' : 'k'; $qs = $white ? 'Q' : 'q'; $opp = $white ? 1 : 0;
                if (!empty($rights[$ks]) && $board[$home][5] === null && $board[$home][6] === null && !$this->chessSquareAttacked($board, $home, 5, $opp) && !$this->chessSquareAttacked($board, $home, 6, $opp)) $push($home, 6, ['castle' => 'king']);
                if (!empty($rights[$qs]) && $board[$home][1] === null && $board[$home][2] === null && $board[$home][3] === null && !$this->chessSquareAttacked($board, $home, 3, $opp) && !$this->chessSquareAttacked($board, $home, 2, $opp)) $push($home, 2, ['castle' => 'queen']);
            }
        }
        return $moves;
    }

    private function chessApply(array $state, array $move): array
    {
        [$fr,$fc] = $move['from']; [$tr,$tc] = $move['to']; $board = $state['board']; $piece = $board[$fr][$fc]; $originalPiece = $piece; $target = $board[$tr][$tc]; $white = strtoupper($piece) === $piece;
        if (($move['en_passant'] ?? false) === true) $board[$fr][$tc] = null;
        $board[$fr][$fc] = null;
        if (isset($move['promotion'])) $piece = $white ? strtoupper($move['promotion']) : strtolower($move['promotion']);
        $board[$tr][$tc] = $piece;
        if (isset($move['castle'])) {
            $home = $white ? 7 : 0; $rookFrom = $move['castle'] === 'king' ? 7 : 0; $rookTo = $move['castle'] === 'king' ? 5 : 3;
            $board[$home][$rookTo] = $board[$home][$rookFrom]; $board[$home][$rookFrom] = null;
        }
        if ($piece === 'K') { $state['castling']['K'] = $state['castling']['Q'] = false; }
        if ($piece === 'k') { $state['castling']['k'] = $state['castling']['q'] = false; }
        foreach ([['R',[7,0],'Q'],['R',[7,7],'K'],['r',[0,0],'q'],['r',[0,7],'k']] as [$rook,$square,$right]) if ([$fr,$fc] === $square || ([$tr,$tc] === $square && $target === $rook)) $state['castling'][$right] = false;
        $state['en_passant'] = null;
        if (strtolower($piece) === 'p' && abs($tr - $fr) === 2) $state['en_passant'] = [intdiv($fr + $tr, 2), $fc];
        $state['halfmove'] = strtolower($originalPiece) === 'p' || $target !== null ? 0 : $state['halfmove'] + 1;
        $state['board'] = $board;
        return $state;
    }

    private function chessInCheck(array $board, int $seat): bool
    {
        $king = $seat === 0 ? 'K' : 'k';
        foreach ($board as $r => $line) foreach ($line as $c => $piece) if ($piece === $king) return $this->chessSquareAttacked($board, $r, $c, 1 - $seat);
        return true;
    }

    private function chessSquareAttacked(array $board, int $row, int $col, int $bySeat): bool
    {
        $isEnemy = fn ($piece) => $piece !== null && ($bySeat === 0 ? strtoupper($piece) === $piece : strtolower($piece) === $piece);
        $pawnRow = $row + ($bySeat === 0 ? 1 : -1);
        foreach ([-1,1] as $dc) if ($pawnRow >= 0 && $pawnRow < 8 && $col+$dc >= 0 && $col+$dc < 8 && $board[$pawnRow][$col+$dc] === ($bySeat === 0 ? 'P' : 'p')) return true;
        foreach ([[2,1],[2,-1],[-2,1],[-2,-1],[1,2],[1,-2],[-1,2],[-1,-2]] as [$dr,$dc]) if ($row+$dr>=0&&$row+$dr<8&&$col+$dc>=0&&$col+$dc<8&&$board[$row+$dr][$col+$dc]===($bySeat===0?'N':'n')) return true;
        foreach ([[1,1],[1,-1],[-1,1],[-1,-1],[1,0],[-1,0],[0,1],[0,-1]] as [$dr,$dc]) {
            for ($distance=1;$distance<8;$distance++) {$r=$row+$dr*$distance;$c=$col+$dc*$distance;if($r<0||$r>7||$c<0||$c>7)break;$piece=$board[$r][$c];if($piece===null)continue;if(!$isEnemy($piece))break;$kind=strtolower($piece);$diagonal=$dr!==0&&$dc!==0;if($distance===1&&$kind==='k')return true;if($kind==='q'||($diagonal&&$kind==='b')||(!$diagonal&&$kind==='r'))return true;break;}
        }
        return false;
    }

    private function chessInsufficientMaterial(array $board): bool
    {
        $pieces=[];foreach($board as $row)foreach($row as $piece)if($piece!==null&&strtolower($piece)!=='k')$pieces[]=$piece;
        return count($pieces)<=1;
    }

    private function draughts(array $state, int $seat, array $action): array
    {
        $from = $this->chessSquare($action['from'] ?? null);
        $to = $this->chessSquare($action['to'] ?? null);
        if (!$from || !$to) throw new InvalidArgumentException('Choose valid draughts squares.');
        [$fr,$fc] = $from; [$tr,$tc] = $to; $piece = $state['board'][$fr][$fc];
        $side = $seat === 0 ? 'w' : 'b';
        if (!$piece || strtolower($piece) !== $side) throw new InvalidArgumentException('That piece does not belong to you.');
        $captures = $this->draughtsMoves($state['board'], $seat, true);
        if ($state['continuing_piece'] !== null) {
            // Mid multi-capture: the same piece must keep capturing - no switching to a quiet
            // move or a different piece until the jump sequence ends.
            $allMoves = array_values(array_filter($captures, fn ($move) => $move['from'] === $state['continuing_piece']));
        } else {
            // Capturing is optional, not forced - a player may choose a quiet move even when a
            // capture is available (e.g. to set up a bigger capture later), matching how the
            // physical game is actually played.
            $allMoves = array_merge($captures, $this->draughtsMoves($state['board'], $seat, false));
        }
        $selected = null;
        foreach ($allMoves as $move) if ($move['from'] === $from && $move['to'] === $to) { $selected = $move; break; }
        if (!$selected) throw new InvalidArgumentException($state['continuing_piece'] !== null ? 'You must continue capturing with the same piece.' : 'That draughts move is not legal.');
        $board = $state['board']; $moving = $board[$fr][$fc]; $board[$fr][$fc] = null;
        if ($selected['capture'] !== null) { [$cr,$cc] = $selected['capture']; $board[$cr][$cc] = null; }
        $promoted = false;
        if ($moving === 'w' && $tr === 0) { $moving = 'W'; $promoted = true; }
        if ($moving === 'b' && $tr === 7) { $moving = 'B'; $promoted = true; }
        $board[$tr][$tc] = $moving; $state['board'] = $board; $state['moves']++;
        $state['quiet_plies'] = ($selected['capture'] !== null || $promoted) ? 0 : (int) ($state['quiet_plies'] ?? 0) + 1;
        if ($selected['capture'] !== null && !$promoted) {
            $nextCaptures = array_values(array_filter($this->draughtsMoves($board, $seat, true), fn ($move) => $move['from'] === $to));
            if ($nextCaptures) { $state['continuing_piece'] = $to; return ['state' => $state, 'winner' => null, 'draw' => false]; }
        }
        $state['continuing_piece'] = null; $state['turn'] = 1 - $seat;
        $opponentPieces = $this->draughtsMoves($board, 1 - $seat, false);
        $opponentCaptures = $this->draughtsMoves($board, 1 - $seat, true);
        if (!$opponentPieces && !$opponentCaptures) return ['state' => $state, 'winner' => $seat, 'draw' => false];
        if ($state['quiet_plies'] >= 80) return ['state' => $state, 'winner' => null, 'draw' => true];
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function draughtsMoves(array $board, int $seat, bool $captureOnly): array
    {
        $moves = []; $side = $seat === 0 ? 'w' : 'b';
        foreach ($board as $row => $line) foreach ($line as $col => $piece) {
            if (!$piece || strtolower($piece) !== $side) continue;
            $king = strtoupper($piece) === $piece;
            if ($king) {
                // Flying king: slides any number of empty squares along a diagonal, and can
                // capture the first enemy piece it meets, landing on any empty square beyond it.
                foreach ([[1,1],[1,-1],[-1,1],[-1,-1]] as [$dr,$dc]) {
                    $r = $row + $dr; $c = $col + $dc;
                    while ($r >= 0 && $r <= 7 && $c >= 0 && $c <= 7 && $board[$r][$c] === null) {
                        if (!$captureOnly) $moves[] = ['from'=>[$row,$col],'to'=>[$r,$c],'capture'=>null];
                        $r += $dr; $c += $dc;
                    }
                    if ($r < 0 || $r > 7 || $c < 0 || $c > 7) continue; // ran off the board
                    if (strtolower($board[$r][$c]) === $side) continue; // blocked by its own piece
                    $captureR = $r; $captureC = $c; $landR = $r + $dr; $landC = $c + $dc;
                    while ($landR >= 0 && $landR <= 7 && $landC >= 0 && $landC <= 7 && $board[$landR][$landC] === null) {
                        $moves[] = ['from'=>[$row,$col],'to'=>[$landR,$landC],'capture'=>[$captureR,$captureC]];
                        $landR += $dr; $landC += $dc;
                    }
                }
                continue;
            }
            $directions = $seat === 0 ? [[-1,1],[-1,-1],[1,1],[1,-1]] : [[1,1],[1,-1],[-1,1],[-1,-1]];
            foreach ($directions as [$dr,$dc]) {
                $r = $row + $dr; $c = $col + $dc;
                if ($r < 0 || $r > 7 || $c < 0 || $c > 7) continue;
                if ($board[$r][$c] === null) {
                    if (!$captureOnly && (($seat === 0 && $dr === -1) || ($seat === 1 && $dr === 1))) $moves[] = ['from'=>[$row,$col],'to'=>[$r,$c],'capture'=>null];
                    continue;
                }
                if (strtolower($board[$r][$c]) === $side) continue;
                $landR = $r + $dr; $landC = $c + $dc;
                if ($landR >= 0 && $landR < 8 && $landC >= 0 && $landC < 8 && $board[$landR][$landC] === null) $moves[] = ['from'=>[$row,$col],'to'=>[$landR,$landC],'capture'=>[$r,$c]];
            }
        }
        return $moves;
    }

    private function ludo(array $state, int $seat, array $action, int $rulesVersion = 1): array
    {
        if ($state['pending_roll'] === null) {
            if (($action['roll'] ?? false) !== true) throw new InvalidArgumentException('Roll the die to take your turn.');
            $roll = $this->rollLudoDie();
            $isBonusRoll = $rulesVersion >= 2 && (bool) ($state['bonus_roll_pending'] ?? false);
            if ($rulesVersion >= 2) $state['bonus_roll_pending'] = false;
            $state['pending_roll'] = $roll;
            $state['last_roll'] = ['seat' => $seat, 'value' => $roll];
            $movable = [];
            foreach ($state['tokens'][$seat] as $index => $position) {
                if ($position === -1 ? $roll === 6 : ($position < 58 && $position + $roll <= 57)) $movable[] = $index;
            }
            $state['moves']++;
            if ($movable) return ['state' => $state, 'winner' => null, 'draw' => false, 'message' => $rulesVersion >= 2 && $isBonusRoll ? 'Bonus roll — choose a token.' : null];
            $state['pending_roll'] = null;
            $turnPassed = $roll !== 6 || $isBonusRoll;
            if ($turnPassed) $state['turn'] = ($seat + 1) % count($state['tokens']);
            $message = $isBonusRoll ? 'No legal move on the bonus roll — turn passed.' : ($turnPassed ? 'No legal move — turn passed.' : 'Rolled a six, but no token can move — roll again.');
            return ['state' => $state, 'winner' => null, 'draw' => false, 'message' => $message];
        }

        $token = filter_var($action['token'] ?? null, FILTER_VALIDATE_INT);
        if ($token === false || $token < 0 || $token > 3) throw new InvalidArgumentException('Choose one of your four tokens.');
        $roll = (int) $state['pending_roll'];
        $position = (int) $state['tokens'][$seat][$token];
        if ($position === -1) {
            if ($roll !== 6) throw new InvalidArgumentException('A token needs a six to leave home.');
            $position = 0;
        } elseif ($position >= 58 || $position + $roll > 57) {
            throw new InvalidArgumentException('Choose a token that can move with this exact roll.');
        } else {
            $position += $roll;
        }
        $state['tokens'][$seat][$token] = $position;
        if ($position < 52) {
            $landing = ((int) $state['starts'][$seat] + $position) % 52;
            $isSafe = in_array($landing, self::LUDO_SAFE_SQUARES, true);
        } else {
            $isSafe = true; // not on the shared ring at all
        }
        $captured = 0;
        if ($position < 52 && !$isSafe) {
            foreach ($state['tokens'] as $otherSeat => &$otherTokens) {
                if ($otherSeat === $seat) continue;
                foreach ($otherTokens as &$otherPosition) {
                    if ($otherPosition >= 0 && $otherPosition < 52 && ((int) $state['starts'][$otherSeat] + $otherPosition) % 52 === $landing) { $otherPosition = -1; $captured++; }
                }
                unset($otherPosition);
            }
            unset($otherTokens);
        }
        $state['moves']++;
        // 57 is the final safe square; mark finished tokens separately from active tokens.
        $reachedHome = $state['tokens'][$seat][$token] === 57;
        if ($reachedHome) $state['tokens'][$seat][$token] = 58;
        if (count(array_filter($state['tokens'][$seat], fn ($value) => $value === 58)) === 4) {
            $state['pending_roll'] = null;
            if ($rulesVersion >= 2) $state['bonus_roll_pending'] = false;
            return ['state' => $state, 'winner' => $seat, 'draw' => false, 'message' => 'All tokens reached home — game complete.'];
        }
        $state['pending_roll'] = null;
        $bonus = $rulesVersion >= 2 && ($roll === 6 || $captured > 0 || $reachedHome);
        if ($rulesVersion >= 2) $state['bonus_roll_pending'] = $bonus;
        if (!$bonus && $roll !== 6) $state['turn'] = ($seat + 1) % count($state['tokens']);
        $message = $rulesVersion >= 2 && $captured > 0
            ? 'Captured ' . $captured . ' token' . ($captured === 1 ? '' : 's') . ' — roll again.'
            : ($rulesVersion >= 2 && $reachedHome ? 'Token reached home — roll again.' : ($roll === 6 ? 'Rolled a six — roll again.' : null));
        return ['state' => $state, 'winner' => null, 'draw' => false, 'message' => $message];
    }

    protected function rollLudoDie(): int
    {
        return random_int(1, 6);
    }

    private function ticTacToe(array $state, int $seat, array $action): array
    {
        $cell = filter_var($action['cell'] ?? null, FILTER_VALIDATE_INT);
        if ($cell === false || $cell < 0 || $cell > 8 || $state['board'][$cell] !== null) {
            throw new InvalidArgumentException('Choose an empty board square.');
        }
        $state['board'][$cell] = $seat;
        $state['moves']++;
        $lines = [[0,1,2],[3,4,5],[6,7,8],[0,3,6],[1,4,7],[2,5,8],[0,4,8],[2,4,6]];
        foreach ($lines as $line) {
            if (count(array_filter($line, fn ($i) => $state['board'][$i] === $seat)) === 3) {
                return ['state' => $state, 'winner' => $seat, 'draw' => false];
            }
        }
        if ($state['moves'] === 9) return ['state' => $state, 'winner' => null, 'draw' => true];
        $state['turn'] = 1 - $seat;
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function fourInARow(array $state, int $seat, array $action): array
    {
        $column = filter_var($action['column'] ?? null, FILTER_VALIDATE_INT);
        if ($column === false || $column < 0 || $column > 6) throw new InvalidArgumentException('Choose a valid column.');
        $row = null;
        for ($r = 5; $r >= 0; $r--) if ($state['board'][$r][$column] === null) { $row = $r; break; }
        if ($row === null) throw new InvalidArgumentException('That column is full.');
        $state['board'][$row][$column] = $seat;
        $state['moves']++;
        foreach ([[0,1],[1,0],[1,1],[1,-1]] as [$dr, $dc]) {
            $count = 1;
            foreach ([-1, 1] as $direction) {
                $r = $row + $dr * $direction;
                $c = $column + $dc * $direction;
                while ($r >= 0 && $r < 6 && $c >= 0 && $c < 7 && $state['board'][$r][$c] === $seat) {
                    $count++; $r += $dr * $direction; $c += $dc * $direction;
                }
            }
            if ($count >= 4) return ['state' => $state, 'winner' => $seat, 'draw' => false];
        }
        if ($state['moves'] === 42) return ['state' => $state, 'winner' => null, 'draw' => true];
        $state['turn'] = 1 - $seat;
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function snakesAndLadders(array $state, int $seat): array
    {
        $roll = random_int(1, 6);
        $current = $state['positions'][$seat];
        $next = $current + $roll;
        if ($next <= 100) {
            $next = $state['ladders'][$next] ?? $state['snakes'][$next] ?? $next;
            $state['positions'][$seat] = $next;
        } else {
            $next = $current;
        }
        $state['roll'] = ['seat' => $seat, 'value' => $roll, 'position' => $next];
        $state['moves']++;
        if ($next === 100) return ['state' => $state, 'winner' => $seat, 'draw' => false];
        $state['turn'] = ($seat + 1) % count($state['positions']);
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }

    private function oware(array $state, int $seat, array $action): array
    {
        $pit = filter_var($action['pit'] ?? null, FILTER_VALIDATE_INT);
        $start = $seat === 0 ? 0 : 6;
        if ($pit === false || $pit < $start || $pit >= $start + 6 || $state['pits'][$pit] < 1) {
            throw new InvalidArgumentException('Choose a non-empty pit on your side.');
        }
        $before = $state['pits'];
        $seeds = $state['pits'][$pit];
        $state['pits'][$pit] = 0;
        $position = $pit;
        while ($seeds > 0) {
            $position = ($position + 1) % 12;
            if ($position === $pit) continue;
            $state['pits'][$position]++;
            $seeds--;
        }
        $opponentStart = $seat === 0 ? 6 : 0;
        $opponentEnd = $opponentStart + 6;
        $captured = 0;
        $capturedPits = [];
        while ($position >= $opponentStart && $position < $opponentEnd && in_array($state['pits'][$position], [2, 3], true)) {
            $captured += $state['pits'][$position];
            $capturedPits[$position] = $state['pits'][$position];
            $state['pits'][$position] = 0;
            $position = ($position + 11) % 12;
        }
        // A capture cannot take the opponent's last seeds.
        $opponentHasSeeds = array_sum(array_slice($state['pits'], $opponentStart, 6)) > 0;
        if (!$opponentHasSeeds && $capturedPits) {
            foreach ($capturedPits as $index => $count) $state['pits'][$index] = $count;
            $captured = 0;
            $opponentHasSeeds = true;
        }
        $state['scores'][$seat] += $captured;
        $state['moves']++;
        $mine = $seat === 0 ? array_slice($state['pits'], 0, 6) : array_slice($state['pits'], 6, 6);
        $theirs = $seat === 0 ? array_slice($state['pits'], 6, 6) : array_slice($state['pits'], 0, 6);
        $opponentCanBeFed = false;
        if (array_sum($theirs) === 0) {
            $ownStart = $seat === 0 ? 0 : 6;
            for ($source = $ownStart; $source < $ownStart + 6; $source++) {
                if ($before[$source] === 0) continue;
                $checkPosition = $source;
                for ($remaining = $before[$source]; $remaining > 0; $remaining--) {
                    $checkPosition = ($checkPosition + 1) % 12;
                    if ($checkPosition === $source) $checkPosition = ($checkPosition + 1) % 12;
                    if ($checkPosition >= $opponentStart && $checkPosition < $opponentEnd) $opponentCanBeFed = true;
                }
                if ($opponentCanBeFed) break;
            }
            if ($opponentCanBeFed) throw new InvalidArgumentException('Choose a move that feeds the opponent when possible.');
        }
        if ($state['scores'][0] >= 25 || $state['scores'][1] >= 25 || array_sum($mine) === 0 || array_sum($theirs) === 0 || $state['moves'] >= 200) {
            $state['scores'][0] += array_sum(array_slice($state['pits'], 0, 6));
            $state['scores'][1] += array_sum(array_slice($state['pits'], 6, 6));
            $winner = $state['scores'][0] === $state['scores'][1] ? null : ($state['scores'][0] > $state['scores'][1] ? 0 : 1);
            return ['state' => $state, 'winner' => $winner, 'draw' => $winner === null];
        }
        $state['turn'] = 1 - $seat;
        return ['state' => $state, 'winner' => null, 'draw' => false];
    }
}
