/**
 * Mobile menu toggle
 */
const menuBtn = document.getElementById('mobile-menu-button');
const closeBtn = document.getElementById('mobile-menu-close');
const menu = document.getElementById('mobile-menu');

function openMenu() {
  menu?.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeMenu() {
  menu?.classList.add('hidden');
  document.body.style.overflow = '';
}

menuBtn?.addEventListener('click', openMenu);
closeBtn?.addEventListener('click', closeMenu);

// Close on Escape
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && menu && !menu.classList.contains('hidden')) {
    closeMenu();
  }
});
