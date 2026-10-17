/**
 * Journey tracking.
 *
 * Page views are Umami's own (Base.astro). These are the questions the site
 * exists to answer: does anyone reach the Discord, does the ban list get read,
 * do people search for a deck.
 *
 * Markup declares its own events — `data-analytics="discord_click"` plus any
 * `data-analytics-*` attributes as parameters — so adding a tracked control never
 * means editing this file. Everything routes through one `track()`, which
 * no-ops while Umami is not loaded (dev, previews, blocked script). Umami sets
 * no cookie, so there is no consent to wait for.
 */
declare global {
  interface Window {
    umami?: { track: (event: string, data?: Record<string, unknown>) => void };
    pdcTrack?: (event: string, params?: Record<string, unknown>) => void;
  }
}

/** Send an event to Umami, when its script is there. */
export function track(event: string, params: Record<string, unknown> = {}): void {
  window.umami?.track(event, params);
}

// Exposed for inline handlers in pages (filters, search, export buttons).
window.pdcTrack = track;

/** Read `data-analytics-*` attributes as event parameters. */
function paramsFrom(element: HTMLElement): Record<string, unknown> {
  const params: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(element.dataset)) {
    if (key === 'analytics' || !key.startsWith('analytics')) continue;
    const name = key
      .slice('analytics'.length)
      .replace(/^[A-Z]/, c => c.toLowerCase())
      .replace(/[A-Z]/g, c => `_${c.toLowerCase()}`);
    params[name] = value;
  }
  return params;
}

// One listener for every declared control, so dynamically inserted markup works.
document.addEventListener('click', event => {
  const target = (event.target as HTMLElement | null)?.closest<HTMLElement>('[data-analytics]');
  if (!target) return;
  // A click on a label is replayed on its input, which bubbles here too: count
  // the input's click only, the one keyboard selection also produces.
  if (target instanceof HTMLLabelElement && target.control && event.target !== target.control) return;
  track(target.dataset.analytics!, paramsFrom(target));
});

// Outbound clicks worth knowing about: Scryfall, Moxfield, sign-up pages.
const INTERESTING_HOSTS = /scryfall|moxfield|archidekt|helloasso|discord/i;

document.addEventListener('click', event => {
  const link = (event.target as HTMLElement | null)?.closest<HTMLAnchorElement>('a[href^="http"]');
  if (!link || link.hasAttribute('data-analytics')) return;
  try {
    const { host } = new URL(link.href);
    if (host !== window.location.host && INTERESTING_HOSTS.test(host)) {
      track('outbound_click', { host });
    }
  } catch {
    // Not a URL we can parse; nothing to report.
  }
});
