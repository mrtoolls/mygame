// Must-pass regression test (see docs/AYO_RULES.md section A): a pit's size must never change
// with its seed count. Renders the SAME pit DOM the live table page produces (both load
// assets/templates/nocturne/js/ayo-board.js and ayo-board.css - no reimplementation here, so
// this exercises the exact shipped code) at 0, 1, 4, 12, 24 and 48 seeds and asserts every
// resulting bounding box is identical.
//
// Run with: node tests/frontend/ayo-pit-size.spec.cjs
// (plain Node + the Playwright library directly, not the @playwright/test runner, so it needs
// no extra config file - just `npm install --save-dev playwright` and
// `npx playwright install chromium` once. .cjs because this project's package.json sets
// "type": "module" sitewide for its Vite build, but this script uses plain require().)

const path = require('path');
const { chromium } = require('playwright');

const HARNESS_URL = 'file://' + path.resolve(__dirname, 'ayo-pit-harness.html').replace(/\\/g, '/');
const SEED_COUNTS = [0, 1, 4, 12, 24, 48];

let failures = 0;
function check(label, cond) {
    console.log((cond ? 'PASS' : 'FAIL') + ' - ' + label);
    if (!cond) failures++;
}

(async () => {
    const browser = await chromium.launch();

    // ── A: pit + board bounding boxes are identical across every seed count, desktop width ──
    {
        const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
        await page.goto(HARNESS_URL);
        const boxes = [];
        for (const count of SEED_COUNTS) {
            const result = await page.evaluate((n) => {
                const pit = window.renderOnePit(n);
                const board = document.getElementById('board');
                const pitBox = pit.getBoundingClientRect();
                const boardBox = board.getBoundingClientRect();
                return {
                    pit: { width: pitBox.width, height: pitBox.height },
                    board: { width: boardBox.width, height: boardBox.height },
                    visibleSeeds: pit.querySelectorAll('.board-pit__seed').length,
                    badgeText: pit.querySelector('.board-pit__count').textContent,
                };
            }, count);
            boxes.push({ count, ...result });
        }
        const reference = boxes[0];
        const allPitSizesMatch = boxes.every((b) => b.pit.width === reference.pit.width && b.pit.height === reference.pit.height);
        const allBoardSizesMatch = boxes.every((b) => b.board.width === reference.board.width && b.board.height === reference.board.height);
        check('A: pit bounding box is identical at 0/1/4/12/24/48 seeds', allPitSizesMatch);
        check('A: board bounding box is identical at 0/1/4/12/24/48 seeds', allBoardSizesMatch);
        if (!allPitSizesMatch || !allBoardSizesMatch) {
            console.log('  ' + JSON.stringify(boxes));
        }

        // Visual seed cap: never draw more than AyoBoard.VISUAL_CAP dots, but the count badge
        // always shows the real number.
        const capInfo = await page.evaluate(() => window.AyoBoard.VISUAL_CAP);
        const cap48 = boxes.find((b) => b.count === 48);
        check('A: seed count above the visual cap draws only the capped number of dots', cap48.visibleSeeds === capInfo);
        check('A: the count badge always shows the real count, not the capped one', cap48.badgeText === '48');
        const cap4 = boxes.find((b) => b.count === 4);
        check('A: a count below the cap draws exactly that many dots', cap4.visibleSeeds === 4);
        await page.close();
    }

    // ── A (continued): tap targets stay >= 44px even at a 320px phone width ──
    {
        const page = await browser.newPage({ viewport: { width: 320, height: 700 } });
        await page.goto(HARNESS_URL);
        const pitSize = await page.evaluate(() => {
            const pit = window.renderOnePit(4);
            const box = pit.getBoundingClientRect();
            return Math.min(box.width, box.height);
        });
        check('A: pit stays at least 44px (tap target) at a 320px viewport (actual: ' + pitSize.toFixed(1) + 'px)', pitSize >= 44);
        await page.close();
    }

    // ── A (continued): same seed count, two independent renders, same positions (deterministic) ──
    {
        const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
        await page.goto(HARNESS_URL);
        const positionsOf = async (count) => page.evaluate((n) => {
            const pit = window.renderOnePit(n);
            return Array.from(pit.querySelectorAll('.board-pit__seed')).map((s) => s.style.left + ',' + s.style.top);
        }, count);
        const first = await positionsOf(12);
        const second = await positionsOf(12);
        check('A: identical seed count renders identical seed positions (no Math.random jumping)', JSON.stringify(first) === JSON.stringify(second));
        await page.close();
    }

    await browser.close();
    console.log('\n' + (failures === 0 ? 'ALL PASS' : failures + ' FAILURE(S)'));
    process.exit(failures === 0 ? 0 : 1);
})();
