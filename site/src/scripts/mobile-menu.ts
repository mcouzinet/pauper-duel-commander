/**
 * Mobile menu.
 *
 * Adds the parts a dialog needs and this one lacked: the button reports its state
 * (`aria-expanded`), focus moves into the panel and is trapped there while it is
 * open, and it returns to the button on close. Without the trap, a screen-reader
 * or keyboard user tabbing through an "open" menu walks straight into the page
 * behind it.
 */
const button = document.getElementById('mobile-menu-button');
const closeButton = document.getElementById('mobile-menu-close');
const menu = document.getElementById('mobile-menu');

const FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

function isOpen(): boolean {
  return !!menu && !menu.classList.contains('hidden');
}

function focusableItems(): HTMLElement[] {
  if (!menu) return [];
  return [...menu.querySelectorAll<HTMLElement>(FOCUSABLE)].filter(el => el.offsetParent !== null);
}

function open() {
  if (!menu) return;
  menu.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  button?.setAttribute('aria-expanded', 'true');
  focusableItems()[0]?.focus();
}

function close() {
  if (!menu) return;
  menu.classList.add('hidden');
  document.body.style.overflow = '';
  button?.setAttribute('aria-expanded', 'false');
  button?.focus();
}

button?.addEventListener('click', () => (isOpen() ? close() : open()));
closeButton?.addEventListener('click', close);

document.addEventListener('keydown', event => {
  if (!isOpen()) return;

  if (event.key === 'Escape') {
    close();
    return;
  }

  if (event.key !== 'Tab') return;

  const items = focusableItems();
  if (items.length === 0) return;

  const first = items[0];
  const last = items[items.length - 1];
  const active = document.activeElement;

  // Wrap at both ends so focus never escapes the open panel.
  if (event.shiftKey && (active === first || !menu?.contains(active))) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && active === last) {
    event.preventDefault();
    first.focus();
  }
});

// A viewport that grows past the breakpoint reveals the desktop nav; leaving the
// overlay mounted would keep the body scroll-locked.
window.matchMedia('(min-width: 768px)').addEventListener('change', event => {
  if (event.matches && isOpen()) close();
});
