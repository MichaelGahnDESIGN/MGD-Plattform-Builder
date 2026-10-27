/* Clientseitige Suche und Kategoriefilter für die Einstellungen (Server-Fallback: ?q= & ?category=). */
(function () {
  'use strict';

  var input = document.getElementById('settings-search');
  var select = document.getElementById('settings-category');
  var rows = Array.prototype.slice.call(document.querySelectorAll('.setting-row'));
  var groups = Array.prototype.slice.call(document.querySelectorAll('.settings-group'));
  var empty = document.querySelector('[data-settings-empty]');

  if (!input || !select || rows.length === 0) {
    return;
  }

  function terms() {
    return input.value.toLowerCase().split(/\s+/).filter(Boolean);
  }

  function filter() {
    var words = terms();
    var category = select.value;
    var visibleCount = 0;

    rows.forEach(function (row) {
      var haystack = row.getAttribute('data-search') || '';
      var matchesCategory = !category || row.getAttribute('data-category') === category;
      var matchesTerms = words.every(function (word) { return haystack.indexOf(word) !== -1; });
      row.hidden = !(matchesCategory && matchesTerms);
      if (!row.hidden) {
        visibleCount += 1;
      }
    });

    groups.forEach(function (group) {
      group.hidden = !group.querySelector('.setting-row:not([hidden])');
    });

    if (empty) {
      empty.hidden = visibleCount !== 0;
    }

    document.querySelectorAll('[data-settings-category]').forEach(function (link) {
      if (link.getAttribute('data-settings-category') === category) {
        link.setAttribute('aria-current', 'page');
      } else {
        link.removeAttribute('aria-current');
      }
    });
  }

  input.addEventListener('input', filter);
  select.addEventListener('change', filter);

  document.querySelectorAll('[data-settings-category]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      // Nur ohne serverseitigen Filter sind alle Kategorien im DOM – sonst normal navigieren.
      if (window.location.search !== '') {
        return;
      }
      var category = link.getAttribute('data-settings-category') || '';
      event.preventDefault();
      select.value = category;
      filter();
    });
  });

  filter();
})();
