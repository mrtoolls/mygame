<?php

namespace App\Games\TableGames;

/**
 * Default "How to play" content shown in the info modal on every live table game.
 * Admins can override any game's text from Admin > Table Games > Settings; these
 * are only the fallback shown when no admin text has been saved yet.
 */
class TableGameGuides
{
    public const GUIDES = [
        'chess' => [
            'goal' => 'Checkmate your opponent\'s king - put it under attack with no legal move that escapes capture.',
            'setup' => 'Standard chess start: each player has 16 pieces on an 8x8 board. You always play from your own side of the board.',
            'how_to_play' => ['Tap a piece you own to select it; legal squares light up.', 'Tap a highlighted square to move there.', 'Pawns promote to a queen automatically on reaching the far rank.', 'Use the Offer Draw or Resign buttons at any time on your turn.'],
            'special_rules' => 'Castling, en passant and pawn promotion all follow standard chess rules and are enforced by the server. Draw offers can be accepted or declined by your opponent.',
            'draw' => 'Stalemate, the 80-ply no-progress rule, or an accepted draw offer ends the game as a draw and refunds both stakes.',
            'win' => 'Checkmate your opponent, or win by resignation or your opponent\'s turn-timer forfeit.',
        ],
        'draughts' => [
            'goal' => 'Capture all of your opponent\'s pieces, or leave them with no legal move.',
            'setup' => 'Standard 8x8 international-style draughts: 12 men per side on the dark squares.',
            'how_to_play' => ['Tap a piece to select it, then tap a highlighted square to move or capture.', 'If a capture is available you must take it.', 'A piece that captures and can capture again continues the same turn automatically.'],
            'special_rules' => 'Captures are mandatory and multi-jumps must be completed. Men may capture both forward and backward. A man reaching the far row becomes a king, which may move one diagonal square in any direction.',
            'draw' => 'A run of 80 half-moves with no capture or promotion ends the game as a draw and refunds both stakes.',
            'win' => 'Capture every opposing piece or leave your opponent with no legal move, or win by your opponent\'s turn-timer forfeit.',
        ],
        'tic_tac_toe' => [
            'goal' => 'Be the first to place three of your marks in a row - horizontally, vertically or diagonally.',
            'setup' => 'A 3x3 grid. One player is X, the other is O.',
            'how_to_play' => ['On your turn, tap any empty square to place your mark.', 'Play alternates until someone wins or the board fills up.'],
            'special_rules' => 'None - classic rules.',
            'draw' => 'A full board with no three-in-a-row is a draw and refunds both stakes.',
            'win' => 'First player to connect three of their own marks in a row wins.',
        ],
        'four_in_a_row' => [
            'goal' => 'Be the first to connect four of your checkers in a row - horizontally, vertically or diagonally.',
            'setup' => 'A 7-column, 6-row standing grid. Checkers are dropped in from the top of a column and fall to the lowest open space.',
            'how_to_play' => ['On your turn, tap a column to drop your checker into it.', 'A full column can no longer be played into.'],
            'special_rules' => 'None - classic rules.',
            'draw' => 'A full board with no four-in-a-row is a draw and refunds both stakes.',
            'win' => 'First player to connect four of their own checkers in a line wins.',
        ],
        'snakes_and_ladders' => [
            'goal' => 'Be the first to reach square 100 exactly.',
            'setup' => 'A 100-square numbered board with ladders (climb up) and snakes (slide down) at fixed positions.',
            'how_to_play' => ['On your turn, tap Roll to have the server roll the die for you.', 'Your token advances automatically by the rolled number.', 'Landing on a ladder\'s base or a snake\'s head moves you instantly.'],
            'special_rules' => 'You must land on 100 exactly - a roll that would overshoot it does not move you.',
            'draw' => 'This game always produces a winner; there is no draw outcome.',
            'win' => 'First player whose token reaches square 100 exactly wins.',
        ],
        'ludo' => [
            'goal' => 'Be the first to bring all four of your tokens all the way around the board and home.',
            'setup' => 'Each player has 4 tokens starting in their home base.',
            'how_to_play' => ['On your turn, tap Roll for a server-generated die roll.', 'Roll a six to bring a token out of home (six also grants another roll).', 'Tap a movable token to advance it by the rolled number.'],
            'special_rules' => 'Landing exactly on an opponent\'s token sends it back home. All four tokens must reach the finish with exact rolls.',
            'draw' => 'This game always produces a winner; there is no draw outcome.',
            'win' => 'First player to bring all four tokens home wins.',
        ],
        'dominoes' => [
            'goal' => 'Be the first to play every tile in your hand.',
            'setup' => 'A standard double-six set (28 tiles), dealt evenly between players with the rest left as a draw stock.',
            'how_to_play' => ['On your turn, tap a tile in your hand that matches either open end of the chain, choosing which end to attach it to.', 'If you cannot play, draw from the stock until you can play or the stock is empty.', 'If you still cannot play, your turn passes.'],
            'special_rules' => 'Both open ends of the chain are playable at any time.',
            'draw' => 'If play is blocked for everyone with tiles remaining, the hand with the fewest pips wins; equal lowest totals are a draw.',
            'win' => 'First player to empty their hand wins immediately. Otherwise, when play is blocked for everyone, the lowest remaining pip count wins.',
        ],
        'backgammon' => [
            'goal' => 'Be the first to move all 15 of your checkers around the board and bear them off.',
            'setup' => 'Each player has 15 checkers in the standard backgammon starting layout, moving in opposite directions around the 24-point board.',
            'how_to_play' => ['On your turn, tap Roll for the server-generated dice.', 'Choose a checker and a die value to move it that many points.', 'If you have a checker on the bar you must re-enter it before any other move.'],
            'special_rules' => 'Landing on a point with exactly one opposing checker sends it to the bar. You may only bear off once all 15 of your checkers are in your home quadrant.',
            'draw' => 'This game always produces a winner; there is no draw outcome.',
            'win' => 'First player to bear off all 15 checkers wins.',
        ],
        'oware' => [
            'goal' => 'Capture more seeds than your opponent by the end of the round.',
            'setup' => 'Two rows of 6 pits each, 4 seeds per pit at the start - one row belongs to each player.',
            'how_to_play' => ['On your turn, tap one of your own non-empty pits to sow its seeds one-by-one anticlockwise into following pits.', 'If your last seed lands in an opponent\'s pit making it hold 2 or 3 seeds, you capture it (and may chain backward through consecutive 2s and 3s).'],
            'special_rules' => 'You must feed your opponent a move if they would otherwise have no seeds to play. A capture that would leave the opponent with no seeds is not allowed.',
            'draw' => 'Equal seed counts when the round ends is a draw and refunds both stakes.',
            'win' => 'The round ends at 25 captured seeds, when a side cannot move, or at the move cap - whoever has captured more seeds wins.',
        ],
        'sea_battle' => [
            'goal' => 'Sink your opponent\'s entire hidden fleet before they sink yours.',
            'setup' => 'Each player has a fleet (17 cells total) placed automatically and privately by the server on a 10x10 grid.',
            'how_to_play' => ['On your turn, tap a cell on the target grid to fire at it.', 'A hit or miss is revealed immediately to both players.'],
            'special_rules' => 'Fleet positions are generated and kept server-side - neither player can see or influence the other\'s layout.',
            'draw' => 'This game always produces a winner; there is no draw outcome.',
            'win' => 'First player to hit all 17 cells of the opponent\'s fleet wins.',
        ],
        'whot' => [
            'goal' => 'Be the first to play every card in your hand.',
            'setup' => 'A Nigerian Whot deck is dealt to each player, with the rest forming a draw pile.',
            'how_to_play' => ['On your turn, tap a card in your hand that matches the top card\'s number or active shape.', 'Whot 20 is wild - play it any time and choose the next shape.', 'If you cannot play, draw a card.'],
            'special_rules' => '1 Hold On (play again), 2 Pick Two, 5 Pick Three (next player draws and is skipped), 8 Suspension (skip next player), 14 General Market (every other player draws one and you play again). Penalty cards do not stack on this table.',
            'draw' => 'If everyone is blocked with the stock empty, the table is a draw and refunds every stake.',
            'win' => 'First player to empty their hand wins.',
        ],
        'rummy' => [
            'goal' => 'Be the first to arrange your entire 7-card hand into valid runs and sets.',
            'setup' => 'Each player is dealt 7 cards; the rest form a draw stock with one card turned face-up as the discard pile.',
            'how_to_play' => ['On your turn, draw a card from the stock or take the top discard.', 'Then discard one card from your hand.'],
            'special_rules' => 'A winning hand must partition completely into 3+ card runs (same suit, consecutive ranks) or sets (same rank, different suits). The server shuffles and validates every hand.',
            'draw' => 'This game always produces a winner; there is no draw outcome.',
            'win' => 'First player whose full hand forms a valid winning combination wins.',
        ],
        'spades' => [
            'goal' => 'As a partnership (seats 1+3 vs seats 2+4), make or beat your bid to win the hand.',
            'setup' => 'A standard 52-card deck dealt evenly to all 4 players, who form two fixed partnerships across the table.',
            'how_to_play' => ['At the start, each player bids 0 (nil) to 13 - the number of tricks they expect their partnership to take.', 'On your turn to play a card, follow the suit that was led if you can.', 'Spades are trump and win over any other suit played in that trick.'],
            'special_rules' => 'Spades cannot be led before they have been "broken" (played off-suit in an earlier trick), unless you have nothing but spades in hand.',
            'draw' => 'This game always produces a result; there is no draw outcome.',
            'win' => 'After one full hand, the partnership whose combined bid and tricks taken score higher wins.',
        ],
        'hearts' => [
            'goal' => 'Finish the hand with the fewest penalty points.',
            'setup' => 'A standard 52-card deck dealt evenly to all 4 players. No card passing on this table.',
            'how_to_play' => ['On your turn, follow the suit that was led if you can.', 'If you cannot follow suit, you may play any card, including a heart or the queen of spades.'],
            'special_rules' => 'Hearts cannot be led until they have been "broken" (played off-suit earlier). On the very first trick you may not play a heart or the queen of spades unless your hand has nothing else.',
            'draw' => 'This game always produces a result; there is no draw outcome.',
            'win' => 'After one full hand, the player with the fewest penalty points wins.',
        ],
        'crazy_eights' => [
            'goal' => 'Be the first to play every card in your hand.',
            'setup' => 'A standard 52-card deck is dealt to each player, with the rest forming a draw stock.',
            'how_to_play' => ['On your turn, tap a card matching the top card\'s rank or suit.', 'Any 8 is wild - play it any time and choose the next suit.', 'If you cannot play, draw a card.'],
            'special_rules' => 'An 8 may be played regardless of the current rank or suit, and sets the suit for the next player.',
            'draw' => 'If everyone is blocked with no cards left to draw, the table is a draw and refunds every stake.',
            'win' => 'First player to empty their hand wins.',
        ],
        'blackjack' => [
            'goal' => 'Finish with a hand total higher than every other player without going over 21.',
            'setup' => 'Each player is dealt two private cards from a server-shuffled deck. This is player-versus-player, not against a house dealer.',
            'how_to_play' => ['On your turn, choose Hit to take another card, or Stand to keep your current total.', 'Play continues until every player has stood or busted.'],
            'special_rules' => 'Aces count as 1 or 11 (whichever is better for the hand); face cards count as 10.',
            'draw' => 'Equal best totals at or below 21 - or every player busting - is a draw and refunds every stake.',
            'win' => 'The highest total at or below 21 once everyone has finished wins.',
        ],
        'texas_holdem' => [
            'goal' => 'Have the best 5-card hand at showdown using your 2 private cards and the shared community cards.',
            'setup' => 'Every player stakes a fixed, equal amount up front - there is no additional betting during the hand.',
            'how_to_play' => ['Each player is dealt 2 private cards.', 'On your turn, tap Check to proceed - there is no bet sizing on this table.', 'Community cards are revealed in stages (flop, turn, river) until showdown.'],
            'special_rules' => 'This is a fixed-stake showdown format with no side bets or raises - every player\'s stake is equal and locked in before the hand starts.',
            'draw' => 'Equal best hands at showdown split the pot evenly as a draw.',
            'win' => 'The best standard poker hand ranking at showdown wins the pot.',
        ],
    ];

    public static function for(string $gameType): array
    {
        return self::GUIDES[$gameType] ?? [
            'goal' => '', 'setup' => '', 'how_to_play' => [], 'special_rules' => '', 'draw' => '', 'win' => '',
        ];
    }
}
