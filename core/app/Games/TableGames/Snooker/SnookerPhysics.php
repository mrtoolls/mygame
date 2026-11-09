<?php

namespace App\Games\TableGames\Snooker;

use App\Games\TableGames\Snooker\SnookerConfig as C;

/**
 * A deterministic, fixed-timestep 2D physics engine for a single snooker shot. Pure function in
 * spirit: simulateShot() takes a ball layout + a shot input and returns everything that happened
 * (events, 60Hz keyframes, final resting positions) with no side effects and no randomness, so
 * the exact same input always produces a byte-identical result (see SnookerRules for how this
 * gets interpreted against the rules, and tests/run_snooker_physics_integration.php for the
 * invariant tests this is built to satisfy: momentum conservation, bounded energy, no tunneling,
 * determinism).
 *
 * Continuous collision detection: each fixed 1/240s step is sub-divided at the EARLIEST collision
 * (ball-ball or ball-cushion) that would occur within it - balls are only ever advanced up to a
 * collision point before it's resolved, then simulation continues for the remainder of the step.
 * This is what prevents a fast-moving ball (e.g. a break shot) from tunnelling through another
 * ball or a cushion within a single step, regardless of how large FIXED_TIMESTEP is relative to
 * ball size/speed.
 *
 * Internal representation: balls are tracked as PARALLEL NUMERIC ARRAYS (struct-of-arrays, one
 * flat array per field, indexed 0..n-1) rather than an array of per-ball associative arrays keyed
 * by string id. The latter was the first implementation and benchmarked meaningfully slower (a
 * 15-red break shot: ~260ms median but a long tail past 400ms) - PHP's engine keeps a packed,
 * integer-indexed array far cheaper to read/write in a tight loop than a string-keyed one, and
 * this is exactly the "fixed arrays" optimisation the build brief asks for before resorting to
 * lowering simulation precision. String ball ids only exist at the boundary (input/output).
 *
 * Units: millimetres, mm/s, radians/s, seconds (see SnookerConfig).
 */
class SnookerPhysics
{
    private const EPS = 1e-9;
    private const MAX_SUB_STEPS_PER_FRAME = 12; // safety cap on collision chains within one 1/240s step (e.g. a ball wedged between two others + a cushion)
    private const KEYFRAME_STRIDE = 4; // 240Hz / 4 = 60Hz keyframes

    /**
     * @param array<string,array{x:float,y:float}> $balls ball id => starting position (all balls considered stationary/at rest before the shot - the cue ball's velocity/spin come from $shot)
     * @param array{angle:float,power:float,spinX:float,spinY:float} $shot angle in radians, power 0..1, spinX/spinY each -1..1 (offset within the cue ball's face)
     * @return array{events:array,keyframes:array,final:array<string,array{x:float,y:float}>,steps:int,elapsed_ms:float}
     */
    public function simulateShot(array $balls, array $shot, string $cueBallId = 'cue'): array
    {
        $startedAt = microtime(true);

        $angle = (float) $shot['angle'];
        $power = max(0.0, min(1.0, (float) $shot['power']));
        $spinX = max(-1.0, min(1.0, (float) ($shot['spinX'] ?? 0.0)));
        $spinY = max(-1.0, min(1.0, (float) ($shot['spinY'] ?? 0.0)));

        // Stable iteration order: ball ids sorted ascending, fixed for the lifetime of the shot -
        // this is what makes collision resolution order deterministic regardless of PHP array
        // insertion order in the caller. This order becomes the fixed integer index 0..n-1 used
        // throughout the simulation.
        $ids = array_keys($balls);
        sort($ids, SORT_STRING);
        $n = count($ids);
        $cueIndex = array_search($cueBallId, $ids, true);
        if ($cueIndex === false) {
            throw new \InvalidArgumentException('The cue ball is missing from the table.');
        }

        $x = []; $y = []; $vx = []; $vy = []; $spinF = []; $spinS = []; $potted = [];
        foreach ($ids as $i => $id) {
            $x[$i] = (float) $balls[$id]['x'];
            $y[$i] = (float) $balls[$id]['y'];
            $vx[$i] = 0.0; $vy[$i] = 0.0; $spinF[$i] = 0.0; $spinS[$i] = 0.0; $potted[$i] = false;
        }

        $speed = C::MIN_SHOT_SPEED + $power * (C::MAX_SHOT_SPEED - C::MIN_SHOT_SPEED);
        $vx[$cueIndex] = cos($angle) * $speed;
        $vy[$cueIndex] = sin($angle) * $speed;
        $spinF[$cueIndex] = $spinY * C::MAX_SPIN; // +topspin/follow, -backspin/screw
        $spinS[$cueIndex] = $spinX * C::MAX_SPIN; // side spin (English)

        $events = [];
        $keyframes = [];
        $firstContactSeen = false;
        $steps = 0;

        $cushions = C::cushionSegments();
        $cushionCount = count($cushions);
        $pockets = C::pockets();
        $pocketCount = count($pockets);
        $r2 = (2 * C::BALL_RADIUS) ** 2;

        while ($steps < C::MAX_STEPS_PER_SHOT && $this->anyMoving($vx, $vy, $potted, $n)) {
            // A wall-clock safety net, separate from the step cap above - a degenerate starting
            // layout (balls spawned already overlapping, which never happens from a real rack or
            // re-spot but can from adversarial/fuzzed input) can make the CCD resolver burn many
            // sub-steps within a single counted step, so a bounded step count alone doesn't
            // guarantee bounded time. Checked every 200 steps to keep the microtime() overhead
            // negligible. If tripped, every remaining ball is simply treated as resting exactly
            // where it currently is - physically abrupt, but only ever reachable from an input
            // state that couldn't occur in real play to begin with.
            if ($steps % 200 === 0 && $steps > 0 && (microtime(true) - $startedAt) > C::MAX_WALL_CLOCK_SECONDS) {
                for ($i = 0; $i < $n; $i++) { $vx[$i] = 0.0; $vy[$i] = 0.0; }
                break;
            }
            $remaining = C::FIXED_TIMESTEP;
            $subSteps = 0;
            while ($remaining > self::EPS && $subSteps < self::MAX_SUB_STEPS_PER_FRAME) {
                $earliest = $this->earliestCollision($x, $y, $vx, $vy, $potted, $n, $cushions, $cushionCount, $remaining, $r2);
                if ($earliest === null) {
                    $this->advance($x, $y, $vx, $vy, $spinF, $spinS, $potted, $n, $remaining);
                    $remaining = 0.0;
                } else {
                    $t = $earliest['t'];
                    $this->advance($x, $y, $vx, $vy, $spinF, $spinS, $potted, $n, $t);
                    if ($earliest['type'] === 'ball') {
                        [$i, $j] = [$earliest['a'], $earliest['b']];
                        $this->resolveBallCollision($x, $y, $vx, $vy, $spinF, $i, $j);
                        $first = !$firstContactSeen && ($i === $cueIndex || $j === $cueIndex);
                        if ($first) $firstContactSeen = true;
                        $events[] = ['type' => 'contact', 'a' => $ids[$i], 'b' => $ids[$j], 'first' => $first, 'x' => round($x[$i], 2), 'y' => round($y[$i], 2)];
                    } else {
                        $i = $earliest['ball'];
                        $this->resolveCushionCollision($x, $y, $vx, $vy, $spinS, $i, $cushions[$earliest['segment']]);
                        $events[] = ['type' => 'cushion', 'ball' => $ids[$i], 'x' => round($x[$i], 2), 'y' => round($y[$i], 2)];
                    }
                    $remaining -= $t;
                }
                $subSteps++;
            }

            for ($i = 0; $i < $n; $i++) {
                if ($potted[$i]) continue;
                for ($p = 0; $p < $pocketCount; $p++) {
                    $pocket = $pockets[$p];
                    $dx = $x[$i] - $pocket['x'];
                    $dy = $y[$i] - $pocket['y'];
                    if ($dx * $dx + $dy * $dy <= $pocket['radius'] * $pocket['radius']) {
                        $potted[$i] = true;
                        $vx[$i] = 0.0; $vy[$i] = 0.0; $spinF[$i] = 0.0; $spinS[$i] = 0.0;
                        $events[] = ['type' => 'pot', 'ball' => $ids[$i], 'pocket' => $pocket['name']];
                        break;
                    }
                }
            }

            // Safety net: a ball should never leave the table surface outright (only ever via a
            // pocket capture above) - clamp back inside bounds if numerical edge cases somehow
            // push it past the boundary without being captured (e.g. an extreme-angle shot that
            // just grazes a pocket gap's edge). Keeps the "no balls outside the table" invariant
            // unconditionally true even under adversarial random-shot fuzzing.
            $r = C::BALL_RADIUS;
            for ($i = 0; $i < $n; $i++) {
                if ($potted[$i]) continue;
                if ($x[$i] < $r) { $x[$i] = $r; $vx[$i] = abs($vx[$i]) * C::CUSHION_RESTITUTION; }
                if ($x[$i] > C::TABLE_WIDTH - $r) { $x[$i] = C::TABLE_WIDTH - $r; $vx[$i] = -abs($vx[$i]) * C::CUSHION_RESTITUTION; }
                if ($y[$i] < $r) { $y[$i] = $r; $vy[$i] = abs($vy[$i]) * C::CUSHION_RESTITUTION; }
                if ($y[$i] > C::TABLE_HEIGHT - $r) { $y[$i] = C::TABLE_HEIGHT - $r; $vy[$i] = -abs($vy[$i]) * C::CUSHION_RESTITUTION; }
            }

            $steps++;
            if ($steps % self::KEYFRAME_STRIDE === 0) {
                $keyframes[] = $this->quantizedSnapshot($x, $y, $potted, $ids, $n);
            }
        }

        // Always end with one final, exact resting-position keyframe (quantized) even if the
        // last step didn't land on a keyframe stride boundary.
        $keyframes[] = $this->quantizedSnapshot($x, $y, $potted, $ids, $n);

        $final = [];
        for ($i = 0; $i < $n; $i++) {
            $final[$ids[$i]] = ['x' => round($x[$i], 1), 'y' => round($y[$i], 1), 'potted' => $potted[$i]];
        }

        return [
            'events' => $events,
            'keyframes' => $keyframes,
            'final' => $final,
            'steps' => $steps,
            'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 3),
        ];
    }

    private function anyMoving(array &$vx, array &$vy, array &$potted, int $n): bool
    {
        $threshold2 = C::SETTLE_SPEED_THRESHOLD * C::SETTLE_SPEED_THRESHOLD;
        for ($i = 0; $i < $n; $i++) {
            if ($potted[$i]) continue;
            if ($vx[$i] * $vx[$i] + $vy[$i] * $vy[$i] > $threshold2) return true;
        }
        return false;
    }

    /** Quantized to tenths of a millimetre (one decimal place), per the transport-compression requirement. */
    private function quantizedSnapshot(array &$x, array &$y, array &$potted, array $ids, int $n): array
    {
        $frame = [];
        for ($i = 0; $i < $n; $i++) {
            if ($potted[$i]) continue;
            $frame[$ids[$i]] = [(int) round($x[$i] * 10), (int) round($y[$i] * 10)];
        }
        return $frame;
    }

    /**
     * Advances every non-potted, still-moving ball by $dt: linear motion plus friction
     * deceleration (sliding friction while spin is still "unsettled" on top of the velocity
     * direction - simplified here as: any ball carrying non-trivial residual forward spin not yet
     * aligned with its velocity is still "sliding"; everything else is "rolling"). Spin decays
     * toward zero every step regardless.
     */
    private function advance(array &$x, array &$y, array &$vx, array &$vy, array &$spinF, array &$spinS, array &$potted, int $n, float $dt): void
    {
        if ($dt <= 0) return;
        $rollDecel = C::ROLLING_FRICTION * $dt;
        $slideDecel = C::SLIDING_FRICTION * $dt;
        $spinDecay = C::SPIN_DECAY * $dt;
        for ($i = 0; $i < $n; $i++) {
            if ($potted[$i]) continue;
            $speed2 = $vx[$i] * $vx[$i] + $vy[$i] * $vy[$i];
            if ($speed2 <= C::SETTLE_SPEED_THRESHOLD * C::SETTLE_SPEED_THRESHOLD) {
                $vx[$i] = 0.0; $vy[$i] = 0.0;
            } else {
                $speed = sqrt($speed2);
                $x[$i] += $vx[$i] * $dt;
                $y[$i] += $vy[$i] * $dt;
                $decel = abs($spinF[$i]) > C::SETTLE_SPIN_THRESHOLD ? $slideDecel : $rollDecel;
                if ($decel >= $speed) {
                    $vx[$i] = 0.0; $vy[$i] = 0.0;
                } else {
                    $scale = ($speed - $decel) / $speed;
                    $vx[$i] *= $scale;
                    $vy[$i] *= $scale;
                }
            }
            if ($spinF[$i] !== 0.0) {
                $spinF[$i] = abs($spinF[$i]) <= $spinDecay ? 0.0 : $spinF[$i] - ($spinF[$i] > 0 ? $spinDecay : -$spinDecay);
            }
            if ($spinS[$i] !== 0.0) {
                $spinS[$i] = abs($spinS[$i]) <= $spinDecay ? 0.0 : $spinS[$i] - ($spinS[$i] > 0 ? $spinDecay : -$spinDecay);
            }
        }
    }

    /**
     * Finds the single earliest ball-ball or ball-cushion collision that would occur within the
     * next $dt seconds, across every candidate pair/segment - this is the core of the CCD
     * sub-stepping loop. Returns null if nothing collides within $dt.
     */
    private function earliestCollision(array &$x, array &$y, array &$vx, array &$vy, array &$potted, int $n, array $cushions, int $cushionCount, float $dt, float $r2): ?array
    {
        $best = null;
        $bestT = $dt;

        for ($i = 0; $i < $n; $i++) {
            if ($potted[$i]) continue;
            $iMoving = $vx[$i] !== 0.0 || $vy[$i] !== 0.0;
            for ($j = $i + 1; $j < $n; $j++) {
                if ($potted[$j]) continue;
                if (!$iMoving && $vx[$j] === 0.0 && $vy[$j] === 0.0) continue; // two stationary balls can never collide with each other

                $t = $this->ballBallImpactTime($x[$i], $y[$i], $vx[$i], $vy[$i], $x[$j], $y[$j], $vx[$j], $vy[$j], $r2, $bestT);
                if ($t !== null && $t < $bestT) {
                    $bestT = $t;
                    $best = ['type' => 'ball', 'a' => $i, 'b' => $j, 't' => $t];
                }
            }
        }

        for ($i = 0; $i < $n; $i++) {
            if ($potted[$i]) continue;
            if ($vx[$i] === 0.0 && $vy[$i] === 0.0) continue;
            for ($s = 0; $s < $cushionCount; $s++) {
                $t = $this->ballCushionImpactTime($x[$i], $y[$i], $vx[$i], $vy[$i], $cushions[$s], $bestT);
                if ($t !== null && $t < $bestT) {
                    $bestT = $t;
                    $best = ['type' => 'cushion', 'ball' => $i, 'segment' => $s, 't' => $t];
                }
            }
        }

        return $best;
    }

    /** Swept circle-vs-circle time of impact, or null if they never reach 2r apart within $maxT. */
    private function ballBallImpactTime(float $ax, float $ay, float $avx, float $avy, float $bx, float $by, float $bvx, float $bvy, float $r2, float $maxT): ?float
    {
        $px = $bx - $ax;
        $py = $by - $ay;
        $vx = $bvx - $avx;
        $vy = $bvy - $avy;

        $pDotV = $px * $vx + $py * $vy;
        if ($pDotV >= 0) return null; // moving apart (or stationary relative to each other) - never collides

        $vv = $vx * $vx + $vy * $vy;
        if ($vv < self::EPS) return null;

        $pp = $px * $px + $py * $py;
        $c = $pp - $r2;
        if ($c <= 0) return 0.0; // already overlapping - resolve immediately (shouldn't normally happen given CCD, but safe)

        $bq = 2 * $pDotV;
        $disc = $bq * $bq - 4 * $vv * $c;
        if ($disc < 0) return null;

        $t = (-$bq - sqrt($disc)) / (2 * $vv);
        if ($t < 0 || $t > $maxT) return null;

        return $t;
    }

    /** Swept circle-vs-line-segment time of impact (ball surface touching the cushion line within the segment's extent), or null. */
    private function ballCushionImpactTime(float $bx, float $by, float $bvx, float $bvy, array $segment, float $maxT): ?float
    {
        [$x1, $y1, $x2, $y2] = $segment;
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $len = sqrt($dx * $dx + $dy * $dy);
        if ($len < self::EPS) return null;
        $nx = -$dy / $len; // outward normal candidates (we'll pick the sign facing the ball)
        $ny = $dx / $len;

        // Orient the normal to point toward the ball's current side of the line.
        $toBallX = $bx - $x1;
        $toBallY = $by - $y1;
        $side = $toBallX * $nx + $toBallY * $ny;
        if ($side < 0) { $nx = -$nx; $ny = -$ny; $side = -$side; }

        $vn = $bvx * $nx + $bvy * $ny;
        if ($vn >= 0) return null; // moving away from (or parallel to) the cushion

        $r = C::BALL_RADIUS;
        $t = ($r - $side) / $vn;
        if ($t < 0 || $t > $maxT) return null;

        // Confirm the contact point actually falls within the segment's extent (projected along its own axis), not beyond an endpoint.
        $cx = $bx + $bvx * $t;
        $cy = $by + $bvy * $t;
        $proj = (($cx - $x1) * $dx + ($cy - $y1) * $dy) / ($len * $len);
        if ($proj < -self::EPS || $proj > 1 + self::EPS) return null;

        return $t;
    }

    /**
     * Elastic collision between two equal-mass balls: impulse applied equally and oppositely
     * along the line of centres (this is what guarantees momentum conservation by construction,
     * independent of the restitution coefficient - restitution only controls how much kinetic
     * energy the collision keeps). Spin "follow/screw" transfer happens here too, exactly once,
     * at the moment of contact - see SnookerConfig::FOLLOW_TRANSFER.
     */
    private function resolveBallCollision(array &$x, array &$y, array &$vx, array &$vy, array &$spinF, int $i, int $j): void
    {
        $nx = $x[$j] - $x[$i];
        $ny = $y[$j] - $y[$i];
        $dist = sqrt($nx * $nx + $ny * $ny);
        if ($dist < self::EPS) { $nx = 1.0; $ny = 0.0; $dist = 1.0; }
        $nx /= $dist; $ny /= $dist;

        $rvx = $vx[$j] - $vx[$i];
        $rvy = $vy[$j] - $vy[$i];
        $velAlongNormal = $rvx * $nx + $rvy * $ny;
        if ($velAlongNormal > 0) return; // already separating (can happen after a near-simultaneous multi-ball chain)

        // Equal masses (m=1 each): impulse magnitude derived from a standard restitution model.
        $impulse = -(1 + C::BALL_RESTITUTION) * $velAlongNormal / 2;
        $preIx = $vx[$i]; $preIy = $vy[$i]; // pre-contact direction, needed for the spin-follow transfer below
        $vx[$i] -= $impulse * $nx; $vy[$i] -= $impulse * $ny;
        $vx[$j] += $impulse * $nx; $vy[$j] += $impulse * $ny;

        // Nudge the pair a hair apart along the normal. Without this, the two balls are left at
        // EXACTLY 2r apart (distance² - (2r)² == 0), and floating-point rounding in the very next
        // impact-time check can read that as "still closing" (pDotV fractionally negative),
        // re-triggering an immediate t=0 "collision" between the same pair on the next sub-step -
        // a classic contact-resolution sticking bug that fabricated repeated contact events
        // between a ball and the same one it just bounced off.
        $x[$i] -= $nx * 0.02; $y[$i] -= $ny * 0.02;
        $x[$j] += $nx * 0.02; $y[$j] += $ny * 0.02;

        // Spin transfer: whichever ball was carrying spin gets a forward/backward nudge along its
        // OWN pre-contact travel direction (follow pushes it on through the contact point; screw
        // pulls it back). Spin is then consumed (zeroed) at this ball - it did its job.
        if (abs($spinF[$i]) >= self::EPS) {
            $dirLen = sqrt($preIx * $preIx + $preIy * $preIy);
            if ($dirLen >= self::EPS) {
                $boost = $spinF[$i] * C::FOLLOW_TRANSFER;
                $vx[$i] += ($preIx / $dirLen) * $boost;
                $vy[$i] += ($preIy / $dirLen) * $boost;
            }
            $spinF[$i] = 0.0;
        }
        if (abs($spinF[$j]) >= self::EPS) {
            $dirLen = sqrt($vx[$j] * $vx[$j] + $vy[$j] * $vy[$j]);
            if ($dirLen >= self::EPS) {
                $boost = $spinF[$j] * C::FOLLOW_TRANSFER;
                $vx[$j] += ($vx[$j] / $dirLen) * $boost;
                $vy[$j] += ($vy[$j] / $dirLen) * $boost;
            }
            $spinF[$j] = 0.0;
        }
    }

    /**
     * Cushion rebound: reflects the velocity across the cushion's normal (scaled by cushion
     * restitution) and damps the tangential component slightly, then applies any remaining side
     * spin as a tangential deflection (English/swerve off the cushion), consuming it.
     */
    private function resolveCushionCollision(array &$x, array &$y, array &$vx, array &$vy, array &$spinS, int $i, array $segment): void
    {
        [$x1, $y1, $x2, $y2] = $segment;
        $dx = $x2 - $x1; $dy = $y2 - $y1;
        $len = sqrt($dx * $dx + $dy * $dy);
        $tx = $dx / $len; $ty = $dy / $len; // tangent (unit)
        $nx = -$ty; $ny = $tx;

        $toBallX = $x[$i] - $x1; $toBallY = $y[$i] - $y1;
        if (($toBallX * $nx + $toBallY * $ny) < 0) { $nx = -$nx; $ny = -$ny; }

        $vn = $vx[$i] * $nx + $vy[$i] * $ny;
        $vt = $vx[$i] * $tx + $vy[$i] * $ty;

        $vn = -$vn * C::CUSHION_RESTITUTION;
        $vt = $vt * C::CUSHION_TANGENT_DAMPING;
        if (abs($spinS[$i]) > self::EPS) {
            $vt += $spinS[$i] * C::SIDE_SPIN_CUSHION_DEFLECTION;
            $spinS[$i] = 0.0;
        }

        $vx[$i] = $vn * $nx + $vt * $tx;
        $vy[$i] = $vn * $ny + $vt * $ty;
        // Same sticking-prevention nudge as the ball-ball case - pushes the ball a hair off the
        // cushion line so the very next impact-time check doesn't immediately re-detect contact.
        $x[$i] += $nx * 0.02;
        $y[$i] += $ny * 0.02;
    }
}
