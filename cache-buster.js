// Cache Busting Utility
// Add this to force browser to reload CSS and JS files

(function() {
    'use strict';
    
    // Force reload CSS and JS files with new timestamps
    function forceReloadAssets() {
        const timestamp = Date.now();
        
        // Reload CSS files
        const cssLinks = document.querySelectorAll('link[rel="stylesheet"][href*=".css"]');
        cssLinks.foreach(link => {
            if (!link.href.includes('cdnjs.cloudflare.com') && !link.href.includes('fonts.googleapis.com')) {
                const originalHref = link.href.split('?')[0];
                link.href = `${originalHref}?v=${timestamp}`;
            }
        });
        
        // For JS files, we'd need to reload the page since they're already loaded
        console.log('CSS files reloaded with timestamp:', timestamp);
    }
    
    // Add keyboard shortcut Ctrl+Shift+R to force reload assets
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'R') {
            e.preventDefault();
            console.log('Force reloading assets...');
            forceReloadAssets();
            setTimeout(() => {
                location.reload(true);
            }, 100);
        }
    });
    
    // Add console command to manually reload assets
    window.reloadAssets = forceReloadAssets;
    window.forceRefresh = function() {
        localStorage.clear();
        sessionStorage.clear();
        location.reload(true);
    };
    
    console.log('Cache busting utility loaded. Use Ctrl+Shift+R to force reload or call reloadAssets() or forceRefresh() in console.');
})();