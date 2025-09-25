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
  const CODE_REGEX = /^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/;
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
      if (typeof stored !== 'string') {
        return '';
      }
      const normalized = sanitizeUserInput(stored);
      return isValidRoomCode(normalized) ? normalized : '';
    } catch (err) {
      return '';
    }
  }

  function saveLastCode(code) {
    try {
      const normalized = sanitizeUserInput(code);
      if (normalized && isValidRoomCode(normalized)) {
        window.localStorage.setItem(LAST_CODE_KEY, normalized);
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
      return 'Use lowercase letters, numbers, and single hyphen (no leading, trailing, or double hyphen).';
    }
    return '';
  }

  function focusFirstField(refs) {
    if (refs.createCodeInput) {
      refs.createCodeInput.focus({ preventScroll: true });
    }
  }

  function redirectToRoom(code, created) {
    const encoded = encodeURIComponent(code);
    const suffix = created ? '?created=1' : '';
    window.location.href = `/r/${encoded}${suffix}`;
  }

  document.addEventListener('DOMContentLoaded', () => {
    const createForm = document.getElementById('create-room-form');
    const createCodeInput = document.getElementById('create-room-code');
    const createSubmit = document.getElementById('create-room-submit');
    const createError = document.getElementById('create-error');
    const regenerateButton = document.getElementById('generate-room-code');

    const refs = {
      createCodeInput
    };

    const suggested = generateRoomCode();
    if (createCodeInput && !createCodeInput.value) {
      createCodeInput.value = suggested;
    }

    // Focus the input on page load
    focusFirstField(refs);

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

  });
})();
