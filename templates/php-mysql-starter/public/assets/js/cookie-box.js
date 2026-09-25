/*
 * Cookie-Box: speichert nur die Entscheidung im lokalen Speicher.
 * Tracking/externe Dienste dürfen erst nach Einwilligung geladen werden:
 *   document.addEventListener('mgd:consent', function (e) { if (e.detail.choice === 'all') { ... } });
 *   window.mgdConsent() liefert 'essential', 'all' oder null.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'mgd-cookie-consent';
  var box = document.getElementById('cookie-box');

  function read() {
    try {
      var value = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || 'null');
      return value && (value.choice === 'essential' || value.choice === 'all') ? value.choice : null;
    } catch (error) {
      return null;
    }
  }

  function write(choice) {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ choice: choice, at: new Date().toISOString() }));
    } catch (error) {
      /* Ohne Speicher erscheint die Box beim nächsten Aufruf erneut. */
    }
  }

  function announce(choice) {
    document.dispatchEvent(new CustomEvent('mgd:consent', { detail: { choice: choice } }));
  }

  window.mgdConsent = read;

  if (!box) {
    return;
  }

  box.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('[data-consent]') : null;
    if (!button) {
      return;
    }
    var choice = button.getAttribute('data-consent') === 'all' ? 'all' : 'essential';
    write(choice);
    box.hidden = true;
    announce(choice);
  });

  document.addEventListener('click', function (event) {
    if (event.target.closest && event.target.closest('[data-cookie-settings]')) {
      box.hidden = false;
      var first = box.querySelector('[data-consent]');
      if (first) {
        first.focus();
      }
    }
  });

  var existing = read();
  if (existing) {
    announce(existing);
  } else {
    box.hidden = false;
  }
})();
