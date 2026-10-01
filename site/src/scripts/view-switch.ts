/**
 * List / visual switch (ViewSwitch.astro).
 *
 * A stored choice is already on <html data-view>, applied before paint by the
 * component's inline script; this syncs the buttons to it and handles clicks.
 */
document.querySelectorAll<HTMLElement>('[data-view-switch]').forEach(group => {
  const key = `pdc:view:${group.dataset.viewSwitch}`;
  const buttons = [...group.querySelectorAll<HTMLButtonElement>('[data-view-option]')];

  const apply = (view: string) => {
    if (view === 'visual') document.documentElement.dataset.view = 'visual';
    else delete document.documentElement.dataset.view;
    for (const button of buttons) button.setAttribute('aria-pressed', String(button.dataset.viewOption === view));
  };

  apply(document.documentElement.dataset.view === 'visual' ? 'visual' : 'list');

  for (const button of buttons) {
    button.addEventListener('click', () => {
      const view = button.dataset.viewOption ?? 'list';
      apply(view);
      try {
        localStorage.setItem(key, view);
      } catch {
        // Storage refused (private browsing): the choice holds for this page only.
      }
    });
  }
});
