# Ayo Olopon — Audit & Target Rules Spec

Status: **Step 1 — audit complete, awaiting confirmation on OPEN items before any game code is written.**

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
