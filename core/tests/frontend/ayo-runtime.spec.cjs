// Runtime smoke test: loads the EXACT script text from show.blade.php (not a reimplementation)
// into a real browser via a minimal DOM harness replicating every element id that script's
// top-level code touches, then drives it with synthetic snapshots shaped exactly like
// TableGameService::snapshot() produces for oware (including a last_move.events payload) to
// exercise the real sowing/relay/capture animation path end to end, not just its syntax.
//
// The production <script> is an IIFE that doesn't expose anything globally - this test appends
// a `window.__ayoTestExports = {...}` hook to the INJECTED COPY of the script text only (string
// manipulation at load time here, never touching the real file), so `gameState` becomes callable
// from the test without changing a single byte of the shipped code.
//
// Run with: node tests/frontend/ayo-runtime.spec.cjs

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const SHOW_BLADE = path.resolve(__dirname, '../../resources/views/templates/nocturne/user/table_games/show.blade.php');
const HARNESS_URL = 'file://' + path.resolve(__dirname, 'ayo-runtime-harness.html').replace(/\\/g, '/');

let failures = 0;
function check(label, cond) {
    console.log((cond ? 'PASS' : 'FAIL') + ' - ' + label);
    if (!cond) failures++;
}

function extractGameScript() {
    const content = fs.readFileSync(SHOW_BLADE, 'utf8');
    const matches = [...content.matchAll(/@push\('script'\)\s*<script>([\s\S]*?)<\/script>\s*@endpush/g)];
    // The second @push('script') block (after the sizeable first one holding chat/gift/etc setup)
    // is the one containing gameState/renderBoard/owareRender - find it by a distinctive marker.
    const block = matches.map((m) => m[1]).find((s) => s.includes('function gameState'));
    if (!block) throw new Error('Could not find the gameState script block in show.blade.php');
    // Export the handful of functions/state the test needs, right before the IIFE's closing
    // call. isAnimating is a live getter (not a snapshot value) so the test can poll it.
    const hook = "\nwindow.__ayoTestExports = { gameState, send: (a)=>send(a), isAnimating: () => owareAnimating };\n";
    const lastCloseIdx = block.lastIndexOf('})();');
    if (lastCloseIdx === -1) throw new Error('Could not find the IIFE closing to attach the test export hook');
    return block.slice(0, lastCloseIdx) + hook + block.slice(lastCloseIdx);
}

function owareSnapshot(overrides) {
    const base = {
        id: 't1', game: 'oware', status: 'active', result: null, player_limit: 2, stake: '0',
        version: 1,
        state: { pits: [4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4], scores: [0, 0], turn: 0, moves: 0, no_capture_streak: 0 },
        is_spectator: false, watching_count: 0, current_user_id: 1, turn_deadline_at: null,
        settlement: null, last_move: null, recent_rolls: null,
        players: [
            { id: 1, name: 'Me', avatar: null, seat: 0, status: 'seated' },
            { id: 2, name: 'Rival', avatar: null, seat: 1, status: 'seated' },
        ],
    };
    return Object.assign(base, overrides);
}

(async () => {
    const script = extractGameScript();
    const browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 1000, height: 900 } });
    page.on('pageerror', (e) => check('no uncaught page error: ' + e.message, false));
    const consoleErrors = [];
    page.on('console', (msg) => {
        if (msg.type() !== 'error') return;
        // The harness uses "about:blank" as a placeholder data-snapshot URL (this test drives
        // everything through direct gameState() calls, never real network polling) - the page's
        // own bootstrap fires one harmless fetch against it on load, logged as a console error
        // but unrelated to anything this test actually exercises.
        if (msg.text().includes('about:blank')) return;
        consoleErrors.push(msg.text());
    });

    await page.goto(HARNESS_URL);
    await page.addScriptTag({ content: script });

    // ── 1: initial load (no previous state) must render statically, no animation, no throw ──
    const initial = owareSnapshot({ version: 1 });
    const afterInitial = await page.evaluate((data) => {
        window.__ayoTestExports.gameState(data);
        const pits = document.querySelectorAll('#ayo-pits .board-pit');
        const counts = Array.from(pits).map((p) => p.querySelector('.board-pit__count').textContent);
        return {
            pitCount: pits.length,
            counts,
            storeMine: document.getElementById('ayo-store-seat-0')?.querySelector('.ayo-store__count')?.textContent,
            storeTheirs: document.getElementById('ayo-store-seat-1')?.querySelector('.ayo-store__count')?.textContent,
        };
    }, initial);
    check('1: initial render produces 12 pits', afterInitial.pitCount === 12);
    check('1: initial render shows 4 seeds in every pit', afterInitial.counts.every((c) => c === '4'));
    check('1: initial render shows 0-0 on the stores', afterInitial.storeMine === '0' && afterInitial.storeTheirs === '0');

    // ── 2: a move with a relay + capture event log animates, then lands on the authoritative state ──
    // Mirrors the engine's own real relay+capture scenario: pit 4 (3 seeds) relays through pit 6,
    // finally capturing pit 7. Matches the exact events owareV2() would produce for that board.
    const events = [
        { type: 'lift', pit: 4, count: 3 },
        { type: 'sow', pit: 5, remaining: 2 },
        { type: 'sow', pit: 6, remaining: 1 },
        { type: 'sow', pit: 7, remaining: 0 },
        { type: 'capture', pit: 7, count: 2, to_seat: 0 },
    ];
    const afterMove = owareSnapshot({
        version: 2,
        state: { pits: [4, 4, 4, 4, 0, 5, 5, 0, 4, 4, 4, 4], scores: [2, 0], turn: 1, moves: 1, no_capture_streak: 0 },
        last_move: { version: 2, events, message: 'Captured 2 seeds!' },
    });
    const result = await page.evaluate(async (data) => {
        window.__ayoTestExports.gameState(data);
        // Animation is async (setTimeout/requestAnimationFrame-driven) - poll the real
        // owareAnimating flag instead of guessing a fixed delay, with a generous timeout.
        await new Promise((resolve) => {
            const start = Date.now();
            (function poll() {
                if (!window.__ayoTestExports.isAnimating() || Date.now() - start > 8000) return resolve();
                setTimeout(poll, 50);
            })();
        });
        const pits = document.querySelectorAll('#ayo-pits .board-pit');
        const counts = Array.from(pits).map((p) => p.querySelector('.board-pit__count').textContent);
        return {
            counts,
            storeMine: document.getElementById('ayo-store-seat-0')?.querySelector('.ayo-store__count')?.textContent,
            pit7Disabled: document.querySelector('.board-pit[data-pit="7"]')?.disabled,
        };
    }, afterMove);
    const expectedByIndex = { 0: '4', 1: '4', 2: '4', 3: '4', 4: '0', 5: '5', 6: '5', 7: '0', 8: '4', 9: '4', 10: '4', 11: '4' };
    // counts[] is in the client's own left-to-right render order (seat 0's view), not pit index
    // order - rebuild the expected sequence the same way the renderer orders them for seat 0.
    const order = [11, 10, 9, 8, 7, 6, 0, 1, 2, 3, 4, 5];
    const expectedSeq = order.map((i) => expectedByIndex[i]);
    check('2: after the animation settles, pits match the authoritative final state', JSON.stringify(result.counts) === JSON.stringify(expectedSeq));
    check('2: captured seeds are credited to the capturing player\'s store', result.storeMine === '2');
    check('2: no console errors were logged during the animated move', consoleErrors.length === 0);
    if (consoleErrors.length) console.log('  console errors: ' + JSON.stringify(consoleErrors));

    // ── 3: a duplicate snapshot at the same version must NOT replay the animation again ──
    // (reconnect/reload/spectator-join all land here too, since they also arrive as a snapshot
    // at a version the client has already rendered)
    const duplicateCheck = await page.evaluate((data) => {
        window.__ayoTestExports.gameState(data); // identical version to the last one already rendered
        return window.__ayoTestExports.isAnimating();
    }, afterMove);
    check('3: re-delivering the same version does not start a new animation', duplicateCheck === false);

    await browser.close();
    console.log('\n' + (failures === 0 ? 'ALL PASS' : failures + ' FAILURE(S)'));
    process.exit(failures === 0 ? 0 : 1);
})().catch((e) => {
    console.error('FAIL - test runner threw:', e);
    process.exit(1);
});
