(function (global) {
  'use strict';

  function adaptiveDelay(remaining) {
    if (remaining == null) return 1000;
    if (remaining > 5) return 1000;
    if (remaining > 2) return 500;
    return 250;
  }

  function createAdaptivePoller(options) {
    const state = {
      code: options.code,
      renderAll: options.renderAll,
      getRemainingFromUI: options.getRemainingFromUI || function () { return null; },
      since: -1,
      running: false
    };

    async function initialSync() {
      const res = await fetch(`/api/rooms/${encodeURIComponent(state.code)}/state?since=-1`);
      if (!res.ok) {
        throw new Error('Failed to load initial state');
      }
      const data = await res.json();
      state.since = data.stateVersion;
      state.renderAll(data);
    }

    async function pollOnce() {
      const res = await fetch(`/api/rooms/${encodeURIComponent(state.code)}/state?since=${state.since}`);
      if (res.status === 204) {
        return null;
      }
      if (!res.ok) {
        throw new Error('Failed to poll state');
      }
      const data = await res.json();
      state.since = data.stateVersion;
      state.renderAll(data);
      return data;
    }

    async function loop() {
      if (!state.running) {
        return;
      }
      try {
        const data = await pollOnce();
        const remaining = data ? data.remaining : (typeof state.getRemainingFromUI === 'function'
          ? state.getRemainingFromUI()
          : null);
        setTimeout(loop, adaptiveDelay(remaining));
      } catch (err) {
        console.error(err);
        setTimeout(loop, 1000);
      }
    }

    async function start() {
      if (state.running) {
        return;
      }
      state.running = true;
      try {
        await initialSync();
      } catch (err) {
        state.running = false;
        throw err;
      }
      loop();
    }

    function stop() {
      state.running = false;
    }

    return {
      start,
      stop,
      getSince: function () { return state.since; }
    };
  }

  function createLongPoller(options) {
    const state = {
      code: options.code,
      renderAll: options.renderAll,
      since: -1,
      stopped: false
    };

    async function initialSync() {
      const res = await fetch(`/api/rooms/${encodeURIComponent(state.code)}/state?since=-1`);
      if (!res.ok) {
        throw new Error('Failed to load initial state');
      }
      const data = await res.json();
      state.since = data.stateVersion;
      state.renderAll(data);
    }

    async function pollLoop() {
      if (state.stopped) {
        return;
      }
      try {
        const controller = new AbortController();
        const timeout = setTimeout(function () { controller.abort(); }, 30000);
        const res = await fetch(`/api/rooms/${encodeURIComponent(state.code)}/state?since=${state.since}`, {
          signal: controller.signal
        });
        clearTimeout(timeout);
        if (res.status !== 204) {
          if (!res.ok) {
            throw new Error('Failed to poll state');
          }
          const data = await res.json();
          state.since = data.stateVersion;
          state.renderAll(data);
        }
        if (!state.stopped) {
          pollLoop();
        }
      } catch (err) {
        console.error(err);
        if (!state.stopped) {
          setTimeout(pollLoop, 1000);
        }
      }
    }

    async function start() {
      if (!state.stopped && state.since >= 0) {
        return;
      }
      state.stopped = false;
      await initialSync();
      if (!state.stopped) {
        pollLoop();
      }
    }

    function stop() {
      state.stopped = true;
    }

    return { start, stop };
  }

  async function submitBid(code, playerId, value) {
    const normalizedPlayerId = typeof playerId === 'string' ? Number.parseInt(playerId, 10) : Number(playerId);
    if (!Number.isInteger(normalizedPlayerId) || normalizedPlayerId <= 0) {
      throw new Error('Player ID must be a positive integer');
    }

    const rawValue = typeof value === 'string' ? value.trim() : value;
    const normalizedValue = Number(rawValue);
    if (!Number.isInteger(normalizedValue) || normalizedValue < 2) {
      throw new Error('Bid must be an integer ≥ 2');
    }

    const res = await fetch(`/api/rooms/${encodeURIComponent(code)}/bid`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ playerId: normalizedPlayerId, value: normalizedValue })
    });
    let data = null;
    try {
      data = await res.json();
    } catch (err) {
      data = null;
    }
    if (!res.ok) {
      const message = data && data.error ? data.error : 'Bid failed';
      throw new Error(message);
    }
    return data;
  }

  async function sendVerifyAction(code, playerId, action) {
    const res = await fetch(`/api/rooms/${encodeURIComponent(code)}/verify/${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ playerId: Number(playerId) })
    });

    let data = null;
    try {
      data = await res.json();
    } catch (err) {
      data = null;
    }

    if (!res.ok) {
      const message = data && data.error ? data.error : 'Verification update failed';
      throw new Error(message);
    }

    return data;
  }

  function verifyPass(code, playerId) {
    return sendVerifyAction(code, playerId, 'pass');
  }

  function verifyFail(code, playerId) {
    return sendVerifyAction(code, playerId, 'fail');
  }

  global.PollingHelpers = {
    createAdaptivePoller: createAdaptivePoller,
    createLongPoller: createLongPoller,
    submitBid: submitBid,
    verifyPass: verifyPass,
    verifyFail: verifyFail
  };
}(window));
