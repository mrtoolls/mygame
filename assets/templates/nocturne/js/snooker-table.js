/**
 * Snooker client: canvas rendering, playback of server-computed physics keyframes, and the
 * aim/power/spin/shoot controls. The server is the only source of truth - this file never
 * computes a collision, a foul, or a score; it only draws what TableGameService::snapshot()
 * already decided and sends back a {angle, power, spinX, spinY[, cue_placement][, nominate]}
 * action object exactly like every other table game's move().
 *
 * Mirrors app/Games/TableGames/Snooker/SnookerConfig.php's geometry constants (table/ball/pocket
 * sizes) so the canvas draws the same proportions the server actually simulated on.
 */
window.SnookerTable = (function () {
    'use strict';

    // ---- Geometry (must match SnookerConfig.php) -----------------------------------------------
    const TABLE_W = 3560, TABLE_H = 1780, BALL_R = 26.25;
    const BAULK_X = 737, D_RADIUS = 292;
    const POCKET_CORNER_R = 72, POCKET_MIDDLE_R = 82;
    const POCKETS = [
        { name: 'top_left', x: 0, y: TABLE_H, r: POCKET_CORNER_R },
        { name: 'top_middle', x: TABLE_W / 2, y: TABLE_H, r: POCKET_MIDDLE_R },
        { name: 'top_right', x: TABLE_W, y: TABLE_H, r: POCKET_CORNER_R },
        { name: 'bottom_right', x: TABLE_W, y: 0, r: POCKET_CORNER_R },
        { name: 'bottom_middle', x: TABLE_W / 2, y: 0, r: POCKET_MIDDLE_R },
        { name: 'bottom_left', x: 0, y: 0, r: POCKET_CORNER_R },
    ];
    const BALL_COLOURS = {
        cue: '#f5f3ea', yellow: '#f3c623', green: '#1f8a4c', brown: '#7a4a2b',
        blue: '#1f5fbf', pink: '#e8789a', black: '#161616',
    };
    const redColour = '#cc2b2b';
    const colourOf = (id) => id.startsWith('red') ? redColour : (BALL_COLOURS[id] || '#999');

    // ---- Module-level per-table state -----------------------------------------------------------
    let canvas, ctx, root, boardEl, hudEl, controlsEl;
    let ctxApi = null; // {board, send, userId, latest, root} from show.blade.php
    let positions = {}; // current (possibly mid-animation) ball positions, id => {x,y}
    let lastRenderedVersion = null;
    let lastShotCache = null; // the most recent last_move payload, for "Replay last shot"
    let playing = false;
    let strikeAnimating = false; // true only during the brief cue pull-back/strike flourish before keyframe playback
    // power starts at 0 deliberately - the shoot button stays disabled until the player actually
    // performs the pull-back gesture (or drags the aim line, which also sets power - see
    // onPointerUp), so a shot is always a deliberate action, never an accidental default-power tap.
    let aim = { angle: -Math.PI / 2, power: 0, spinX: 0, spinY: 0 };
    let placingCue = false;
    let cuePlacement = null;
    let dragState = null; // active pointer drag: 'aim' | 'power' | 'spin' | 'cue'
    let muted = false;
    let audioCtx = null;
    const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    try { muted = localStorage.getItem('snookerMuted') === '1'; } catch (_) {}

    // ---- Public entry point ----------------------------------------------------------------------
    // Returns true while a shot is actively animating (strike flourish or keyframe playback) -
    // show.blade.php's gameState() checks this before calling render() again, the same way it
    // already does for Ludo's dice roll and Oware's sow animation, so an in-flight poll update
    // can't tear the board down mid-animation (see the DOM-rebuild note in render() below).
    function isAnimating() { return playing || strikeAnimating; }

    function render(data, active, context) {
        ctxApi = context;
        const state = data.state || {};
        // renderBoard() in show.blade.php unconditionally calls board.replaceChildren() before
        // dispatching here on EVERY call, including every real poll update - not just the first
        // one. A one-time `if (!built)` build assumed the DOM it created would persist, but it
        // gets wiped out from under it on the very next update, leaving #table-board empty with
        // no code path that ever rebuilds it (exactly the "board goes blank, needs a reload"
        // report - reloading is what reset `built` back to false). Checking that the DOM we
        // actually built is still attached - not a one-shot flag - is what Oware's own render
        // effectively does too, just by rebuilding unconditionally every single call instead.
        if (!root || !root.isConnected) buildDom(context.board);
        syncHud(data, state, active);
        // Resolves whether this is a FRESH "ball in hand" (clearing any stale placement) before
        // snapToState()/drawFrame() below read cuePlacement - must run first, not after.
        updateControlsAvailability(active, state);

        const lastMove = data.last_move;
        const isNewShot = lastMove && typeof lastMove.version === 'number' && lastMove.version !== lastRenderedVersion;

        if (isNewShot) {
            lastRenderedVersion = lastMove.version;
            lastShotCache = lastMove;
            showEventTicker(lastMove, state);
            if (!reducedMotion && lastMove.keyframes && lastMove.keyframes.length) {
                playKeyframes(lastMove.keyframes, lastMove.events || [], state);
            } else {
                snapToState(state);
                drawFrame();
            }
        } else if (!playing) {
            snapToState(state);
            drawFrame();
        }
    }

    // ---- DOM construction --------------------------------------------------------------------------
    function buildDom(boardMount) {
        boardEl = boardMount;
        boardEl.innerHTML = '';
        root = document.createElement('div');
        root.className = 'sk-root';

        hudEl = document.createElement('div');
        hudEl.className = 'sk-hud';
        hudEl.innerHTML = `
            <div class="sk-hud__row">
                <div class="sk-score" data-seat="0"><span class="sk-score__name" data-name="0"></span><span class="sk-score__value" data-score="0">0</span></div>
                <div class="sk-mid">
                    <span class="sk-frame" data-frame>Frame 1</span>
                    <span class="sk-break" data-break hidden>Break <b data-break-value>0</b></span>
                    <span class="sk-ballon" data-ballon></span>
                </div>
                <div class="sk-score sk-score--right" data-seat="1"><span class="sk-score__value" data-score="1">0</span><span class="sk-score__name" data-name="1"></span></div>
            </div>
            <div class="sk-hud__row sk-hud__row--sub">
                <span class="sk-timer" data-timer hidden><i class="ph-fill ph-clock-countdown"></i> <b data-timer-value>45</b>s</span>
                <span class="sk-ticker" data-ticker aria-live="polite"></span>
                <div class="sk-hud__tools">
                    <button type="button" class="sk-icon-btn" data-replay title="Replay last shot"><i class="ph-bold ph-arrow-clockwise"></i></button>
                    <button type="button" class="sk-icon-btn" data-mute title="Mute"><i class="ph-fill ${muted ? 'ph-speaker-x' : 'ph-speaker-high'}"></i></button>
                </div>
            </div>`;

        const stage = document.createElement('div');
        stage.className = 'sk-stage';
        canvas = document.createElement('canvas');
        canvas.className = 'sk-canvas';
        canvas.width = 900; canvas.height = 450; // internal resolution, scaled via CSS - keeps draw math simple at a fixed TABLE_W:TABLE_H (2:1) ratio
        ctx = canvas.getContext('2d');
        stage.appendChild(canvas);

        const aimDial = document.createElement('div');
        aimDial.className = 'sk-spin-dial';
        aimDial.innerHTML = '<div class="sk-spin-dial__dot" data-spin-dot></div>';
        stage.appendChild(aimDial);

        root.appendChild(hudEl);
        root.appendChild(stage);

        controlsEl = document.createElement('div');
        controlsEl.className = 'sk-controls';
        controlsEl.innerHTML = `
            <div class="sk-power">
                <div class="sk-power__track"><div class="sk-power__fill" data-power-fill></div></div>
                <span class="sk-power__label">Pull back on the table to set power, release to shoot</span>
            </div>
            <div class="sk-actions">
                <button type="button" class="sk-shoot" data-shoot disabled>Shoot</button>
            </div>
            <div class="sk-nominate" data-nominate hidden>
                <p>Potted a red - nominate your next colour:</p>
                <div class="sk-nominate__grid" data-nominate-grid></div>
            </div>`;
        root.appendChild(controlsEl);

        boardEl.appendChild(root);

        wireControls();
        window.addEventListener('resize', layoutOrientation);
        layoutOrientation();

        hudEl.querySelector('[data-mute]').addEventListener('click', toggleMute);
        hudEl.querySelector('[data-replay]').addEventListener('click', () => {
            if (lastShotCache && lastShotCache.keyframes && lastShotCache.keyframes.length) {
                playKeyframes(lastShotCache.keyframes, lastShotCache.events || [], ctxApi.latest?.state || {});
            }
        });
    }

    // ---- Orientation: rotate the stage in portrait so the table's long side runs vertically -----
    function layoutOrientation() {
        if (!root) return;
        const portrait = window.innerHeight > window.innerWidth;
        root.classList.toggle('sk-root--portrait', portrait);
        const stage = root.querySelector('.sk-stage');
        if (!stage) return;
        if (!portrait) {
            stage.style.height = ''; canvas.style.width = ''; canvas.style.height = '';
            return;
        }
        // Pure-CSS rotation (transform: rotate(90deg)) leaves the element's LAYOUT box at its
        // original (landscape) footprint even though it now visually reads as portrait-shaped -
        // the surrounding flow never reserves the right space for that, so the rotated table
        // overflowed off the right edge of the screen. Sizing it explicitly in JS instead: the
        // stage reserves a portrait-shaped box, and the canvas (rotated around its own centre)
        // is sized so that once rotated, it exactly fills that box.
        //
        // Solve width and height TOGETHER, not height-first: the table's on-screen box keeps a
        // fixed 2:1 (height:width) ratio once rotated, so start from "use the full available
        // screen width" and only shrink toward the height budget if that would actually overflow
        // it. Deriving width from a height-first value (the previous approach) left the table far
        // narrower than the screen on any tall phone, where the height budget binds before the
        // width budget does - wasting most of the screen's width.
        const widthBudget = Math.min(window.innerWidth - 24, 480); // the visual WIDTH of the rotated table on screen, capped to the viewport
        const heightBudget = window.innerHeight * 0.64; // the visual HEIGHT budget, capped so it doesn't crowd out the HUD/controls
        let visualWidth = widthBudget;
        let visualHeight = visualWidth * 2; // fixed 2:1 table ratio
        if (visualHeight > heightBudget) { visualHeight = heightBudget; visualWidth = visualHeight / 2; }
        stage.style.height = visualHeight + 'px';
        // The canvas itself is sized and rotated in its OWN (unrotated) coordinate space - once
        // rotated 90deg about its centre, its pre-rotation width becomes the visual height and
        // vice versa, so the pre-rotation box is the swap of the on-screen box solved above.
        canvas.style.width = visualHeight + 'px';
        canvas.style.height = visualWidth + 'px';
    }

    // ---- HUD / scoreboard --------------------------------------------------------------------------
    function syncHud(data, state, active) {
        const players = data.players || [];
        const names = [players[0]?.name || 'Player 1', players[1]?.name || 'Player 2'];
        hudEl.querySelector('[data-name="0"]').textContent = names[0];
        hudEl.querySelector('[data-name="1"]').textContent = names[1];
        hudEl.querySelector('[data-score="0"]').textContent = state.scores?.[0] ?? 0;
        hudEl.querySelector('[data-score="1"]').textContent = state.scores?.[1] ?? 0;
        hudEl.querySelector('[data-frame]').textContent = `Frame ${state.frame_number || 1} of ${state.frames_target || 1} · ${state.frames_won?.[0] ?? 0}-${state.frames_won?.[1] ?? 0}`;
        const breakEl = hudEl.querySelector('[data-break]');
        if (state.current_break > 0) { breakEl.hidden = false; hudEl.querySelector('[data-break-value]').textContent = state.current_break; }
        else breakEl.hidden = true;
        const ballOnLabel = state.ball_on === 'red' ? 'Red' : state.ball_on === 'any' ? 'Nominate a colour' : (state.ball_on ? state.ball_on[0].toUpperCase() + state.ball_on.slice(1) : '');
        hudEl.querySelector('[data-ballon]').textContent = state.free_ball_available ? 'Free ball available' : (ballOnLabel ? 'On: ' + ballOnLabel : '');

        [0, 1].forEach(seat => hudEl.querySelector(`.sk-score[data-seat="${seat}"]`).classList.toggle('is-turn', state.turn === seat));

        const timerEl = hudEl.querySelector('[data-timer]');
        if (active && data.turn_deadline_at) {
            timerEl.hidden = false;
            tickTimer(data.turn_deadline_at);
        } else {
            timerEl.hidden = true;
        }

        const nominateBox = controlsEl?.querySelector('[data-nominate]');
        if (nominateBox) {
            const show = active && state.awaiting_nomination;
            nominateBox.hidden = !show;
            if (show) buildNominateGrid();
        }
    }

    let timerInterval = null;
    function tickTimer(deadlineIso) {
        if (timerInterval) clearInterval(timerInterval);
        const valueEl = hudEl.querySelector('[data-timer-value]');
        const deadline = new Date(deadlineIso).getTime();
        const tick = () => {
            const remaining = Math.max(0, Math.round((deadline - Date.now()) / 1000));
            valueEl.textContent = remaining;
            valueEl.parentElement.classList.toggle('is-warning', remaining <= 10);
            if (remaining <= 0) clearInterval(timerInterval);
        };
        tick();
        timerInterval = setInterval(tick, 1000);
    }

    function showEventTicker(lastMove, state) {
        const el = hudEl.querySelector('[data-ticker]');
        if (!el) return;
        let text = '';
        if (lastMove.frame_result) {
            text = `Frame ${lastMove.frame_result.frame} to ${lastMove.frame_result.winner_seat === 0 ? 'Player 1' : 'Player 2'}`;
        } else if (lastMove.foul) {
            const reasons = { potted_cue_ball: 'potted the cue ball', no_contact: 'made no contact', wrong_ball_first: 'hit the wrong ball first', potted_wrong_ball: 'potted the wrong ball' };
            text = `Foul (${lastMove.penalty} pts) - ${reasons[lastMove.foul_reason] || 'a foul occurred'}`;
        } else if (lastMove.points) {
            text = `Potted for ${lastMove.points}`;
        }
        if (state.free_ball_available) text += (text ? ' · ' : '') + 'Free ball available';
        el.textContent = text;
    }

    // ---- Nomination UI -----------------------------------------------------------------------------
    function buildNominateGrid() {
        const grid = controlsEl.querySelector('[data-nominate-grid]');
        if (grid.dataset.built) return;
        grid.dataset.built = '1';
        ['yellow', 'green', 'brown', 'blue', 'pink', 'black'].forEach(colour => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sk-nominate__chip';
            btn.style.setProperty('--chip-colour', BALL_COLOURS[colour]);
            btn.textContent = colour[0].toUpperCase() + colour.slice(1);
            btn.addEventListener('click', () => { pendingNomination = colour; btn.closest('[data-nominate]').classList.add('is-selected'); Array.from(grid.children).forEach(c => c.classList.toggle('is-active', c === btn)); });
            grid.appendChild(btn);
        });
    }
    let pendingNomination = null;

    // ---- Rendering: table + balls --------------------------------------------------------------------
    function snapToState(state) {
        positions = {};
        Object.entries(state.balls || {}).forEach(([id, ball]) => {
            if (ball.potted) return;
            // While the cue ball is "in hand", its stored position is just wherever it last was
            // (potted, or sitting from before a foul) - not a real on-table position - so it's
            // only drawn once the player has actually placed it (see updateControlsAvailability).
            if (id === 'cue' && state.cue_ball_in_hand && !cuePlacement) return;
            positions[id] = { x: ball.x, y: ball.y };
        });
        if (state.cue_ball_in_hand && cuePlacement) positions.cue = cuePlacement;
    }

    function toCanvas(x, y) {
        const pad = 40;
        const scaleX = (canvas.width - pad * 2) / TABLE_W;
        const scaleY = (canvas.height - pad * 2) / TABLE_H;
        const scale = Math.min(scaleX, scaleY);
        const offX = (canvas.width - TABLE_W * scale) / 2;
        const offY = (canvas.height - TABLE_H * scale) / 2;
        return { cx: offX + x * scale, cy: canvas.height - (offY + y * scale), scale, offX, offY };
    }

    function drawFrame() {
        if (!ctx) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const ref = toCanvas(0, 0);
        const scale = ref.scale;
        const tableX = ref.offX, tableY = canvas.height - ref.offY - TABLE_H * scale;

        // Rail (wood-toned gradient) then cloth (green, radial sheen for a felt-like look).
        const railPad = 26;
        const railGrad = ctx.createLinearGradient(0, tableY - railPad, 0, tableY + TABLE_H * scale + railPad);
        railGrad.addColorStop(0, '#4a2e18'); railGrad.addColorStop(0.08, '#6b4322'); railGrad.addColorStop(0.5, '#523018'); railGrad.addColorStop(0.92, '#3a2414'); railGrad.addColorStop(1, '#241408');
        ctx.fillStyle = railGrad;
        roundRect(ctx, tableX - railPad, tableY - railPad, TABLE_W * scale + railPad * 2, TABLE_H * scale + railPad * 2, 18);
        ctx.fill();

        const clothGrad = ctx.createRadialGradient(tableX + TABLE_W * scale * 0.5, tableY + TABLE_H * scale * 0.4, 20, tableX + TABLE_W * scale * 0.5, tableY + TABLE_H * scale * 0.5, TABLE_W * scale * 0.6);
        clothGrad.addColorStop(0, '#118249'); clothGrad.addColorStop(0.65, '#0f7a44'); clothGrad.addColorStop(1, '#0a5733');
        ctx.fillStyle = clothGrad;
        ctx.fillRect(tableX, tableY, TABLE_W * scale, TABLE_H * scale);

        // Inner rail lip: a thin dark seam then a pale highlight where cushion meets cloth - gives
        // the rail actual thickness/depth instead of reading as a flat painted border.
        ctx.strokeStyle = 'rgba(0,0,0,.45)'; ctx.lineWidth = Math.max(1, 2 * scale);
        ctx.strokeRect(tableX, tableY, TABLE_W * scale, TABLE_H * scale);
        ctx.strokeStyle = 'rgba(255,255,255,.06)'; ctx.lineWidth = Math.max(1, 1 * scale);
        ctx.strokeRect(tableX + 1.5, tableY + 1.5, TABLE_W * scale - 3, TABLE_H * scale - 3);

        // Baulk line + D.
        const baulk = toCanvas(BAULK_X, 0);
        ctx.strokeStyle = 'rgba(255,255,255,.35)'; ctx.lineWidth = 1.4;
        ctx.beginPath(); ctx.moveTo(baulk.cx, tableY); ctx.lineTo(baulk.cx, tableY + TABLE_H * scale); ctx.stroke();
        ctx.beginPath(); ctx.arc(baulk.cx, toCanvas(BAULK_X, TABLE_H / 2).cy, D_RADIUS * scale, Math.PI / 2, Math.PI * 1.5); ctx.stroke();

        // Pockets: a radial falloff into black (instead of a flat disc) reads as a real hole with
        // depth rather than a painted circle, plus a thin rim highlight where the jaw catches light.
        POCKETS.forEach(p => {
            const c = toCanvas(p.x, p.y);
            const pr = p.r * scale;
            const pocketGrad = ctx.createRadialGradient(c.cx, c.cy, pr * 0.15, c.cx, c.cy, pr);
            pocketGrad.addColorStop(0, '#000000'); pocketGrad.addColorStop(0.75, '#060f09'); pocketGrad.addColorStop(1, '#13231a');
            ctx.fillStyle = pocketGrad;
            ctx.beginPath(); ctx.arc(c.cx, c.cy, pr, 0, Math.PI * 2); ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,.08)'; ctx.lineWidth = Math.max(1, 1.2 * scale);
            ctx.beginPath(); ctx.arc(c.cx, c.cy, pr - 1, 0, Math.PI * 2); ctx.stroke();
        });

        // Placement preview (cue-ball-in-hand).
        if (placingCue && cuePlacement) {
            const c = toCanvas(cuePlacement.x, cuePlacement.y);
            ctx.strokeStyle = 'rgba(255,255,255,.6)'; ctx.setLineDash([4, 4]);
            ctx.beginPath(); ctx.arc(c.cx, c.cy, BALL_R * scale, 0, Math.PI * 2); ctx.stroke();
            ctx.setLineDash([]);
        }

        // Aim guide line from the cue ball (competitive: short stub at the cue ball only; full
        // length only ever reaches the cue ball's own edge, matching "no aim line beyond the cue
        // ball" - the casual aid, if enabled server-side, is drawn longer).
        const cueBall = positions.cue;
        if (cueBall && !playing && dragState !== 'cue') {
            const c = toCanvas(cueBall.x, cueBall.y);
            const aidLevel = (ctxApi?.latest?.state?.aid_level) || 'competitive';
            const len = (aidLevel === 'casual' ? 140 : 34) * scale;
            ctx.strokeStyle = 'rgba(255,255,255,.55)'; ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(c.cx, c.cy);
            ctx.lineTo(c.cx + Math.cos(aim.angle) * len, c.cy - Math.sin(aim.angle) * len);
            ctx.stroke();
        }

        // The cue stick itself: only drawn for the player whose turn it is (nobody else can drag
        // it, so showing it otherwise would just be a stale, non-interactive leftover), and only
        // while idle/aiming - playStrikeAnimation()/the strike-animation path draws its own stick
        // with its own pull-back state during a shot, so the two never run at the same time.
        if (cueBall && !playing && !strikeAnimating && dragState !== 'cue' && isMyTurn()) {
            drawCueStick(cueBall, aim.angle, aim.power * STICK_MAX_PULLBACK_MM, 1);
        }

        // Balls, drawn back-to-front by y for a touch of depth (no real 3D, just paint order).
        Object.entries(positions).sort((a, b) => a[1].y - b[1].y).forEach(([id, pos]) => {
            drawBall(toCanvas(pos.x, pos.y), colourOf(id), id === 'cue');
        });
    }

    // Draws a tapered wooden cue stick: tip (with a pale ferrule cap) pulled back from the cue
    // ball by pullBackMm along the direction OPPOSITE the shot's travel angle, butt further back
    // still. alpha fades the stick in/out during the strike flourish.
    const STICK_MAX_PULLBACK_MM = 260;
    function drawCueStick(cueWorldPos, angle, pullBackMm, alpha) {
        const c = toCanvas(cueWorldPos.x, cueWorldPos.y);
        const dirX = Math.cos(angle), dirY = -Math.sin(angle); // ball-travel direction, in canvas space
        const perpX = -dirY, perpY = dirX; // perpendicular, for the stick's width
        const tipGapPx = (6 + pullBackMm) * c.scale;
        const tipX = c.cx - dirX * tipGapPx, tipY = c.cy - dirY * tipGapPx;
        // A real cue is roughly 55x the ball's diameter and tapers from a wide butt to a narrow
        // tip - a uniform-width stroked line (the previous approach) reads as a thin stray line
        // rather than a cue, especially at typical canvas scales. Drawing it as a tapered
        // polygon, long enough to visibly run off the near edge of the table the way a real cue
        // does when aiming, is what actually makes it read as a stick.
        const lengthPx = Math.min(1500, BALL_R * 30) * c.scale;
        const tipWidth = Math.max(1.8, BALL_R * 0.16 * c.scale);
        const buttWidth = Math.max(4, BALL_R * 0.5 * c.scale);
        const buttX = tipX - dirX * lengthPx, buttY = tipY - dirY * lengthPx;

        ctx.save();
        ctx.globalAlpha = alpha;
        // Soft shadow on the cloth, offset slightly toward the viewer's implied light source.
        ctx.strokeStyle = 'rgba(0,0,0,.35)';
        ctx.lineWidth = buttWidth * 0.9;
        ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(tipX + 3, tipY + 4); ctx.lineTo(buttX + 3, buttY + 4); ctx.stroke();

        const grad = ctx.createLinearGradient(tipX, tipY, buttX, buttY);
        grad.addColorStop(0, '#f1dfb2'); grad.addColorStop(0.1, '#fdfdfb'); grad.addColorStop(0.2, '#dcb97c');
        grad.addColorStop(0.75, '#a9713c'); grad.addColorStop(1, '#3f2414');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.moveTo(tipX + perpX * tipWidth, tipY + perpY * tipWidth);
        ctx.lineTo(buttX + perpX * buttWidth, buttY + perpY * buttWidth);
        ctx.lineTo(buttX - perpX * buttWidth, buttY - perpY * buttWidth);
        ctx.lineTo(tipX - perpX * tipWidth, tipY - perpY * tipWidth);
        ctx.closePath();
        ctx.fill();
        ctx.strokeStyle = 'rgba(0,0,0,.25)'; ctx.lineWidth = Math.max(0.6, 0.8 * c.scale); ctx.stroke();
        // A thin highlight stripe down the shaft's lit edge sells the cylindrical/wood look.
        ctx.strokeStyle = 'rgba(255,255,255,.35)'; ctx.lineWidth = Math.max(0.5, tipWidth * 0.4);
        ctx.beginPath();
        ctx.moveTo(tipX + perpX * tipWidth * 0.3, tipY + perpY * tipWidth * 0.3);
        ctx.lineTo(buttX + perpX * buttWidth * 0.3, buttY + perpY * buttWidth * 0.3);
        ctx.stroke();
        // Ferrule: the pale cap right at the tip, same visual language a real cue uses.
        ctx.fillStyle = '#f2f4f8';
        ctx.beginPath(); ctx.arc(tipX, tipY, Math.max(2.4, tipWidth * 1.3), 0, Math.PI * 2); ctx.fill();
        ctx.restore();
    }

    function drawBall(c, colour, isCue) {
        const r = BALL_R * c.scale;
        ctx.save();
        // Soft contact shadow, a touch bigger/softer than before for more weight/separation from
        // the cloth - a single hard-edged ellipse read as "sticker on felt" rather than a sphere
        // actually resting on it.
        ctx.beginPath(); ctx.ellipse(c.cx, c.cy + r * 0.92, r * 0.95, r * 0.4, 0, 0, Math.PI * 2);
        const shadowGrad = ctx.createRadialGradient(c.cx, c.cy + r * 0.92, 0, c.cx, c.cy + r * 0.92, r * 0.95);
        shadowGrad.addColorStop(0, 'rgba(0,0,0,.4)'); shadowGrad.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = shadowGrad; ctx.fill();

        // Base sphere shading, lit from the upper-left.
        const grad = ctx.createRadialGradient(c.cx - r * 0.35, c.cy - r * 0.4, r * 0.1, c.cx, c.cy, r);
        grad.addColorStop(0, lighten(colour, 0.55));
        grad.addColorStop(0.55, colour);
        grad.addColorStop(1, darken(colour, 0.35));
        ctx.beginPath(); ctx.arc(c.cx, c.cy, r, 0, Math.PI * 2);
        ctx.fillStyle = grad; ctx.fill();
        if (isCue) { ctx.strokeStyle = 'rgba(0,0,0,.15)'; ctx.lineWidth = 1; ctx.stroke(); }

        // Rim light along the lower-right edge - light bouncing up off the cloth is what makes a
        // glossy sphere read as sitting IN a lit scene rather than a flat painted circle.
        ctx.save();
        ctx.beginPath(); ctx.arc(c.cx, c.cy, r, 0, Math.PI * 2); ctx.clip();
        const rim = ctx.createRadialGradient(c.cx + r * 0.45, c.cy + r * 0.5, r * 0.6, c.cx + r * 0.45, c.cy + r * 0.5, r * 1.05);
        rim.addColorStop(0, 'rgba(255,255,255,0)'); rim.addColorStop(0.85, 'rgba(255,255,255,0)'); rim.addColorStop(1, lightenAlpha(colour, 0.22));
        ctx.fillStyle = rim; ctx.beginPath(); ctx.arc(c.cx, c.cy, r, 0, Math.PI * 2); ctx.fill();
        ctx.restore();

        // Primary specular highlight (the light source itself) plus a small secondary sparkle -
        // two highlights of different sizes read as "glossy" far more than one ever does alone.
        ctx.beginPath(); ctx.arc(c.cx - r * 0.32, c.cy - r * 0.35, r * 0.28, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255,255,255,.55)'; ctx.fill();
        ctx.beginPath(); ctx.arc(c.cx - r * 0.1, c.cy - r * 0.08, r * 0.09, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255,255,255,.35)'; ctx.fill();
        ctx.restore();
    }
    function lightenAlpha(hex, alpha) {
        const a = hexToRgb(hex), b = { r: 255, g: 255, b: 255 }, amt = 0.6;
        const r = Math.round(a.r + (b.r - a.r) * amt), g = Math.round(a.g + (b.g - a.g) * amt), bl = Math.round(a.b + (b.b - a.b) * amt);
        return `rgba(${r},${g},${bl},${alpha})`;
    }

    function roundRect(c, x, y, w, h, r) {
        c.beginPath(); c.moveTo(x + r, y);
        c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r);
        c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath();
    }
    function lighten(hex, amt) { return mix(hex, '#ffffff', amt); }
    function darken(hex, amt) { return mix(hex, '#000000', amt); }
    function mix(hex, withHex, amt) {
        const a = hexToRgb(hex), b = hexToRgb(withHex);
        const r = Math.round(a.r + (b.r - a.r) * amt), g = Math.round(a.g + (b.g - a.g) * amt), bl = Math.round(a.b + (b.b - a.b) * amt);
        return `rgb(${r},${g},${bl})`;
    }
    function hexToRgb(hex) {
        const v = parseInt(hex.replace('#', ''), 16);
        return { r: (v >> 16) & 255, g: (v >> 8) & 255, b: v & 255 };
    }

    // ---- Keyframe playback --------------------------------------------------------------------------
    // Every shot replay (natural or via the "Replay last shot" button) opens with a brief cue
    // pull-back/strike flourish before the physics keyframes play - the server doesn't echo the
    // original angle/power, so the travel angle is derived from the first real displacement the
    // cue ball shows in its own keyframes (the authoritative source both players already share,
    // so their local flourishes end up pointing the same way without needing a new field on the
    // wire), and the pull-back distance is purely cosmetic.
    function playKeyframes(keyframes, events, finalState) {
        const cueStart = positions.cue;
        let angle = aim.angle;
        if (cueStart && keyframes && keyframes.length) {
            for (let i = 0; i < Math.min(keyframes.length, 20); i++) {
                const xy = keyframes[i].cue;
                if (!xy) continue;
                const dx = xy[0] / 10 - cueStart.x, dy = xy[1] / 10 - cueStart.y;
                if (dx * dx + dy * dy > 1) { angle = Math.atan2(-dy, dx); break; } // > ~1mm of real movement
            }
        }
        if (!cueStart || strikeAnimating) { startPlayback(); return; }
        playStrikeAnimation(cueStart, angle, startPlayback);

        function startPlayback() {
        playing = true;
        let frameIndex = 0;
        let eventCursor = 0;
        const fps = 60;
        const frameMs = 1000 / fps;
        let lastTick = performance.now();
        let acc = 0;

        function step(now) {
            if (!playing) return;
            acc += now - lastTick;
            lastTick = now;
            while (acc >= frameMs && frameIndex < keyframes.length) {
                applyKeyframe(keyframes[frameIndex]);
                // Fire sound/haptics for any physics event whose approximate timing has arrived
                // (events don't carry an exact frame index from the server, so this plays them in
                // order, spaced across the frames that remain - close enough for a believable cue,
                // not meant to be sample-accurate).
                const eventsPerFrame = events.length / Math.max(1, keyframes.length);
                const dueEvents = Math.floor((frameIndex + 1) * eventsPerFrame);
                while (eventCursor < dueEvents && eventCursor < events.length) {
                    fireEventFeedback(events[eventCursor]);
                    eventCursor++;
                }
                frameIndex++;
                acc -= frameMs;
            }
            drawFrame();
            if (frameIndex < keyframes.length) {
                requestAnimationFrame(step);
            } else {
                while (eventCursor < events.length) { fireEventFeedback(events[eventCursor]); eventCursor++; }
                playing = false;
                snapToState(finalState);
                drawFrame();
            }
        }
        requestAnimationFrame((t) => { lastTick = t; requestAnimationFrame(step); });
        } // end startPlayback
    }

    // Brief (~230ms) cue pull-back/forward-strike flourish, purely cosmetic: pulls the stick back
    // from the cue ball, then snaps it forward to "contact" (playing the strike sound at that
    // instant), then hands off to onDone (the real physics keyframe playback).
    function playStrikeAnimation(cueWorldPos, angle, onDone) {
        strikeAnimating = true;
        const pullMs = 130, strikeMs = 90;
        const totalMs = pullMs + strikeMs;
        const startedAt = performance.now();
        let struck = false;
        function tick(now) {
            const t = now - startedAt;
            let pullBack;
            if (t < pullMs) {
                pullBack = STICK_MAX_PULLBACK_MM * easeOutQuad(t / pullMs);
            } else {
                const strikeT = Math.min(1, (t - pullMs) / strikeMs);
                pullBack = STICK_MAX_PULLBACK_MM * (1 - easeInQuad(strikeT));
                if (!struck && strikeT >= 0.92) { struck = true; playSound('strike'); vibrate([12]); }
            }
            drawFrame();
            drawCueStick(cueWorldPos, angle, pullBack, 1);
            if (t < totalMs) {
                requestAnimationFrame(tick);
            } else {
                strikeAnimating = false;
                onDone();
            }
        }
        requestAnimationFrame(tick);
    }
    function easeOutQuad(t) { return 1 - (1 - t) * (1 - t); }
    function easeInQuad(t) { return t * t; }

    function applyKeyframe(frame) {
        Object.entries(frame).forEach(([id, xy]) => {
            positions[id] = { x: xy[0] / 10, y: xy[1] / 10 }; // quantized tenths-of-mm back to mm
        });
        // Any ball not present in this frame has already been potted - stop drawing it.
        Object.keys(positions).forEach(id => { if (!(id in frame)) delete positions[id]; });
    }

    function fireEventFeedback(event) {
        // A touch of random intensity variance so a run of several contacts/cushions in one shot
        // doesn't sound like the exact same sample looping - no speed data comes from the server
        // per event, so this is cosmetic variety, not a real physics-driven loudness cue.
        const amt = 0.75 + Math.random() * 0.25;
        if (event.type === 'contact') playSound('contact', amt);
        else if (event.type === 'cushion') playSound('cushion', amt);
        else if (event.type === 'pot') { playSound('pot', 1); vibrate([30, 20, 30]); }
    }

    // ---- Controls: aim (drag), power (pull-back), spin (dial), shoot, cue placement --------------
    function wireControls() {
        const shootBtn = controlsEl.querySelector('[data-shoot]');
        shootBtn.addEventListener('click', submitShot);

        canvas.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', onPointerUp);
        canvas.style.touchAction = 'none'; // block page scroll while aiming/placing on the table

        const dial = root.querySelector('.sk-spin-dial');
        dial.addEventListener('pointerdown', (e) => { dragState = 'spin'; updateSpinFromEvent(e, dial); e.preventDefault(); });
    }

    function onPointerDown(e) {
        if (!ctxApi || !isMyTurn()) return;
        const state = ctxApi.latest?.state || {};
        // Ball-in-hand stays true (server-side) until the shot is actually submitted, so it can't
        // be used alone to mean "every tap repositions the cue ball" - otherwise the player could
        // never switch to aiming after their first placement tap. Instead: a tap starting INSIDE
        // the D repositions it (even after an initial placement, so they can still adjust it);
        // a tap starting anywhere else aims, using whatever placement (or last known position) is
        // already set.
        if (state.cue_ball_in_hand && (!cuePlacement || isInsideD(worldPoint(e)))) {
            dragState = 'cue';
            updateCuePlacementFromEvent(e);
        } else {
            dragState = 'aim';
            updateAimFromEvent(e, false);
        }
        e.preventDefault();
    }

    function isInsideD(w) {
        const dx = w.x - BAULK_X, dy = w.y - TABLE_H / 2;
        return w.x <= BAULK_X + 10 && (dx * dx + dy * dy) <= (D_RADIUS + 20) * (D_RADIUS + 20);
    }

    function onPointerMove(e) {
        if (!dragState) return;
        if (dragState === 'aim') updateAimFromEvent(e, false);
        else if (dragState === 'power') updatePowerFromEvent(e);
        else if (dragState === 'spin') updateSpinFromEvent(e, root.querySelector('.sk-spin-dial'));
        else if (dragState === 'cue') updateCuePlacementFromEvent(e);
    }

    function onPointerUp(e) {
        if (dragState === 'aim') {
            // A drag that travelled a meaningful distance also sets power from the pull-back
            // distance (power gesture), per the brief's "drag to aim ... power bar with a pull-
            // back gesture" - a short tap-drag just fine-tunes the angle without changing power.
            updatePowerFromEvent(e, true);
        }
        dragState = null;
    }

    function canvasPoint(e) {
        const rect = canvas.getBoundingClientRect();
        let x = (e.clientX - rect.left) / rect.width * canvas.width;
        let y = (e.clientY - rect.top) / rect.height * canvas.height;
        // Portrait mode rotates the stage 90deg via CSS - transform the pointer back into the
        // canvas's own (unrotated) coordinate space so aim/placement math stays simple.
        if (root.classList.contains('sk-root--portrait')) {
            const cx = canvas.width / 2, cy = canvas.height / 2;
            const dx = x - cx, dy = y - cy;
            x = cx - dy; y = cy + dx;
        }
        return { x, y };
    }

    function worldPoint(e) {
        const p = canvasPoint(e);
        const ref = toCanvas(0, 0);
        return { x: (p.x - ref.offX) / ref.scale, y: (canvas.height - p.y - ref.offY) / ref.scale };
    }

    function updateAimFromEvent(e, fromCue) {
        const cueBall = positions.cue;
        if (!cueBall) return;
        const w = worldPoint(e);
        aim.angle = Math.atan2(cueBall.y - w.y, w.x - cueBall.x) + Math.PI; // aim points FROM the click point back through the cue ball (pulling the cue back to strike forward)
        drawFrame();
    }

    function updatePowerFromEvent(e, finalize) {
        const cueBall = positions.cue;
        if (!cueBall) return;
        const w = worldPoint(e);
        const dx = w.x - cueBall.x, dy = w.y - cueBall.y;
        const dist = Math.hypot(dx, dy);
        aim.power = Math.max(0.08, Math.min(1, dist / 600));
        controlsEl.querySelector('[data-power-fill]').style.width = Math.round(aim.power * 100) + '%';
        updateShootEnabled();
    }

    function updateSpinFromEvent(e, dial) {
        const rect = dial.getBoundingClientRect();
        const cx = rect.left + rect.width / 2, cy = rect.top + rect.height / 2;
        const r = rect.width / 2;
        let dx = (e.clientX - cx) / r, dy = (e.clientY - cy) / r;
        const mag = Math.hypot(dx, dy);
        if (mag > 1) { dx /= mag; dy /= mag; }
        aim.spinX = Math.max(-1, Math.min(1, dx));
        aim.spinY = Math.max(-1, Math.min(1, -dy));
        const dot = dial.querySelector('[data-spin-dot]');
        dot.style.transform = `translate(${dx * r * 0.7}px, ${dy * r * 0.7}px)`;
    }

    function updateCuePlacementFromEvent(e) {
        const w = worldPoint(e);
        const dx = w.x - BAULK_X, dy = w.y - TABLE_H / 2;
        const dist = Math.hypot(dx, dy);
        const clampedDist = Math.min(dist, D_RADIUS - BALL_R);
        const angle = Math.atan2(dy, dx);
        let x = BAULK_X + Math.cos(angle) * clampedDist;
        if (x > BAULK_X) x = BAULK_X; // never past the baulk line
        const y = TABLE_H / 2 + Math.sin(angle) * clampedDist;
        let placement = { x: Math.max(BALL_R, Math.min(TABLE_W - BALL_R, x)), y: Math.max(BALL_R, Math.min(TABLE_H - BALL_R, y)) };
        // Push the placement away from any ball it would overlap (mirrors the server's own
        // 2*BALL_R rejection in SnookerRules::placeCueBall()) INSTEAD of letting the player drop
        // it there and only finding out it was invalid after pressing Shoot - a real cue ball
        // physically can't rest touching another ball, so the UI shouldn't let you aim one at a
        // spot that doesn't exist either. The brown spot sits exactly at the D's own centre (true
        // to a real table), which made this the single most common way to trigger that rejection.
        placement = resolveCuePlacementCollisions(placement);
        cuePlacement = placement;
        placingCue = true;
        positions.cue = cuePlacement;
        drawFrame();
        updateShootEnabled();
    }

    function resolveCuePlacementCollisions(placement) {
        const minDist = BALL_R * 2 + 1.5; // +1.5mm clearance so float rounding never re-trips the server's own check
        for (let pass = 0; pass < 8; pass++) {
            let moved = false;
            for (const [id, ball] of Object.entries(positions)) {
                if (id === 'cue') continue;
                const dx = placement.x - ball.x, dy = placement.y - ball.y;
                const dist = Math.hypot(dx, dy);
                if (dist < minDist) {
                    // Push straight away from the offending ball's centre; if the two centres
                    // coincide exactly (dist===0, vanishingly rare but possible at the D's centre)
                    // push along the angle back toward the D's own centre instead of by zero.
                    const pushAngle = dist > 0.01 ? Math.atan2(dy, dx) : Math.atan2(placement.y - TABLE_H / 2, placement.x - BAULK_X);
                    placement = {
                        x: Math.max(BALL_R, Math.min(TABLE_W - BALL_R, ball.x + Math.cos(pushAngle) * minDist)),
                        y: Math.max(BALL_R, Math.min(TABLE_H - BALL_R, ball.y + Math.sin(pushAngle) * minDist)),
                    };
                    moved = true;
                }
            }
            if (!moved) break;
        }
        // Re-clamp to the D/baulk-line constraint - pushing away from a ball can otherwise land
        // just outside it, especially near the D's own edge.
        const dx = placement.x - BAULK_X, dy = placement.y - TABLE_H / 2;
        const dist = Math.hypot(dx, dy);
        if (placement.x > BAULK_X || dist > D_RADIUS - 1) {
            const angle = Math.atan2(dy, dx);
            const clampedDist = Math.min(dist, D_RADIUS - 1);
            let x = BAULK_X + Math.cos(angle) * clampedDist;
            if (x > BAULK_X) x = BAULK_X;
            placement = { x: Math.max(BALL_R, Math.min(TABLE_W - BALL_R, x)), y: Math.max(BALL_R, Math.min(TABLE_H - BALL_R, TABLE_H / 2 + Math.sin(angle) * clampedDist)) };
        }
        return placement;
    }

    function isMyTurn() {
        const d = ctxApi?.latest;
        return d && d.status === 'active' && d.current_user_id === ctxApi.userId;
    }

    let wasCueBallInHand = false;
    function updateControlsAvailability(active, state) {
        if (!controlsEl) return;
        placingCue = !!state.cue_ball_in_hand;
        if (placingCue && !wasCueBallInHand) {
            // A fresh "ball in hand" situation (just fouled, cue ball just potted, or a new
            // frame) - clear any placement left over from a previous shot so the player must
            // deliberately place it again; the shoot button stays disabled until they do.
            cuePlacement = null;
        }
        wasCueBallInHand = placingCue;
        controlsEl.classList.toggle('is-disabled', !active);
        updateShootEnabled();
    }

    function updateShootEnabled() {
        const btn = controlsEl?.querySelector('[data-shoot]');
        if (!btn) return;
        const state = ctxApi?.latest?.state || {};
        const ready = isMyTurn() && aim.power > 0 && (!state.cue_ball_in_hand || !!cuePlacement) && (!state.awaiting_nomination || !!pendingNomination);
        btn.disabled = !ready;
    }

    function submitShot() {
        if (!ctxApi || !isMyTurn()) return;
        const action = { angle: aim.angle, power: aim.power, spinX: aim.spinX, spinY: aim.spinY };
        const state = ctxApi.latest?.state || {};
        if (state.cue_ball_in_hand && cuePlacement) action.cue_placement = cuePlacement;
        if (state.awaiting_nomination && pendingNomination) action.nominate = pendingNomination;
        pendingNomination = null;
        aim.power = 0;
        controlsEl.querySelector('[data-power-fill]').style.width = '0%';
        ctxApi.send(action);
    }

    // ---- Sound (synthesised - no external audio files) + haptics -----------------------------------
    // Every sound is built from a short noise burst (through a bandpass/lowpass filter) layered
    // with a pitched tone - the noise gives the percussive "click"/"thud" transient real balls and
    // cushions actually have, which a bare oscillator tone alone can't produce; the tone underneath
    // gives it a believable pitch. All generated at play time, so there's nothing to load/host.
    let noiseBuffer = null;
    function ensureAudio() {
        if (audioCtx || muted) return audioCtx;
        try {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            noiseBuffer = audioCtx.createBuffer(1, audioCtx.sampleRate * 0.3, audioCtx.sampleRate);
            const data = noiseBuffer.getChannelData(0);
            for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
        } catch (_) {}
        return audioCtx;
    }
    function playSound(kind, intensity) {
        if (muted) return;
        const ac = ensureAudio();
        if (!ac) return;
        const now = ac.currentTime;
        const amt = Math.max(0.35, Math.min(1, intensity ?? 1)); // soft impacts should sound softer, not just shorter
        const presets = {
            strike: { freq: 1500, filterFreq: 2600, filterQ: 1.1, filterType: 'bandpass', dur: 0.1, vol: 0.4, toneType: 'triangle' },
            contact: { freq: 1100, filterFreq: 2200, filterQ: 1.2, filterType: 'bandpass', dur: 0.07, vol: 0.26, toneType: 'triangle' },
            cushion: { freq: 220, filterFreq: 450, filterQ: 0.9, filterType: 'lowpass', dur: 0.1, vol: 0.18, toneType: 'sine' },
            pot: { freq: 190, filterFreq: 900, filterQ: 0.8, filterType: 'lowpass', dur: 0.26, vol: 0.34, toneType: 'sine' },
        };
        const p = presets[kind] || presets.contact;

        // Noise transient: the percussive "click"/"thud" body of the sound.
        const noise = ac.createBufferSource();
        noise.buffer = noiseBuffer;
        const filter = ac.createBiquadFilter();
        filter.type = p.filterType; filter.frequency.setValueAtTime(p.filterFreq, now); filter.Q.value = p.filterQ;
        const noiseGain = ac.createGain();
        noiseGain.gain.setValueAtTime(p.vol * amt, now);
        noiseGain.gain.exponentialRampToValueAtTime(0.001, now + p.dur);
        noise.connect(filter); filter.connect(noiseGain); noiseGain.connect(ac.destination);
        noise.start(now); noise.stop(now + p.dur + 0.02);

        // Pitched tone underneath, for a believable "what kind of impact was that" cue.
        const osc = ac.createOscillator();
        const oscGain = ac.createGain();
        osc.connect(oscGain); oscGain.connect(ac.destination);
        osc.type = p.toneType; osc.frequency.setValueAtTime(p.freq, now);
        if (kind === 'pot') osc.frequency.exponentialRampToValueAtTime(65, now + p.dur);
        oscGain.gain.setValueAtTime(p.vol * amt * 0.55, now);
        oscGain.gain.exponentialRampToValueAtTime(0.001, now + p.dur);
        osc.start(now); osc.stop(now + p.dur + 0.02);

        // Pot gets a short soft reverb-like tail (a second, quieter/lower echo) for a satisfying
        // "ball dropping into the pocket" feel instead of just stopping dead.
        if (kind === 'pot') {
            const tail = ac.createOscillator();
            const tailGain = ac.createGain();
            tail.connect(tailGain); tailGain.connect(ac.destination);
            tail.type = 'sine'; tail.frequency.setValueAtTime(110, now + 0.07);
            tailGain.gain.setValueAtTime(0, now);
            tailGain.gain.linearRampToValueAtTime(p.vol * 0.18, now + 0.09);
            tailGain.gain.exponentialRampToValueAtTime(0.001, now + 0.32);
            tail.start(now + 0.07); tail.stop(now + 0.36);
        }
    }
    function vibrate(pattern) {
        if (muted) return;
        try { navigator.vibrate && navigator.vibrate(pattern); } catch (_) {}
    }
    function toggleMute() {
        muted = !muted;
        try { localStorage.setItem('snookerMuted', muted ? '1' : '0'); } catch (_) {}
        const icon = hudEl.querySelector('[data-mute] i');
        icon.className = muted ? 'ph-fill ph-speaker-x' : 'ph-fill ph-speaker-high';
    }

    return { render, isAnimating };
})();
