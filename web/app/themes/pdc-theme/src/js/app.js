/**
 * Main JavaScript entry point
 */

// Import CSS
import '../css/app.css';

// Import decklist functionality
import './decklist.js';

/**
 * Smooth scroll to anchor links
 */
document.addEventListener('DOMContentLoaded', () => {
  // Header height for offset (logo h-36 + padding)
  const headerOffset = 160;

  // Handle all anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const targetId = this.getAttribute('href');

      // Ignore empty anchors
      if (targetId === '#') {
        return;
      }

      const targetElement = document.querySelector(targetId);

      if (targetElement) {
        e.preventDefault();

        const elementPosition = targetElement.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth'
        });

        // Update URL without jumping
        if (history.pushState) {
          history.pushState(null, null, targetId);
        }
      }
    });
  });

  // Handle direct URL with hash on page load
  if (window.location.hash) {
    setTimeout(() => {
      const targetElement = document.querySelector(window.location.hash);
      if (targetElement) {
        const elementPosition = targetElement.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth'
        });
      }
    }, 100);
  }
});

console.log('Theme loaded!');
