import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

import './player.js';
import './studio.js';

// Actual link-sharing action. Never rely on an inert or demo CTA.
document.querySelectorAll('[data-share-url]').forEach((button) => {
    button.addEventListener('click', async () => {
        const url = button.dataset.shareUrl;
        const original = button.textContent;
        if (!url) return;
        try {
            if (navigator.share) {
                await navigator.share({ title: document.title, url });
            } else if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(url);
                button.textContent = '✓ Link copied';
                window.setTimeout(() => { button.textContent = original; }, 2500);
            } else {
                // Clipboard requires browser/HTTPS permissions; show the URL
                // in a native prompt so it can still be copied.
                window.prompt('Copy this Gomovie title link', url);
            }
        } catch (error) {
            // User cancellation is not an error; never expose exception data.
            if (error?.name !== 'AbortError') {
                button.textContent = 'Share unavailable';
                window.setTimeout(() => { button.textContent = original; }, 2500);
            }
        }
    });
});
