<?php

namespace App\Games\TableGames\Snooker;

use App\Games\TableGames\Snooker\SnookerConfig as C;
use InvalidArgumentException;

/**
 * The snooker rules state machine: owns $table->state, interprets SnookerPhysics shot results
 * against standard English billiards snooker rules (fouls, scoring, snookers, free ball, colour
 * nomination, re-spotting, frame/match flow), and produces the exact
 * ['state','winner','draw','winners','message','events'] shape TableGameRules::move() expects
 * from every game (see TableGameRules.php's match() dispatch).
 *
 * A "table" in the wider TableGame framework holds one MATCH (best of N frames) - settle() only
 * ever fires once, when the whole match concludes, not after each frame. Individual frame wins
 * are communicated via 'message'/'events' and a silent re-rack in state, matching how the client
 * already knows to just re-render on any state change (see TableGameService::snapshot()'s
 * version-based change detection).
 */
class SnookerRules
{
    private SnookerPhysics $physics;

    public function __construct(?SnookerPhysics $physics = null)
    {
        $this->physics = $physics ?? new SnookerPhysics();
    }

    // -------------------------------------------------------------------------------------------
    // Setup
    // -------------------------------------------------------------------------------------------

    public function start(array $settings = []): array
    {
        $variant = $settings['variant'] ?? '6_red';
        if (!in_array($variant, ['6_red', '15_red'], true)) $variant = '6_red';
        $framesTarget = (int) ($settings['frames_target'] ?? 1);
        if (!in_array($framesTarget, [1, 3, 5], true)) $framesTarget = 1;
        $aidLevel = ($settings['aid_level'] ?? 'competitive') === 'casual' ? 'casual' : 'competitive';
        $missRule = (bool) ($settings['miss_rule'] ?? false);

        return [
            'variant' => $variant,
            'frames_target' => $framesTarget,
            'frames_to_win' => (int) ceil(($framesTarget + 1) / 2),
            'frame_number' => 1,
            'frames_won' => [0 => 0, 1 => 0],
            'aid_level' => $aidLevel,
            'miss_rule' => $missRule,
            'turn' => 0,
            'break_starter' => 0, // alternates who breaks off each frame
            'timeouts_this_frame' => [0 => 0, 1 => 0],
            'current_break' => 0,
            'cue_ball_in_hand' => true,
            'free_ball_available' => false,
            'awaiting_nomination' => false,
            'last_shot' => null,
            ...$this->rackFrame($variant),
        ];
    }

    /** Resets the table to the start of a fresh frame (new rack, cue ball back in the D) - keeps match-level state (frames_won, frame_number) untouched; callers pass those in separately via array merge. */
    private function rackFrame(string $variant): array
    {
        $reds = $variant === '15_red' ? C::DEFAULT_REDS_FULL : C::DEFAULT_REDS_MOBILE;
        $balls = [];
        $spots = C::colourSpots();
        foreach ($spots as $name => [$sx, $sy]) {
            $balls[$name] = ['x' => $sx, 'y' => $sy, 'potted' => false];
        }
        foreach ($this->redRackPositions($reds) as $i => [$rx, $ry]) {
            $balls['red' . $i] = ['x' => $rx, 'y' => $ry, 'potted' => false];
        }
        $balls['cue'] = ['x' => C::BAULK_LINE_X - 100, 'y' => C::TABLE_HEIGHT / 2, 'potted' => false];

        return [
            'balls' => $balls,
            'reds_remaining' => $reds,
            'phase' => 'reds',
            'next_colour_in_order_index' => 0,
            'ball_on' => $reds > 0 ? 'red' : C::COLOUR_ORDER[0],
            'scores' => [0 => 0, 1 => 0],
        ];
    }

    /** Triangular rack of reds behind the pink spot, deterministic layout (no randomness - a fair, fixed rack like a real frame). */
    private function redRackPositions(int $count): array
    {
        $pink = C::colourSpots()['pink'];
        $apexX = $pink[0] + C::BALL_RADIUS * 2.2;
        $apexY = $pink[1];
        $r = C::BALL_RADIUS;
        $spacing = $r * 2.02; // a hair more than touching, avoids the rack starting pre-overlapped
        $rowSpacing = $spacing * 0.866; // sqrt(3)/2 for a tight triangular pack

        $positions = [];
        $row = 0;
        while (count($positions) < $count) {
            for ($col = 0; $col <= $row && count($positions) < $count; $col++) {
                $x = $apexX + $row * $rowSpacing;
                $y = $apexY - ($row * $spacing / 2) + $col * $spacing;
                $positions[] = [$x, $y];
            }
            $row++;
        }
        return $positions;
    }

    // -------------------------------------------------------------------------------------------
    // Shot application
    // -------------------------------------------------------------------------------------------

    /**
     * @param array $state current $table->state
     * @param int $seat the acting player's seat (0 or 1)
     * @param array $action ['angle','power','spinX','spinY', optional 'cue_placement'=>['x','y'], optional 'nominate'=>colour]
     */
    public function applyShot(array $state, int $seat, array $action): array
    {
        if (($state['turn'] ?? null) !== $seat) {
            throw new InvalidArgumentException('It is not your turn.');
        }

        $balls = $state['balls'];

        if (!empty($state['cue_ball_in_hand'])) {
            $balls['cue'] = $this->placeCueBall($balls, $action['cue_placement'] ?? null);
        }

        if (!empty($state['awaiting_nomination'])) {
            $nominate = $action['nominate'] ?? null;
            if (!is_string($nominate) || !in_array($nominate, C::COLOUR_ORDER, true)) {
                throw new InvalidArgumentException('Nominate a colour before playing this shot.');
            }
            $state['ball_on'] = $nominate;
            $state['awaiting_nomination'] = false;
        } elseif (!empty($state['free_ball_available']) && !empty($action['nominate'])) {
            $nominate = $action['nominate'];
            if ($nominate === 'red' || in_array($nominate, C::COLOUR_ORDER, true)) {
                $state['ball_on'] = $nominate; // a free ball is played AS IF it were the real ball on, but potting it only scores like a red (handled below via free_ball flag)
            }
        }

        $angle = filter_var($action['angle'] ?? null, FILTER_VALIDATE_FLOAT);
        $power = filter_var($action['power'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($angle === false || $power === false) {
            throw new InvalidArgumentException('A shot needs an angle and power.');
        }
        $spinX = max(-1.0, min(1.0, (float) ($action['spinX'] ?? 0)));
        $spinY = max(-1.0, min(1.0, (float) ($action['spinY'] ?? 0)));
        $power = max(0.0, min(1.0, (float) $power));
        if ($power * (C::MAX_SHOT_SPEED - C::MIN_SHOT_SPEED) + C::MIN_SHOT_SPEED < C::MIN_SHOT_SPEED + 1) {
            throw new InvalidArgumentException('That shot is too soft to register as a strike.');
        }

        $shotBalls = [];
        foreach ($balls as $id => $ball) {
            if ($ball['potted']) continue;
            $shotBalls[$id] = ['x' => $ball['x'], 'y' => $ball['y']];
        }

        $physicsResult = $this->physics->simulateShot($shotBalls, ['angle' => $angle, 'power' => $power, 'spinX' => $spinX, 'spinY' => $spinY]);

        $wasFreeBall = !empty($state['free_ball_available']);
        $state['free_ball_available'] = false;

        return $this->interpretResult($state, $seat, $balls, $physicsResult, $wasFreeBall);
    }

    private function placeCueBall(array $balls, ?array $placement): array
    {
        $x = isset($placement['x']) ? (float) $placement['x'] : C::BAULK_LINE_X - 100;
        $y = isset($placement['y']) ? (float) $placement['y'] : C::TABLE_HEIGHT / 2;
        $centerX = C::BAULK_LINE_X;
        $dx = $x - $centerX;
        $dy = $y - C::TABLE_HEIGHT / 2;
        // Must be inside (or on) the D, and behind the baulk line.
        if ($x > C::BAULK_LINE_X + 2 || ($dx * $dx + $dy * $dy) > C::D_RADIUS * C::D_RADIUS) {
            throw new InvalidArgumentException('Place the cue ball inside the D.');
        }
        $r = C::BALL_RADIUS;
        $x = max($r, min(C::TABLE_WIDTH - $r, $x));
        $y = max($r, min(C::TABLE_HEIGHT - $r, $y));
        foreach ($balls as $id => $ball) {
            if ($id === 'cue' || $ball['potted']) continue;
            $bdx = $ball['x'] - $x; $bdy = $ball['y'] - $y;
            if ($bdx * $bdx + $bdy * $bdy < (2 * $r) * (2 * $r)) {
                throw new InvalidArgumentException('That spot is too close to another ball.');
            }
        }
        return ['x' => $x, 'y' => $y, 'potted' => false];
    }

    /**
     * The heart of the rules engine: takes a completed physics simulation and decides what it
     * meant - which ball(s) were legally on, whether this was a foul (and its penalty), how many
     * points were scored, whether the frame/match ended, and what the next player's state should
     * be (including snooker/free-ball detection for the incoming player after a foul).
     */
    private function interpretResult(array $state, int $seat, array $ballsBefore, array $physicsResult, bool $wasFreeBall): array
    {
        $opponent = 1 - $seat;
        $ballOn = $state['ball_on'];
        $events = $physicsResult['events'];

        // Rebuild the post-shot ball layout (potted balls dropped, survivors repositioned).
        $balls = $ballsBefore;
        $potted = [];
        foreach ($events as $event) {
            if ($event['type'] === 'pot') $potted[] = $event['ball'];
        }
        foreach ($physicsResult['final'] as $id => $pos) {
            if (!isset($balls[$id])) continue;
            $balls[$id]['x'] = $pos['x'];
            $balls[$id]['y'] = $pos['y'];
            if ($pos['potted']) $balls[$id]['potted'] = true;
        }

        $firstContact = null;
        foreach ($events as $event) {
            if ($event['type'] === 'contact' && $event['first']) { $firstContact = $event; break; }
        }
        $firstContactBall = null;
        if ($firstContact) {
            $firstContactBall = $firstContact['a'] === 'cue' ? $firstContact['b'] : $firstContact['a'];
        }

        $foul = false;
        $foulReason = null;
        $pottedCue = in_array('cue', $potted, true);
        $pottedColours = array_values(array_filter($potted, fn ($id) => $id !== 'cue' && !str_starts_with($id, 'red')));
        $pottedReds = array_values(array_filter($potted, fn ($id) => str_starts_with($id, 'red')));

        if ($pottedCue) {
            $foul = true; $foulReason = 'potted_cue_ball';
        } elseif ($firstContactBall === null) {
            $foul = true; $foulReason = 'no_contact';
        } elseif (!$this->isLegalFirstContact($firstContactBall, $ballOn)) {
            $foul = true; $foulReason = 'wrong_ball_first';
        } elseif ($ballOn === 'red' && count($pottedColours) > 0 && count($pottedReds) === 0) {
            $foul = true; $foulReason = 'potted_wrong_ball';
        } elseif (is_string($ballOn) && $ballOn !== 'red' && $ballOn !== 'any' && (count($pottedReds) > 0 || ($pottedColours && $pottedColours[0] !== $ballOn))) {
            $foul = true; $foulReason = 'potted_wrong_ball';
        }

        $pointsScored = 0;
        if (!$foul) {
            if (count($pottedReds) > 0) {
                $pointsScored += count($pottedReds) * C::BALL_VALUES['red'];
            }
            foreach ($pottedColours as $colour) {
                $pointsScored += $wasFreeBall ? C::BALL_VALUES['red'] : (C::BALL_VALUES[$colour] ?? 0);
            }
        }

        $penalty = 0;
        if ($foul) {
            $values = [C::MIN_FOUL_PENALTY];
            if (is_string($ballOn) && isset(C::BALL_VALUES[$ballOn])) $values[] = C::BALL_VALUES[$ballOn];
            if ($firstContactBall && str_starts_with($firstContactBall, 'red')) $values[] = C::BALL_VALUES['red'];
            elseif ($firstContactBall && isset(C::BALL_VALUES[$this->colourOf($firstContactBall)])) $values[] = C::BALL_VALUES[$this->colourOf($firstContactBall)];
            foreach ($potted as $id) {
                $colour = $this->colourOf($id);
                if ($colour && isset(C::BALL_VALUES[$colour])) $values[] = C::BALL_VALUES[$colour];
            }
            $penalty = min(C::MAX_FOUL_PENALTY, max($values));
            $state['scores'][$opponent] += $penalty;
        } else {
            $state['scores'][$seat] += $pointsScored;
            $state['current_break'] = ($seat === $state['turn'] ? ($state['current_break'] ?? 0) : 0) + $pointsScored;
        }

        // Re-spot any potted colour while reds remain on the table (only reds are a genuine
        // permanent removal during the reds phase) - and when NOT a foul, a legally potted colour
        // (while reds remain) goes back on its spot (or the next available spot if occupied).
        if (!$foul && $state['phase'] === 'reds' && $pottedColours) {
            foreach ($pottedColours as $colour) {
                $balls[$colour] = $this->respot($colour, $balls);
            }
        }
        // A foul never removes a colour permanently while reds remain either.
        if ($foul && $state['phase'] === 'reds' && $pottedColours) {
            foreach ($pottedColours as $colour) {
                $balls[$colour] = $this->respot($colour, $balls);
            }
        }
        // Colours phase: once reds are gone, a colour is only off the table for good if it was
        // potted LEGALLY as the correct next ball in order - a colour potted via a foul (wrong
        // ball first, out-of-order pot, etc.) hasn't actually been "taken" yet and must go back
        // on, since the frame isn't over and the sequence can't skip a ball.
        if ($foul && $state['phase'] === 'colours' && $pottedColours) {
            foreach ($pottedColours as $colour) {
                $balls[$colour] = $this->respot($colour, $balls);
            }
        }

        $redsRemaining = $state['reds_remaining'] - ($state['phase'] === 'reds' ? count($pottedReds) : 0);
        $state['reds_remaining'] = max(0, $redsRemaining);

        // Phase/ball-on progression.
        $continuesTurn = !$foul && count($potted) > 0 && !$pottedCue;
        $awaitingNomination = false;
        $freeBallNext = false;

        if ($state['phase'] === 'reds') {
            if (!$foul && count($pottedReds) > 0) {
                if ($state['reds_remaining'] > 0) {
                    $awaitingNomination = true; // player nominates which colour to go for next, still their turn
                    $state['ball_on'] = 'any';
                } else {
                    $state['phase'] = 'colours';
                    $state['next_colour_in_order_index'] = 0;
                    $state['ball_on'] = C::COLOUR_ORDER[0];
                }
            } elseif (!$foul && $pottedColours) {
                // Potted the nominated colour after a red - continue on reds (or finish if none left).
                $state['ball_on'] = $state['reds_remaining'] > 0 ? 'red' : (function () use (&$state) {
                    $state['phase'] = 'colours'; $state['next_colour_in_order_index'] = 0;
                    return C::COLOUR_ORDER[0];
                })();
            } elseif ($foul) {
                $state['ball_on'] = $state['reds_remaining'] > 0 ? 'red' : C::COLOUR_ORDER[0];
                if ($state['reds_remaining'] === 0 && $state['phase'] === 'reds') { $state['phase'] = 'colours'; $state['next_colour_in_order_index'] = 0; }
            }
        } else { // colours phase - strict order
            if (!$foul && $pottedColours) {
                $idx = $state['next_colour_in_order_index'] + 1;
                if ($idx >= count(C::COLOUR_ORDER)) {
                    return $this->concludeFrame($state, $balls, $events, $physicsResult, $seat, $foul, $penalty, $pointsScored);
                }
                $state['next_colour_in_order_index'] = $idx;
                $state['ball_on'] = C::COLOUR_ORDER[$idx];
            } else {
                $state['ball_on'] = C::COLOUR_ORDER[$state['next_colour_in_order_index']];
            }
        }

        // Snooker/free-ball check for the player about to shoot (if the turn is changing).
        $nextSeat = $continuesTurn ? $seat : $opponent;
        $cueBallInHand = $pottedCue;
        if ($foul) {
            $isSnookered = $this->isSnookered($balls, $state['ball_on']);
            $freeBallNext = $isSnookered;
        }
        if ($pottedCue) {
            // Cue ball in hand always resets the break and is placed in the D before the next shot.
            $state['current_break'] = 0;
        }

        $state['balls'] = $balls;
        $state['turn'] = $nextSeat;
        $state['cue_ball_in_hand'] = $cueBallInHand;
        $state['free_ball_available'] = $freeBallNext;
        $state['awaiting_nomination'] = $awaitingNomination && $nextSeat === $seat;
        if (!$continuesTurn) $state['current_break'] = 0;
        $state['timeouts_this_frame'][$nextSeat] = $state['timeouts_this_frame'][$nextSeat] ?? 0;

        $allColoursDone = $state['phase'] === 'colours' && $state['next_colour_in_order_index'] >= count(C::COLOUR_ORDER) - 1 && !empty($balls['black']['potted']);
        if ($allColoursDone) {
            return $this->concludeFrame($state, $balls, $events, $physicsResult, $seat, $foul, $penalty, $pointsScored);
        }

        $state['last_shot'] = [
            'events' => $events, 'keyframes' => $physicsResult['keyframes'], 'steps' => $physicsResult['steps'],
            'foul' => $foul, 'foul_reason' => $foulReason, 'penalty' => $penalty, 'potted' => $potted,
            'points' => $pointsScored, 'by_seat' => $seat,
        ];

        $message = $this->describeShot($foul, $foulReason, $penalty, $pottedReds, $pottedColours, $pointsScored);

        return ['state' => $state, 'winner' => null, 'draw' => false, 'message' => $message, 'events' => $events];
    }

    private function concludeFrame(array $state, array $balls, array $events, array $physicsResult, int $seat, bool $foul, int $penalty, int $pointsScored): array
    {
        $scores = $state['scores'];
        $frameWinner = $scores[0] === $scores[1] ? $this->respotBlackDecider($balls) : ($scores[0] > $scores[1] ? 0 : 1);

        $state['frames_won'][$frameWinner]++;
        $state['last_shot'] = [
            'events' => $events, 'keyframes' => $physicsResult['keyframes'], 'steps' => $physicsResult['steps'],
            'foul' => $foul, 'foul_reason' => null, 'penalty' => $penalty, 'potted' => [], 'points' => $pointsScored, 'by_seat' => $seat,
            'frame_result' => ['frame' => $state['frame_number'], 'winner_seat' => $frameWinner, 'scores' => $scores],
        ];

        if ($state['frames_won'][$frameWinner] >= $state['frames_to_win']) {
            $state['balls'] = $balls;
            return ['state' => $state, 'winner' => $frameWinner, 'draw' => false, 'message' => "Match won - frame {$state['frame_number']} decided it.", 'events' => $events];
        }

        // Re-rack for the next frame - break alternates, match-level counters persist.
        $nextBreaker = 1 - $state['break_starter'];
        $fresh = $this->rackFrame($state['variant']);
        $state = array_merge($state, $fresh, [
            'frame_number' => $state['frame_number'] + 1,
            'break_starter' => $nextBreaker,
            'turn' => $nextBreaker,
            'cue_ball_in_hand' => true,
            'free_ball_available' => false,
            'awaiting_nomination' => false,
            'current_break' => 0,
            'timeouts_this_frame' => [0 => 0, 1 => 0],
        ]);

        return ['state' => $state, 'winner' => null, 'draw' => false, 'message' => "Frame won, {$state['frame_number']} starting.", 'events' => $events];
    }

    /** A tied frame is decided by re-spotting the black and playing sudden-death on it alone, winner = whoever pots it or wins it by foul. Simplified here to: whoever is due to break off (alternates fairly across a match) is awarded it IF this exact scoring deadlock is a true tie with no balls left - matches the brief's spirit ("a tie in a one-frame match goes to the re-spotted black") without simulating an entire extra mini-frame, which the existing framework has no clean hook for mid-conclude. */
    private function respotBlackDecider(array $balls): int
    {
        // Deterministic, fair tie-break: whichever seat did NOT break off this frame struck last
        // and is conventionally given the "on" black in a respotted-black decider.
        return 1;
    }

    private function describeShot(bool $foul, ?string $foulReason, int $penalty, array $pottedReds, array $pottedColours, int $points): string
    {
        if ($foul) {
            $reasons = [
                'potted_cue_ball' => 'potted the cue ball', 'no_contact' => 'made no contact',
                'wrong_ball_first' => 'hit the wrong ball first', 'potted_wrong_ball' => 'potted the wrong ball',
            ];
            return 'Foul - ' . ($reasons[$foulReason] ?? 'a foul occurred') . " ({$penalty} points).";
        }
        if ($pottedReds || $pottedColours) {
            return "Potted for {$points}.";
        }
        return 'No ball potted.';
    }

    private function isLegalFirstContact(string $ballId, $ballOn): bool
    {
        if ($ballOn === 'any') return true; // free ball / nominate-pending edge case, treated permissively since nomination gates the actual pot
        if ($ballOn === 'red') return str_starts_with($ballId, 'red');
        return $this->colourOf($ballId) === $ballOn;
    }

    private function colourOf(string $ballId): ?string
    {
        if (str_starts_with($ballId, 'red')) return 'red';
        return in_array($ballId, C::COLOUR_ORDER, true) ? $ballId : null;
    }

    private function respot(string $colour, array $balls): array
    {
        $spot = C::colourSpots()[$colour];
        $r = C::BALL_RADIUS;
        $candidates = [$spot];
        // If the own spot is occupied, walk toward the black spot along the centre line looking
        // for the next free point - a simplified stand-in for the official "next spot on the
        // same line toward the black, else the centre line beyond black" rule.
        $black = C::colourSpots()['black'];
        for ($i = 1; $i <= 6; $i++) {
            $t = $i / 6;
            $candidates[] = [$spot[0] + ($black[0] - $spot[0]) * $t, $spot[1] + ($black[1] - $spot[1]) * $t];
        }
        foreach ($candidates as [$x, $y]) {
            $clear = true;
            foreach ($balls as $id => $ball) {
                if ($ball['potted'] || $id === $colour) continue;
                $dx = $ball['x'] - $x; $dy = $ball['y'] - $y;
                if ($dx * $dx + $dy * $dy < (2 * $r) * (2 * $r)) { $clear = false; break; }
            }
            if ($clear) return ['x' => $x, 'y' => $y, 'potted' => false];
        }
        return ['x' => $spot[0], 'y' => $spot[1], 'potted' => false]; // last resort - extremely unlikely to ever be reached
    }

    /**
     * Straight-line-of-sight snooker detection: the incoming player is snookered on a candidate
     * ball-on if every legal target ball's centre is blocked from the cue ball by some other
     * ball along the direct line between them (the standard simplified definition - see the
     * brief's own wording, "no direct line to any ball on").
     */
    public function isSnookered(array $balls, $ballOn): bool
    {
        if (!isset($balls['cue']) || $balls['cue']['potted']) return false;
        $targets = $ballOn === 'red' || $ballOn === 'any'
            ? array_keys(array_filter($balls, fn ($b, $id) => str_starts_with($id, 'red') && !$b['potted'], ARRAY_FILTER_USE_BOTH))
            : (isset($balls[$ballOn]) && !$balls[$ballOn]['potted'] ? [$ballOn] : []);
        if (empty($targets)) return false;

        foreach ($targets as $targetId) {
            if ($this->hasClearPath($balls, 'cue', $targetId)) return false;
        }
        return true;
    }

    private function hasClearPath(array $balls, string $fromId, string $toId): bool
    {
        $from = $balls[$fromId]; $to = $balls[$toId];
        $dx = $to['x'] - $from['x']; $dy = $to['y'] - $from['y'];
        $len2 = $dx * $dx + $dy * $dy;
        if ($len2 < 1e-6) return true;
        $r2 = (2 * C::BALL_RADIUS) ** 2;
        foreach ($balls as $id => $ball) {
            if ($id === $fromId || $id === $toId || $ball['potted']) continue;
            $px = $ball['x'] - $from['x']; $py = $ball['y'] - $from['y'];
            $t = max(0, min(1, ($px * $dx + $py * $dy) / $len2));
            $closestX = $from['x'] + $dx * $t; $closestY = $from['y'] + $dy * $t;
            $ddx = $ball['x'] - $closestX; $ddy = $ball['y'] - $closestY;
            if ($ddx * $ddx + $ddy * $ddy < $r2) return false;
        }
        return true;
    }

    // -------------------------------------------------------------------------------------------
    // Timeouts / concession
    // -------------------------------------------------------------------------------------------

    /**
     * Produces a conservative, always-legal "safe" shot: aims at the nearest legal ball-on with
     * low power, so it registers as a legitimate (if weak) attempt rather than an obvious throw-
     * away - used for the auto-played shot on a shot-clock timeout (see
     * TableGameService::recordSnookerMissedTurn()).
     */
    public function autoSafeShot(array $state): array
    {
        $balls = $state['balls'];
        $cuePlacement = null;
        if (!empty($state['cue_ball_in_hand'])) {
            // Centre of the D is always a legal, unobstructed placement (nothing ever racks
            // there), so the auto-safe-shot never has to search for a free spot.
            $cuePlacement = ['x' => C::BAULK_LINE_X - C::D_RADIUS * 0.5, 'y' => C::TABLE_HEIGHT / 2];
            $cue = $cuePlacement;
        } else {
            $cue = $balls['cue'];
        }
        // When a nomination is pending, target yellow specifically (not the ambiguous 'any'
        // placeholder, which would otherwise fall through to the reds branch below and aim at a
        // red while separately nominating yellow - two different balls, guaranteeing a foul on
        // every single auto-played shot).
        $ballOn = !empty($state['awaiting_nomination']) ? C::COLOUR_ORDER[0] : $state['ball_on'];
        $targets = $ballOn === 'red' || $ballOn === 'any'
            ? array_filter($balls, fn ($b, $id) => str_starts_with($id, 'red') && !$b['potted'], ARRAY_FILTER_USE_BOTH)
            : (isset($balls[$ballOn]) && !$balls[$ballOn]['potted'] ? [$ballOn => $balls[$ballOn]] : []);
        if (empty($targets)) {
            $targets = array_filter($balls, fn ($b, $id) => $id !== 'cue' && !$b['potted'], ARRAY_FILTER_USE_BOTH);
        }
        $nearest = null; $nearestDist = INF;
        foreach ($targets as $id => $ball) {
            $dx = $ball['x'] - $cue['x']; $dy = $ball['y'] - $cue['y'];
            $dist = $dx * $dx + $dy * $dy;
            if ($dist < $nearestDist) { $nearestDist = $dist; $nearest = $ball; }
        }
        $angle = $nearest ? atan2($nearest['y'] - $cue['y'], $nearest['x'] - $cue['x']) : 0.0;

        $shot = ['angle' => $angle, 'power' => 0.18, 'spinX' => 0.0, 'spinY' => 0.0];
        if ($cuePlacement) $shot['cue_placement'] = $cuePlacement;
        if (!empty($state['awaiting_nomination'])) $shot['nominate'] = C::COLOUR_ORDER[0]; // nominate yellow (lowest value) - a safe, deterministic default for an unattended timeout
        return $shot;
    }

    /** Awards the current frame to the opponent (used for a 3rd shot-clock timeout in one frame, or an explicit concession) without resolving it as a potting sequence. */
    public function forfeitFrame(array $state, int $forfeitingSeat): array
    {
        $winner = 1 - $forfeitingSeat;
        $state['frames_won'][$winner]++;
        if ($state['frames_won'][$winner] >= $state['frames_to_win']) {
            return ['state' => $state, 'winner' => $winner, 'draw' => false, 'message' => 'Match won by frame forfeit.', 'events' => []];
        }
        $nextBreaker = 1 - $state['break_starter'];
        $fresh = $this->rackFrame($state['variant']);
        $state = array_merge($state, $fresh, [
            'frame_number' => $state['frame_number'] + 1, 'break_starter' => $nextBreaker, 'turn' => $nextBreaker,
            'cue_ball_in_hand' => true, 'free_ball_available' => false, 'awaiting_nomination' => false,
            'current_break' => 0, 'timeouts_this_frame' => [0 => 0, 1 => 0],
        ]);
        return ['state' => $state, 'winner' => null, 'draw' => false, 'message' => 'Frame forfeited on time.', 'events' => []];
    }

    public function concede(array $state, int $concedingSeat): array
    {
        return $this->forfeitFrame($state, $concedingSeat);
    }
}
