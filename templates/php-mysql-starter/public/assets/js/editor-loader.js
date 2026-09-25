/*
 * Lädt den gewählten CMS-Editor (TinyMCE, GrapesJS oder Quill) lokal oder per CDN.
 * GrapesJS speichert HTML (content), CSS (content_css) und Projektdaten (content_source, JSON).
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

  function parseProject(value) {
    if (!value) {
      return null;
    }
    try {
      var data = JSON.parse(value);
      return data && typeof data === 'object' && !Array.isArray(data) ? data : null;
    } catch (error) {
      return null;
    }
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
      var cssField = form.querySelector('[data-editor-css]');
      var projectField = form.querySelector('[data-editor-project]');
      var formatField = form.querySelector('[name="content_format"]');
      var project = parseProject(projectField ? projectField.value : '');
      var canvas = canvasAfterTextarea();
      var options = {
        container: canvas,
        fromElement: false,
        storageManager: false,
        height: '32rem',
        // Stile als Klassen-/ID-Regeln statt Inline-Styles: der Server entfernt style-Attribute.
        avoidInlineStyle: true,
        forceClass: true,
        selectorManager: { componentFirst: true },
        allowScripts: 0
      };
      if (!project) {
        options.components = textarea.value;
        options.style = cssField ? cssField.value : '';
      }
      var instance = window.grapesjs.init(options);
      if (project) {
        try {
          instance.loadProjectData(project);
        } catch (error) {
          instance.setComponents(textarea.value);
          instance.setStyle(cssField ? cssField.value : '');
        }
      }
      form.addEventListener('submit', function () {
        textarea.value = instance.getHtml();
        if (cssField) {
          cssField.value = instance.getCss();
        }
        if (projectField) {
          projectField.value = JSON.stringify(instance.getProjectData());
        }
        if (formatField) {
          formatField.value = 'grapesjs';
        }
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
