/*
 * Lädt den gewählten CMS-Editor (TinyMCE, GrapesJS oder Quill) lokal oder per CDN.
 * Fehlen die Dateien oder schlägt die Initialisierung fehl, bleibt das einfache Textfeld aktiv.
 * Der Server bereinigt das HTML in jedem Fall.
 */
(function () {
  'use strict';

  var textarea = document.getElementById('page-content');
  if (!textarea) {
    return;
  }

  var editor = textarea.getAttribute('data-editor');
  var scriptUrl = textarea.getAttribute('data-editor-script');
  var styleUrl = textarea.getAttribute('data-editor-style');
  var status = document.querySelector('[data-editor-status]');
  var form = textarea.form;
  var isDark = document.documentElement.getAttribute('data-theme') === 'dark';

  function setStatus(message) {
    if (status) {
      status.textContent = message;
    }
  }

  function fallback() {
    textarea.hidden = false;
    setStatus('Der Editor konnte nicht geladen werden – das einfache Textfeld ist aktiv.');
  }

  function canvasAfterTextarea() {
    var canvas = document.createElement('div');
    canvas.className = 'editor-canvas';
    textarea.insertAdjacentElement('afterend', canvas);
    textarea.hidden = true;
    return canvas;
  }

  var initializers = {
    tinymce: function () {
      window.tinymce.init({
        target: textarea,
        license_key: 'gpl',
        menubar: false,
        promotion: false,
        branding: false,
        convert_urls: false,
        plugins: 'lists link image table code',
        toolbar: 'undo redo | blocks | bold italic | bullist numlist | link image table | code',
        skin: isDark ? 'oxide-dark' : 'oxide',
        content_css: isDark ? 'dark' : 'default'
      });
    },
    quill: function () {
      var canvas = canvasAfterTextarea();
      var quill = new window.Quill(canvas, { theme: 'snow' });
      quill.setContents(quill.clipboard.convert({ html: textarea.value }));
      form.addEventListener('submit', function () {
        textarea.value = typeof quill.getSemanticHTML === 'function' ? quill.getSemanticHTML() : quill.root.innerHTML;
      });
    },
    grapesjs: function () {
      var canvas = canvasAfterTextarea();
      var instance = window.grapesjs.init({
        container: canvas,
        fromElement: false,
        components: textarea.value,
        storageManager: false,
        height: '32rem'
      });
      form.addEventListener('submit', function () {
        textarea.value = instance.getHtml();
      });
    }
  };

  if (!scriptUrl || !initializers[editor]) {
    return;
  }

  if (styleUrl) {
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = styleUrl;
    document.head.appendChild(link);
  }

  var script = document.createElement('script');
  script.src = scriptUrl;
  script.onload = function () {
    try {
      initializers[editor]();
      setStatus('');
    } catch (error) {
      fallback();
    }
  };
  script.onerror = fallback;
  document.head.appendChild(script);
})();
