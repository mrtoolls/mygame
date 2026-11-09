<?php

namespace App\Games\TableGames\Snooker;

/**
 * Every physics and rules constant for Snooker lives here, so tuning never requires touching the
 * engine code itself. Units: millimetres for distance, mm/s for velocity, seconds for time.
 *
 * All values are simplified-but-physically-plausible approximations of a real snooker table
 * (official table/ball ratios), not a certified regulation spec - see the "simplified but
 * believable" requirement in the build brief. Where a real-world figure exists (ball diameter,
 * table ratio) we use it; where the brief explicitly asks for "simplified", we pick a clean,
 * documented approximation instead of chasing full rigor (friction/spin coupling in particular -
 * real cloth/ball spin interaction is a deep research topic, not reproducible exactly here).
 */
final class SnookerConfig
{
    // ---- Table geometry ----------------------------------------------------------------------
    // A real full-size table's playing surface is ~3569mm x 1778mm (~2.0073:1). We round to a
    // clean 2:1 (3560 x 1780) per the brief's "about 2:1" - close enough to be visually and
    // physically indistinguishable, and it keeps every derived constant a round number.
    public const TABLE_WIDTH = 3560.0;   // long side (x axis)
    public const TABLE_HEIGHT = 1780.0;  // short side (y axis)
    public const CUSHION_THICKNESS = 50.0; // visual/collision margin only, not simulated as its own body

    // Real snooker ball diameter is 52.5mm.
    public const BALL_DIAMETER = 52.5;
    public const BALL_RADIUS = self::BALL_DIAMETER / 2; // 26.25

    // Baulk line sits 737mm from the baulk (short) cushion on a full table; the "D" radius is
    // 292mm. Scaled proportionally against our rounded table width for a table of this size.
    public const BAULK_LINE_X = 737.0;
    public const D_RADIUS = 292.0;

    // ---- Pockets -------------------------------------------------------------------------------
    // 4 corner pockets + 2 middle (centre) pockets. Corner pockets play slightly tighter than
    // middle pockets on a real table (undercut angle vs straight-on approach) - modelled here as
    // a simple capture-radius difference, which is the standard simplified approach: a ball is
    // potted once its centre passes within the pocket's capture radius of the pocket's centre
    // point. Real jaws/fallback behaviour (a ball rattling out) is out of scope - see the final
    // report for what's simplified.
    public const POCKET_CORNER_RADIUS = 72.0; // capture radius, corner pockets (tighter)
    public const POCKET_MIDDLE_RADIUS = 82.0; // capture radius, middle pockets (slightly easier)
    // How far inside the table edge pocket mouths cut into the cushion line, used to carve the
    // cushion-segment gaps so a ball heading into a pocket doesn't rebound off a "cushion" there.
    public const POCKET_MOUTH_HALF_WIDTH = 95.0;

    // ---- Physics: motion / friction ------------------------------------------------------------
    public const FIXED_TIMESTEP = 1.0 / 240.0; // seconds per physics step
    public const MAX_STEPS_PER_SHOT = 3600;    // hard cap (= 15s of simulated time) - safety net against a pathological runaway
    // A SEPARATE wall-clock cap, independent of the step cap above. A degenerate starting layout
    // (balls spawned already overlapping - never happens from a real rack or a real re-spot, both
    // of which always check clearance, but very much CAN happen from adversarial/random fuzz
    // input) can make the collision resolver burn many CCD sub-steps within a single counted
    // step, so "bounded step count" alone doesn't guarantee "bounded time" the way the build
    // brief asks for. This is checked every few hundred steps (see SnookerPhysics) and forces an
    // immediate stop - treating every ball as resting exactly where it is - if ever exceeded.
    //
    // Deliberately set far above any realistic shot's actual cost (organic worst-case break
    // shots benchmark at roughly 150-400ms even under noisy system load - see the physics test's
    // own benchmark). A value checked against real wall-clock TIME is inherently NOT deterministic
    // (the same input can legitimately take different real milliseconds run to run under CPU
    // contention), which would directly break the "same input -> byte-identical result, 100 runs"
    // requirement if this cap were ever close enough to normal variance to actually trip for an
    // honest shot - this was caught by the determinism test itself catching a real, if rare,
    // divergence while other heavy test processes happened to be running concurrently. Set high
    // enough that only genuinely pathological input (not merely "the machine was busy") can ever
    // reach it, so it stays a true safety net rather than a second, flaky step cap.
    public const MAX_WALL_CLOCK_SECONDS = 10.0;
    public const SETTLE_SPEED_THRESHOLD = 4.0; // mm/s - below this a ball is considered stopped
    public const SETTLE_SPIN_THRESHOLD = 0.4;  // rad/s - below this residual spin is zeroed out

    // Rolling friction deceleration (mm/s^2) applied while a ball is in contact with the cloth -
    // a simplified constant-deceleration model (not velocity-squared drag), which is the standard
    // simplified approach for arcade-realistic billiards physics.
    public const ROLLING_FRICTION = 340.0;
    // Sliding friction (skidding, e.g. right after a stun/screw shot before the ball "grabs" the
    // cloth and starts rolling) decelerates faster than pure rolling friction.
    public const SLIDING_FRICTION = 780.0;
    // Spin decays toward zero as the ball rolls/slides - this is what makes screw/follow effects
    // fade out over distance rather than persisting forever.
    public const SPIN_DECAY = 3.4; // rad/s^2

    // ---- Physics: collisions --------------------------------------------------------------------
    public const BALL_RESTITUTION = 0.96;    // ball-to-ball bounce coefficient (snooker balls are very elastic)
    public const CUSHION_RESTITUTION = 0.82; // cushion rebound coefficient (cloth-covered rubber absorbs more)
    public const CUSHION_TANGENT_DAMPING = 0.88; // tangential velocity retained after a cushion hit (some grip/scuff)

    // ---- Physics: spin effects (simplified) ------------------------------------------------------
    // Fraction of a ball's spin (rad/s) converted into extra linear velocity (mm/s) along the
    // shot direction at the moment of ball-to-ball contact - positive values model "follow"
    // (topspin keeps the cue ball travelling forward through the contact point), negative models
    // "screw/stun-back" (backspin drags the cue ball backward after contact). This single
    // coefficient drives both, since backspin is simply negative topspin in our spin encoding.
    public const FOLLOW_TRANSFER = 10.5;
    // Side-spin (positive = right-hand side / "right-hand side" English) perturbs the ball's
    // tangential velocity on a CUSHION contact, which is what produces swerve/curve off cushions.
    public const SIDE_SPIN_CUSHION_DEFLECTION = 10.0;
    // Maximum spin magnitude (rad/s) obtainable from a maximum-power, maximum-offset strike.
    public const MAX_SPIN = 60.0;

    // ---- Shot input ------------------------------------------------------------------------------
    // Maximum cue-ball speed imparted at power = 1.0 (a full-strength break shot). Chosen so a
    // full-power shot can traverse the table's long diagonal and still have enough energy left to
    // break a tightly packed rack realistically.
    public const MAX_SHOT_SPEED = 8200.0; // mm/s
    public const MIN_SHOT_SPEED = 120.0;  // mm/s - below this a "shot" is rejected as not a real strike

    // ---- Scoring ---------------------------------------------------------------------------------
    public const BALL_VALUES = [
        'red' => 1, 'yellow' => 2, 'green' => 3, 'brown' => 4, 'blue' => 5, 'pink' => 6, 'black' => 7,
    ];
    public const COLOUR_ORDER = ['yellow', 'green', 'brown', 'blue', 'pink', 'black'];
    public const MIN_FOUL_PENALTY = 4;
    public const MAX_FOUL_PENALTY = 7;

    // ---- Match flow ------------------------------------------------------------------------------
    public const DEFAULT_SHOT_SECONDS = 45;
    public const SHOT_WARNING_SECONDS = 10; // client shows a warning once this many seconds remain
    public const MAX_TIMEOUTS_PER_FRAME = 3; // a 3rd timeout forfeits the frame
    public const DEFAULT_REDS_FULL = 15;
    public const DEFAULT_REDS_MOBILE = 6;

    /** Colour spot positions, expressed as fractions of (TABLE_WIDTH, TABLE_HEIGHT) from the bottom-left corner. Matches real table proportions. */
    public static function colourSpots(): array
    {
        return [
            'yellow' => [self::BAULK_LINE_X, self::TABLE_HEIGHT * 0.2],
            'green' => [self::BAULK_LINE_X, self::TABLE_HEIGHT * 0.8],
            'brown' => [self::BAULK_LINE_X, self::TABLE_HEIGHT * 0.5],
            'blue' => [self::TABLE_WIDTH * 0.5, self::TABLE_HEIGHT * 0.5],
            'pink' => [self::TABLE_WIDTH * 0.75, self::TABLE_HEIGHT * 0.5],
            'black' => [self::TABLE_WIDTH - 320.0, self::TABLE_HEIGHT * 0.5],
        ];
    }

    /** The 6 pocket centres and their capture radius, named for client-side rendering + physics capture checks. */
    public static function pockets(): array
    {
        $w = self::TABLE_WIDTH;
        $h = self::TABLE_HEIGHT;
        $c = self::POCKET_CORNER_RADIUS;
        $m = self::POCKET_MIDDLE_RADIUS;
        return [
            ['name' => 'top_left', 'x' => 0.0, 'y' => $h, 'radius' => $c],
            ['name' => 'top_middle', 'x' => $w / 2, 'y' => $h, 'radius' => $m],
            ['name' => 'top_right', 'x' => $w, 'y' => $h, 'radius' => $c],
            ['name' => 'bottom_right', 'x' => $w, 'y' => 0.0, 'radius' => $c],
            ['name' => 'bottom_middle', 'x' => $w / 2, 'y' => 0.0, 'radius' => $m],
            ['name' => 'bottom_left', 'x' => 0.0, 'y' => 0.0, 'radius' => $c],
        ];
    }

    /** Cushion boundary as line segments [x1,y1,x2,y2], with gaps carved out around each pocket mouth so a ball heading for a pocket doesn't rebound off a "cushion" there. */
    public static function cushionSegments(): array
    {
        $w = self::TABLE_WIDTH;
        $h = self::TABLE_HEIGHT;
        $g = self::POCKET_MOUTH_HALF_WIDTH;
        $r = self::BALL_RADIUS;
        return [
            // Bottom edge (y = r), split around the bottom-middle pocket.
            [$g, $r, $w / 2 - $g, $r],
            [$w / 2 + $g, $r, $w - $g, $r],
            // Top edge (y = h - r), split around the top-middle pocket.
            [$g, $h - $r, $w / 2 - $g, $h - $r],
            [$w / 2 + $g, $h - $r, $w - $g, $h - $r],
            // Left edge (x = r), single segment between the two left corner pockets.
            [$r, $g, $r, $h - $g],
            // Right edge (x = w - r), single segment between the two right corner pockets.
            [$w - $r, $g, $w - $r, $h - $g],
        ];
    }
}
