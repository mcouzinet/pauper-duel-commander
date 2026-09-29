// The sound of the video, synthesized offline (OfflineAudioContext): the effects, same palette as the other
// reels, over a music bed at 120 BPM whose beats carry every cut. The instants come from the constants of
// reel.html (D, R, W, BIG, LAND…), loaded before this file.
;(() => {
  const SR = 48000
  // The compressor starts cold: without this pre-roll, the first impact comes out 10 dB under the next ones.
  const PRE = 0.5

  /** Instant the roll of generals passes its k-th card (same curve as W3). */
  const rollAt = (k) => ROLL_A + (ROLL_B - ROLL_A) * (1 - (1 - k / ROLL_TOTAL) ** (1 / 2.4))
  const fanAt = (i) => FAN_T + i * FAN_DT

  const EVENTS = [
    // the hook, then the drop on the logo
    ['hit', 0.0, { f: 130.81 }], ['hit', HOOK_L, { f: 164.81 }], ['hit', 2 * HOOK_L, { f: 196 }],
    ['riser', 1.55, { dur: 0.7 }],
    ['boom', D], ['reveal', D + 0.02], ['shimmer', D + 0.12],
    ['whoosh', D + 0.83, { dur: 0.35 }],
    ...TAG.map(([, t], i) => ['thud', t, { g: 0.55 + 0.1 * i }]), ...TAG.map(([, t], i) => ['pop', t, { g: 0.5, f: 1 + 0.08 * i }]),
    ['swish', D + 3.25], ['whoosh', T_RULES - 0.3, { dur: 0.3 }],
    // the rules, against the clock
    ['hit', T_RULES, { f: 220 }], ['pop', T_RULES + 0.25, { g: 1.2 }], ['whoosh', T_RULES + 0.63, { dur: 0.28, pan: 0.6 }], ['ding', HUD_IN + 0.02],
    ...Array.from({ length: RULES_S - 1 }, (_, k) => ['tick', T0 + k + 1, { f: k >= RULES_S - 4 ? 1500 : 1050 }]),
    ['count', GRID_A, { n: 20, dur: GRID_B - GRID_A, f0: 500, f1: 1400 }], ['hit', GRID_B, { f: 261.63 }],
    ['pop', R1_GEN, { g: 1.2 }], ['shimmer', R1_GEN + 0.02], ['pop', R1_X1, { g: 1.1 }], ['whoosh', R[1] - 0.25, { dur: 0.3 }],
    ['whoosh', R[1] + 0.03, { dur: 0.35, pan: 0.5 }], ['thud', R[1] + 0.2, { g: 0.7 }], ['thud', R[1] + 0.35, { g: 0.8 }], ['pop', R[1] + 0.6, { g: 1.1 }], ['shimmer', R[1] + 0.65],
    ...R2_ROWS.map((t) => ['swish', t]), ['pop', R2_ROWS[0] + 0.25, { g: 0.9 }], ['pop', R2_ROWS[1] + 0.25, { g: 1.0 }],
    ['swish', NOPE - 0.12], ['stamp', NOPE], ['pop', R2_NAME, { g: 1.1 }], ['reveal', R2_NAME + 0.02], ['whoosh', R[2] - 0.25, { dur: 0.3 }],
    ['thud', R[2] + 0.1, { g: 0.8 }], ...FAN.map((_, i) => ['card', fanAt(i), { pan: (i - 5) / 7 }]), ['swish', FAN_LIFT], ['shimmer', FAN_LIFT + 0.05],
    ['whoosh', R[3] - 0.25, { dur: 0.3 }],
    ['thud', R[3] + 0.1, { g: 0.7 }], ['thud', R[3] + 0.25, { g: 0.8 }], ...[0, 1, 2, 3, 4].map((i) => ['pop', MANA_T + i * 0.08, { g: 0.8, f: 1 + 0.12 * i }]),
    ['thud', FOCUS, { g: 0.9 }], ['shimmer', FOCUS + 0.05], ['whoosh', R[4] - 0.25, { dur: 0.3 }],
    ['pop', R[4] + 0.05, { g: 1.1 }], ['count', LIFE_A, { n: 20, dur: LIFE_B - LIFE_A, f0: 1300, f1: 450 }], ['hit', LIFE_B, { f: 196 }],
    ['thud', LIFE_B + 0.15, { g: 0.7 }], ['swish', R[4] + 1.4], ['pop', R[4] + 2.0, { g: 1.1 }], ['nope', R[4] + 2.02],
    ['whoosh', R[5] - 0.25, { dur: 0.3 }],
    // time's up
    ['bell', T_END], ['boom', T_END, { g: 0.8 }], ['stamp', T_END], ['riser', T_WHY - 0.55, { dur: 0.55 }],
    // why play
    ['boom', T_WHY], ['thud', T_WHY + 0.25, { g: 0.7 }], ['thud', T_WHY + 0.37, { g: 0.8 }], ['pop', T_WHY + 0.55, { g: 1.5 }],
    ...[0, 1, 2, 3, 4].map((i) => ['pop', T_WHY + 0.7 + i * 0.06, { g: 0.6, f: 1 + 0.12 * i }]), ['whoosh', W[0] - 0.25, { dur: 0.3 }],
    ['thud', W[0] + 0.1, { g: 0.8 }], ['whoosh', W[0] + 0.12, { dur: 0.33, pan: -0.6 }], ['whoosh', W[0] + 0.12, { dur: 0.33, pan: 0.6 }],
    ['clash', PLATES], ['boom', PLATES, { g: 0.7 }], ['swish', W[0] + 0.8], ['whoosh', W[1] - 0.25, { dur: 0.3 }],
    ['thud', W[1] + 0.05, { g: 0.8 }], ...[0, 1, 2, 3].map((i) => ['pop', RC_T + i * 0.12, { g: 0.7, f: 1 + 0.1 * i }]),
    ['ding', RC_OK], ['ding', RC_OK + 0.12, { f: 1.26 }], ['nope', RC_NO], ['nope', RC_NO + 0.12], ['swish', W[1] + 1.5],
    ['whoosh', W[2] - 0.25, { dur: 0.3 }],
    ['thud', W[2] + 0.05, { g: 0.8 }], ['drumroll', ROLL_A, { n: Math.floor((ROLL_B - ROLL_A) / 0.055) }], ...Array.from({ length: 12 }, (_, j) => ['tap', rollAt(ROLL_TOTAL - 11 + j)]),
    ['boom', BIG], ['reveal', BIG + 0.02], ['shimmer', BIG + 0.12], ['swish', BIG + 0.5],
    ['whoosh', W[3] - 0.25, { dur: 0.3 }],
    ['riser', W[3] + 0.05, { dur: 0.5 }], ['thud', W[3] + 0.1, { g: 0.8 }], ...TOWNS.map((_, i) => ['pop', MAP_T + i * 0.09, { g: 0.8, f: 1 + 0.07 * i }]),
    ['thud', FR_T, { g: 0.8 }], ['thud', FR_T + 0.15, { g: 0.9 }], ['swish', FR_T + 0.5],
    ['whoosh', W[4] - 0.25, { dur: 0.3 }],
    ['whoosh', W[4] + 0.05, { dur: 0.3, pan: -0.4 }], ['whoosh', W[4] + 0.15, { dur: 0.3, pan: -0.1 }], ['thud', W[4] + 0.1, { g: 0.7 }], ['thud', W[4] + 0.25, { g: 0.8 }],
    ['stamp', STAMPS[0]], ['stamp', STAMPS[1]], ['sting', STAMPS[0] + 0.02], ['swish', W[4] + 1.1], ['swish', W[4] + 1.6],
    ['whoosh', W[5] - 0.25, { dur: 0.3 }],
    // the validator
    ['thud', T_VAL + 0.05, { g: 0.8 }], ['whoosh', T_VAL + 0.1, { dur: 0.35, pan: -0.4 }], ['whoosh', T_VAL + 0.2, { dur: 0.35, pan: 0.4 }],
    ...Array.from({ length: 20 }, (_, k) => ['key', V_TYPE[0] + ((V_TYPE[1] - V_TYPE[0]) * k) / 20]),
    ...DECK.map((_, k) => ['key', V_LINES[0] + ((V_LINES[1] - V_LINES[0]) * k) / DECK.length, { g: 0.6 }]),
    ['click', V_PRESS], ...VROWS.map((_, i) => ['pop', V_CHECKS + i * 0.15, { g: 0.8, f: 1 + 0.1 * i }]),
    ['reveal', V_OK], ['ding', V_OK + 0.03], ['shimmer', V_OK + 0.1],
    // the cube, then the signature
    ['whoosh', T_CUBE, { dur: 0.4 }], ['thud', T_CUBE + 0.35, { g: 1.3 }], ['thud', T_CUBE + 0.5, { g: 0.4 }],
    ['swish', T_CUBE + 0.85], ['swish', T_CUBE + 1.5], ['whoosh', LAND - 0.4, { dur: 0.4, pan: -0.2 }], ['thud', LAND, { g: 0.9 }], ['reveal', LAND + 0.02],
    ['shimmer', LAND + 0.35], ['pop', LAND + 0.55, { g: 1.0 }], ['tap', LAND + 0.75], ['tap', LAND + 0.95],
  ]

  function rng(seed) { return () => { seed |= 0; seed = (seed + 0x6d2b79f5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296 } }

  window.__audio = async () => {
    const c = new OfflineAudioContext(2, Math.ceil(SR * (DUR + PRE)), SR)
    const R0 = rng(11)
    const comp = c.createDynamicsCompressor()
    comp.threshold.value = -16; comp.ratio.value = 4; comp.attack.value = 0.003; comp.release.value = 0.2
    // headroom before the int16 export: the loudness comes from the mastering in render.cjs
    const trim = c.createGain(); trim.gain.value = 0.72
    comp.connect(trim); trim.connect(c.destination)
    // effects: high-passed at 55 Hz, the bass belongs to the music
    const master = c.createGain(); master.gain.value = 0.9
    const hpf = c.createBiquadFilter(); hpf.type = 'highpass'; hpf.frequency.value = 55
    master.connect(hpf); hpf.connect(comp)
    // a small shared reverb
    const verb = c.createConvolver()
    {
      const len = SR * 1.6, ir = c.createBuffer(2, len, SR)
      for (let ch = 0; ch < 2; ch++) { const d = ir.getChannelData(ch); for (let i = 0; i < len; i++) d[i] = (R0() * 2 - 1) * Math.pow(1 - i / len, 3) }
      verb.buffer = ir
    }
    const wet = c.createGain(); wet.gain.value = 0.22
    verb.connect(wet); wet.connect(master)
    // one noise buffer for everything: the music alone fires hundreds of hats
    const NOISE = c.createBuffer(1, SR * 2, SR)
    { const d = NOISE.getChannelData(0); for (let i = 0; i < d.length; i++) d[i] = R0() * 2 - 1 }

    function out(node, pan = 0, send = 0.5, dest = master) {
      const p = c.createStereoPanner(); p.pan.value = pan
      node.connect(p); p.connect(dest)
      const s = c.createGain(); s.gain.value = send; p.connect(s); s.connect(verb)
    }
    function tone({ freq, at = 0, dur = 0.2, type = 'triangle', gain = 0.1, glideTo, attack = 0.012, pan = 0, send = 0.5, dest = master }) {
      const osc = c.createOscillator(), g = c.createGain()
      osc.type = type
      osc.frequency.setValueAtTime(freq, at)
      if (glideTo) osc.frequency.exponentialRampToValueAtTime(glideTo, at + dur)
      if (attack > 0.05) { g.gain.setValueAtTime(0, at); g.gain.linearRampToValueAtTime(gain, at + attack) } else { g.gain.setValueAtTime(0.0001, at); g.gain.exponentialRampToValueAtTime(gain, at + attack) }
      g.gain.exponentialRampToValueAtTime(0.0001, at + Math.max(dur, attack + 0.02))
      osc.connect(g); out(g, pan, send, dest)
      osc.start(at); osc.stop(at + dur + 0.05)
    }
    function noise({ at = 0, dur = 0.08, gain = 0.06, filterFreq = 3000, filterType = 'bandpass', sweepTo, q = 1, attack = 0, pan = 0, send = 0.5, dest = master }) {
      const src = c.createBufferSource(); src.buffer = NOISE
      const f = c.createBiquadFilter(); f.type = filterType; f.Q.value = q
      f.frequency.setValueAtTime(filterFreq, at)
      if (sweepTo) f.frequency.exponentialRampToValueAtTime(sweepTo, at + dur)
      const g = c.createGain()
      if (attack) { g.gain.setValueAtTime(0, at); g.gain.linearRampToValueAtTime(gain, at + attack) } else g.gain.setValueAtTime(gain, at)
      g.gain.exponentialRampToValueAtTime(0.0001, at + dur)
      src.connect(f); f.connect(g); out(g, pan, send, dest)
      src.start(at, R0() * Math.max(0, 1.9 - dur), dur + 0.05)
    }
    function kick(at, g = 1, from = 150, to = 42, dur = 0.38, dest = master) {
      const osc = c.createOscillator(), gn = c.createGain()
      osc.type = 'sine'
      osc.frequency.setValueAtTime(from, at); osc.frequency.exponentialRampToValueAtTime(to, at + 0.16)
      gn.gain.setValueAtTime(0.0001, at); gn.gain.exponentialRampToValueAtTime(0.9 * g, at + 0.004); gn.gain.exponentialRampToValueAtTime(0.0001, at + dur)
      osc.connect(gn); out(gn, 0, 0.1, dest)
      osc.start(at); osc.stop(at + dur + 0.05)
    }
    // presence in the mids: that is what cuts through laptop speakers
    function snap(at, g = 1) { noise({ at, dur: 0.06, gain: 1.4 * g, filterFreq: 2200, q: 0.7, send: 0.2 }) }

    /* ------------------------------------------------------------ effects */
    const S = {
      hit(at, { f = 196, g = 1 }) {
        kick(at, g)
        snap(at, g)
        noise({ at, dur: 0.05, gain: 0.25 * g, filterFreq: 5000, filterType: 'highpass' })
        tone({ freq: f * 2, at, dur: 0.2, gain: 0.09 * g, type: 'sawtooth', send: 0.8 })
        tone({ freq: f * 3, at, dur: 0.18, gain: 0.05 * g, type: 'triangle', send: 0.8 })
        tone({ freq: f * 4, at, dur: 0.15, gain: 0.04 * g, type: 'sine', send: 0.8 })
      },
      boom(at, { g = 1 } = {}) {
        kick(at, 1.1 * g, 120, 32, 0.8)
        snap(at, 1.3 * g)
        noise({ at, dur: 0.9, gain: 0.22 * g, filterFreq: 900, filterType: 'lowpass', sweepTo: 120 })
        noise({ at, dur: 0.06, gain: 0.3 * g, filterFreq: 4000, filterType: 'highpass' })
      },
      riser(at, { dur = 0.5 }) {
        noise({ at, dur, gain: 0.35, filterFreq: 400, sweepTo: 6000, q: 1.2, attack: dur * 0.95, send: 0.4 })
        tone({ freq: 220, glideTo: 880, at, dur: dur + 0.03, type: 'sawtooth', gain: 0.05, attack: dur * 0.95, send: 0.4 })
      },
      whoosh(at, { dur = 0.35, pan = 0 }) {
        noise({ at, dur, gain: 0.5, filterFreq: 400, sweepTo: 4000, q: 0.8, attack: dur * 0.6, pan, send: 0.5 })
      },
      swish(at) {
        noise({ at: at - 0.03, dur: 0.24, gain: 0.4, filterFreq: 900, sweepTo: 5000, q: 1, attack: 0.1, send: 0.5 })
        tone({ freq: 1568, at: at + 0.2, dur: 0.12, type: 'sine', gain: 0.04 })
      },
      thud(at, { g = 1 } = {}) { kick(at, 0.8 * g, 110, 40, 0.3); snap(at, 0.6 * g); noise({ at, dur: 0.08, gain: 0.12 * g, filterFreq: 1500, filterType: 'lowpass' }) },
      stamp(at) { kick(at, 0.8, 130, 45, 0.28); snap(at, 0.8); noise({ at, dur: 0.12, gain: 0.18, filterFreq: 1400, q: 0.8 }) },
      tap(at) { tone({ freq: 2200, at, dur: 0.03, type: 'sine', gain: 0.06, send: 0.1 }); noise({ at, dur: 0.025, gain: 0.08, filterFreq: 6000, filterType: 'highpass', send: 0.1 }) },
      key(at, { g = 1 } = {}) { noise({ at, dur: 0.018, gain: 0.16 * g, filterFreq: 3200, q: 1.4, send: 0.05 }); tone({ freq: 1900, at, dur: 0.015, type: 'sine', gain: 0.025 * g, send: 0.05 }) },
      click(at) { kick(at, 0.35, 300, 120, 0.08); noise({ at, dur: 0.03, gain: 0.3, filterFreq: 2500, q: 1, send: 0.1 }) },
      card(at, { pan = 0 }) { noise({ at, dur: 0.07, gain: 0.35, filterFreq: 2600, sweepTo: 1200, q: 0.9, pan, send: 0.2 }); S.tap(at + 0.05) },
      ding(at, { f = 1 } = {}) { tone({ freq: 1046.5 * f, at, dur: 0.5, type: 'sine', gain: 0.07 }); tone({ freq: 1568 * f, at: at + 0.04, dur: 0.45, type: 'sine', gain: 0.045 }) },
      shimmer(at) { [1318.5, 1568, 2093, 2637].forEach((f, i) => tone({ freq: f, at: at + i * 0.05, dur: 0.9, type: 'sine', gain: 0.035, send: 1 })) },
      reveal(at) { [523.25, 659.25, 783.99].forEach((freq, i) => tone({ freq, at: at + i * 0.09, dur: 0.22, gain: 0.12 })) },
      // the bans: a minor arpeggio
      sting(at) {
        ;[392, 466.16, 587.33].forEach((freq, i) => tone({ freq, at: at + i * 0.08, dur: 0.16, gain: 0.13 }))
        tone({ freq: 783.99, at: at + 0.26, dur: 0.7, gain: 0.14 })
        tone({ freq: 932.33, at: at + 0.26, dur: 0.7, gain: 0.07, type: 'sine' })
      },
      tick(at, { f = 1050 } = {}) { tone({ freq: f, at, dur: 0.05, type: 'square', gain: 0.06, send: 0.2 }); noise({ at, dur: 0.02, gain: 0.1, filterFreq: 7000, filterType: 'highpass', send: 0.1 }) },
      pop(at, { g = 1, f = 1 } = {}) {
        tone({ freq: 420 * f, glideTo: 680 * f, at, dur: 0.09, type: 'sine', gain: 0.14 * g, send: 0.3 })
        tone({ freq: 840 * f, glideTo: 1360 * f, at, dur: 0.07, type: 'sine', gain: 0.07 * g, send: 0.3 })
      },
      // a run of blips that follows a counter
      count(at, { n = 20, dur = 0.7, f0 = 500, f1 = 1400 }) {
        for (let i = 0; i < n; i++) {
          const u = i / (n - 1)
          tone({ freq: f0 * (f1 / f0) ** u, at: at + dur * u, dur: 0.04, type: 'square', gain: 0.03, send: 0.15 })
        }
      },
      // struck out: a short dull blip, falling
      nope(at) { tone({ freq: 240, glideTo: 150, at, dur: 0.16, type: 'square', gain: 0.05, send: 0.2 }); noise({ at, dur: 0.1, gain: 0.12, filterFreq: 700, q: 1, send: 0.2 }) },
      // two blades: inharmonic partials that ring
      clash(at) {
        ;[1180, 1730, 2410, 3170, 4260].forEach((f, i) => tone({ freq: f, at, dur: 0.9 - i * 0.12, type: 'sine', gain: 0.05 - i * 0.006, attack: 0.002, send: 0.9, pan: i % 2 ? 0.2 : -0.2 }))
        noise({ at, dur: 0.12, gain: 0.5, filterFreq: 5500, filterType: 'highpass', send: 0.6 })
      },
      // time's up: a bell
      bell(at) {
        ;[[660, 0.14, 1.6], [1822, 0.07, 1.1], [3564, 0.04, 0.7], [1320, 0.05, 1.3]].forEach(([f, g, d]) => tone({ freq: f, at, dur: d, type: 'sine', gain: g, attack: 0.002, send: 0.8 }))
      },
      drumroll(at, { n = 26 } = {}) {
        for (let i = 0; i < n; i++) noise({ at: at + i * 0.055, dur: 0.05, gain: 0.12 + i * 0.012, filterFreq: 1600, q: 0.9, pan: i % 2 ? 0.15 : -0.15, send: 0.3 })
      },
    }
    for (const [name, at, opts] of EVENTS) S[name](at + PRE, opts || {})

    /* ------------------------------------------------------------ music */
    // i-VI-III-VII in A minor, one chord a bar; the last bar before the logo turns V-I in C
    const CH = {
      Am: { pad: [220, 261.63, 329.63, 440], bass: 55, arp: [440, 523.25, 659.25, 880] },
      F: { pad: [174.61, 220, 261.63, 349.23], bass: 43.65, arp: [349.23, 440, 523.25, 698.46] },
      C: { pad: [196, 261.63, 329.63, 392], bass: 65.41, arp: [392, 523.25, 659.25, 783.99] },
      G: { pad: [196, 246.94, 293.66, 392], bass: 49, arp: [392, 493.88, 587.33, 783.99] },
    }
    const PROG = ['Am', 'F', 'C', 'G']
    // [from, to, feel, chord origin]: the rules run lighter; the silence before "Pourquoi y jouer ?" is the break
    const SECTIONS = [[D, T_RULES, 'full', D], [T_RULES, T_END, 'rules', D], [T_WHY, LAND, 'drive', T_WHY]]
    const chordAt = (t, origin) => (t >= LAND - 1 && origin === T_WHY ? 'G' : PROG[Math.floor((t - origin) / 2 + 1e-6) % 4])

    const mus = c.createGain(); mus.gain.value = 0.55
    const mhp = c.createBiquadFilter(); mhp.type = 'highpass'; mhp.frequency.value = 32
    mus.connect(mhp); mhp.connect(comp)
    // pad, bass and arp duck under every kick: the pump that makes it move
    const duck = c.createGain(); duck.connect(mus)
    const echo = c.createDelay(1); echo.delayTime.value = 0.375
    const fb = c.createGain(); fb.gain.value = 0.32
    const echoOut = c.createGain(); echoOut.gain.value = 0.35
    echo.connect(fb); fb.connect(echo); echo.connect(echoOut); echoOut.connect(duck)

    const mKick = (at, g) => kick(at, 0.75 * g, 140, 44, 0.34, mus)
    const mHat = (at, g, dec) => noise({ at, dur: dec, gain: g, filterFreq: 8000, filterType: 'highpass', send: 0.05, dest: mus, pan: 0.15 })
    const mClap = (at, g) => { for (let k = 0; k < 3; k++) noise({ at: at + k * 0.011, dur: 0.1 + k * 0.03, gain: g * (k === 2 ? 1 : 0.6), filterFreq: 1300, q: 0.9, send: 0.5, dest: mus }) }
    function mBass(at, dur, f, g) {
      // a sub for the weight, a filtered saw an octave up for the small speakers
      tone({ freq: f, at, dur, type: 'sine', gain: 0.3 * g, attack: 0.006, send: 0, dest: duck })
      const o = c.createOscillator(), lp = c.createBiquadFilter(), gn = c.createGain()
      o.type = 'sawtooth'; o.frequency.value = f * 2
      lp.type = 'lowpass'; lp.Q.value = 2; lp.frequency.setValueAtTime(1100, at); lp.frequency.exponentialRampToValueAtTime(260, at + dur)
      gn.gain.setValueAtTime(0.0001, at); gn.gain.exponentialRampToValueAtTime(0.11 * g, at + 0.008); gn.gain.exponentialRampToValueAtTime(0.0001, at + dur)
      o.connect(lp); lp.connect(gn); out(gn, 0, 0.05, duck)
      o.start(at); o.stop(at + dur + 0.05)
    }
    function mPad(at, dur, fs, g, cut = 1500) {
      const lp = c.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = cut; lp.Q.value = 0.6
      const gn = c.createGain()
      gn.gain.setValueAtTime(0, at); gn.gain.linearRampToValueAtTime(g, at + 0.18)
      gn.gain.setValueAtTime(g, at + dur - 0.1); gn.gain.linearRampToValueAtTime(0, at + dur + 0.35)
      lp.connect(gn); out(gn, 0, 0.6, duck)
      fs.forEach((f, i) => [-7, 7].forEach((cents) => {
        const o = c.createOscillator(); o.type = 'sawtooth'; o.frequency.value = f; o.detune.value = cents + (i - 1.5) * 2
        o.connect(lp); o.start(at); o.stop(at + dur + 0.45)
      }))
    }
    function mArp(at, f, g) {
      const o = c.createOscillator(), gn = c.createGain(), lp = c.createBiquadFilter()
      o.type = 'square'; o.frequency.value = f
      lp.type = 'lowpass'; lp.frequency.value = 2600
      gn.gain.setValueAtTime(0.0001, at); gn.gain.exponentialRampToValueAtTime(g, at + 0.004); gn.gain.exponentialRampToValueAtTime(0.0001, at + 0.13)
      o.connect(lp); lp.connect(gn); gn.connect(echo); out(gn, 0.25, 0.2, duck)
      o.start(at); o.stop(at + 0.16)
    }
    function crash(at, g) { noise({ at, dur: 1.8, gain: g, filterFreq: 4500, filterType: 'highpass', send: 0.7, dest: mus }) }
    function pump(at) {
      duck.gain.setValueAtTime(1, at - 0.004)
      duck.gain.linearRampToValueAtTime(0.32, at + 0.012)
      duck.gain.linearRampToValueAtTime(1, at + 0.24)
    }

    const ARP = [0, 2, 1, 3, 2, 1, 3, 2]
    for (const [a, b, feel, origin] of SECTIONS) {
      const full = feel !== 'rules'
      crash(a + PRE, full ? 0.1 : 0.05)
      // the pad follows the chord: a new one at each change, on a bar line or not (the V before the logo)
      let padAt = a, padName = chordAt(a, origin)
      const padOut = (to) => mPad(padAt + PRE, to - padAt, CH[padName].pad, full ? 0.022 : 0.016, full ? 1600 : 1000)
      for (let tb = a; tb < b - 1e-6; tb += 0.5) {
        const at = tb + PRE, beat = Math.round((tb - origin) / 0.5) % 4, name = chordAt(tb, origin), ch = CH[name]
        if (name !== padName) { padOut(tb); padAt = tb; padName = name }
        mKick(at, full ? 1 : 0.75)
        pump(at)
        if (full && (beat === 1 || beat === 3)) mClap(at, feel === 'drive' ? 0.3 : 0.24)
        // hats: eighths in full sections, the ticking sixteenths of a clock under the rules
        for (let s = 0; s < (full ? 2 : 4); s++) {
          const ht = at + s * (full ? 0.25 : 0.125), off = full ? s === 1 : s % 2 === 1
          mHat(ht, off ? 0.07 : 0.035, off && full ? 0.09 : 0.03)
        }
        // bass on the eighths, the root of the bar
        mBass(at, 0.22, ch.bass, 1); mBass(at + 0.25, 0.2, ch.bass, 0.8)
        if (feel === 'drive') for (let s = 0; s < 4; s++) mArp(at + s * 0.125, ch.arp[ARP[(beat * 4 + s) % 8]], 0.028)
      }
      padOut(b)
    }
    // the drop of "85": one more crash on the downbeat it lands on
    crash(BIG + PRE, 0.09)
    // the logo lands: V-I, then a C major that holds and fades under the signature
    mKick(LAND + PRE, 1.1); crash(LAND + PRE, 0.1)
    mPad(LAND + PRE, DUR - LAND - 1.2, [261.63, 329.63, 392, 523.25], 0.04, 2000)
    tone({ freq: 65.41, at: LAND + PRE, dur: 3.6, type: 'sine', gain: 0.36, attack: 0.01, send: 0, dest: mus })
    tone({ freq: 130.81, at: LAND + PRE, dur: 2.2, type: 'triangle', gain: 0.06, attack: 0.01, send: 0.3, dest: mus })

    const buf = await c.startRendering()
    const L = buf.getChannelData(0).subarray(SR * PRE), Rt = buf.getChannelData(1).subarray(SR * PRE)
    const pcm = new Int16Array(L.length * 2)
    for (let i = 0; i < L.length; i++) {
      pcm[2 * i] = Math.max(-1, Math.min(1, L[i])) * 32767
      pcm[2 * i + 1] = Math.max(-1, Math.min(1, Rt[i])) * 32767
    }
    const bytes = new Uint8Array(pcm.buffer)
    let bin = ''
    for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000))
    return btoa(bin)
  }
})()
