<?php
/**
 * Console Suppression Script
 * Include this in standalone pages (those not using index_layout.php)
 * to prevent console output from appearing in browser DevTools.
 */
?>
<script>
    (function () {
        // 1. Security Warning (Deterrent)
        const warningTitle = "STOP!";
        const warningMsg = "This feature is intended for developers only. If someone told you to copy-paste something here to enable a feature or 'hack' someone's account, it is a scam.";
        const warningStyleTitle = "color: red; font-size: 50px; font-weight: bold; text-shadow: 1px 1px 5px black;";
        const warningStyleMsg = "font-size: 18px; color: #333;";

        // 2. Continuous Console Clearing & Suppression with Persistent Warning
        const originalLog = console.log;
        const originalClear = console.clear;

        const showWarning = () => {
            try {
                if (originalClear) originalClear.apply(console);
                if (originalLog) {
                    originalLog.apply(console, ["%c" + warningTitle, warningStyleTitle]);
                    originalLog.apply(console, ["%c" + warningMsg, warningStyleMsg]);
                }
            } catch (e) { }
        };

        const noop = () => { };
        const methods = ['log', 'debug', 'info', 'warn', 'error', 'table', 'clear', 'time', 'timeEnd', 'group', 'groupEnd', 'trace', 'dir', 'assert'];

        function suppressConsole() {
            try {
                for (const method of methods) {
                    console[method] = noop;
                }
            } catch (e) { }
        }

        // Initial run
        showWarning();
        suppressConsole();

        // Persistent Warning Loop
        setInterval(() => {
            showWarning();
            suppressConsole();
        }, 2000); // Check every 2 seconds

        // 3. Disable Right Click
        window.addEventListener('contextmenu', e => e.preventDefault());

        // 4. Disable Keyboard Shortcuts (F12, Ctrl+Shift+I/J/C/U)
        window.addEventListener('keydown', function (e) {
            // F12
            if (e.key === 'F12' || e.keyCode === 123) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            // Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
            if (e.ctrlKey && e.shiftKey && (['I', 'J', 'C'].includes(e.key) || ['I', 'J', 'C'].includes(String.fromCharCode(e.keyCode)))) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            // Ctrl+U (View Source)
            if (e.ctrlKey && (e.key === 'u' || e.key === 'U' || e.keyCode === 85)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });

        // 5. Anti-Debugging (Debugger Loop)
        // This will pause execution if DevTools is open
        setInterval(function () {
            const startTime = performance.now();
            debugger; // Execution pauses here if DevTools is open
            const endTime = performance.now();

            if (endTime - startTime > 100) {
                // DevTools detection - closed it or paused
                console.clear();
                suppressConsole();
            }
        }, 100);

    })();
</script>