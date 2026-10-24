/**
 * Pure, dependency-free pit-rendering helpers for the Ayo (Oware) board.
 * Loaded as a plain global script (window.AyoBoard), shared by:
 *   - the live table page (resources/views/templates/nocturne/user/table_games/show.blade.php)
 *   - the Playwright bounding-box regression test (tests/frontend/ayo-pit-size.spec.js)
 * Keeping this logic in one file means the test exercises the exact code the page ships,
 * not a reimplementation of it.
 *
 * Seed positions are a deterministic phyllotaxis (sunflower) packing - a pure function of
 * (pitId, seedIndex, count), never Math.random() - so the same board state always renders
 * identical seed positions, and positions only shift smoothly as the count itself changes.
 * The pit's own size never depends on count: seeds are absolutely positioned inside a fixed
 * aspect-ratio:1 circle (see the .board-pit CSS), never affecting layout.
 */
(function (global) {
    'use strict';

    var VISUAL_CAP = 18; // above this many seeds, draw only the cap and rely on the count badge
    var GOLDEN_ANGLE = 137.5077640500378; // degrees

    function seedPosition(pitId, index, count) {
        var capped = Math.min(count, VISUAL_CAP);
        var angleOffset = (Number(pitId) * 47) % 360; // so same-count pits don't look identical
        var angle = (index * GOLDEN_ANGLE + angleOffset) * Math.PI / 180;
        var radius = capped <= 1 ? 0 : Math.sqrt((index + 0.5) / capped);
        var r = radius * 36; // max 36% out from center, leaving room for the seed + pit border
        return {
            xPct: 50 + r * Math.cos(angle),
            yPct: 50 + r * Math.sin(angle)
        };
    }

    function seedScale(count) {
        var capped = Math.min(count, VISUAL_CAP);
        // 1.0 at a single seed, shrinking down to a still-legible minimum at the visual cap.
        return Math.max(0.46, 1 - (capped / VISUAL_CAP) * 0.54);
    }

    // doc: the Document to create elements in (always `document` in practice; passed explicitly
    // so the Playwright test can call this against a plain loaded page without any extra glue).
    function buildPitElement(doc, pitId, count) {
        var pit = doc.createElement('button');
        pit.type = 'button';
        pit.className = 'board-pit';
        pit.dataset.pit = String(pitId);

        var field = doc.createElement('span');
        field.className = 'board-pit__field';
        var shown = Math.min(count, VISUAL_CAP);
        var scale = seedScale(count);
        for (var i = 0; i < shown; i++) {
            var pos = seedPosition(pitId, i, count);
            var seed = doc.createElement('i');
            seed.className = 'board-pit__seed';
            seed.style.left = pos.xPct + '%';
            seed.style.top = pos.yPct + '%';
            seed.style.setProperty('--seed-scale', String(scale));
            field.appendChild(seed);
        }

        var badge = doc.createElement('span');
        badge.className = 'board-pit__count';
        badge.textContent = String(count);

        pit.appendChild(field);
        pit.appendChild(badge);
        return pit;
    }

    global.AyoBoard = {
        VISUAL_CAP: VISUAL_CAP,
        seedPosition: seedPosition,
        seedScale: seedScale,
        buildPitElement: buildPitElement
    };
})(typeof window !== 'undefined' ? window : globalThis);
