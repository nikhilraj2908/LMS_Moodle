(function () {
  const exitUrl = window.exitUrl;

  function sendExit() {
    if (exitUrl) {
      console.log("📤 Sending exit beacon to:", exitUrl);
      navigator.sendBeacon(exitUrl);
    } else {
      console.log("⚠️ No exit URL found.");
    }
  }

  window.addEventListener('beforeunload', sendExit);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
      sendExit();
    }
  });

  const exitBtn = document.querySelector('#exitactivity') ||
    Array.from(document.querySelectorAll('.btn.btn-secondary'))
      .find(btn => btn.textContent.includes('Exit activity'));

  if (exitBtn) {
    exitBtn.addEventListener('click', function () {
      console.log("🖱️ Exit button clicked.");
      sendExit();
    });
  } else {
    console.log("❌ Exit button not found.");
  }
})();
