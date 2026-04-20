/**
 * OCNHS CLINIC SYSTEM - SECURITY GUARD
 * Prevents unauthorized inspection of source code.
 */

(function () {
    // 1. CONSOLE WARNING MESSAGE
    const warningTitle = "STOP!";
    const warningMsg = "This feature is intended for developers only. If someone told you to copy-paste something here to enable a feature or 'hack' someone's account, it is a scam and will give them access to your account.";
    const warningStyleTitle = "color: red; font-size: 50px; font-weight: bold; text-shadow: 1px 1px 5px black;";
    const warningStyleMsg = "font-size: 18px; color: #333;";

    console.log("%c" + warningTitle, warningStyleTitle);
    console.log("%c" + warningMsg, warningStyleMsg);

    // 2. DISABLE RIGHT CLICK
    document.addEventListener('contextmenu', function (e) {
        e.preventDefault();
    });

    // 3. DISABLE INSPECT SHORTCUTS (F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U)
    document.addEventListener('keydown', function (e) {
        // F12
        if (e.key === 'F12' || e.keyCode === 123) {
            e.preventDefault();
            return false;
        }

        // Ctrl+Shift+I (Inspect) or Ctrl+Shift+J (Console) or Ctrl+Shift+C (Element)
        if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C' || e.key === 'i' || e.key === 'j' || e.key === 'c')) {
            e.preventDefault();
            return false;
        }

        // Ctrl+U (View Source)
        if (e.ctrlKey && (e.key === 'U' || e.key === 'u')) {
            e.preventDefault();
            return false;
        }
    });

    // 4. ANTI-DEBUGGING (Soft)
    // Checking if DevTools is open by measuring window vs inner dimensions difference threshold.
    // This is less aggressive than an infinite loop.
    setInterval(function () {
        if ((window.outerWidth - window.innerWidth) > 200 || (window.outerHeight - window.innerHeight) > 200) {
            // Provide a vague warning or clear console
            console.clear();
            console.log("%c" + warningTitle, warningStyleTitle);
            console.log("%cSystem Security Active. Please close Developer Tools.", warningStyleMsg);
        }
    }, 1000);

})();
