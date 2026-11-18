<?php
// WeRide System Startup Cinematic Logo Zoom-In Intro (Option 1: Once per app session)
?>
<!-- WeRide Cinematic Logo Zoom-In Component -->
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
        z-index: 2147483647; /* Maximum z-index */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        overflow: hidden;
        user-select: none;
        -webkit-user-select: none;
        cursor: pointer;
        transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        perspective: 1000px;
    }

    #weride-intro-screen.intro-zooming {
        pointer-events: none;
    }

    #weride-intro-screen.intro-exit {
        opacity: 0 !important;
        pointer-events: none !important;
    }

    /* Ambient Cyan Glow */
    .weride-intro-glow {
        position: absolute;
        width: 380px;
        height: 380px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(0, 166, 214, 0.45) 0%, rgba(56, 189, 248, 0.12) 50%, transparent 70%);
        filter: blur(50px);
        pointer-events: none;
        transition: transform 2.2s cubic-bezier(0.2, 0.8, 0.2, 1), opacity 2.2s ease;
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
    }

    .weride-intro-logo-img {
        width: 110px;
        height: 110px;
        object-fit: contain;
        filter: drop-shadow(0 0 25px rgba(0, 166, 214, 0.95)) drop-shadow(0 0 10px #38bdf8);
        transition: filter 0.8s ease;
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

    /* The Cinematic Zoom-In Flight Into the System */
    .intro-zooming .weride-intro-logo-box {
        animation: werideFlyThrough 2.4s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    }

    .intro-zooming .weride-intro-glow {
        transform: scale(3.5);
        opacity: 0;
    }

    @keyframes werideFlyThrough {
        0% {
            transform: scale(1) translateZ(0);
            opacity: 1;
            filter: drop-shadow(0 0 25px rgba(0, 166, 214, 0.9));
        }
        30% {
            transform: scale(1.15) translateZ(50px);
            opacity: 1;
            filter: drop-shadow(0 0 50px rgba(56, 189, 248, 1)) drop-shadow(0 0 20px #00f2fe);
        }
        65% {
            transform: scale(3.5) translateZ(250px);
            opacity: 0.9;
            filter: blur(2px) drop-shadow(0 0 65px rgba(0, 166, 214, 1));
        }
        100% {
            transform: scale(12) translateZ(500px);
            opacity: 0;
            filter: blur(14px) drop-shadow(0 0 90px rgba(56, 189, 248, 1));
        }
    }

    /* Subtle touch prompt for iOS cold start */
    .weride-intro-prompt {
        position: absolute;
        bottom: max(32px, env(safe-area-inset-bottom, 24px));
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 1.2px;
        color: rgba(56, 189, 248, 0.85);
        text-transform: uppercase;
        animation: weridePromptPulse 1.8s infinite ease-in-out;
    }

    @keyframes weridePromptPulse {
        0%, 100% { opacity: 0.5; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.04); }
    }
</style>

<script>
(function() {
    const isTest = window.location.search.indexOf('test_boot=1') !== -1 || window.location.search.indexOf('boot=1') !== -1;
    const hasBooted = sessionStorage.getItem('weride_session_booted');

    if (hasBooted && !isTest) {
        return; // Already booted, do nothing
    }

    sessionStorage.setItem('weride_session_booted', '1');

    // Pre-create Audio
    let audio = window.werideBootAudio;
    if (!audio) {
        audio = new Audio('assets/audio/weride_boot.mp3');
        audio.preload = 'auto';
        window.werideBootAudio = audio;
    }

    function createAndShowIntro() {
        if (document.getElementById('weride-intro-screen')) return;

        const introScreen = document.createElement('div');
        introScreen.id = 'weride-intro-screen';
        introScreen.innerHTML = `
            <div class="weride-intro-glow"></div>
            <div class="weride-intro-logo-box">
                <img src="pic/logo_only.png" alt="WeRide" class="weride-intro-logo-img">
                <div class="weride-intro-brand">WeRide</div>
            </div>
            <div class="weride-intro-prompt" id="weride-intro-prompt">Tap to Enter</div>
        `;

        (document.body || document.documentElement).appendChild(introScreen);

        let transitionTriggered = false;

        function runZoomTransition() {
            if (transitionTriggered) return;
            transitionTriggered = true;

            // Start sound
            if (audio) {
                audio.play().catch(() => {});
            }

            // Hide prompt
            const prompt = document.getElementById('weride-intro-prompt');
            if (prompt) prompt.style.display = 'none';

            // Start cinematic fly-through
            introScreen.classList.add('intro-zooming');

            // At peak zoom (2.2s), dissolve backdrop into the system
            setTimeout(() => {
                introScreen.classList.add('intro-exit');
                setTimeout(() => {
                    introScreen.remove();
                }, 600);
            }, 2200);
        }

        // Tap listener anywhere on the screen immediately triggers zoom + audio
        introScreen.addEventListener('click', runZoomTransition, { once: true });
        introScreen.addEventListener('pointerdown', runZoomTransition, { once: true });
        introScreen.addEventListener('touchstart', runZoomTransition, { once: true });

        // Attempt automatic playback (e.g. desktop, Android, or post-login navigation)
        const p = audio.play();
        if (p !== undefined) {
            p.then(() => {
                // Autoplay succeeded! Trigger zoom automatically
                setTimeout(runZoomTransition, 300);
            }).catch(() => {
                // Autoplay blocked by iOS on cold start:
                // Show prompt; will instantly zoom + play on first tap!
                // Fallback auto-transition after 2.8s if no tap
                setTimeout(() => {
                    if (!transitionTriggered) runZoomTransition();
                }, 2800);
            });
        }
    }

    // Attach immediately without waiting for DOMContentLoaded
    if (document.body || document.documentElement) {
        createAndShowIntro();
    } else {
        document.addEventListener('DOMContentLoaded', createAndShowIntro);
    }
})();
</script>
