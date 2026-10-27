/* Hell/Dunkel/System-Umschalter. Der Anfangszustand wird bereits im <head> gesetzt (kein Flackern). */
(function () {
  'use strict';

  var root = document.documentElement;
  var STORAGE_KEY = 'mgd-theme';
  var MODES = ['system', 'light', 'dark'];
  var LABELS = { system: 'System', light: 'Hell', dark: 'Dunkel' };
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var allowStore = root.getAttribute('data-theme-user') === '1';

  function resolve(mode) {
    if (mode === 'system') {
      return media && media.matches ? 'dark' : 'light';
    }
    return mode;
  }

  function currentMode() {
    var mode = root.getAttribute('data-theme-mode');
    return MODES.indexOf(mode) === -1 ? 'system' : mode;
  }

  function store(mode) {
    if (!allowStore) {
      return;
    }
    try {
      window.localStorage.setItem(STORAGE_KEY, mode);
    } catch (error) {
      /* Speicher nicht verfügbar (z. B. privater Modus) – Wahl gilt nur für diese Seite. */
    }
  }

  function apply(mode) {
    root.setAttribute('data-theme-mode', mode);
    root.setAttribute('data-theme', resolve(mode));
    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
      var label = button.querySelector('.theme-toggle__label');
      if (label) {
        label.textContent = LABELS[mode];
      }
      button.setAttribute('aria-label', 'Farbmodus: ' + LABELS[mode] + ' (klicken zum Wechseln)');
    });
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
    if (!button) {
      return;
    }
    var next = MODES[(MODES.indexOf(currentMode()) + 1) % MODES.length];
    store(next);
    apply(next);
  });

  if (media && media.addEventListener) {
    media.addEventListener('change', function () {
      if (currentMode() === 'system') {
        apply('system');
      }
    });
  }

  apply(currentMode());
})();
