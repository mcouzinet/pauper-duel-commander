/**
 * Deck Validator JavaScript
 *
 * Handles the AJAX deck validation form:
 * - Submits commander + partner + decklist to the WordPress AJAX handler
 * - Renders validation results (valid/invalid, error list) without page reload
 */

// Rule label map (module-level constant, not recreated per call)
const RULE_LABELS = {
  format: 'Format invalide',
  commander: 'Général introuvable',
  commander_rarity: 'Rareté du général',
  deck_size: 'Nombre de cartes',
  not_found: 'Cartes introuvables',
  duplicates: 'Doublons',
  rarity: 'Rareté des cartes',
  color_identity: 'Identité de couleur',
  ban_list: 'Cartes bannies',
};

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('deck-validator-form');
  if (!form) return;

  const hasPartnerCheckbox = document.getElementById('has-partner');
  const partnerField       = document.getElementById('partner-field');
  const validateBtn        = document.getElementById('validate-btn');
  const btnText            = document.getElementById('btn-text');
  const btnSpinner         = document.getElementById('btn-spinner');
  const resultsArea        = document.getElementById('validator-results');

  // Guard: all critical elements must exist
  if (!hasPartnerCheckbox || !partnerField || !validateBtn || !btnText || !btnSpinner || !resultsArea) {
    return;
  }

  // --- Toggle partner field visibility ---
  hasPartnerCheckbox.addEventListener('change', () => {
    partnerField.classList.toggle('hidden', !hasPartnerCheckbox.checked);
    if (!hasPartnerCheckbox.checked) {
      document.getElementById('partner-name').value = '';
    }
  });

  // --- Form submission ---
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const commander = document.getElementById('commander-name').value.trim();
    const partner   = document.getElementById('partner-name').value.trim();
    const decklist  = document.getElementById('decklist-input').value.trim();
    const nonce     = document.getElementById('validator-nonce').value;
    const ajaxUrl   = document.getElementById('ajax-url').value;

    if (!commander) {
      renderClientError('Veuillez saisir le nom du général.');
      return;
    }
    if (!decklist) {
      renderClientError('Veuillez saisir votre decklist.');
      return;
    }

    setLoading(true);
    resultsArea.classList.add('hidden');
    resultsArea.innerHTML = '';

    try {
      const body = new URLSearchParams({ action: 'pdc_validate_deck', nonce, commander, partner, decklist });

      const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });

      if (!response.ok) {
        throw new Error(`Erreur réseau : ${response.status}`);
      }

      const data = await response.json();

      if (!data.success) {
        renderClientError(data.data?.message || 'Une erreur inattendue s\'est produite. Veuillez réessayer.');
        return;
      }

      renderResults(data.data);
    } catch (err) {
      renderClientError('Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.');
      console.error('[PDC Validator]', err);
    } finally {
      setLoading(false);
    }
  });

  // -------------------------------------------------------------------------
  // Rendering helpers
  // -------------------------------------------------------------------------

  function setLoading(loading) {
    validateBtn.disabled = loading;
    btnText.textContent  = loading ? 'Validation en cours…' : 'Valider le deck';
    btnSpinner.classList.toggle('hidden', !loading);
  }

  function renderClientError(message) {
    resultsArea.innerHTML = buildAlertBanner(message, 'red');
    resultsArea.classList.remove('hidden');
    resultsArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /**
   * Render full validation results from the server.
   *
   * @param {Object} result - { is_valid, errors, warnings, stats }
   */
  function renderResults(result) {
    const isValid    = result.is_valid;
    const color      = isValid ? 'green' : 'red';
    const iconPath   = isValid
      ? 'M5 13l4 4L19 7'
      : 'M6 18L18 6M6 6l12 12';
    const title      = isValid ? 'Deck Valide !' : 'Deck Invalide';
    const subtitle   = isValid
      ? 'Votre deck respecte toutes les règles du format PDC.'
      : `${result.errors.length} problème${result.errors.length > 1 ? 's' : ''} détecté${result.errors.length > 1 ? 's' : ''}. Consultez les détails ci-dessous.`;

    let html = `
      <div class="magic-card p-8 mb-6 border-${color}-500/50 bg-${color}-900/10">
        <div class="flex items-center gap-4">
          <div class="flex-shrink-0 w-16 h-16 rounded-full bg-${color}-500/20 flex items-center justify-center">
            <svg class="w-8 h-8 text-${color}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${escHtml(iconPath)}"/>
            </svg>
          </div>
          <div>
            <h2 class="text-3xl font-heading font-bold text-${color}-400 uppercase">${escHtml(title)}</h2>
            <p class="text-text-secondary mt-1">${escHtml(subtitle)}</p>
          </div>
        </div>
        ${buildStats(result.stats)}
      </div>`;

    if (result.errors && result.errors.length > 0) {
      html += '<div class="space-y-4 mb-6">';
      for (const error of result.errors) {
        html += buildErrorCard(error);
      }
      html += '</div>';
    }

    if (result.warnings && result.warnings.length > 0) {
      for (const warning of result.warnings) {
        html += buildAlertBanner(warning, 'yellow');
      }
    }

    resultsArea.innerHTML = html;
    resultsArea.classList.remove('hidden');
    resultsArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function buildStats(stats) {
    if (!stats) return '';
    return `
      <div class="mt-4 flex flex-wrap gap-4">
        <span class="px-3 py-1 bg-bg-secondary rounded-lg text-sm text-text-secondary">
          <strong class="text-text-primary">${stats.total_cards}</strong> carte${stats.total_cards !== 1 ? 's' : ''} au total
        </span>
        <span class="px-3 py-1 bg-bg-secondary rounded-lg text-sm text-text-secondary">
          <strong class="text-text-primary">${stats.unique_cards}</strong> carte${stats.unique_cards !== 1 ? 's' : ''} uniques
        </span>
      </div>`;
  }

  function buildErrorCard(error) {
    const label    = RULE_LABELS[error.rule] || error.rule;
    const cardList = error.cards && error.cards.length > 0
      ? `<ul class="mt-3 pl-4 space-y-1 border-l-2 border-red-500/30 list-none">
           ${error.cards.map(c => `<li class="text-sm text-text-secondary font-mono">${escHtml(c)}</li>`).join('')}
         </ul>`
      : '';

    return `
      <div class="magic-card p-6 border-red-500/30 bg-red-900/5">
        <div class="flex items-start gap-3">
          ${buildIcon('w-5 h-5 text-red-400 flex-shrink-0 mt-0.5', 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z')}
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-red-400 mb-1">${escHtml(label)}</p>
            <p class="text-sm text-text-primary">${escHtml(error.message)}</p>
            ${cardList}
          </div>
        </div>
      </div>`;
  }

  /**
   * Build a coloured alert banner (client errors, warnings).
   *
   * @param {string} message
   * @param {'red'|'yellow'} color
   */
  function buildAlertBanner(message, color) {
    const iconPath  = color === 'yellow'
      ? 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'
      : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
    const textColor = color === 'yellow' ? 'yellow-300' : 'red-300';

    return `
      <div class="flex items-start gap-3 p-4 bg-${color}-900/10 border border-${color}-500/30 rounded-xl mb-3">
        ${buildIcon(`w-5 h-5 text-${color}-400 flex-shrink-0 mt-0.5`, iconPath)}
        <p class="text-sm text-${textColor}">${escHtml(message)}</p>
      </div>`;
  }

  /** Shared SVG icon builder. */
  function buildIcon(classes, pathD) {
    return `<svg class="${classes}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${pathD}"/>
    </svg>`;
  }

  /** Minimal HTML escaping to prevent XSS from server-returned strings. */
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
});
