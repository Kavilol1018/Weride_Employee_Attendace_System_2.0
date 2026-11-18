<?php
// WeRide System Startup Cinematic Logo Zoom and Fade Out to Web (Option 1: Once per app session)
?>
<!-- WeRide Logo Zoom and Fade Out Component -->
<style id="weride-intro-style">
    #weride-intro-screen {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100vw;
        height: 100vh;
        height: 100dvh;
        background: #070b14;
        background: radial-gradient(circle at center, #0f1c33 0%, #070b14 75%);
        z-index: 2147483647; /* Top layer */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        overflow: hidden;
        user-select: none;
        -webkit-user-select: none;
        cursor: pointer;
        opacity: 1;
        transition: opacity 0.8s cubic-bezier(0.25, 1, 0.5, 1);
    }

    #weride-intro-screen.intro-fading {
        opacity: 0 !important;
        pointer-events: none !important;
    }

    /* Ambient Cyan Glow */
    .weride-intro-glow {
        position: absolute;
        width: 360px;
        height: 360px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(0, 166, 214, 0.45) 0%, rgba(56, 189, 248, 0.12) 50%, transparent 70%);
        filter: blur(48px);
        pointer-events: none;
        transform: scale(1);
        opacity: 1;
        transition: transform 1.2s cubic-bezier(0.25, 1, 0.5, 1), opacity 0.8s ease;
    }

    /* Logo Centerpiece */
    .weride-intro-logo-box {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 2;
        transform-origin: center center;
        transform: scale(1);
        opacity: 1;
        transition: transform 1.1s cubic-bezier(0.25, 1, 0.5, 1), opacity 0.9s cubic-bezier(0.25, 1, 0.5, 1);
    }

    .weride-intro-logo-img {
        width: 120px;
        height: 120px;
        object-fit: contain;
        filter: drop-shadow(0 0 25px rgba(0, 166, 214, 0.95)) drop-shadow(0 0 10px #38bdf8);
    }

    .weride-intro-brand {
        margin-top: 16px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 28px;
        font-weight: 900;
        letter-spacing: 2px;
        color: #ffffff;
        text-transform: uppercase;
        background: linear-gradient(135deg, #ffffff 40%, #38bdf8 80%, #00a6d6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* The Smooth Zoom and Fade Out To Web */
    .intro-fading .weride-intro-logo-box {
        transform: scale(2.2);
        opacity: 0;
    }

    .intro-fading .weride-intro-glow {
        transform: scale(2.5);
        opacity: 0;
    }
</style>

<script>
(function() {
    const isTest = window.location.search.indexOf('test_boot=1') !== -1 || window.location.search.indexOf('boot=1') !== -1;
    const hasBooted = sessionStorage.getItem('weride_session_booted');

    if (hasBooted && !isTest) {
        return; // Already booted, do nothing!
    }

    sessionStorage.setItem('weride_session_booted', '1');

    // Setup global persistent audio
    let audio = window.werideBootAudio;
    if (!audio) {
        audio = new Audio('assets/audio/weride_boot.mp3');
        audio.preload = 'auto';
        window.werideBootAudio = audio;
    }

    function createAndRunIntro() {
        if (document.getElementById('weride-intro-screen')) return;

        const introScreen = document.createElement('div');
        introScreen.id = 'weride-intro-screen';
        introScreen.innerHTML = `
            <div class="weride-intro-glow"></div>
            <div class="weride-intro-logo-box">
                <img src="pic/logo_only.png" alt="WeRide" class="weride-intro-logo-img">
                <div class="weride-intro-brand">WeRide</div>
            </div>
        `;

        (document.body || document.documentElement).appendChild(introScreen);

        let transitionTriggered = false;

        function runZoomAndFadeOut() {
            if (transitionTriggered) return;
            transitionTriggered = true;

            // Start sound (keeps playing smoothly into the web page)
            if (audio) {
                audio.play().catch(() => {});
            }

            // Smooth zoom and fade out to web
            introScreen.classList.add('intro-fading');

            setTimeout(() => {
                introScreen.remove();
            }, 1000);
        }

        // Tap/click anywhere on screen triggers immediate zoom & fade out + sound
        introScreen.addEventListener('click', runZoomAndFadeOut, { once: true });
        introScreen.addEventListener('pointerdown', runZoomAndFadeOut, { once: true });
        introScreen.addEventListener('touchstart', runZoomAndFadeOut, { once: true });

        // Auto-trigger sound and zoom-fade into the web after a brief moment
        const p = audio.play();
        if (p !== undefined) {
            p.then(() => {
                // Audio autoplay permitted: zoom and fade into web after 600ms
                setTimeout(runZoomAndFadeOut, 600);
            }).catch(() => {
                // If iOS blocked unprompted sound:
                // Auto-fade into web after 1.8s if user hasn't tapped yet
                setTimeout(() => {
                    if (!transitionTriggered) runZoomAndFadeOut();
                }, 1800);
            });
        } else {
            setTimeout(runZoomAndFadeOut, 600);
        }
    }

    // Attach immediately without waiting
    if (document.body || document.documentElement) {
        createAndRunIntro();
    } else {
        document.addEventListener('DOMContentLoaded', createAndRunIntro);
    }
})();
</script>
