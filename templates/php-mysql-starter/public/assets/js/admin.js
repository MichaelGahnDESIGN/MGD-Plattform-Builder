/* Backoffice-Helfer: Bestätigungsdialoge, Live-Vorschau der Design-Farben, Pfad kopieren (Medien). */
(function () {
  'use strict';

  var menuToggle = document.querySelector('.admin-menu-toggle');
  var menu = document.getElementById('admin-nav');
  var narrow = window.matchMedia('(max-width: 900px)');
  var syncMenu = function () {
    if (!menu || !menuToggle) {
      return;
    }
    var open = !narrow.matches || menuToggle.getAttribute('aria-expanded') === 'true';
    menu.setAttribute('data-collapsed', open ? 'false' : 'true');
  };
  if (menuToggle && menu) {
    menuToggle.addEventListener('click', function () {
      var expanded = menuToggle.getAttribute('aria-expanded') === 'true';
      menuToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      syncMenu();
    });
    if (narrow.addEventListener) {
      narrow.addEventListener('change', syncMenu);
    }
    syncMenu();
  }

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

  document.addEventListener('click', function (event) {
    var button = event.target.closest && event.target.closest('[data-copy-target]');
    if (!button) {
      return;
    }
    var value = button.getAttribute('data-copy-target') || '';
    var done = function () {
      var label = button.textContent;
      button.textContent = 'Kopiert';
      window.setTimeout(function () { button.textContent = label; }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).then(done, function () {});
      return;
    }
    var input = button.closest('.media-card') && button.closest('.media-card').querySelector('[data-copy-path]');
    if (input) {
      input.select();
    }
  });
})();
