(function () {
  const page = document.getElementById('page');
  function clearBodyComp() {
    document.body.style.paddingRight = '';
    document.documentElement.style.paddingRight = '';
    if (page && !document.body.classList.contains('drawer-open-right')) {
      page.classList.remove('show-drawer-right');
    }
  }
  new MutationObserver(() => {
    if (!document.body.classList.contains('drawer-open-right')) clearBodyComp();
  }).observe(document.body, { attributes: true, attributeFilter: ['class','style'] });
  window.addEventListener('pageshow', clearBodyComp);
  window.addEventListener('resize', clearBodyComp);
  document.addEventListener('click', (e) => {
    if (e.target?.classList?.contains('drawer-backdrop')) setTimeout(clearBodyComp, 0);
  });
})();
