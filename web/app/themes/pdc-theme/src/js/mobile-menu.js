/**
 * Mobile Menu Toggle
 *
 * Handles the opening and closing of the mobile burger menu
 */

/**
 * Initialize mobile menu functionality
 */
function initMobileMenu() {
  const mobileMenuButton = document.getElementById('mobile-menu-button');
  const mobileMenuClose = document.getElementById('mobile-menu-close');
  const mobileMenu = document.getElementById('mobile-menu');
  const burgerOpen = document.getElementById('burger-open');
  const burgerClose = document.getElementById('burger-close');

  if (!mobileMenuButton || !mobileMenu) {
    return;
  }

  /**
   * Toggle mobile menu visibility
   */
  function toggleMobileMenu() {
    const isHidden = mobileMenu.classList.contains('hidden');

    if (isHidden) {
      // Open menu
      mobileMenu.classList.remove('hidden');
      document.body.style.overflow = 'hidden'; // Prevent scrolling when menu is open

      // Toggle burger icon
      if (burgerOpen && burgerClose) {
        burgerOpen.classList.add('hidden');
        burgerClose.classList.remove('hidden');
      }
    } else {
      // Close menu
      mobileMenu.classList.add('hidden');
      document.body.style.overflow = ''; // Restore scrolling

      // Toggle burger icon
      if (burgerOpen && burgerClose) {
        burgerOpen.classList.remove('hidden');
        burgerClose.classList.add('hidden');
      }
    }
  }

  /**
   * Close mobile menu
   */
  function closeMobileMenu() {
    mobileMenu.classList.add('hidden');
    document.body.style.overflow = '';

    // Reset burger icon
    if (burgerOpen && burgerClose) {
      burgerOpen.classList.remove('hidden');
      burgerClose.classList.add('hidden');
    }
  }

  // Open/close menu when clicking burger button
  mobileMenuButton.addEventListener('click', toggleMobileMenu);

  // Close menu when clicking close button
  if (mobileMenuClose) {
    mobileMenuClose.addEventListener('click', closeMobileMenu);
  }

  // Close menu when clicking on a menu link
  const mobileMenuLinks = mobileMenu.querySelectorAll('a');
  mobileMenuLinks.forEach((link) => {
    link.addEventListener('click', closeMobileMenu);
  });

  // Close menu when pressing Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !mobileMenu.classList.contains('hidden')) {
      closeMobileMenu();
    }
  });

  // Close menu when clicking outside (on the overlay)
  mobileMenu.addEventListener('click', (e) => {
    if (e.target === mobileMenu) {
      closeMobileMenu();
    }
  });
}

/**
 * Initialize on DOM ready
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initMobileMenu);
} else {
  initMobileMenu();
}
