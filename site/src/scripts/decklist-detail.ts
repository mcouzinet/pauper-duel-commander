/**
 * Decklist page: view switch, grouping and sorting, cost filter, export.
 *
 * Every control works on the rows CardList already rendered (`.deck-item`), so
 * there is one copy of the deck in the page. The list as served is grouped by
 * type and sorted by cost, which is also what a visitor without JavaScript
 * reads: everything here is an extra.
 *
 * Labels come from a JSON block the page builds with `t()`, so this file holds
 * no copy in any language.
 */

type GroupMode = 'type' | 'cost' | 'color';
type SortMode = 'cost' | 'name';

interface Labels {
  typeOrder: string[];
  types: Record<string, string>;
  colors: Record<string, string>;
  costGroup: string;
  countOf: string;
  filterStatus: string;
  bannedOne: string;
  bannedMany: string;
  group: Record<GroupMode, string>;
  sort: Record<SortMode, string>;
}

interface ExportData {
  commander: string;
  partner: string;
  decklist: string;
  filename: string;
}

const readJson = <T>(selector: string): T | null => {
  const node = document.querySelector(selector);
  try {
    return node ? (JSON.parse(node.textContent || '') as T) : null;
  } catch {
    return null;
  }
};

const fill = (template: string, vars: Record<string, string | number>) =>
  template.replace(/%(\w+)%/g, (match, name: string) => (name in vars ? String(vars[name]) : match));

const labels = readJson<Labels>('#deck-labels');
const list = document.querySelector<HTMLElement>('[data-deck-list]');

// ---------------------------------------------------------------------------
// View: list or visual. Stored per visitor; the page's inline script applies a
// stored choice to <html> before paint, so a returning visitor sees no switch.
// ---------------------------------------------------------------------------

const VIEW_KEY = 'pdc:deck-view';
const viewButtons = document.querySelectorAll<HTMLButtonElement>('[data-view-button]');

function setView(view: string, remember: boolean) {
  if (view === 'visual') document.documentElement.dataset.deckView = 'visual';
  else delete document.documentElement.dataset.deckView;
  viewButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.viewButton === view)));
  if (!remember) return;
  try {
    localStorage.setItem(VIEW_KEY, view);
  } catch {
    // Storage refused (private browsing): the choice holds for this page only.
  }
}

setView(document.documentElement.dataset.deckView === 'visual' ? 'visual' : 'list', false);
viewButtons.forEach(button => button.addEventListener('click', () => setView(button.dataset.viewButton ?? 'list', true)));

// ---------------------------------------------------------------------------
// Grouping, sorting and the cost filter.
// ---------------------------------------------------------------------------

if (labels && list) {
  const items = [...list.querySelectorAll<HTMLElement>('.deck-item')];
  const qty = (item: HTMLElement) => Number(item.dataset.qty ?? 1);
  const cmc = (item: HTMLElement) => Number(item.dataset.cmc ?? 0);
  const isLand = (item: HTMLElement) => item.dataset.land === '1';
  const costBucket = (item: HTMLElement) => (cmc(item) >= 7 ? '7+' : String(cmc(item)));
  const totalCards = items.reduce((sum, item) => sum + qty(item), 0);

  const COST_ORDER = ['0', '1', '2', '3', '4', '5', '6', '7+', 'land'];
  const COLOR_ORDER = ['W', 'U', 'B', 'R', 'G', 'M', 'C', 'land'];

  /** Group key, its rank and its heading for one row. Lands get their own group by cost and by colour. */
  function groupOf(item: HTMLElement, mode: GroupMode): { key: string; rank: number; label: string } {
    const lands = { key: 'land', label: labels!.types.Land };
    if (mode === 'type') {
      const type = item.dataset.type ?? 'Other';
      return { key: type.toLowerCase(), rank: labels!.typeOrder.indexOf(type), label: labels!.types[type] ?? labels!.types.Other };
    }
    if (mode === 'cost') {
      if (isLand(item)) return { ...lands, rank: COST_ORDER.indexOf('land') };
      const bucket = costBucket(item);
      return { key: bucket.replace('+', 'plus'), rank: COST_ORDER.indexOf(bucket), label: fill(labels!.costGroup, { n: bucket }) };
    }
    if (isLand(item)) return { ...lands, rank: COLOR_ORDER.indexOf('land') };
    const colors = item.dataset.colors ?? '';
    const key = colors.length === 0 ? 'C' : colors.length > 1 ? 'M' : colors;
    return { key: key.toLowerCase(), rank: COLOR_ORDER.indexOf(key), label: labels!.colors[key] ?? key };
  }

  const byName = (a: HTMLElement, b: HTMLElement) =>
    (a.dataset.name ?? '').localeCompare(b.dataset.name ?? '', undefined, { sensitivity: 'base' });
  const byCost = (a: HTMLElement, b: HTMLElement) => cmc(a) - cmc(b) || byName(a, b);

  const nav = document.querySelector<HTMLElement>('[data-deck-nav]');
  const navList = document.querySelector<HTMLElement>('[data-deck-nav-list]');
  const filterBar = document.querySelector<HTMLElement>('[data-filter-bar]');
  const filterStatus = document.querySelector<HTMLElement>('[data-filter-status]');
  const filterLive = document.querySelector<HTMLElement>('[data-filter-live]');
  const costButtons = document.querySelectorAll<HTMLButtonElement>('[data-cost-filter]');
  const summary = document.querySelector<HTMLElement>('[data-arrange-summary]');

  let groupMode: GroupMode = 'type';
  let sortMode: SortMode = 'cost';
  let filter: string | null = null;

  function heading(id: string, label: string, groupItems: HTMLElement[]): HTMLElement {
    const title = document.createElement('h2');
    title.className = 'deck-group__title';
    title.id = `${id}-title`;
    const text = document.createElement('span');
    text.textContent = label;
    const count = document.createElement('span');
    count.className = 'deck-group__count';
    count.dataset.groupCount = '';
    count.dataset.total = String(groupItems.reduce((sum, item) => sum + qty(item), 0));
    count.textContent = count.dataset.total;
    title.append(text, count);
    const banned = groupItems.filter(item => item.dataset.banned === '1').length;
    if (banned > 0) {
      const badge = document.createElement('span');
      badge.className = 'badge badge--danger normal-case tracking-normal';
      badge.textContent = banned === 1 ? labels!.bannedOne : `${banned} ${labels!.bannedMany}`;
      title.append(badge);
    }
    return title;
  }

  function navLink(id: string, label: string, total: number): HTMLLIElement {
    const item = document.createElement('li');
    item.className = 'flex-shrink-0';
    const link = document.createElement('a');
    link.className = 'deck-nav__link';
    link.href = `#${id}`;
    const count = document.createElement('span');
    count.className = 'text-text-muted tabular-nums';
    count.textContent = String(total);
    link.append(`${label} `, count);
    item.append(link);
    return item;
  }

  /** Rebuild the groups around the same row elements, which keeps their listeners. */
  function arrange() {
    const groups = new Map<string, { rank: number; label: string; items: HTMLElement[] }>();
    for (const item of items) {
      const { key, rank, label } = groupOf(item, groupMode);
      if (!groups.has(key)) groups.set(key, { rank, label, items: [] });
      groups.get(key)!.items.push(item);
    }

    const sections: HTMLElement[] = [];
    const links: HTMLLIElement[] = [];
    [...groups.entries()]
      .sort(([, a], [, b]) => a.rank - b.rank)
      .forEach(([key, group]) => {
        const id = `${groupMode}-${key}`;
        group.items.sort(sortMode === 'name' ? byName : byCost);
        const section = document.createElement('section');
        section.className = 'deck-group';
        section.id = id;
        section.setAttribute('aria-labelledby', `${id}-title`);
        const cards = document.createElement('ul');
        cards.className = 'deck-group__cards';
        cards.setAttribute('role', 'list');
        cards.append(...group.items);
        section.append(heading(id, group.label, group.items), cards);
        sections.push(section);
        links.push(navLink(id, group.label, group.items.reduce((sum, item) => sum + qty(item), 0)));
      });

    list!.replaceChildren(...sections);
    navList?.replaceChildren(...links);
    // "Coût · Coût" said nothing: the sort only shows when it is not the default.
    if (summary) summary.textContent = sortMode === 'cost'
      ? labels!.group[groupMode]
      : `${labels!.group[groupMode]} · ${labels!.sort[sortMode]}`;
    applyFilter();
  }

  function applyFilter() {
    let shown = 0;
    for (const item of items) {
      const match = filter === null || (!isLand(item) && costBucket(item) === filter);
      item.hidden = !match;
      if (match) shown += qty(item);
    }

    list!.querySelectorAll<HTMLElement>('.deck-group').forEach(section => {
      const visible = [...section.querySelectorAll<HTMLElement>('.deck-item')].filter(item => !item.hidden);
      section.hidden = visible.length === 0;
      const count = section.querySelector<HTMLElement>('[data-group-count]');
      if (!count) return;
      count.textContent = filter === null
        ? count.dataset.total ?? ''
        : fill(labels!.countOf, { n: visible.reduce((sum, item) => sum + qty(item), 0), t: count.dataset.total ?? '' });
    });

    costButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.costFilter === filter)));
    const status = filter === null
      ? ''
      : fill(labels!.filterStatus, { label: fill(labels!.costGroup, { n: filter }), n: shown, t: totalCards });
    if (nav) nav.hidden = filter !== null;
    if (filterBar) filterBar.hidden = filter === null;
    if (filterStatus) filterStatus.textContent = status;
    if (filterLive) filterLive.textContent = status;
  }

  costButtons.forEach(button => button.addEventListener('click', () => {
    const value = button.dataset.costFilter ?? null;
    filter = filter === value ? null : value;
    applyFilter();

    // From the profile section, under the list, the result is out of sight:
    // bring the top of the list back into view.
    const top = list!.getBoundingClientRect().top;
    if (filter !== null && (top < 0 || top > window.innerHeight * 0.6)) {
      const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      list!.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'start' });
    }
  }));

  document.querySelector('[data-filter-clear]')?.addEventListener('click', () => {
    filter = null;
    applyFilter();
  });

  document.querySelectorAll<HTMLInputElement>('input[name="deck-group"]').forEach(input =>
    input.addEventListener('change', () => {
      groupMode = input.value as GroupMode;
      arrange();
    }));
  document.querySelectorAll<HTMLInputElement>('input[name="deck-sort"]').forEach(input =>
    input.addEventListener('change', () => {
      sortMode = input.value as SortMode;
      arrange();
    }));

  // Phone: grouping and sorting fold away behind one button.
  const arrangeToggle = document.querySelector<HTMLButtonElement>('[data-arrange-toggle]');
  const arrangePanel = document.querySelector<HTMLElement>('[data-arrange-panel]');
  arrangeToggle?.addEventListener('click', () => {
    const open = arrangeToggle.getAttribute('aria-expanded') !== 'true';
    arrangeToggle.setAttribute('aria-expanded', String(open));
    if (arrangePanel) arrangePanel.dataset.open = String(open);
  });
}

// ---------------------------------------------------------------------------
// Export: copy, other formats, download, validator prefill.
// ---------------------------------------------------------------------------

const exportData = readJson<ExportData>('#deck-export-data');

/** Commander block then the deck: what Arena, Moxfield and Archidekt import. */
function asText(): string {
  if (!exportData) return '';
  const lines: string[] = [];
  if (exportData.commander) {
    lines.push('Commander', `1 ${exportData.commander}`);
    if (exportData.partner) lines.push(`1 ${exportData.partner}`);
    lines.push('');
  }
  lines.push('Deck', exportData.decklist.replace(/\r\n/g, '\n').trim());
  return lines.join('\n');
}

/** MTGO: the 99, a blank line, then the commander(s) in the sideboard. */
function asMtgo(): string {
  if (!exportData) return '';
  const commanders = [exportData.commander, exportData.partner].filter(Boolean).map(name => `1 ${name}`);
  return `${exportData.decklist.replace(/\r\n/g, '\n').trim()}\n\n${commanders.join('\n')}`;
}

const copyLive = document.querySelector<HTMLElement>('[data-copy-live]');

async function copy(text: string, feedback: HTMLElement) {
  try {
    await navigator.clipboard.writeText(text);
  } catch {
    // Clipboard denied (insecure context, or refused): the download stays available.
    return;
  }
  const done = feedback.dataset.labelDone;
  if (copyLive && done) copyLive.textContent = done;
  if (!done || feedback.dataset.copying) return;
  const previous = feedback.textContent;
  feedback.dataset.copying = '1';
  feedback.textContent = done;
  window.setTimeout(() => {
    feedback.textContent = previous;
    delete feedback.dataset.copying;
  }, 2000);
}

function download() {
  const blob = new Blob([asText()], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = exportData?.filename ?? 'decklist.txt';
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

document.querySelectorAll<HTMLButtonElement>('[data-deck-copy]').forEach(button =>
  button.addEventListener('click', () => copy(asText(), button)));

document.querySelectorAll<HTMLElement>('.deck-actions').forEach(actions => {
  const toggle = actions.querySelector<HTMLButtonElement>('[data-export-toggle]');
  const menu = actions.querySelector<HTMLElement>('[data-export-menu]');
  const primary = actions.querySelector<HTMLElement>('[data-deck-copy]');
  if (!toggle || !menu) return;

  const setOpen = (open: boolean) => {
    menu.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
  };

  toggle.addEventListener('click', event => {
    event.stopPropagation();
    setOpen(menu.hidden);
    if (!menu.hidden) menu.querySelector<HTMLButtonElement>('button')?.focus();
  });
  menu.addEventListener('click', event => event.stopPropagation());
  menu.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    setOpen(false);
    toggle.focus();
  });
  document.addEventListener('click', () => setOpen(false));

  menu.querySelectorAll<HTMLButtonElement>('[data-export]').forEach(item => item.addEventListener('click', () => {
    const format = item.dataset.export;
    setOpen(false);
    toggle.focus();
    if (format === 'download') download();
    else if (primary) copy(format === 'mtgo' ? asMtgo() : asText(), primary);
  }));
});

// Hand the list to the validator through sessionStorage: a 99-card decklist does
// not belong in a query string.
document.querySelectorAll('[data-deck-validate]').forEach(link => link.addEventListener('click', () => {
  if (!exportData) return;
  try {
    sessionStorage.setItem('pdc:prefill', JSON.stringify({
      commander: exportData.commander,
      partner: exportData.partner,
      decklist: exportData.decklist,
    }));
  } catch {
    // Private browsing can refuse storage; the validator then opens empty.
  }
}));
