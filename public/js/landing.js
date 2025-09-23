(function () {
  const ADJECTIVES = [
    'brave', 'clever', 'daring', 'eager', 'frosty', 'gentle', 'happy', 'jazzy', 'lively', 'merry',
    'nimble', 'plucky', 'quick', 'sly', 'sunny', 'swift', 'witty', 'zesty', 'bold', 'calm',
    'bright', 'cheery', 'cosmic', 'dapper', 'elegant', 'feisty', 'glad', 'kind', 'lucid', 'mighty',
    'peppy', 'quirky', 'radiant', 'spry', 'tidy', 'vivid', 'whimsical', 'zany'
  ];
  const NOUNS = [
    'badger', 'beaver', 'cougar', 'dragon', 'eagle', 'ferret', 'gibbon', 'heron', 'iguana', 'jaguar',
    'koala', 'lemur', 'llama', 'marmot', 'narwhal', 'otter', 'panda', 'quokka', 'rabbit', 'seal',
    'tiger', 'urchin', 'viper', 'walrus', 'yak', 'zorilla', 'falcon', 'goose', 'kiwi', 'owl',
    'puppy', 'turtle', 'swiftlet', 'lynx', 'panther', 'phoenix', 'sparrow', 'corgi'
  ];
  const CODE_REGEX = /^[a-z][a-z0-9-]{1,31}$/;
  const DOUBLE_HYPHEN = /--/;
  const LAST_CODE_KEY = 'rr.lastRoomCode';

  function pickRandom(list) {
    return list[Math.floor(Math.random() * list.length)];
  }

  function isValidRoomCode(code) {
    if (typeof code !== 'string') {
      return false;
    }
    return CODE_REGEX.test(code) && !DOUBLE_HYPHEN.test(code);
  }

  function normalizeGeneratedSlug(text) {
    if (typeof text !== 'string') {
      return null;
    }
    let slug = text.toLowerCase().replace(/[^a-z0-9-]+/g, '-');
    slug = slug.replace(/-{2,}/g, '-');
    slug = slug.replace(/^-+/, '');
    slug = slug.replace(/-+$/, '');
    if (!slug) {
      slug = 'room';
    }
    if (!/^[a-z]/.test(slug)) {
      slug = `r${slug}`;
    }
    if (slug.length > 32) {
      slug = slug.slice(0, 32);
    }
    slug = slug.replace(/-+$/, '');
    if (slug.length < 2) {
      slug = slug.padEnd(2, 'a');
    }
    if (!isValidRoomCode(slug)) {
      return null;
    }
    return slug;
  }

  function generateRoomCode() {
    for (let i = 0; i < 20; i += 1) {
      const suggestion = `${pickRandom(ADJECTIVES)}-${pickRandom(NOUNS)}`;
      const normalized = normalizeGeneratedSlug(suggestion);
      if (normalized) {
        return normalized;
      }
    }
    return 'brave-lion';
  }

  function sanitizeUserInput(value) {
    if (typeof value !== 'string') {
      return '';
    }
    return value.trim().toLowerCase();
  }

  function getLastCode() {
    try {
      const stored = window.localStorage.getItem(LAST_CODE_KEY);
      return typeof stored === 'string' ? stored : '';
    } catch (err) {
      return '';
    }
  }

  function saveLastCode(code) {
    try {
      if (code) {
        window.localStorage.setItem(LAST_CODE_KEY, code);
      }
    } catch (err) {
      // Ignore storage failures.
    }
  }

  function setFieldError(input, errorEl, message) {
    if (input) {
      if (message) {
        input.setAttribute('aria-invalid', 'true');
      } else {
        input.removeAttribute('aria-invalid');
      }
    }
    if (errorEl) {
      errorEl.textContent = message || '';
    }
  }

  function validateCode(code) {
    if (!code) {
      return 'Enter a room code to continue.';
    }
    if (!isValidRoomCode(code)) {
      return 'Use lowercase letters, numbers, and single hyphen (start with a letter).';
    }
    return '';
  }

  function switchTab(name, elements) {
    const { tabButtons, panels } = elements;
    tabButtons.forEach((button) => {
      const isActive = button.dataset.tab === name;
      button.setAttribute('aria-selected', isActive ? 'true' : 'false');
      button.tabIndex = isActive ? 0 : -1;
    });
    panels.forEach((panel) => {
      const isActive = panel.dataset.panel === name;
      panel.classList.toggle('is-active', isActive);
      panel.hidden = !isActive;
    });
  }

  function focusFirstField(name, refs) {
    if (name === 'create' && refs.createCodeInput) {
      refs.createCodeInput.focus({ preventScroll: true });
    } else if (name === 'join' && refs.joinCodeInput) {
      refs.joinCodeInput.focus({ preventScroll: true });
    }
  }

  function attachTabKeyboardNavigation(container, elements) {
    if (!container) {
      return;
    }
    container.addEventListener('keydown', (event) => {
      const key = event.key;
      if (key !== 'ArrowLeft' && key !== 'ArrowRight') {
        return;
      }
      event.preventDefault();
      const order = elements.tabButtons;
      const activeIndex = order.findIndex((btn) => btn.getAttribute('aria-selected') === 'true');
      if (activeIndex < 0) {
        return;
      }
      const delta = key === 'ArrowLeft' ? -1 : 1;
      let nextIndex = (activeIndex + delta + order.length) % order.length;
      const nextButton = order[nextIndex];
      if (!nextButton) {
        return;
      }
      const targetTab = nextButton.dataset.tab;
      if (!targetTab) {
        return;
      }
      switchTab(targetTab, elements);
      focusFirstField(targetTab, elements);
      nextButton.focus({ preventScroll: true });
    });
  }

  function redirectToRoom(code, created) {
    const encoded = encodeURIComponent(code);
    const suffix = created ? '?created=1' : '';
    window.location.href = `/r/${encoded}${suffix}`;
  }

  document.addEventListener('DOMContentLoaded', () => {
    const tabButtons = Array.from(document.querySelectorAll('[data-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-panel]'));
    const tablist = document.querySelector('.tablist');
    const createForm = document.getElementById('create-room-form');
    const createCodeInput = document.getElementById('create-room-code');
    const createSubmit = document.getElementById('create-room-submit');
    const createError = document.getElementById('create-error');
    const regenerateButton = document.getElementById('generate-room-code');
    const joinForm = document.getElementById('join-room-form');
    const joinCodeInput = document.getElementById('join-room-code');
    const joinSubmit = document.getElementById('join-room-submit');
    const joinError = document.getElementById('join-error');

    const refs = {
      tabButtons,
      panels,
      createCodeInput,
      joinCodeInput
    };

    const elements = { tabButtons, panels };

    const suggested = generateRoomCode();
    if (createCodeInput && !createCodeInput.value) {
      createCodeInput.value = suggested;
    }

    const lastCode = getLastCode();
    if (joinCodeInput && !joinCodeInput.value && lastCode) {
      joinCodeInput.value = lastCode;
    }

    tabButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const tabName = button.dataset.tab;
        if (!tabName) {
          return;
        }
        switchTab(tabName, elements);
        focusFirstField(tabName, refs);
      });
    });

    attachTabKeyboardNavigation(tablist, elements);

    if (createCodeInput) {
      createCodeInput.addEventListener('input', () => {
        setFieldError(createCodeInput, createError, '');
      });
      createCodeInput.addEventListener('blur', () => {
        if (!createCodeInput) {
          return;
        }
        const formatted = sanitizeUserInput(createCodeInput.value);
        createCodeInput.value = formatted;
        const message = validateCode(formatted);
        if (formatted && message) {
          setFieldError(createCodeInput, createError, message);
        }
      });
    }

    if (joinCodeInput) {
      joinCodeInput.addEventListener('input', () => {
        setFieldError(joinCodeInput, joinError, '');
      });
      joinCodeInput.addEventListener('blur', () => {
        if (!joinCodeInput) {
          return;
        }
        const formatted = sanitizeUserInput(joinCodeInput.value);
        joinCodeInput.value = formatted;
        if (formatted) {
          const message = validateCode(formatted);
          if (message) {
            setFieldError(joinCodeInput, joinError, message);
          }
        }
      });
    }

    if (regenerateButton && createCodeInput) {
      regenerateButton.addEventListener('click', () => {
        const next = generateRoomCode();
        createCodeInput.value = next;
        setFieldError(createCodeInput, createError, '');
        createCodeInput.focus({ preventScroll: true });
      });
    }

    if (createForm && createSubmit && createCodeInput) {
      createForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const code = sanitizeUserInput(createCodeInput.value);
        createCodeInput.value = code;
        const errorText = validateCode(code);
        if (errorText) {
          setFieldError(createCodeInput, createError, errorText);
          createCodeInput.focus({ preventScroll: true });
          return;
        }
        setFieldError(createCodeInput, createError, '');
        createSubmit.disabled = true;
        createSubmit.textContent = 'Creating…';
        try {
          const response = await fetch(`/api/rooms/${encodeURIComponent(code)}/create`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
          });
          if (response.status === 200 || response.status === 409) {
            saveLastCode(code);
            redirectToRoom(code, true);
            return;
          }
          let message = 'Unable to create room. Please try again.';
          try {
            const data = await response.json();
            if (data && typeof data.error === 'string' && data.error.trim()) {
              message = data.error.trim();
            }
          } catch (err) {
            // Ignore parse failures.
          }
          setFieldError(createCodeInput, createError, message);
        } catch (err) {
          setFieldError(createCodeInput, createError, 'Network error. Check your connection and try again.');
        } finally {
          createSubmit.disabled = false;
          createSubmit.textContent = 'Create room';
        }
      });
    }

    if (joinForm && joinSubmit && joinCodeInput) {
      joinForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const code = sanitizeUserInput(joinCodeInput.value);
        joinCodeInput.value = code;
        const errorText = validateCode(code);
        if (errorText) {
          setFieldError(joinCodeInput, joinError, errorText);
          joinCodeInput.focus({ preventScroll: true });
          return;
        }
        setFieldError(joinCodeInput, joinError, '');
        joinSubmit.disabled = true;
        joinSubmit.textContent = 'Joining…';
        saveLastCode(code);
        redirectToRoom(code, false);
      });
    }
  });
})();
