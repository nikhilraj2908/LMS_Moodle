/* eslint-disable */
define([], function () {
  const playerTpl = (cfg) => `
<div class="tts-player" id="ttsPlayer" style="--tts-highlight:${cfg.highlight};">
  <div class="tts-header my-3">
    <h3 class="tts-title">Text-to-Speech Player</h3>
    <button id="ttsClose" class="tts-close" title="Close" aria-label="Close">×</button>
  </div>

  <div class="row tts-actions mb-2">
    <button id="ttsPlay"  class="tts-btn tts-btn--play"  aria-label="Play">
      <span class="tts-ic">▶</span><span class="tts-label">Play</span>
    </button>
    <button id="ttsPause" class="tts-btn tts-btn--pause" aria-label="Pause" disabled>
      <span class="tts-ic">‖</span><span class="tts-label">Pause</span>
    </button>
    <button id="ttsStop"  class="tts-btn tts-btn--stop"  aria-label="Stop" disabled>
      <span class="tts-ic">■</span><span class="tts-label">Stop</span>
    </button>
  </div>

  <div class="row tts-speed mb-2">
    <label class="tts-left">Speed</label>
    <span id="ttsRateVal" class="tts-right">${(Number(cfg.defaultSpeed) || 1).toFixed(1)}x</span>
    <input id="ttsRate" type="range" min="0.5" max="2" step="0.1" value="${cfg.defaultSpeed}" aria-label="Speed">
  </div>

  <div class="row">
    <label class="tts-left">Voice</label>
    <select id="ttsVoice" aria-label="Voice"></select>
  </div>

  <div class="row tts-color-row">
    <label class="tts-left">Highlight</label>
    <span id="ttsChange" class="tts-right tts-change" role="button" tabindex="0">Change</span>
    <input id="ttsColor" type="color" value="${cfg.highlight}" aria-label="Highlight color">
  </div>
</div>

<div id="ttsBubble" class="tts-bubble"${cfg.showBubble ? '' : ' style="display:none"'}>🔊 Listen</div>
`;

  // ------- state -------
  let cfg = { defaultSpeed: 1.0, highlight: '#c8facc', showBubble: true };
  let player, bubble, btnPlay, btnPause, btnStop, rate, rateVal, voiceSel, colorInp, btnClose, changeBtn;
  let selectionText = '', selectionRange = null, activeNodes = [], bubbleTimer = null;
  let ttsAvailable = false, status = 'idle'; // idle | speaking | paused
  let utter = null;

  // ------- utils -------
  const byId = (id) => document.getElementById(id);
  const setBtns = (s) => {
    status = s;
    if (btnPlay)  btnPlay.disabled  = (s === 'speaking');
    if (btnPause) btnPause.disabled = !(s === 'speaking');
    if (btnStop)  btnStop.disabled  = (s === 'idle');
  };
  function clearNativeSelection(){
    try { const sel = window.getSelection(); if (sel?.removeAllRanges) sel.removeAllRanges(); } catch(e){}
  }
  function hexToRgba(hex, a){
    let h = (hex||'').replace('#','').trim(); if (h.length===3) h = h.split('').map(c=>c+c).join('');
    const n = parseInt(h, 16); if (isNaN(n)) return `rgba(200,250,204,${a})`;
    const r=(n>>16)&255, g=(n>>8)&255, b=n&255; return `rgba(${r},${g},${b},${a})`;
  }

  // ------- UI -------
  function mountUI(){
    if (byId('ttsPlayer')) return;
    document.body.insertAdjacentHTML('beforeend', playerTpl(cfg));

    player   = byId('ttsPlayer');
    bubble   = byId('ttsBubble');
    btnPlay  = byId('ttsPlay');
    btnPause = byId('ttsPause');
    btnStop  = byId('ttsStop');
    rate     = byId('ttsRate');
    rateVal  = byId('ttsRateVal');
    voiceSel = byId('ttsVoice');
    colorInp = byId('ttsColor');
    btnClose = byId('ttsClose');
    changeBtn= byId('ttsChange');

    player.style.zIndex = '2147483647';
    bubble.style.zIndex = '2147483647';

    // Buttons
    btnPlay.addEventListener('click', onPlayClick);
    btnPause.addEventListener('click', onPauseClick);
    btnStop.addEventListener('click', stopSpeaking);
    btnClose.addEventListener('click', hidePlayer);
    document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') hidePlayer(); });
    document.addEventListener('mousedown', (e)=>{
      const inside = player.contains(e.target) || (bubble && bubble.contains(e.target));
      if (!inside) hidePlayer();
    });

    // Speed
    rate.addEventListener('input', ()=>{
      const v = parseFloat(rate.value || '1');
      rateVal.textContent = `${v.toFixed(1)}x`;
      if (utter) utter.rate = v;
    });

    // Color
    colorInp.addEventListener('input', ()=>{
      player.style.setProperty('--tts-highlight', colorInp.value);
      const light = hexToRgba(colorInp.value, 0.25);
      activeNodes.forEach(n => { if (!n.classList.contains('tts-current-word')) n.style.background = light; });
    });
    const triggerColor = () => colorInp.click();
    changeBtn.addEventListener('click', triggerColor);
    changeBtn.addEventListener('keydown', (e)=>{ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); triggerColor(); } });

    // Voices
    function fillVoices(){
      try{
        const voices = window.speechSynthesis?.getVoices?.() || [];
        voiceSel.innerHTML = '';
        voices.forEach((v,i)=>{
          const o = document.createElement('option');
          o.value = i; o.textContent = `${v.name} (${v.lang})${v.default ? ' — default' : ''}`;
          voiceSel.appendChild(o);
        });
      }catch(e){}
    }
    fillVoices();
    if (window.speechSynthesis && speechSynthesis.onvoiceschanged !== undefined)
      speechSynthesis.onvoiceschanged = fillVoices;

    setBtns('idle');
  }

  function showPlayer(){ player.style.display = 'block'; }
  function hidePlayer(){ player.style.display = 'none'; hideBubble(); stopSpeaking(); }

  // ------- selection + 1s debounce bubble -------
  function getSelectionData(){
    const sel = window.getSelection();
    if (!sel || sel.isCollapsed) return { text:'', range:null, rect:null };
    const text = sel.toString(); if (!text?.trim()) return { text:'', range:null, rect:null };
    const range = sel.getRangeAt(0).cloneRange();
    const rect = range.getBoundingClientRect();
    return { text: text.trim(), range, rect };
  }
  function positionBubble(rect){
    bubble.style.left = `${(rect.right||rect.x||0)+window.scrollX+8}px`;
    bubble.style.top  = `${(rect.top||rect.y||0)+window.scrollY-6}px`;
    bubble.style.display = 'inline-block';
  }
  function hideBubble(){ bubble.style.display = 'none'; }
  function attachSelectionHandlers(){
    const handler = ()=>{
      const {text, range, rect} = getSelectionData();
      selectionText = text; selectionRange = range;
      if (bubbleTimer) clearTimeout(bubbleTimer);
      hideBubble();
      if (!text) return;
      bubbleTimer = setTimeout(()=>{ if (cfg.showBubble) positionBubble(rect); }, 1000);
    };
    ['selectionchange','mouseup','keyup'].forEach(evt => document.addEventListener(evt, handler));
    bubble.addEventListener('click', ()=>{ showPlayer(); startSpeaking(); });
  }

  // ------- highlighting (robust across multi-node selections) -------
  function clearHighlights(){
    activeNodes.forEach(n => { n.classList.remove('tts-current-word','tts-word'); n.style.background=''; });
    activeNodes = [];
  }
  function wrapWords(range){
    try{
      clearHighlights();
      const doc = range.commonAncestorContainer.ownerDocument || document;
      const walker = doc.createTreeWalker(range.commonAncestorContainer, NodeFilter.SHOW_TEXT, {
        acceptNode(node){
          if (!node.nodeValue || !node.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
          const nr = doc.createRange(); nr.selectNodeContents(node);
          return (range.compareBoundaryPoints(Range.END_TO_START, nr) < 0 &&
                  range.compareBoundaryPoints(Range.START_TO_END, nr) > 0)
            ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
        }
      });

      const baseBg = hexToRgba(colorInp ? colorInp.value : cfg.highlight, 0.25);
      const targets = []; while (walker.nextNode()) targets.push(walker.currentNode);

      targets.forEach(node=>{
        const txt = node.nodeValue;
        const start = (node===range.startContainer) ? range.startOffset : 0;
        const end   = (node===range.endContainer)   ? range.endOffset   : txt.length;
        if (start===end) return;

        const before = txt.slice(0,start), middle = txt.slice(start,end), after = txt.slice(end);
        const frag = doc.createDocumentFragment();

        if (before) frag.appendChild(doc.createTextNode(before));

        const pieces = middle.match(/\S+|\s+/g) || [middle];
        pieces.forEach(w=>{
          if (/\s+/.test(w)) frag.appendChild(doc.createTextNode(w));
          else {
            const s = doc.createElement('span');
            s.textContent = w;
            s.className = 'tts-word';
            s.style.background = baseBg;
            frag.appendChild(s);
            activeNodes.push(s);
          }
        });

        if (after) frag.appendChild(doc.createTextNode(after));
        node.parentNode.replaceChild(frag, node);
      });
    }catch(e){
      activeNodes = []; // graceful fallback
    }
  }

  // ------- TTS -------
  function wireUtter(u){
    u.onboundary = () => {
      if (!activeNodes.length) return;
      activeNodes.forEach(n => n.classList.remove('tts-current-word'));
      const next = activeNodes.shift();
      if (next){ next.classList.add('tts-current-word'); activeNodes.push(next); }
    };
    u.onend = () => { setBtns('idle'); clearHighlights(); };
    u.onerror = () => { setBtns('idle'); clearHighlights(); };
  }
  function buildUtter(text){
    const u = new SpeechSynthesisUtterance(text);
    u.rate = parseFloat(rate.value || cfg.defaultSpeed);
    const voices = speechSynthesis.getVoices(); const idx = parseInt(voiceSel.value || '0', 10);
    if (voices[idx]) u.voice = voices[idx];
    wireUtter(u);
    return u;
  }

  function startSpeaking(){
    if (!selectionText){
      const container = document.querySelector('#region-main, .course-content, main, .content, body');
      selectionText = container ? (container.innerText||'').trim() : (document.body.innerText||'').trim();
      selectionRange = null;
    }
    if (!selectionText) return;

    showPlayer(); hideBubble(); stopSpeaking(); // reset
    if (selectionRange){ wrapWords(selectionRange); clearNativeSelection(); }

    if (!ttsAvailable){ console.warn('[TTS] speechSynthesis not available.'); return; }
    utter = buildUtter(selectionText);
    speechSynthesis.speak(utter);
    setBtns('speaking');
  }
  function onPlayClick(){
    if (!ttsAvailable){ startSpeaking(); return; }
    if (status === 'paused'){ try{ speechSynthesis.resume(); }catch(e){} setBtns('speaking'); return; }
    if (status === 'speaking') return;
    startSpeaking();
  }
  function onPauseClick(){
    if (!ttsAvailable || status !== 'speaking') return;
    try{ speechSynthesis.pause(); }catch(e){}
    setBtns('paused');
  }
  function stopSpeaking(){
    try{ if (window.speechSynthesis) speechSynthesis.cancel(); }catch(e){}
    clearHighlights(); setBtns('idle');
  }

  // ------- init -------
  return {
    init(options){
      cfg = Object.assign({}, cfg, options || {});
      mountUI();
      attachSelectionHandlers();
      ttsAvailable = !!(window.speechSynthesis && window.SpeechSynthesisUtterance);
    }
  };
});
