<!-- PWA Manifest & Mobile Configuration -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="manifest" href="manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="WeRide">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<meta name="theme-color" content="#0f172a">

<script>
// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
            .then(reg => {
                // Check for updates
                reg.update();
            })
            .catch(err => console.log('WeRide PWA registration skipped:', err));
    });
}
</script>
<script src="assets/js/push_notifications.js?v=<?php echo time(); ?>"></script>
<script>
// Global PWA Install Helper
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // Show any install buttons on the page
    const installBtns = document.querySelectorAll('.pwa-install-btn, #pwaInstallBanner');
    installBtns.forEach(btn => {
        btn.style.display = 'inline-flex';
    });
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    const installBtns = document.querySelectorAll('.pwa-install-btn, #pwaInstallBanner');
    installBtns.forEach(btn => btn.style.display = 'none');
    console.log('WeRide PWA was installed successfully.');
});

function promptPwaInstall() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted PWA installation');
            }
            deferredPrompt = null;
        });
    } else {
        // Detect iOS
        const isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
        
        if (isIos && !isStandalone) {
            alert("To install WeRide on your iPhone:\n\n1. Tap the Share button at the bottom (square with arrow ↑)\n2. Scroll down and tap 'Add to Home Screen' (+)\n3. Tap 'Add' at the top right!");
        } else if (isStandalone) {
            alert("WeRide is already installed and running as an app!");
        } else {
            alert("To install WeRide on your device, use your browser menu (⋮) and select 'Install app' or 'Add to Home Screen'.");
        }
    }
}
</script>
<?php include_once __DIR__ . '/boot_screen.php'; ?>
