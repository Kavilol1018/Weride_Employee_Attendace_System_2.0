document.addEventListener('DOMContentLoaded', () => {
    const currentTheme = localStorage.getItem('theme');
    const body = document.body;
    const themeCheckbox = document.getElementById('theme-toggle');
    // Get the div containing the sun/moon icon and text which is right before the <label class="switch">
    const themeTextContainer = themeCheckbox ? themeCheckbox.parentElement.previousElementSibling : null;

    let isCurrentlyDark = body.classList.contains('dark-mode');
    // Default to dark mode if saved in local storage, OR if it's the dashboard and no preference is saved yet
    if (currentTheme === 'dark' || (!currentTheme && window.location.pathname.includes('dashboard.php'))) {
        body.classList.add('dark-mode');
        isCurrentlyDark = true;
    }

    if (themeCheckbox) {
        themeCheckbox.checked = isCurrentlyDark;
        updateToggleUI(isCurrentlyDark);

        // Define global function called by the onchange attribute in the HTML
        window.toggleTheme = function() {
            const isDark = themeCheckbox.checked;
            updateToggleUI(isDark);
            
            if (isDark) {
                body.classList.add('dark-mode');
            } else {
                body.classList.remove('dark-mode');
            }
            
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            document.cookie = "theme=" + (isDark ? "dark" : "light") + "; path=/; max-age=31536000";
            
            if (typeof updateChartTheme === 'function') {
                updateChartTheme(isDark);
            }
        };

        function updateToggleUI(isDark) {
            if (themeTextContainer) {
                if (isDark) {
                    themeTextContainer.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="4.22" x2="19.78" y2="5.64"></line></svg> Light mode`;
                } else {
                    themeTextContainer.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg> Dark mode`;
                }
            }
        }
    }
});

// Dual scrollbar feature for all table-containers
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.table-container').forEach(container => {
        const table = container.querySelector('table');
        if (!table) return;

        if (table.parentElement.classList.contains('table-scroll-wrapper')) return;

        // Remove overflow-x from the outer container so headers don't scroll
        container.style.overflowX = 'hidden';
        
        const tableWrapper = document.createElement('div');
        tableWrapper.className = 'table-scroll-wrapper';
        tableWrapper.style.overflowX = 'auto';
        tableWrapper.style.webkitOverflowScrolling = 'touch';
        tableWrapper.style.width = '100%';
        // Ensure padding/margins don't hide the bottom scrollbar
        tableWrapper.style.paddingBottom = '4px';

        const topScroll = document.createElement('div');
        topScroll.className = 'top-scrollbar-wrapper';
        topScroll.style.overflowX = 'auto';
        topScroll.style.overflowY = 'hidden';
        topScroll.style.height = '14px';
        topScroll.style.marginBottom = '2px';

        const topScrollInner = document.createElement('div');
        topScrollInner.style.height = '14px';
        topScroll.appendChild(topScrollInner);

        table.parentNode.insertBefore(topScroll, table);
        table.parentNode.insertBefore(tableWrapper, table);
        tableWrapper.appendChild(table);

        const updateWidth = () => {
            topScrollInner.style.width = table.scrollWidth + 'px';
        };

        setTimeout(updateWidth, 100);
        window.addEventListener('resize', updateWidth);
        const observer = new MutationObserver(updateWidth);
        observer.observe(table, { childList: true, subtree: true });

        let isSyncingTop = false;
        let isSyncingBottom = false;

        topScroll.addEventListener('scroll', () => {
            if (!isSyncingTop) {
                isSyncingBottom = true;
                tableWrapper.scrollLeft = topScroll.scrollLeft;
            }
            isSyncingTop = false;
        });

        tableWrapper.addEventListener('scroll', () => {
            if (!isSyncingBottom) {
                isSyncingTop = true;
                topScroll.scrollLeft = tableWrapper.scrollLeft;
            }
            isSyncingBottom = false;
        });
    });
});
