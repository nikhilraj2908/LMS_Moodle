/* eslint-disable */
define(['core/ajax','core/notification'],function(Ajax,Notification){'use strict';
  function L(){ try{ console.log('[AISUM]', ...arguments); }catch(e){} }

  function findTinySummaryEditor(){
    try{
      if(!(window.tinymce&&tinymce.editors&&tinymce.editors.length))return null;
      // Try the usual Moodle id first
      var ed=tinymce.get&&tinymce.get('id_summary_editor'); if(ed) return ed;
      // Otherwise pick any editor whose id/target name/id contains "summary"
      for(var i=0;i<tinymce.editors.length;i++){
        ed=tinymce.editors[i]; if(!ed) continue;
        var tid=(ed.id||'').toLowerCase();
        var tnm=(ed.targetElm&&(ed.targetElm.name||ed.targetElm.id||'')).toLowerCase();
        if(tid.includes('summary')||tnm.includes('summary')) return ed;
      }
    }catch(e){ L('findTinySummaryEditor error',e); }
    return null;
  }

  function clearAutosave(){
    try{
      var ed=findTinySummaryEditor();
      if(ed&&ed.plugins&&ed.plugins.autosave&&typeof ed.plugins.autosave.removeDraft==='function'){
        try{ed.plugins.autosave.removeDraft();}catch(e){}
      }
      try{
        for(var k in localStorage){
          if(!Object.prototype.hasOwnProperty.call(localStorage,k))continue;
          if(k.indexOf('tinymce-autosave')===0||k.indexOf('atto_autosave')===0)localStorage.removeItem(k);
        }
      }catch(e){}
      setTimeout(function(){try{
        document.querySelectorAll('.tox .tox-notification,.atto_autosave_message').forEach(function(el){el.style.display='none';});
      }catch(e){}},150);
    }catch(e){}
  }

  function setSummaryHTML(html){
    L('setSummaryHTML start, len=', (html||'').length);
    var updated=false, ed=findTinySummaryEditor();

    // Preferred: TinyMCE API
    if(ed){
      try{
        ed.setContent(html); ed.fire('change'); ed.save(); updated=true;
        L('TinyMCE setContent/save OK:', ed.id);
      }catch(e){ L('TinyMCE API error',e); }
    }

    // Fallback 1: ATTO (contenteditable)
    if(!updated){
      var editable=document.querySelector('[id^="id_summary_editoreditable"]')||
                     document.querySelector('.editor_atto [contenteditable="true"]');
      if(editable){ editable.innerHTML=html.replace(/\n/g,'<br>'); updated=true; L('ATTO innerHTML set'); }
    }

    // Fallback 2: TinyMCE iframe (visible area) inside the Summary field
    if(!updated){
      var ifr=document.querySelector('[id^="fitem_id_summary"] iframe.tox-edit-area__iframe')||
               document.querySelector('textarea#id_summary_editor ~ .tox .tox-edit-area__iframe');
      if(ifr&&ifr.contentWindow&&ifr.contentWindow.document){
        try{
          var doc=ifr.contentWindow.document;
          doc.body.innerHTML=html;
          updated=true; L('TinyMCE iframe body set');
        }catch(e){ L('iframe write error',e); }
      }
    }

    // Keep underlying value in sync (what Moodle submits)
    var hidden=document.querySelector('#id_summary_editor')||
                document.querySelector('input[name="summary_editor[text]"]')||
                document.querySelector('textarea[name="summary_editor[text]"]');
    if(hidden){ hidden.value=html; try{ hidden.dispatchEvent(new Event('change',{bubbles:true})); }catch(e){} L('hidden value set'); }

    // Raw textarea fallback (some themes expose it)
    var raw=document.querySelector('#id_summary'); if(raw){ raw.value=html; L('raw textarea set'); }

    clearAutosave();
    L('setSummaryHTML done');
  }

  function onClick(btn){
    try{
      L('click');
      var title='', f=document.querySelector('#id_fullname'); if(f&&f.value){title=f.value.trim();}
      if(!title){var s=document.querySelector('#id_shortname'); if(s&&s.value){title=s.value.trim();}}
      if(!title){Notification.alert('AI Summary','Please enter a Course full name first.','OK');return;}

      btn.disabled=true; btn.textContent='Generating...';

      var reqs=Ajax.call([{methodname:'local_aisummary_generate_summary',args:{title:title},fail:Notification.exception}]);
      reqs[0].then(function(resp){
        var summary=(resp&&resp.summary)?resp.summary:'';
        L('response len=', (summary||'').length);
        if(!summary){ btn.disabled=false; btn.textContent='Generate with AI'; Notification.alert('AI Summary','The AI returned empty text.','OK'); return; }
        clearAutosave();          // defensive: drop any stale draft
        setSummaryHTML(summary);  // write into editor
        btn.disabled=false; btn.textContent='Regenerate';
      }).catch(function(err){
        btn.disabled=false; btn.textContent='Generate with AI';
        L('ajax error', err); Notification.exception(err);
      });
    }catch(e){
      btn.disabled=false; btn.textContent='Generate with AI';
      L('click error', e); Notification.exception(e);
    }
  }

  function mount(){
    if(!/\/course\/edit\.php$/.test(location.pathname)) return;
    L('mount');
    try{var p=new URLSearchParams(location.search);var editing=p.has('id')&&p.get('id');if(!editing)setTimeout(clearAutosave,500);}catch(e){}
    var wrap=document.querySelector('[id^="fitem_id_summary"]')||(function(){var el=document.querySelector('#id_summary_editoreditable');return el?el.closest('.fitem'):null;})();
    if(!wrap){ L('wrap not found'); return; }
    var btn=document.createElement('button');
    btn.type='button'; btn.className='btn btn-secondary'; btn.style.marginLeft='8px'; btn.textContent='Generate with AI';
    var label=wrap.querySelector('label')||wrap.querySelector('.col-form-label'); (label&&label.parentElement?label.parentElement:wrap).appendChild(btn);
    btn.addEventListener('click', function(){ onClick(btn); });
    L('button added');
  }
  return { init:function(){ try{ window.__AISUM_VER='1.3.1'; L('init'); mount(); }catch(e){ L('init error',e); } } };
});
