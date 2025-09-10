/* eslint-disable */
define(['core/ajax','core/notification'],function(Ajax,Notification){'use strict';
  function q(s){return document.querySelector(s);}
  function setSummaryHTML(html){
    var text=(html||'').trim();
    if(window.tinymce&&window.tinymce.editors&&window.tinymce.editors.length){
      for(var i=0;i<tinymce.editors.length;i++){
        var ed=tinymce.editors[i]; if(!ed||!ed.id) continue;
        if(ed.id.indexOf('summary')!==-1 || /summary/i.test(ed.targetElm&&ed.targetElm.id||'')){
          try{ ed.setContent(text); ed.fire&&ed.fire('change'); }catch(e){}
        }
      }
    }
    var editable=q('[id^="id_summary_editoreditable"]')||q('.editor_atto [contenteditable="true"]');
    if(editable){ editable.innerHTML=text.replace(/\n/g,'<br>'); }
    var hidden=q('#id_summary_editor')||q('input[name="summary_editor[text]"]')||q('textarea[name="summary_editor[text]"]');
    if(hidden){ hidden.value=text; try{ hidden.dispatchEvent(new Event('change',{bubbles:true})); }catch(e){} }
  }
  function callAI(title){
    return Ajax.call([{methodname:'local_aisummary_generate_summary',args:{title:title},fail:Notification.exception}])[0];
  }
  function onClick(e,autosave){
    e.preventDefault();
    var title=(q('#id_fullname')&&q('#id_fullname').value||'').trim()||(q('#id_shortname')&&q('#id_shortname').value||'').trim();
    if(!title) return Notification.alert('AI Summary','Please enter a Course full name first.','OK');
    var btn=e.currentTarget; btn.disabled=true; btn.textContent=autosave?'Generating & Saving…':'Generating…';
    callAI(title).then(function(resp){
      var summary=resp&&resp.summary?String(resp.summary).trim():'';
      if(!summary){ btn.textContent=autosave?'Generate & Save':'Generate with AI'; btn.disabled=false; return Notification.alert('AI Summary','The AI returned empty text. Try another model in plugin settings (e.g., gemma-2-9b-it:free).','OK'); }
      setSummaryHTML(summary); btn.textContent='Regenerate'; btn.disabled=false;
      if(autosave){ var submit=q('#id_saveanddisplay')||q('#id_saveandreturn')||q('button[type="submit"].btn-primary')||q('button[type="submit"]'); submit&&submit.click(); }
    }).catch(function(err){ btn.textContent=autosave?'Generate & Save':'Generate with AI'; btn.disabled=false; Notification.exception(err); });
  }
  function mount(){
    if(!/\/course\/edit\.php$/.test(location.pathname)) return;
    var wrap=q('[id^="fitem_id_summary"]')||(function(){ var el=q('#id_summary_editoreditable'); return el?el.closest('.fitem'):null; })();
    if(!wrap) return;
    function makeBtn(txt){ var b=document.createElement('button'); b.type='button'; b.className='btn btn-secondary'; b.style.marginLeft='8px'; b.textContent=txt; return b; }
    var genBtn=makeBtn('Generate with AI'); var genSaveBtn=makeBtn('Generate & Save');
    var label=wrap.querySelector('label')||wrap.querySelector('.col-form-label'); (label&&label.parentElement?label.parentElement:wrap).appendChild(genBtn); (label&&label.parentElement?label.parentElement:wrap).appendChild(genSaveBtn);
    genBtn.addEventListener('click',function(e){ onClick(e,false); }); genSaveBtn.addEventListener('click',function(e){ onClick(e,true); });
  }
  return { init:function(){ try{ mount(); }catch(e){} } };
});
