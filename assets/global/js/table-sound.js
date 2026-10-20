/*
 * TableSound - lightweight, dependency-free sound effects for the live table games.
 * Every sound is synthesized at runtime with the Web Audio API (oscillators, noise
 * buffers and gain envelopes) - there are no audio files to license, host or lazy-load.
 * Respects the browser autoplay policy: the AudioContext is created lazily and resumed
 * on the first user gesture (tap/click/keydown) rather than on page load.
 */
(function (window) {
    'use strict';

    var STORAGE_ENABLED = 'tableSoundEnabled';
    var STORAGE_VOLUME = 'tableSoundVolume';
    var ctx = null;
    var unlocked = false;

    function readBool(key, fallback) {
        try {
            var raw = localStorage.getItem(key);
            return raw === null ? fallback : raw === '1';
        } catch (e) { return fallback; }
    }
    function readFloat(key, fallback) {
        try {
            var raw = localStorage.getItem(key);
            var value = raw === null ? NaN : parseFloat(raw);
            return isNaN(value) ? fallback : Math.min(1, Math.max(0, value));
        } catch (e) { return fallback; }
    }

    var state = {
        enabled: readBool(STORAGE_ENABLED, true),
        volume: readFloat(STORAGE_VOLUME, 0.5)
    };

    function ensureContext() {
        if (!ctx) {
            var AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return null;
            ctx = new AudioCtx();
        }
        if (ctx.state === 'suspended') ctx.resume().catch(function () {});
        return ctx;
    }

    function unlock() {
        if (unlocked) return;
        unlocked = true;
        ensureContext();
    }
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, unlock, { once: true, passive: true });
    });

    function tone(audio, when, freq, duration, opts) {
        opts = opts || {};
        var osc = audio.createOscillator();
        var gain = audio.createGain();
        osc.type = opts.type || 'sine';
        osc.frequency.setValueAtTime(freq, when);
        if (opts.slideTo) osc.frequency.exponentialRampToValueAtTime(Math.max(1, opts.slideTo), when + duration);
        var peak = (opts.gain != null ? opts.gain : 0.22) * state.volume;
        gain.gain.setValueAtTime(0.0001, when);
        gain.gain.exponentialRampToValueAtTime(Math.max(0.0001, peak), when + (opts.attack || 0.012));
        gain.gain.exponentialRampToValueAtTime(0.0001, when + duration);
        osc.connect(gain).connect(audio.destination);
        osc.start(when);
        osc.stop(when + duration + 0.02);
    }

    function noiseBurst(audio, when, duration, opts) {
        opts = opts || {};
        var frames = Math.max(1, Math.floor(audio.sampleRate * duration));
        var buffer = audio.createBuffer(1, frames, audio.sampleRate);
        var data = buffer.getChannelData(0);
        for (var i = 0; i < frames; i++) data[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / frames, 1.6);
        var source = audio.createBufferSource();
        source.buffer = buffer;
        var filter = audio.createBiquadFilter();
        filter.type = opts.filter || 'highpass';
        filter.frequency.value = opts.frequency || 1200;
        var gain = audio.createGain();
        gain.gain.setValueAtTime((opts.gain != null ? opts.gain : 0.25) * state.volume, when);
        gain.gain.exponentialRampToValueAtTime(0.0001, when + duration);
        source.connect(filter).connect(gain).connect(audio.destination);
        source.start(when);
    }

    function vibrate(pattern) {
        try { if (navigator.vibrate) navigator.vibrate(pattern); } catch (e) {}
    }

    var EFFECTS = {
        move: function (a, t) { tone(a, t, 520, 0.07, { type: 'sine', gain: 0.16 }); },
        capture: function (a, t) { tone(a, t, 180, 0.12, { type: 'square', gain: 0.18, slideTo: 90 }); noiseBurst(a, t, 0.08, { gain: 0.12, filter: 'lowpass', frequency: 800 }); vibrate(30); },
        dice: function (a, t) { for (var i = 0; i < 5; i++) noiseBurst(a, t + i * 0.07, 0.05, { gain: 0.14, filter: 'bandpass', frequency: 1800 + Math.random() * 1200 }); vibrate([15, 30, 15, 30, 15]); },
        card: function (a, t) { noiseBurst(a, t, 0.1, { gain: 0.16, filter: 'highpass', frequency: 2500 }); },
        deal: function (a, t) { noiseBurst(a, t, 0.06, { gain: 0.13, filter: 'highpass', frequency: 3000 }); },
        turn: function (a, t) { tone(a, t, 523, 0.09, { type: 'triangle', gain: 0.2 }); tone(a, t + 0.1, 659, 0.12, { type: 'triangle', gain: 0.2 }); vibrate(20); },
        warning: function (a, t) { tone(a, t, 880, 0.08, { type: 'square', gain: 0.16 }); tone(a, t + 0.14, 880, 0.08, { type: 'square', gain: 0.16 }); },
        win: function (a, t) {
            [523.25, 659.25, 783.99, 1046.5].forEach(function (freq, i) { tone(a, t + i * 0.1, freq, 0.28, { type: 'triangle', gain: 0.22 }); });
            vibrate([40, 40, 40, 40, 80]);
        },
        lose: function (a, t) { tone(a, t, 311, 0.3, { type: 'sine', gain: 0.18, slideTo: 196 }); vibrate(120); },
        draw: function (a, t) { tone(a, t, 440, 0.18, { type: 'sine', gain: 0.16 }); tone(a, t + 0.18, 440, 0.18, { type: 'sine', gain: 0.16 }); },
        join: function (a, t) { tone(a, t, 660, 0.1, { type: 'sine', gain: 0.16, slideTo: 880 }); },
        leave: function (a, t) { tone(a, t, 660, 0.1, { type: 'sine', gain: 0.14, slideTo: 440 }); },
        click: function (a, t) { tone(a, t, 740, 0.04, { type: 'sine', gain: 0.1 }); }
    };
    // Aliases so callers can use whichever name reads naturally at the call site.
    EFFECTS.flip = EFFECTS.card;
    EFFECTS.roll = EFFECTS.dice;

    function play(name) {
        if (!state.enabled) return;
        var audio = ensureContext();
        if (!audio) return;
        var effect = EFFECTS[name];
        if (!effect) return;
        effect(audio, audio.currentTime);
    }

    function setEnabled(value) {
        state.enabled = !!value;
        try { localStorage.setItem(STORAGE_ENABLED, state.enabled ? '1' : '0'); } catch (e) {}
    }
    function setVolume(value) {
        state.volume = Math.min(1, Math.max(0, Number(value)));
        try { localStorage.setItem(STORAGE_VOLUME, String(state.volume)); } catch (e) {}
    }

    window.TableSound = {
        play: play,
        unlock: unlock,
        setEnabled: setEnabled,
        setVolume: setVolume,
        isEnabled: function () { return state.enabled; },
        getVolume: function () { return state.volume; }
    };
})(window);
