console.log("✅ topbuttons.js is loading...");

// Watch DOM changes until both form and buttons are loaded
const observer = new MutationObserver(() => {
    const form = document.querySelector('form#mform1');
    const submitButton = document.querySelector('#id_submitbutton2'); // Save and return to course

    if (form && submitButton && !form.querySelector('.top-clone')) {
        // Traverse up to find the correct wrapper div
        let buttonWrapper = submitButton.closest('div');

        // Walk up until we find the parent that contains ALL buttons
        while (buttonWrapper && buttonWrapper.querySelectorAll('input[type="submit"]').length < 2) {
            buttonWrapper = buttonWrapper.parentElement;
        }

        if (!buttonWrapper) {
            console.log("❌ Could not find common button wrapper.");
            return;
        }

        const clone = buttonWrapper.cloneNode(true);
        clone.classList.add('top-clone');
        clone.style.marginBottom = '20px';

        form.insertBefore(clone, form.firstChild);
        console.log("✅ Buttons cloned to top.");.
        observer.disconnect();
    }
});

observer.observe(document, { childList: true, subtree: true });
