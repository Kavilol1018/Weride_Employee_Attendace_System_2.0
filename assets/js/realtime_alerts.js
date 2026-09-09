document.addEventListener('DOMContentLoaded', () => {
    setInterval(() => {
        fetch('api/api_check_alerts.php')
            .then(res => res.json())
            .then(data => {
                if (data.violations && data.violations.length > 0) {
                    data.violations.forEach(v => {
                        if (v.type === 'missed') {
                            window.showToast(`🚨 Violation: ${v.name} completely missed their lunch window (${v.window})!`, true);
                        } else {
                            window.showToast(`🚨 Violation: ${v.name} took a ${v.duration} min break (Overtime)!`);
                        }
                    });
                }
            })
            .catch(err => console.error('Alerts poll error:', err));
    }, 15000);
});

window.showToast = function(message, isMissed = false) {
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.innerText = message;
        
        const bgColor = isMissed ? '#ef4444' : '#f97316'; // Red for missed, Orange for overtime
        const shadowColor = isMissed ? 'rgba(239, 68, 68, 0.4)' : 'rgba(249, 115, 22, 0.4)';
        
        Object.assign(toast.style, {
            position: 'fixed',
            bottom: '20px',
            right: '20px',
            backgroundColor: bgColor,
            color: '#ffffff',
            padding: '16px 24px',
            borderRadius: '12px',
            boxShadow: `0 10px 25px -5px ${shadowColor}`,
            fontWeight: '600',
            zIndex: '9999',
            animation: 'slideInRight 0.3s ease-out forwards',
            transition: 'opacity 0.3s ease-out'
        });

        document.body.appendChild(toast);

        if (!document.getElementById('toast-keyframes')) {
            const style = document.createElement('style');
            style.id = 'toast-keyframes';
            style.innerHTML = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 6000);
    }
