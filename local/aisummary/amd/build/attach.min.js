/* eslint-disable */
define(['core/ajax','core/notification'],function(Ajax,Notification){'use strict';
  function findTinySummaryEditor(){
    if(!(window.tinymce&&tinymce.editors&&tinymce.editors.length))return null;
    var ed=tinymce.get&&tinymce.get('id_summary_editor'); if(ed)return ed;
    for(var i=0;i<tinymce.editors.length;i++){
      ed=tinymce.editors[i]; if(!ed)continue;
      var tid=(ed.id||'').toLowerCase();
      var tnm=(ed.targetElm&&(ed.targetElm.name||ed.targetElm.id||'')).toLowerCase();
      if(tid.includes('summary')||tnm.includes('summary')) return ed;
    }
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
    var updated=false,ed=findTinySummaryEditor();
    if(ed){try{ed.setContent(html);ed.fire('change');ed.save();updated=true;}catch(e){}}
    if(!updated){
      var editable=document.querySelector('[id^="id_summary_editoreditable"]')||document.querySelector('.editor_atto [contenteditable="true"]');
      if(editable){editable.innerHTML=html.replace(/\n/g,'<br>');updated=true;}
    }
    var hidden=document.querySelector('#id_summary_editor')||document.querySelector('input[name="summary_editor[text]"]')||document.querySelector('textarea[name="summary_editor[text]"]');
    if(hidden){hidden.value=html;try{hidden.dispatchEvent(new Event('change',{bubbles:true}));}catch(e){}}
    var raw=document.querySelector('#id_summary'); if(raw){raw.value=html;}
    clearAutosave();
  }
  function onClick(btn){
    try{
      var title='',f=document.querySelector('#id_fullname'); if(f&&f.value){title=f.value.trim();}
      if(!title){var s=document.querySelector('#id_shortname'); if(s&&s.value){title=s.value.trim();}}
      if(!title){Notification.alert('AI Summary','Please enter a Course full name first.','OK');return;}
      btn.disabled=true;btn.textContent='Generating...';
      var reqs=Ajax.call([{methodname:'local_aisummary_generate_summary',args:{title:title},fail:Notification.exception}]);
      reqs[0].then(function(resp){
        var summary=(resp&&resp.summary)?resp.summary:'';
        if(!summary){btn.disabled=false;btn.textContent='Generate with AI';Notification.alert('AI Summary','The AI returned empty text.','OK');return;}
        setSummaryHTML(summary);
        setTimeout(function(){try{
          document.querySelectorAll('.tox .tox-notification,.atto_autosave_message').forEach(function(el){el.style.display='none';});
        }catch(e){}},200);
        btn.disabled=false;btn.textContent='Regenerate';
      }).catch(function(err){btn.disabled=false;btn.textContent='Generate with AI';Notification.exception(err);});
    }catch(e){btn.disabled=false;btn.textContent='Generate with AI';Notification.exception(e);}
  }
  function mount(){
    if(!/\/course\/edit\.php$/.test(location.pathname))return;
    try{var p=new URLSearchParams(location.search);var editing=p.has('id')&&p.get('id');if(!editing)setTimeout(clearAutosave,500);}catch(e){}
    var wrap=document.querySelector('[id^="fitem_id_summary"]')||(function(){var el=document.querySelector('#id_summary_editoreditable');return el?el.closest('.fitem'):null;})();
    if(!wrap)return;
    var btn=document.createElement('button');btn.type='button';btn.className='btn btn-secondary';btn.style.marginLeft='8px';btn.textContent='Generate with AI';
    var label=wrap.querySelector('label')||wrap.querySelector('.col-form-label');(label&&label.parentElement?label.parentElement:wrap).appendChild(btn);
    btn.addEventListener('click',function(){onClick(btn);});
  }
  return{init:function(){try{mount();}catch(e){}}};
});
