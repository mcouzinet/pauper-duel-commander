/**
 * Journey tracking.
 *
 * GA4 was loaded but only ever recorded page views, so none of the questions the
 * site exists to answer could be measured: does anyone reach the Discord, does
 * the ban list get read, do people search for a deck.
 *
 * Markup declares its own events — `data-analytics="discord_click"` plus any
 * `data-analytics-*` attributes as parameters — so adding a tracked control never
 * means editing this file. Everything routes through one `track()` that no-ops
 * until analytics consent is granted, which keeps the consent contract in a
 * single place.
 */
declare global {
  interface Window {
    dataLayer?: unknown[];
    gtag?: (...args: unknown[]) => void;
    pdcTrack?: (event: string, params?: Record<string, unknown>) => void;
  }
}

/** Send an event, if the visitor allowed analytics. gtag is absent otherwise. */
export function track(event: string, params: Record<string, unknown> = {}): void {
  if (typeof window.gtag !== 'function') return;
  window.gtag('event', event, params);
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
