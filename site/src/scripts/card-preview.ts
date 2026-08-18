/**
 * Card image preview.
 *
 * Replaces the mouse-only version. That one bound `mouseenter`/`mouseleave` to
 * every trigger, which on a touch device fires on tap and leaves the image
 * stranded on screen with no way to dismiss it — on a page the audit found is
 * mostly read on a phone, next to a play mat.
 *
 * Now: hover on pointer devices, tap-to-toggle on touch, focus for keyboard,
 * Escape / outside-tap / close button to dismiss, and a Scryfall link so the
 * preview is a route to the card's full rules text rather than a dead end.
 *
 * Triggers are `.card-hover-trigger[data-card-image]`, with an optional
 * `data-card-name` used for the label and the Scryfall query.
 */
const MARGIN = 12;
const WIDTH = 244; // Scryfall "normal" is 488px wide; half of it stays crisp.

let root: HTMLDivElement | null = null;
let pinnedTo: HTMLElement | null = null;

/**
 * Accessible name for the preview popover.
 *
 * The element is built by script, so it cannot take the label from a `t()` call
 * at build time; the page's `<html lang>` is what identifies the locale here.
 */
const PREVIEW_LABEL: Record<string, string> = {
  fr: 'Aperçu de la carte',
  en: 'Card preview',
  it: 'Anteprima della carta',
};

const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

function build(): HTMLDivElement {
  if (root) return root;

  root = document.createElement('div');
  root.id = 'card-preview';
  root.hidden = true;
  root.setAttribute('role', 'dialog');
  root.setAttribute('aria-label', PREVIEW_LABEL[document.documentElement.lang] ?? PREVIEW_LABEL.fr);
  root.style.cssText = [
    'position:fixed',
    'z-index:10000',
    `width:${WIDTH}px`,
    'display:flex',
    'flex-direction:column',
    'gap:6px',
    'filter:drop-shadow(0 12px 32px rgba(0,0,0,.6))',
  ].join(';');

  root.innerHTML = `
    <img alt="" style="width:100%;height:auto;border-radius:12px;display:block;background:#141821" />
    <div data-actions style="display:flex;gap:6px;align-items:center;justify-content:space-between">
      <a data-scryfall target="_blank" rel="noopener noreferrer"
         style="flex:1;text-align:center;padding:7px 10px;border-radius:8px;background:#141821;border:1px solid rgba(255,87,34,.4);color:#FF7043;font:600 12px/1 Inter,system-ui,sans-serif;text-decoration:none">Scryfall</a>
      <button data-close type="button"
              style="width:34px;height:34px;border-radius:8px;background:#141821;border:1px solid rgba(255,255,255,.15);color:#B8BEC8;font:600 15px/1 Inter,system-ui,sans-serif;cursor:pointer">&times;</button>
    </div>`;

  document.body.appendChild(root);

  root.querySelector<HTMLButtonElement>('[data-close]')?.addEventListener('click', () => hide());
  // Clicks inside must not bubble out to the document handler that dismisses it.
  root.addEventListener('click', event => event.stopPropagation());

  return root;
}

/** Show the preview for a trigger. `pinned` keeps it up until dismissed. */
function show(trigger: HTMLElement, pinned: boolean) {
  const imageUrl = trigger.dataset.cardImage;
  if (!imageUrl) return;

  const element = build();
  const name = trigger.dataset.cardName ?? '';

  const img = element.querySelector('img')!;
  img.src = imageUrl;
  img.alt = name;

  const link = element.querySelector<HTMLAnchorElement>('[data-scryfall]')!;
  link.href = `https://scryfall.com/search?q=${encodeURIComponent(`!"${name}"`)}`;

  // The action row is only reachable when the preview is pinned; on hover it
  // would vanish before the pointer got there.
  element.querySelector<HTMLElement>('[data-actions]')!.style.display = pinned ? 'flex' : 'none';

  element.hidden = false;
  pinnedTo = pinned ? trigger : null;
  position(trigger);
}

function hide() {
  if (!root) return;
  root.hidden = true;
  pinnedTo = null;
}

/**
 * Place the preview beside the trigger, flipping side and clamping vertically so
 * it stays on screen — including on a 390px-wide phone, where "beside" often
 * means "centred underneath".
 */
function position(trigger: HTMLElement) {
  const element = build();
  const rect = trigger.getBoundingClientRect();
  const height = element.offsetHeight || WIDTH * 1.4;
  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;

  let left: number;
  if (viewportWidth < WIDTH + 2 * MARGIN + rect.width) {
    left = Math.max(MARGIN, (viewportWidth - WIDTH) / 2);
  } else {
    const right = rect.right + MARGIN;
    left = right + WIDTH <= viewportWidth - MARGIN ? right : rect.left - WIDTH - MARGIN;
    left = Math.min(Math.max(MARGIN, left), viewportWidth - WIDTH - MARGIN);
  }

  const top = Math.min(Math.max(MARGIN, rect.top - 20), Math.max(MARGIN, viewportHeight - height - MARGIN));

  element.style.left = `${Math.round(left)}px`;
  element.style.top = `${Math.round(top)}px`;
}

function bind(trigger: HTMLElement) {
  if (!trigger.dataset.cardImage) return;

  // Announce it as interactive so keyboard users can reach it at all.
  if (!trigger.hasAttribute('tabindex') && !trigger.closest('a, button')) {
    trigger.setAttribute('tabindex', '0');
    trigger.setAttribute('role', 'button');
    const name = trigger.dataset.cardName;
    if (name && !trigger.hasAttribute('aria-label')) {
      trigger.setAttribute('aria-label', name);
    }
  }

  if (canHover) {
    trigger.addEventListener('mouseenter', () => {
      if (!pinnedTo) show(trigger, false);
    });
    trigger.addEventListener('mouseleave', () => {
      if (!pinnedTo) hide();
    });
  }

  trigger.addEventListener('focus', () => show(trigger, true));
  trigger.addEventListener('blur', event => {
    // Keep it up when focus moves into the preview's own actions.
    const next = (event as FocusEvent).relatedTarget as Node | null;
    if (next && root?.contains(next)) return;
    if (pinnedTo === trigger) hide();
  });

  trigger.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      pinnedTo === trigger ? hide() : show(trigger, true);
    }
  });

  // Tap toggles. Inside a link, the tap must not follow the link — the deck row
  // in a top 8 is both a preview trigger and a link to the decklist, and on
  // touch the preview is what the finger was aiming for.
  trigger.addEventListener('click', event => {
    if (canHover) return;
    event.preventDefault();
    event.stopPropagation();
    pinnedTo === trigger ? hide() : show(trigger, true);
  });
}

document.querySelectorAll<HTMLElement>('.card-hover-trigger').forEach(bind);

document.addEventListener('click', () => {
  if (pinnedTo) hide();
});

document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && root && !root.hidden) hide();
});

window.addEventListener('scroll', () => {
  if (pinnedTo) position(pinnedTo);
  else if (root && !root.hidden) hide();
}, { passive: true });

window.addEventListener('resize', () => {
  if (pinnedTo) position(pinnedTo);
  else hide();
});
