/**
 * BlogWriter anti-tamper guards.
 * Blocks right-click and DevTools shortcuts on plugin pages (best-effort).
 */
(function () {
    'use strict';

    document.addEventListener('contextmenu', function (event) {
        if (window.BLOGWRITER_BLOCK_RIGHT_CLICK !== false) {
            event.preventDefault();
        }
    });

    var lastTime = 0;
    document.addEventListener('keydown', function (event) {
        // F12
        if (event.key === 'F12') {
            event.preventDefault();
        }
        // Ctrl/Cmd + Shift + I / J / C
        if ((event.ctrlKey || event.metaKey) && event.shiftKey &&
            ['I', 'J', 'C'].indexOf(String(event.key).toUpperCase()) !== -1) {
            event.preventDefault();
        }
        // Ctrl/Cmd + U (view source)
        if ((event.ctrlKey || event.metaKey) && String(event.key).toUpperCase() === 'U') {
            event.preventDefault();
        }
    });

    // Simple debugger trap.
    setInterval(function () {
        if (window.outerWidth - window.innerWidth > 160 && window.outerHeight - window.innerHeight > 160) {
            lastTime = Date.now();
        }
        if (Date.now() - lastTime > 3000) {
            debugger; // eslint-disable-line no-debugger
        }
    }, 500);
})();
