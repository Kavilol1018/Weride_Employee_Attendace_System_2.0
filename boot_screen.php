<?php
// WeRide System Startup Boot Screen (Option 1: Once per app session)
?>
<!-- WeRide Boot Screen Component -->
<style id="weride-boot-style">
    #weride-boot-screen {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100vw;
        height: 100vh;
        height: 100dvh;
        background: #070b14;
        background: radial-gradient(circle at center, #0f1c33 0%, #070b14 70%);
        z-index: 2147483647; /* Maximum z-index */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        overflow: hidden;
        user-select: none;
        -webkit-user-select: none;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #ffffff;
        padding: max(20px, env(safe-area-inset-top, 20px)) 20px max(20px, env(safe-area-inset-bottom, 20px)) 20px;
        transition: opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1), transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 1;
        transform: scale(1);
    }

    #weride-boot-screen.boot-exit {
        opacity: 0 !important;
        transform: scale(1.04) !important;
        pointer-events: none !important;
    }

    .weride-boot-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        position: relative;
        z-index: 2;
    }

    /* Skip button */
    .weride-boot-skip {
        position: absolute;
        top: max(20px, calc(14px + env(safe-area-inset-top, 0px)));
        right: 20px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.18);
        color: rgba(255, 255, 255, 0.7);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.2s ease;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 10;
    }

    .weride-boot-skip:active {
        background: rgba(255, 255, 255, 0.22);
        color: #ffffff;
        transform: scale(0.96);
    }

    /* Ambient background glow */
    .weride-boot-ambient {
        position: absolute;
        width: 320px;
        height: 320px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(0, 166, 214, 0.35) 0%, rgba(56, 189, 248, 0.08) 50%, transparent 70%);
        filter: blur(40px);
        pointer-events: none;
        animation: bootAmbientPulse 3s infinite alternate ease-in-out;
    }

    @keyframes bootAmbientPulse {
        0% { transform: scale(0.85); opacity: 0.5; }
        100% { transform: scale(1.25); opacity: 0.95; }
    }

    /* Logo Centerpiece & Ripple Rings */
    .weride-boot-logo-wrapper {
        position: relative;
        width: 130px;
        height: 130px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 22px;
    }

    .weride-boot-ring {
        position: absolute;
        width: 130px;
        height: 130px;
        border-radius: 50%;
        border: 2px solid rgba(56, 189, 248, 0.6);
        pointer-events: none;
        box-sizing: border-box;
    }

    .weride-boot-ring-1 {
        animation: bootRingPulse 2.4s infinite cubic-bezier(0.1, 0.8, 0.3, 1);
    }

    .weride-boot-ring-2 {
        animation: bootRingPulse 2.4s infinite cubic-bezier(0.1, 0.8, 0.3, 1) 0.8s;
    }

    @keyframes bootRingPulse {
        0% {
            transform: scale(0.8);
            opacity: 0.85;
            border-color: rgba(56, 189, 248, 0.8);
        }
        50% {
            opacity: 0.45;
        }
        100% {
            transform: scale(1.85);
            opacity: 0;
            border-color: rgba(0, 166, 214, 0);
        }
    }

    .weride-boot-logo-img {
        width: 90px;
        height: 90px;
        object-fit: contain;
        position: relative;
        z-index: 3;
        animation: bootLogoEntrance 5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    }

    @keyframes bootLogoEntrance {
        0% {
            transform: scale(0.55) rotate(-6deg);
            opacity: 0;
            filter: drop-shadow(0 0 0px rgba(0, 166, 214, 0));
        }
        18% {
            transform: scale(1.1) rotate(0deg);
            opacity: 1;
            filter: drop-shadow(0 0 28px rgba(0, 166, 214, 0.95)) drop-shadow(0 0 10px #38bdf8);
        }
        30% {
            transform: scale(1.0);
            filter: drop-shadow(0 0 18px rgba(0, 166, 214, 0.7));
        }
        60% {
            transform: scale(1.04);
            filter: drop-shadow(0 0 24px rgba(56, 189, 248, 0.85));
        }
        85% {
            transform: scale(1.0);
            filter: drop-shadow(0 0 18px rgba(0, 166, 214, 0.7));
        }
        100% {
            transform: scale(1.02);
            filter: drop-shadow(0 0 22px rgba(0, 166, 214, 0.8));
        }
    }

    /* Brand Typography */
    .weride-boot-brand {
        font-size: 32px;
        font-weight: 900;
        letter-spacing: 1.5px;
        background: linear-gradient(135deg, #ffffff 30%, #38bdf8 70%, #00a6d6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 6px;
        text-transform: uppercase;
        animation: bootTextFade 0.8s ease-out forwards;
    }

    .weride-boot-tagline {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 2.5px;
        color: rgba(255, 255, 255, 0.6);
        text-transform: uppercase;
        margin-bottom: 28px;
        animation: bootTextFade 1.2s ease-out forwards;
    }

    @keyframes bootTextFade {
        0% { opacity: 0; transform: translateY(6px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    /* 5-Second Progress Bar */
    .weride-boot-progress-track {
        width: 220px;
        max-width: 80vw;
        height: 4px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 999px;
        overflow: hidden;
        position: relative;
        margin-bottom: 12px;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.5);
    }

    .weride-boot-progress-bar {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #00a6d6, #38bdf8, #00f2fe);
        border-radius: 999px;
        box-shadow: 0 0 12px rgba(56, 189, 248, 0.85);
        animation: bootProgress 4.8s cubic-bezier(0.2, 0.8, 0.25, 1) forwards;
    }

    @keyframes bootProgress {
        0%   { width: 0%; }
        15%  { width: 22%; }
        35%  { width: 45%; }
        65%  { width: 75%; }
        85%  { width: 92%; }
        100% { width: 100%; }
    }

    .weride-boot-status {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 1px;
        color: rgba(56, 189, 248, 0.85);
        text-transform: uppercase;
        min-height: 16px;
        animation: bootStatusBlink 1.4s infinite ease-in-out;
    }

    @keyframes bootStatusBlink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.55; }
    }

    .weride-boot-hint {
        position: absolute;
        bottom: max(24px, env(safe-area-inset-bottom, 20px));
        font-size: 11px;
        color: rgba(255, 255, 255, 0.35);
        letter-spacing: 0.5px;
    }
</style>

<script>
(function() {
    // Only run if not already booted in this session (Option 1), or forced via ?boot=1 / ?test_boot=1
    const isTest = window.location.search.indexOf('test_boot=1') !== -1 || window.location.search.indexOf('boot=1') !== -1;
    const hasBooted = sessionStorage.getItem('weride_session_booted');

    if (hasBooted && !isTest) {
        return; // Already booted, do nothing!
    }

    sessionStorage.setItem('weride_session_booted', '1');

    // 1. START AUDIO PLAYBACK IMMEDIATELY (Zero delay, plays while screen is loading)
    let audio = window.werideBootAudio;
    if (!audio) {
        audio = new Audio('assets/audio/weride_boot.mp3');
        audio.preload = 'auto';
        window.werideBootAudio = audio; // Keep globally referenced so it never cuts off
    }

    const tryPlayAudio = () => {
        if (!audio) return;
        const p = audio.play();
        if (p !== undefined) {
            p.catch(() => {
                // If browser autoplay policy blocks unprompted audio, play on first user gesture anywhere
                const unlock = () => {
                    if (audio) audio.play().catch(() => {});
                    window.removeEventListener('pointerdown', unlock, true);
                    window.removeEventListener('touchstart', unlock, true);
                    window.removeEventListener('click', unlock, true);
                };
                window.addEventListener('pointerdown', unlock, { capture: true, once: true });
                window.addEventListener('touchstart', unlock, { capture: true, once: true });
                window.addEventListener('click', unlock, { capture: true, once: true });
            });
        }
    };
    tryPlayAudio();

    // 2. CREATE AND DISPLAY BOOT SCREEN OVERLAY IMMEDIATELY
    function createAndShowBootScreen() {
        if (document.getElementById('weride-boot-screen')) return;

        const bootContainer = document.createElement('div');
        bootContainer.id = 'weride-boot-screen';
        bootContainer.innerHTML = `
            <button type="button" class="weride-boot-skip" onclick="dismissWerideBoot(true)">Skip &rarr;</button>
            <div class="weride-boot-ambient"></div>
            <div class="weride-boot-content">
                <div class="weride-boot-logo-wrapper">
                    <div class="weride-boot-ring weride-boot-ring-1"></div>
                    <div class="weride-boot-ring weride-boot-ring-2"></div>
                    <img src="pic/logo_only.png" alt="WeRide" class="weride-boot-logo-img">
                </div>
                <div class="weride-boot-brand">WeRide</div>
                <div class="weride-boot-tagline">One Team &bull; One Community</div>
                <div class="weride-boot-progress-track">
                    <div class="weride-boot-progress-bar"></div>
                </div>
                <div class="weride-boot-status" id="weride-boot-status-text">INITIALIZING WERIDE 2.0...</div>
            </div>
            <div class="weride-boot-hint">Connecting to workspace &bull; Version 2.0</div>
        `;

        (document.body || document.documentElement).appendChild(bootContainer);

        // Ensure audio is playing
        tryPlayAudio();

        // Status text sequence across the 5 seconds
        const statusText = document.getElementById('weride-boot-status-text');
        setTimeout(() => { if (statusText) statusText.innerText = "AUTHENTICATING SESSION..."; }, 1400);
        setTimeout(() => { if (statusText) statusText.innerText = "SYNCHRONIZING FEED & ATTENDANCE..."; }, 2800);
        setTimeout(() => { if (statusText) statusText.innerText = "READY &bull; WELCOME TO WERIDE"; }, 4200);

        // 5-second auto dismissal
        window.werideBootTimer = setTimeout(() => {
            if (window.dismissWerideBoot) window.dismissWerideBoot(false);
        }, 5000);

        window.dismissWerideBoot = function(skipped) {
            if (window.werideBootTimer) {
                clearTimeout(window.werideBootTimer);
                window.werideBootTimer = null;
            }
            // CRUCIAL: Do NOT pause audio when skipping!
            // The startup sound continues playing seamlessly in the background as user enters the app.
            bootContainer.classList.add('boot-exit');
            setTimeout(() => {
                bootContainer.remove();
            }, 520);
        };
    }

    // Attach immediately or as soon as documentElement exists
    if (document.body || document.documentElement) {
        createAndShowBootScreen();
    } else {
        document.addEventListener('DOMContentLoaded', createAndShowBootScreen);
    }
})();
</script>
