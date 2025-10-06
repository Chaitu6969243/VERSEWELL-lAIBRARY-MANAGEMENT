// Global Scroll Blur Effect
(function() {
  'use strict';

  function addScrollBlur() {
    // Add scroll blur to main content areas
    const contentSelectors = [
      'main',
      '.container',
      '.books-grid',
      '.cards',
      '.hiw-steps',
      '.mobile-nav'
    ];

    contentSelectors.forEach(selector => {
      const elements = document.querySelectorAll(selector);
      elements.forEach(element => {
        if (element.scrollHeight > element.clientHeight || 
            getComputedStyle(element).overflowY === 'auto' ||
            getComputedStyle(element).overflowY === 'scroll') {
          
          if (!element.querySelector('.scroll-blur-bottom')) {
            const container = document.createElement('div');
            container.className = 'scroll-container';
            
            const blur = document.createElement('div');
            blur.className = 'scroll-blur-bottom';
            
            element.parentNode.insertBefore(container, element);
            container.appendChild(element);
            container.appendChild(blur);
          }
        }
      });
    });

    // Add page-level scroll blur for long pages
    if (document.body.scrollHeight > window.innerHeight * 1.2) {
      document.body.classList.add('scroll-blur-enabled');
    }
  }

  // Initialize on DOM load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addScrollBlur);
  } else {
    addScrollBlur();
  }

  // Re-apply on dynamic content changes
  const observer = new MutationObserver(() => {
    setTimeout(addScrollBlur, 100);
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true
  });

})();