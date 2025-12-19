/**
 * Decklist JavaScript
 *
 * Handles card hover previews and deck exports.
 */

// Global card preview element
let cardPreview = null;

/**
 * Create the global card preview element
 */
function createCardPreview() {
  const preview = document.createElement('div');
  preview.id = 'card-preview';
  preview.className = 'fixed z-[9999] pointer-events-none hidden';

  const img = document.createElement('img');
  img.className = 'w-64 rounded-xl shadow-2xl border-2 border-brand-orange';
  img.style.display = 'block';

  preview.appendChild(img);
  document.body.appendChild(preview);

  return preview;
}

/**
 * Show card preview and update its image
 *
 * @param {string} imageUrl - Card image URL
 * @param {MouseEvent} event - Mouse event for positioning
 */
function showCardPreview(imageUrl, event) {
  if (!cardPreview) {
    cardPreview = createCardPreview();
  }

  const img = cardPreview.querySelector('img');
  img.src = imageUrl;

  cardPreview.classList.remove('hidden');
  updatePreviewPosition(event);
}

/**
 * Hide card preview
 */
function hideCardPreview() {
  if (cardPreview) {
    cardPreview.classList.add('hidden');
  }
}

/**
 * Update preview position to follow cursor
 * Positions the image to the bottom-right of the cursor
 *
 * @param {MouseEvent} event - Mouse event with cursor position
 */
function updatePreviewPosition(event) {
  if (!cardPreview) return;

  const offsetX = 15; // Offset to the right of cursor
  const offsetY = 15; // Offset below cursor

  cardPreview.style.left = `${event.clientX + offsetX}px`;
  cardPreview.style.top = `${event.clientY + offsetY}px`;
}

/**
 * Initialize card hover previews for all cards in the decklist
 */
function initCardHoverPreviews() {
  const cardElements = document.querySelectorAll('.card-hover-trigger');

  if (cardElements.length === 0) {
    return;
  }

  cardElements.forEach(card => {
    const imageUrl = card.dataset.cardImage;

    if (!imageUrl) {
      return;
    }

    card.addEventListener('mouseenter', (e) => {
      showCardPreview(imageUrl, e);
    });

    card.addEventListener('mouseleave', () => {
      hideCardPreview();
    });
  });
}

/**
 * Helper function to download text content as a file
 * @param {string} content - The text content to download
 * @param {string} filename - The filename for the download
 */
function downloadTextFile(content, filename) {
  const blob = new Blob([content], { type: 'text/plain' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');

  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

/**
 * Export deck in MTGO format
 */
window.exportDeckMTGO = function() {
  const dataElement = document.getElementById('deck-export-data');

  if (!dataElement) {
    console.error('Export data not found');
    return;
  }

  try {
    const data = JSON.parse(dataElement.textContent);
    const decklistText = data.decklist;

    // Download as MTGO format
    downloadTextFile(decklistText, 'decklist-mtgo.txt');
  } catch (error) {
    console.error('Error exporting MTGO format:', error);
  }
};

/**
 * Export deck in Moxfield format
 */
window.exportDeckMoxfield = function() {
  const dataElement = document.getElementById('deck-export-data');

  if (!dataElement) {
    console.error('Export data not found');
    return;
  }

  try {
    const data = JSON.parse(dataElement.textContent);

    // Build Moxfield format
    let moxfieldText = '';

    // Commander section
    if (data.commander) {
      moxfieldText += 'Commander\n';
      moxfieldText += '1 ' + data.commander + '\n';

      if (data.partner) {
        moxfieldText += '1 ' + data.partner + '\n';
      }

      moxfieldText += '\n';
    }

    // Deck section
    moxfieldText += 'Deck\n';
    moxfieldText += data.decklist;

    // Download as Moxfield format
    downloadTextFile(moxfieldText, 'decklist-moxfield.txt');
  } catch (error) {
    console.error('Error exporting Moxfield format:', error);
  }
};

/**
 * Initialize on DOM ready
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCardHoverPreviews);
} else {
  initCardHoverPreviews();
}
