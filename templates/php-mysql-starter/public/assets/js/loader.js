/* Blendet den Ladebildschirm nach dem Laden (mind. data-loader-min Millisekunden) aus. */
(function () {
  'use strict';

  var root = document.documentElement;
  var start = (window.performance && performance.now) ? performance.now() : 0;
  var minimum = parseInt(root.getAttribute('data-loader-min') || '0', 10) || 0;

  function hide() {
    var elapsed = ((window.performance && performance.now) ? performance.now() : minimum) - start;
    window.setTimeout(function () {
      root.classList.remove('is-loading');
    }, Math.max(0, minimum - elapsed));
  }

  if (document.readyState === 'complete') {
    hide();
  } else {
    window.addEventListener('load', hide, { once: true });
  }
})();
