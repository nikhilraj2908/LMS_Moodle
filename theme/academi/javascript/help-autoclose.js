define(['jquery'], function ($) {
  'use strict';

  const HIDE_AFTER_MS = 10000; // 10 seconds
  const timers = new Map();

  function clearTimer(node) {
    const t = timers.get(node);
    if (t) { clearTimeout(t); timers.delete(node); }
  }

  function cleanTrigger($trig) {
    try {
      if ($trig.popover) $trig.popover('hide');
    } catch (e) {}
    const id = $trig.attr('aria-describedby');
    if (id) $('#' + id).remove();
    $trig.removeAttr('aria-describedby');
    clearTimer($trig[0]);
  }

  function hideAll() {
    $('.popover').remove();
    $('a.helptooltip[aria-describedby]').each(function () { cleanTrigger($(this)); });
  }

  function hideOthers($keep) {
    $('a.helptooltip[aria-describedby]').each(function () {
      if (!$keep || this !== $keep[0]) cleanTrigger($(this));
    });
  }

  function startTimer($trig) {
    const node = $trig[0];
    clearTimer(node);
    timers.set(node, setTimeout(() => cleanTrigger($trig), HIDE_AFTER_MS));
  }

  function init() {
    // When a help icon is clicked, close others and start a timer for the opened one.
    $('body').on('click', 'a.helptooltip', function () {
      const $el = $(this);
      hideOthers($el);
      // Let Moodle create the popover first, then start the timer.
      setTimeout(() => {
        if ($el.attr('aria-describedby')) startTimer($el);
      }, 0);
    });

    // Outside click closes all.
    $(document).on('mousedown', function (e) {
      const $t = $(e.target);
      if (!$t.closest('.popover, .helptooltip').length) hideAll();
    });

    // ESC closes all.
    $(document).on('keydown', function (e) {
      if (e.key === 'Escape') hideAll();
    });

    // Observe DOM for newly-created popovers (covers versions where BS events don't fire).
    const obs = new MutationObserver(muts => {
      muts.forEach(m => {
        m.addedNodes.forEach(node => {
          if (node.nodeType === 1 && node.classList.contains('popover')) {
            const id = node.getAttribute('id');
            if (!id) return;
            const $owner = $('a.helptooltip[aria-describedby="' + id + '"]');
            if ($owner.length) {
              hideOthers($owner);
              startTimer($owner);
            }
          }
        });
      });
    });
    obs.observe(document.body, { childList: true, subtree: true });

    // Debug hook (optional): uncomment to verify the module loaded.
    // console.log('[help_autoclose] loaded, helptooltips:', $('a.helptooltip').length);
  }

  return { init };
});
