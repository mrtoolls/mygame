# Ayo Olopon — Audit & Target Rules Spec

Status: **Shipped.** Engine, event log, frontend, and tests are implemented per the confirmed
defaults below. See [§5 Final Report](#5-final-report) and [§6 Manual Test Checklist](#6-manual-test-checklist)
at the bottom.

All file paths are relative to `core/`.

## 1. Current behavior (audit)

The game is registered as **`oware`** everywhere in code (`TableGameRules::GAMES['oware']['name'] = 'Ayo / Oware'`), not `ayo`. There is no separate "Ayo" game type — "Ayo" and "Oware" are the same entry. I'll keep the internal key `oware` and only add user-facing "Ayo"-flavored copy where relevant, unless you'd rather I rename the key (renaming touches `GAMES`, `AVAILABLE`, routes/catalog filters, and every `$action`/state reference — doable, but it's a bigger, purely-cosmetic diff I'd rather not bundle in unless you want it).

**Registration & setup**
- `app/Games/TableGames/TableGameRules.php:20` — `GAMES['oware']`, 2-player only (`min=2, max=2`).
- `app/Games/TableGames/TableGameRules.php:30` — listed in `AVAILABLE` (live today).
- `app/Games/TableGames/TableGameRules.php:92` — `start()`: 12 pits, 4 seeds each (48 total), flat array `pits[0..11]`. Seat 0 owns pits 0–5, seat 1 owns pits 6–11. `scores = [0, 0]`.
- No per-pit ownership metadata, no move history, no event log in state — just `pits`, `scores`, `turn`, `moves`.

**Move logic — `oware()`, `app/Games/TableGames/TableGameRules.php:1181-1242`**
- **Validation**: pit must be a non-empty pit on the mover's own side. That's the only pre-check.
- **Sowing**: single pass only. Lifts all seeds from the chosen pit, drops one per pit going forward (`(position+1) % 12`), **skipping the origin pit** if the lap wraps around to it (so a 12+ seed sow already skips the start pit — this part matches your target's default). Stops once all seeds are dropped. **There is no relay/multi-lap sowing** — if the last seed lands in a pit that already had seeds, nothing further happens. This is the single biggest gap vs. your target spec, which is explicit that relay sowing is "the key Ayo rule."
- **Capture**: if the last seed lands in an **opponent** pit now holding 2 or 3, it's captured. Capture then chains **backward** through consecutive opponent pits that also hold 2 or 3 (this chain-backward behavior is currently always ON — your target spec defaults this OFF).
- **Grand-slam guard**: if a capture would leave the opponent's entire row at 0, the capture is undone (seeds put back, nothing captured). This already matches your target's grand-slam default.
- **Must-feed rule**: checked *reactively*, after simulating the move — if the opponent's row sums to 0 *as a result of your move*, the code checks (using the pre-move board) whether any other pit you could have chosen would have reached the opponent's row; if so, your move is rejected outright with a validation error, forcing you to pick again. This is a stricter, differently-triggered rule than your target's "if the opponent is *already* empty at the start of your turn, you must feed them" — see §3 below for why.
- **End of round**: triggers immediately whenever, after the move, either row is empty, either score reaches ≥25, or `moves >= 200`. On end, each row's remaining seeds are swept into that side's own score; higher score wins, equal = draw.
- **Turn passes** to the other seat otherwise. No extra-turn mechanic exists in the current code (none was described as needed in the current implementation, and your target spec doesn't ask for an extra-turn rule either — captures just end the turn, which already matches).

**Frontend — `resources/views/templates/nocturne/user/table_games/show.blade.php:1057-1064`**
- Pure re-render from scratch every move: no sowing animation, no capture animation, nothing tied to gameplay (the only animated CSS in the file is generic table chrome — turn pulse, rematch glow, error shake).
- Each pit renders as a `<button class="board-pit">` with up to 11 seed dots plus a numeric count label. **This already shows an exact count, but the visible dot markup is not laid out with a fixed-size grid/aspect-ratio guarantee** — needs the fixed-grid treatment your spec requires (section A).
- Action payload sent is simply `{pit: index}` — matches what the server reads.

**Move history / events**
- `TableGameMove.public_data` stores only the raw `{pit:N}` action plus (for other games) a `message`; `oware()` never populates `message`. There is **no structured per-move event log** (lift/sow/relay/capture) anywhere — only the raw action and the resulting full board snapshot at each version. This has to be added from scratch for the animation requirements in your spec.

**Draw/refund logic** (not Ayo-specific — shared by every table game)
- `TableGameService::settle()` (`app/Games/TableGames/TableGameService.php:873-926`): when the result is a draw, every seated player's stake is **fully refunded** (`TableGameSettlementMath::refunds()`), no commission taken. A non-draw win pays the winner the commission-adjusted pot and marks the loser. This is what "24-24 is a draw, handled through existing draw/refund logic" will do automatically once `oware()` returns `draw: true` — no new settlement code needed.

**`rules_version`**
- Added by `database/migrations/2026_10_22_000011_add_rules_version_to_table_games.php` (`unsignedSmallInteger`, default `1`, idempotent). Already wired through `start()`/`move()` signatures and set at table-creation time (`TableGameService.php:83`), but **only `ludo` and `whot` currently branch on it** (both use `2` for new tables). `oware` is hard-coded to always create at version `1` and its `oware()` method doesn't even accept `$rulesVersion` as a parameter yet. Following the same established pattern (gate new logic behind `rules_version >= 2`, set `2` for new `oware` tables only) is exactly how I'll satisfy your hard rule #2 (old games keep old rules).

**Timeouts/disconnects** (shared infrastructure, not Ayo-specific)
- `TableGameService::recordMissedTurn()` (`app/Games/TableGames/TableGameService.php:976-1022`): on timeout, **no game auto-plays a move today** — any game, not just Ayo. A missed turn just increments a `missed_turns` counter, marks the player `out` after 2 misses, and passes the turn along (or ends the table if only one player remains). Your spec says "auto-play on timeout must pick a legal pit," which would be new behavior, and specifically for Ayo only, diverging from how every other game on the platform currently handles timeouts. Flagged as an open item below — I don't want to silently special-case Ayo's timeout handling away from the shared mechanic without you confirming that's actually what you want.

**Tests**
- Exactly one automated test exists for Ayo/Oware: `tests/Unit/TableGameRulesTest.php:179-185`, a seed-conservation check after one move. No coverage for captures, the feed rule, grand slam, or any end condition.

---

## 2. Target ruleset — OPEN items, with recommended defaults

Per your instructions, every OPEN item below is written as a configurable option with my recommended default. Nothing is implemented yet.

| # | Open item | Options | Recommended default |
|---|---|---|---|
| 1 | Skip starting pit on 12+ seed laps | On / Off | **On** — current code already does this; keep it. |
| 2 | Relay sowing (pick up again from a non-empty landing pit) | On / Off | **On** — this is the defining mechanic you asked for and the current code has none of it. This is the largest piece of new engine work. |
| 3 | Capture numbers | {2,3} / {2,4,6} | **{2,3}** — matches current code, standard Ayo/Oware convention. |
| 4 | Chain capture (consecutive earlier opponent pits) | On / Off | **Off**, per your stated default — **this is a behavior change from current code**, which has chain-capture always on. I recommend following your default (off) since it's the simpler, more standard single-pit capture and matches most Ayo Olopon descriptions; happy to keep it on if you prefer the current feel. |
| 5 | Last seed lands in your own empty pit | Turn ends / capture opposite pit | **Turn ends** — matches current code's behavior already (no special-case fires today). |
| 6 | Must-feed-opponent rule | On / Off | **On**, but **the trigger condition needs to change** — see "Key structural difference" below. |
| 7 | Grand slam (capturing opponent's entire row) | Allowed-but-captures-nothing / Disallowed | **Allowed but captures nothing** — matches current code already. |
| 8 | Repetition/no-progress ending | Proposal needed | **Proposed**: if **40 half-moves (20 per player) pass with no capture by either side**, end the round immediately — sweep each row into its own score, higher total wins, equal = draw (refund, same as any other draw). This replaces the current flat `moves >= 200` hard cap with something tied to actual stalling rather than an arbitrary total-move count. Open to a different threshold if 40 feels off once we see real games. |
| 9 | Auto-play on timeout picks a legal pit | New Ayo-only behavior / keep shared missed-turn (skip) behavior | **Recommend confirming**: every other game on the platform uses the shared missed-turn/skip mechanic (no auto-play exists anywhere today). Adding real auto-play specifically for Ayo is a bigger, standalone piece of new shared-adjacent infrastructure. My default recommendation is to **keep the existing missed-turn/skip behavior** (already fair: 2 misses = eliminated) rather than build bespoke auto-play just for this one game, unless you specifically want Ayo to never skip a turn. |

### Key structural difference: must-feed rule (needs your decision)

Your target describes must-feed as: *"if the opponent **already has no seeds**, you must make a move that gives them seeds if one exists. If none exists, the game ends."* That implies a row reaching zero does **not**, by itself, end the round — the round only ends when feeding becomes impossible.

Current code instead ends the round **immediately** the moment any row hits zero after a move (checked reactively, not proactively), with the must-feed check only used to reject a move that would needlessly cause that outcome when a feeding alternative existed.

These produce different game lengths and different "the game ends" moments. I recommend adopting your spec's version exactly as written (proactive, ongoing obligation each turn; round only ends when feeding is truly impossible), which also makes the win conditions (25 captured, or no legal feeding move left) the real endgame drivers instead of "a row happened to go to zero." This is a bigger logic rewrite than it sounds like, but it's the correct standard-Ayo behavior and I'd rather do it right than preserve the current shortcut. Flagging explicitly for your sign-off since it changes how long a typical game runs.

---

## 3. Summary of every difference from current behavior

1. **Relay sowing**: absent today, must be added — this is the core fix for "behaves strangely compared to the real game."
2. **Chain capture**: currently always on; your default asks for off.
3. **Must-feed trigger**: currently reactive + bundled into immediate round-end-on-empty-row; target wants it proactive and ongoing, with round-end only when feeding is impossible.
4. **Round-ending conditions**: currently ends on any empty row post-move or `moves >= 200`; target replaces the move cap with a no-capture-streak repetition rule, and removes "any empty row ends it" in favor of the must-feed-until-impossible structure.
5. **Event log for animation**: doesn't exist; net-new addition to `TableGameMove.public_data` (or the move response), needed for every animation requirement in section B/C of your spec.
6. **`rules_version` branching**: `oware()` doesn't currently accept or use `$rulesVersion` at all; needs wiring exactly like `ludo`/`whot` already do, with old tables pinned to version 1 behavior (today's logic, bugs and all) and new tables getting version 2.
7. **Pit sizing/layout**: current board pits aren't guaranteed fixed-size against seed count (no aspect-ratio/grid containment yet) — needs the CSS Grid rework in section A of your spec, plus the bounding-box regression test.
8. **Sowing/capture animation**: none exists; net-new frontend work.
9. **Auto-play-on-timeout**: doesn't exist for any game today; flagged as open item #9 above rather than assumed.

---

## 4. What I need from you before writing any game code

1. Confirm or adjust the recommended defaults in the table above (items 1–9), especially:
   - Chain capture off (changes current behavior)
   - Rewriting must-feed/round-ending to the proactive/ongoing structure (biggest logic change)
   - The 40-half-move no-capture repetition proposal (or give me a number you prefer)
   - Whether Ayo gets bespoke auto-play-on-timeout or keeps the shared skip-turn mechanic
2. Confirm whether to keep the internal game key `oware` (cosmetic-only "Ayo" labeling where the user sees it) or actually rename it throughout the codebase.

Once you confirm, I'll proceed to the engine, event log, frontend, and tests exactly as scoped in your deliverables list.

**Confirmed**: "Go with the best recommendation" — every default above was adopted as-is, including
chain capture off, the proactive must-feed rewrite, the 40-half-move repetition rule, keeping the
shared skip-turn timeout behavior (no bespoke Ayo auto-play), and keeping the internal key `oware`.

---

## 5. Final Report

### What changed

**Engine** (`app/Games/TableGames/TableGameRules.php`)
- `oware()` is now a thin dispatcher: `rules_version >= 2` → `owareV2()` (new), otherwise →
  `owareLegacy()` (the original method, body unchanged, byte-for-byte).
- `owareV2()` implements: real relay/"yielding" sowing (`owareRunSow()`, shared by the real move
  and by the must-feed dry-run probe `owareWouldFeedOpponent()` so "does this move feed the
  opponent" is answered by actually simulating the sow, not an approximation), single-pit capture
  (no chain), the grand-slam guard (capture allowed, takes nothing), a proactive must-feed check
  at the start of every turn (rejects a move that ignores a feeding alternative; ends the round
  outright with no move played when no pit can feed at all), and a 40-half-move no-capture-streak
  repetition ending — replacing the old flat `moves >= 200` cap.
- Every end-of-round sweep (score win, repetition, no-seeds-left) now zeroes the swept pits after
  crediting their seeds to the scores. This was a genuine bug caught during stress-testing: without
  it, a finished game's pits still showed their last values while the score already counted them,
  so `pits + scores` no longer summed to the true 48-seed total. v1/legacy is unaffected (it always
  had this same gap, and is left untouched per the hard rule against changing in-progress games).
- `start()`'s oware branch adds `no_capture_streak: 0` to state for `rules_version >= 2` only; v1
  tables keep the original four-key state shape.
- `rules_version` is set to `2` for new `oware` tables only (`TableGameService.php:83`), the same
  place/pattern Ludo and Whot already use. Existing tables keep whatever version they were created
  with — rules never change mid-game.

**Event log** (`TableGameService.php`)
- `move()`'s `TableGameMove.public_data` now also stores `events` from the rules engine's result.
- `snapshot()` gained an `oware` branch for `last_move`, returning `{version, events, message}` for
  the most recent move — `version` lets the client tell a genuinely new move apart from a stale/
  duplicate poll, reconnect, or spectator join (it only animates when the version is strictly newer
  than the last one it already rendered).

**Frontend**
- `assets/templates/nocturne/js/ayo-board.js` (new): pure, dependency-free pit-rendering helpers
  — deterministic phyllotaxis seed-position packing (seeded by pit id + seed index, never
  `Math.random()`), a visual cap of 18 drawn seeds with the count badge always showing the real
  number, and the DOM builder for one pit. Loaded by both the live page and the Playwright test,
  so there is exactly one implementation to keep correct.
- `assets/templates/nocturne/css/ayo-board.css` (new): the fixed-size pit grid (`aspect-ratio:1`,
  `minmax(44px,1fr)` columns, `overflow:hidden`, `contain:layout paint` — seed count can never
  resize a pit or the board), stores, the sowing "hand" bubble, the flying-seed overlay, legal-pit/
  last-move/current-pit highlight states, the speed control, and a `prefers-reduced-motion` block
  that disables the keyframe animations (pulse/shake/glow) while keeping the instant box-shadow
  highlights.
- `show.blade.php`: replaced the old flex-wrap seed layout (the actual cause of pits resizing as
  seeds were added - the bug you originally flagged) with a dispatch to a new `owareRender()` plus
  a full animation sequencer (`owarePlayEvents()`) that replays `lift`/`sow`/`relay`/`capture`/
  `capture_blocked`/`end` events: seeds hop pit-to-pit via a `fixed`-positioned overlay element
  (Web Animations API, a slight arc, 60-240ms per hop scaled to keep any move under ~4-5s total),
  relay pauses 160-340ms with the hand bubble staying visible as a "continue" cue, captures fly
  staggered 60-90ms apart into the capturing player's store with a glow, and the board always
  snaps to the authoritative server state once the replay finishes (or immediately, with no
  animation, on first load/reconnect/spectator join/an unchanged poll). Input is locked for the
  whole sequence via a new `owareAnimating` flag wired into the same `pendingGameState` queueing
  mechanism already used for the Ludo die roll.
- Legal-pit highlighting: a pit is only marked `.is-legal` (and clickable) when it's the player's
  own, non-empty, and it's their turn — the two client-detectable illegal-tap reasons ("Choose one
  of your own pits.", "Empty pit.") are caught locally with no round trip; the must-feed rule is
  never duplicated client-side, so that rejection reason comes back from the server's own message
  through the existing error-toast path, same as the rest of this codebase's convention.
- Speed control (Normal/Fast/Skip) persists to `localStorage`; Skip and `prefers-reduced-motion`
  both resolve to the same "instant, no flight animation" path.

**Turn timer vs. animation** — confirmed the established pattern (Ludo's die roll) rather than
building new pause logic: the server resets `turn_deadline_at` the moment a move is accepted,
*before* the client even starts animating, and the countdown is driven purely by that deadline
with no client-side pause anywhere in this codebase. Ayo follows the same pattern - the clock is
never paused during a replay.

**Tests**
- `tests/Unit/TableGameRulesTest.php`: 12 new PHPUnit test methods (relay firing, single-pit-only
  capture, grand slam, must-feed reject, must-feed auto-end + pit-zeroing, score-win sweep +
  zeroing, repetition sweep + zeroing, v1 isolation/no-relay, and a 25-random-game conservation
  simulation). **Caveat**: this environment can't run the project's actual PHPUnit (`composer.json`
  requires PHP `^8.3` + `ext-gmp`; this box has PHP 8.2 without `gmp`, and `composer install --dev`
  fails outright on the platform check). I verified every test method for real by extracting the
  committed test file and running its `test_*` methods through a from-scratch, file-local
  `PHPUnit\Framework\TestCase` stand-in (just the handful of assert methods actually used) — all
  12 new tests plus the pre-existing `oware` test pass; 3 unrelated pre-existing failures (Ludo
  dice RNG, Ludo bonus chain, Draughts mandatory capture) are in code this work never touched and
  were confirmed pre-existing, not caused by this change (one is a flaky non-deterministic-dice
  test that was never using the file's own deterministic-dice test subclass). This stand-in is not
  a substitute for running the real suite in CI once the environment can support it.
- `tests/frontend/ayo-pit-size.spec.cjs` (Playwright, real Chromium): the explicitly-required
  "must-pass" bounding-box test — renders a pit at 0/1/4/12/24/48 seeds and asserts the pit and
  board bounding boxes are pixel-identical every time, plus the visual-cap/count-badge behavior,
  a 320px-viewport tap-target-size check (≥44px), and a determinism check (same seed count renders
  identical seed positions twice in a row). All passing against the real shipped CSS/JS.
- `tests/frontend/ayo-runtime.spec.cjs` (Playwright): a true end-to-end runtime smoke test — loads
  the *exact* `<script>` text from `show.blade.php` (not a reimplementation) into a real browser
  against a DOM harness replicating every element id that script touches, then drives it with
  synthetic snapshots shaped exactly like `TableGameService::snapshot()`'s real payload, including
  a relay+capture `last_move.events` log matching what the engine actually produces for that board.
  Confirms: a fresh/first load renders statically with no animation; an animated move settles on
  the exact authoritative final board and credits the capturing player's store; no console errors
  fire during the whole sequence; and re-delivering the same version afterward does not replay the
  animation again (the reconnect/spectator/duplicate-poll requirement). `playwright` was added as a
  project devDependency and Chromium was installed locally to make these runnable going forward.
- `tests/frontend/ayo-pit-harness.html` / `ayo-runtime-harness.html`: the minimal static pages the
  two Playwright specs load.

### Key differences from the old (legacy/v1) behavior
See §3 above for the full list; in one line: v1 was a single-pass Oware sow with always-on chain
capture and an immediate end-on-any-empty-row rule. v2 is relay-sowing Ayo Olopon with single-pit
capture, a proactive must-feed obligation, and a repetition-based ending - exactly the rule set in
§2, gated so no table started before this shipped ever changes underfoot.

### Assumptions / judgment calls made along the way
- Legal-pit highlighting intentionally does **not** pre-compute the must-feed rule client-side
  (would mean duplicating rules logic in JS); an occasional server rejection with a clear message
  is treated as expected, acceptable behavior per the spec's own "illegal taps give a reason"
  wording, not a bug to engineer around.
- The end-of-round "sweep" animation glows both stores and snaps the board to empty rather than
  flying every individual leftover seed from every pit — a deliberate simplification versus the
  per-capture flight treatment, to avoid spawning dozens of flight animations for a single
  game-ending move while still being visually clear that a sweep happened.
- Direction arrows are a static label ("Seeds sow counter-clockwise"), not an animated indicator -
  the spec's own A/B/C/D sections don't demand motion here, and the sowing animation itself already
  demonstrates the direction far more clearly than a static arrow ever could.
- Stores are rendered opponent-above / mine-below on every screen size (not opponent/mine
  side-by-side flanking the board on desktop as literally described). Simpler and consistently
  correct across breakpoints; flagged here as a conscious scope trim if you'd like the desktop-only
  side-by-side layout built out separately.

---

## 6. Manual Test Checklist

Run through this on two real accounts (one per seat), on both a phone and a desktop browser,
against a freshly created table (so it gets `rules_version = 2`).

**Setup & basic play**
- [ ] Create an Ayo table, both players join, the board starts at 4 seeds in every pit, 0-0 on
      the stores.
- [ ] On your turn, only your own non-empty pits are highlighted/tappable; tapping an empty pit
      or one of the opponent's shows an immediate "Empty pit."/"Choose one of your own pits."
      message with no network delay.
- [ ] Make a move. Seeds visibly hop one at a time from pit to pit; the pit count updates as each
      one lands. The opposing player's screen shows the identical animation at the same time (via
      polling).

**Relay**
- [ ] Find/force a move where the sow lands on a non-empty pit that isn't a capture (the very
      first move of the game almost always qualifies, since every pit starts non-empty) - confirm
      the "hand" bubble reappears and a brief pause happens before sowing continues from that pit.

**Capture**
- [ ] Capture an opponent pit (lands making it 2 or 3 seeds) - confirm the seeds fly to your store
      with a staggered/glowing animation and your store count updates correctly.
- [ ] Confirm a pit at 4+ seeds on the opponent's side does **not** capture (relays through
      instead).
- [ ] Confirm a capture does not also sweep an earlier qualifying pit next to it (chain capture is
      off) - that pit's seeds stay on the board.

**Grand slam**
- [ ] Set up (or find in play) a capture that would take the opponent's last seeds - confirm the
      move is allowed but nothing is captured, and the pit stays as-is.

**Must-feed**
- [ ] Get the opponent's row down to zero while you still have a feeding move available - confirm
      a non-feeding move is rejected with a clear message.
- [ ] Reach a position where no move can feed an empty opponent row - confirm the round ends
      immediately with your remaining seeds credited to your score.

**Winning / draw / repetition**
- [ ] Reach 25 captured seeds - confirm the round ends immediately, declares you the winner, and
      every pit visually empties (no leftover seeds shown anywhere once it's over).
- [ ] Engineer a 24-24 final split (or trust the unit test) - confirm it's recorded as a draw and
      both stakes are refunded.
- [ ] (Optional, slow) Let a game stall with no captures for ~40 half-moves - confirm it ends via
      repetition with the higher on-board total winning.

**Reconnect / spectate**
- [ ] Reload the page mid-game - confirm the board just shows the current state with no replay of
      prior moves.
- [ ] Open the table as a third/spectator account (or wait for a spectator) - confirm they see the
      live board update without any animation replay of moves made before they joined.

**Speed / accessibility**
- [ ] Switch between Normal / Fast / Skip - confirm later moves visibly respect the chosen speed,
      and the choice persists across a reload.
- [ ] Enable "reduce motion" at the OS level and reload - confirm moves apply instantly with just
      a brief highlight, no flying seeds.
- [ ] On a ~320-375px phone width, confirm every pit is comfortably tappable (no missed taps), the
      board never scrolls horizontally, and the Stand up / Start buttons are fully visible, not
      clipped.

**Old games unaffected**
- [ ] Any Ayo table created *before* this change (rules_version 1, if one exists) continues to
      behave exactly as before - no relay, chain capture still fires, old must-feed/end timing.
- [ ] A quick sanity pass on Ludo and Draughts - unaffected, since neither game's code was touched.
