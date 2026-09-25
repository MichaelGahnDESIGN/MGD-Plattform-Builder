/* Backoffice-Helfer: Bestätigungsdialoge und Live-Vorschau der Design-Farben. */
(function () {
  'use strict';

  document.addEventListener('submit', function (event) {
    var form = event.target;
    var message = form.getAttribute && form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  document.querySelectorAll('[data-swatch-input]').forEach(function (input) {
    var key = (input.getAttribute('name') || '').replace(/^s\[(.*)\]$/, '$1');
    var swatch = document.querySelector('[data-swatch="' + key + '"]');
    var code = input.parentNode.querySelector('code');
    input.addEventListener('input', function () {
      if (swatch) {
        swatch.style.background = input.value;
      }
      if (code) {
        code.textContent = input.value;
      }
    });
  });
})();
