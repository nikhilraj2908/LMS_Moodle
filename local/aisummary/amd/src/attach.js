/* eslint-disable */
define(['core/ajax','core/notification'],function(Ajax,Notification){'use strict';

  function setSummaryHTML(html){
    var updated=false;

    // TinyMCE 6: update any editor whose id includes "summary"
    if (window.tinymce && window.tinymce.editors && window.tinymce.editors.length){
      for (var i=0;i<tinymce.editors.length;i++){
        var ed=tinymce.editors[i];
        if (!ed || !ed.id) continue;
        if (ed.id.indexOf('summary') !== -1){ try{ ed.setContent(html);}catch(e){} updated=true; }
      }
    }

    // Atto / contenteditable visual area
    if (!updated){
      var editable =
        document.querySelector('[id^="id_summary_editoreditable"]') ||
        document.querySelector('.editor_atto [contenteditable="true"]');
      if (editable){ editable.innerHTML = html.replace(/\n/g,'<br>'); updated=true; }
    }

    // Hidden input that Moodle actually submits
    var hidden = document.querySelector('#id_summary_editor') ||
                 document.querySelector('input[name="summary_editor[text]"]') ||
                 document.querySelector('textarea[name="summary_editor[text]"]');
    if (hidden){
      hidden.value = html;
      try{ hidden.dispatchEvent(new Event('change',{bubbles:true})); }catch(e){}
    }
  }

  function onClick(btn){
    try{
      var title='', f=document.querySelector('#id_fullname'); if(f&&f.value){title=f.value.trim();}
      if(!title){ var s=document.querySelector('#id_shortname'); if(s&&s.value){title=s.value.trim();} }
      if(!title){ Notification.alert('AI Summary','Please enter a Course full name first.','OK'); return; }

      btn.disabled=true; btn.textContent='Generating...';

      var reqs = Ajax.call([{
        methodname:'local_aisummary_generate_summary',
        args:{ title:title },
        fail:Notification.exception
      }]);

      reqs[0].then(function(resp){
        var summary = (resp && resp.summary) ? resp.summary : '';
        if(!summary){
          btn.disabled=false; btn.textContent='Generate with AI';
          Notification.alert('AI Summary','The AI returned empty text. Try again or change the model.','OK');
          return;
        }
        setSummaryHTML(summary);
        btn.disabled=false; btn.textContent='Regenerate';
      }).catch(function(err){
        btn.disabled=false; btn.textContent='Generate with AI';
        Notification.exception(err);
      });
    }catch(e){
      btn.disabled=false; btn.textContent='Generate with AI';
      Notification.exception(e);
    }
  }

  function mount(){
    if(!/\/course\/edit\.php$/.test(location.pathname)) return;

    var wrap = document.querySelector('[id^="fitem_id_summary"]') ||
               (function(){var el=document.querySelector('#id_summary_editoreditable'); return el?el.closest('.fitem'):null; })();
    if(!wrap) return;

    var btn = document.createElement('button');
    btn.type='button'; btn.className='btn btn-secondary';
    btn.style.marginLeft='8px'; btn.textContent='Generate with AI';

    var label = wrap.querySelector('label') || wrap.querySelector('.col-form-label');
    (label && label.parentElement ? label.parentElement : wrap).appendChild(btn);

    btn.addEventListener('click', function(){ onClick(btn); });
  }

  return { init:function(){ try{ mount(); }catch(e){} } };
});
