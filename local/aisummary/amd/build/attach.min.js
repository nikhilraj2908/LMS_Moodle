/* eslint-disable */
define(['core/ajax', 'core/notification'], function (Ajax, Notification) {
  'use strict';

  // -----------------------------------------------
  // Helper logging function
  function L() {
    try {
      console.log('[AISUM]', ...arguments);
    } catch (e) {}
  }

  // -----------------------------------------------
  // Early autosave clearance - runs immediately
  (function earlyClearAutosave() {
    try {
      for (var k in localStorage) {
        if (!Object.prototype.hasOwnProperty.call(localStorage, k)) continue;
        if (k.indexOf('tinymce-autosave') === 0 || k.indexOf('atto_autosave') === 0) {
          localStorage.removeItem(k);
          console.log('🧽 Cleared draft:', k);
        }
      }
    } catch (e) {
      console.warn('Autosave clear failed', e);
    }
  })();

  // -----------------------------------------------
  // Find TinyMCE editor instance for summary field
  function findTinySummaryEditor() {
    try {
      if (window.tinymce && tinymce.editors) {
        var editorIds = ['id_summary_editor', 'summary_editor', 'id_summary', 'summary'];

        for (var i = 0; i < editorIds.length; i++) {
          var ed = tinymce.get(editorIds[i]);
          if (ed) {
            L('Found TinyMCE editor by ID:', editorIds[i]);
            return ed;
          }
        }

        // fallback search
        for (var id in tinymce.editors) {
          if (tinymce.editors.hasOwnProperty(id)) {
            var editor = tinymce.editors[id];
            var elementName = (editor.targetElm && editor.targetElm.name) ? editor.targetElm.name.toLowerCase() : '';
            var elementId = (editor.targetElm && editor.targetElm.id) ? editor.targetElm.id.toLowerCase() : '';

            if (elementName.includes('summary') || elementId.includes('summary')) {
              L('Found TinyMCE editor by name/id:', elementName, elementId);
              return editor;
            }
          }
        }
      }
    } catch (e) {
      L('Error finding TinyMCE editor:', e);
    }
    return null;
  }

  // -----------------------------------------------
  // Clear Moodle autosave drafts
  function clearAutosave() {
    try {
      var ed = findTinySummaryEditor();
      if (ed && ed.plugins && ed.plugins.autosave && typeof ed.plugins.autosave.removeDraft === 'function') {
        try { ed.plugins.autosave.removeDraft(); } catch (e) {}
      }
      try {
        for (var k in localStorage) {
          if (!Object.prototype.hasOwnProperty.call(localStorage, k)) continue;
          if (k.indexOf('tinymce-autosave') === 0 || k.indexOf('atto_autosave') === 0) {
            localStorage.removeItem(k);
          }
        }
      } catch (e) {}
      setTimeout(function () {
        try {
          document.querySelectorAll('.tox .tox-notification,.atto_autosave_message')
            .forEach(function (el) { el.style.display = 'none'; });
        } catch (e) {}
      }, 150);
    } catch (e) {}
  }

  // -----------------------------------------------
  // Paste summary text into Moodle's description (summary) field
  function pasteIntoSummary(html) {
    try {
      L('pasteIntoSummary start, len=', (html || '').length);
      var updated = false;

      // STRATEGY 1: TinyMCE API (most reliable)
      var ed = findTinySummaryEditor();
      if (ed) {
        try {
          ed.setContent(html);
          ed.fire('change');
          ed.save(); // sync to hidden input
          updated = true;
          L('TinyMCE setContent/save OK:', ed.id);
        } catch (e) {
          L('TinyMCE API error', e);
        }
      }

      // STRATEGY 2: ATTO contenteditable
      if (!updated) {
        var editable = document.querySelector('[id^="id_summary_editoreditable"]') ||
          document.querySelector('.editor_atto [contenteditable="true"]');
        if (editable) {
          editable.innerHTML = html.replace(/\n/g, '<br>');
          updated = true;
          L('ATTO innerHTML set');
        }
      }

      // STRATEGY 3: TinyMCE iframe fallback
      if (!updated) {
        var ifr = document.querySelector('[id^="fitem_id_summary"] iframe.tox-edit-area__iframe') ||
          document.querySelector('textarea#id_summary_editor ~ .tox .tox-edit-area__iframe');
        if (ifr && ifr.contentWindow && ifr.contentWindow.document) {
          try {
            var doc = ifr.contentWindow.document;
            doc.body.innerHTML = html;
            updated = true;
            L('TinyMCE iframe body set');
          } catch (e) {
            L('iframe write error', e);
          }
        }
      }

      // STRATEGY 4: hidden input field sync
      var hidden = document.querySelector('#id_summary_editor') ||
        document.querySelector('input[name="summary_editor[text]"]') ||
        document.querySelector('textarea[name="summary_editor[text]"]');
      if (hidden) {
        hidden.value = html;
        try { hidden.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
        L('hidden value set');
      }

      // STRATEGY 5: raw textarea fallback
      var raw = document.querySelector('#id_summary');
      if (raw) {
        raw.value = html;
        L('raw textarea set');
      }

      clearAutosave();
      L('pasteIntoSummary done');
      return true;
    } catch (e) {
      L('pasteIntoSummary error', e);
      return false;
    }
  }

  // -----------------------------------------------
  // Auto-fill course title into modal
  function getCourseTitle() {
    var name = document.querySelector('#id_fullname, [name="fullname"]');
    if (name && name.value) return name.value.trim();
    var h = document.querySelector('h1, .page-header-headings h1');
    if (h) return h.textContent.trim();
    return '';
  }

  // -----------------------------------------------
  // Build popup modal for Title + Context
  function buildModal() {
    var modal = document.createElement('div');
    modal.className = 'modal fade show';
    modal.style.display = 'block';
    modal.style.background = 'rgba(0,0,0,.35)';
    modal.style.zIndex = 1050;
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100%';
    modal.style.height = '100%';

    modal.innerHTML = ''
      + '<div class="modal-dialog" role="document" style="margin-top: 100px;">'
      + '  <div class="modal-content">'
      + '    <div class="modal-header">'
      + '      <h5 class="modal-title">AI Summary</h5>'
      + '      <button type="button" class="close" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
      + '    </div>'
      + '    <div class="modal-body">'
      + '      <div class="form-group">'
      + '        <label>Title</label>'
      + '        <input type="text" class="form-control" id="aisum-title" placeholder="Enter topic / course title">'
      + '      </div>'
      + '      <div class="form-group mt-2">'
      + '        <label>Short description (context)</label>'
      + '        <textarea class="form-control" id="aisum-context" rows="4" placeholder="Add 1–3 lines about the topic so AI stays on track (e.g., JBL is a brand of audio speakers)..."></textarea>'
      + '      </div>'
      + '      <div class="small text-muted mt-2">Tip: Give a hint if the title is ambiguous (e.g., acronyms like JBL).</div>'
      + '    </div>'
      + '    <div class="modal-footer">'
      + '      <button type="button" class="btn btn-secondary btn-cancel">Cancel</button>'
      + '      <button type="button" class="btn btn-primary btn-generate">Generate</button>'
      + '    </div>'
      + '  </div>'
      + '</div>';

    document.body.appendChild(modal);

    var api = {
      el: modal,
      close: function () {
        try { document.body.removeChild(modal); } catch (e) {}
      },
      focus: function () {
        try { modal.querySelector('#aisum-title').focus(); } catch (e) {}
      },
      on: function (sel, ev, cb) {
        var el = modal.querySelector(sel);
        if (el) el.addEventListener(ev, cb);
      }
    };

    api.on('.close', 'click', api.close);
    api.on('.btn-cancel', 'click', api.close);

    modal.addEventListener('click', function (e) {
      if (e.target === modal) api.close();
    });

    return api;
  }

  // -----------------------------------------------
  // Open modal and handle Generate action
  function openFormAndGenerate() {
    var modal = buildModal();
    var titleEl = modal.el.querySelector('#aisum-title');
    var contextEl = modal.el.querySelector('#aisum-context');

    var guessTitle = getCourseTitle();
    titleEl.value = guessTitle || '';
    modal.focus();

    modal.on('.btn-generate', 'click', function () {
      var title = (titleEl.value || '').trim();
      var context = (contextEl.value || '').trim();

      if (!context.trim()) {
        Notification.alert('Add some description', 'Please enter a short description or hint before generating the summary.', 'OK');
        return;
      }

      if (!title) {
        Notification.alert('Missing title', 'Please enter a title/topic for the summary.', 'OK');
        return;
      }

      if ((/^[A-Z0-9]{2,6}$/.test(title) || title.length < 3) && context.length < 10) {
        Notification.alert('Add a hint', 'The title looks ambiguous. Please add 1–2 lines in the description so AI won\'t guess the meaning.', 'OK');
        return;
      }

      var btn = modal.el.querySelector('.btn-generate');
      btn.disabled = true;
      btn.textContent = 'Generating…';

      Ajax.call([{
        methodname: 'local_aisummary_generate_summary',
        args: { title: title, context: context },
        fail: Notification.exception
      }])[0].then(function (resp) {
        console.log('✅ API Response Data:', resp);
        btn.disabled = false;
        btn.textContent = 'Generate';
        modal.close();

        var summary = (resp && resp.summary) ? resp.summary : '';
        if (!summary) {
          Notification.alert('Empty response', 'AI did not return any text. Please try again with more context.', 'OK');
          return;
        }

        L('Generated summary length:', summary.length);

        if (!pasteIntoSummary(summary)) {
          Notification.alert('Copied to clipboard', 'We could not paste into the editor. The summary has been copied to your clipboard.', 'OK');
          try { navigator.clipboard.writeText(summary); } catch (e) { L('Clipboard failed:', e); }
        } else {
          L('Summary successfully pasted into editor');
          Notification.alert('Success', 'AI summary has been added to the course summary field!', 'OK');
        }
      }).catch(function (err) {
        btn.disabled = false;
        btn.textContent = 'Generate';
        Notification.exception(err);
      });
    });

    // Allow Enter key to submit
    modal.on('#aisum-title, #aisum-context', 'keypress', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        modal.el.querySelector('.btn-generate').click();
      }
    });
  }

  // -----------------------------------------------
  // Mount Generate with AI button - SINGLE CORRECT VERSION
  function mount() {
    // 🧹 CLEAR SUMMARY FIELD ON PAGE LOAD - Fix for ghost content
    function forceClearSummaryField() {
      L('Force clearing summary field on page load');
      
      // Strategy 1: Clear TinyMCE editors
      if (window.tinymce && tinymce.editors) {
        for (var id in tinymce.editors) {
          if (tinymce.editors.hasOwnProperty(id)) {
            var editor = tinymce.editors[id];
            var elementName = (editor.targetElm && editor.targetElm.name) ? editor.targetElm.name.toLowerCase() : '';
            var elementId = (editor.targetElm && editor.targetElm.id) ? editor.targetElm.id.toLowerCase() : '';
            
            // Check if this is a summary editor
            if (elementName.includes('summary') || elementId.includes('summary')) {
              try {
                editor.setContent('');
                editor.save();
                L('Cleared TinyMCE summary editor:', id);
              } catch (e) {
                L('Error clearing TinyMCE:', e);
              }
            }
          }
        }
      }
      
      // Strategy 2: Clear textareas
      var textareas = document.querySelectorAll('textarea[id*="summary"], textarea[name*="summary"]');
      textareas.forEach(function(ta) {
        ta.value = '';
        try { ta.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
        L('Cleared textarea:', ta.id || ta.name);
      });
      
      // Strategy 3: Clear hidden inputs
      var hiddenInputs = document.querySelectorAll('input[type="hidden"][id*="summary"], input[type="hidden"][name*="summary"]');
      hiddenInputs.forEach(function(input) {
        input.value = '';
        L('Cleared hidden input:', input.id || input.name);
      });
      
      // Strategy 4: Clear ATTO editors
      var attoEditables = document.querySelectorAll('[id^="id_summary_editoreditable"], .editor_atto[contenteditable="true"]');
      attoEditables.forEach(function(editable) {
        editable.innerHTML = '';
        L('Cleared ATTO editable');
      });
      
      // Clear autosave drafts
      clearAutosave();
    }

    // Only run on course edit page
    if (!/\/course\/edit\.php$/.test(location.pathname)) return;
    
    L('Mounting AI Summary button');
    
    // Clear summary field immediately and on delays to catch all editor initializations
    forceClearSummaryField();
    setTimeout(forceClearSummaryField, 300);
    setTimeout(forceClearSummaryField, 1000);
    setTimeout(forceClearSummaryField, 2000);

    // Add the AI button
    setTimeout(function() {
      var wrap = document.querySelector('[id^="fitem_id_summary"], [data-fieldtype="editor"]');
      if (!wrap) {
        L('Summary field wrapper not found');
        return;
      }

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-secondary btn-sm local-aisummary-btn';
      btn.style.marginLeft = '8px';
      btn.style.marginTop = '5px';
      btn.textContent = 'Generate with AI';

      var label = wrap.querySelector('label') || wrap.querySelector('.col-form-label');
      var parent = (label && label.parentElement) ? label.parentElement : wrap;

      if (!parent.querySelector('.local-aisummary-btn')) {
        parent.appendChild(btn);
        btn.addEventListener('click', openFormAndGenerate);
        L('AI Summary button mounted successfully');
      }
    }, 500);
  }

  return {
    init: function () {
      try {
        L('AI Summary initializing');
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', mount);
        } else {
          mount();
        }
      } catch (e) {
        L('Init error', e);
      }
    }
  };
});