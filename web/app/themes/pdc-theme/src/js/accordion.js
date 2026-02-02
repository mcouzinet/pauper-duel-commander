/**
 * Accordion Initialization
 *
 * Initializes all accordion components on the page using the
 * reusable Accordion class.
 */

import { Accordion } from './components/Accordion.js';

/**
 * Initialize all accordions on the page
 */
function initAccordions() {
  const accordionElements = document.querySelectorAll('[data-accordion-group]');

  if (accordionElements.length === 0) {
    return;
  }

  // Store accordion instances for potential future use
  const accordionInstances = [];

  accordionElements.forEach((element) => {
    // Create a new accordion instance with default options
    const accordion = new Accordion(element, {
      duration: 300,
      allowMultiple: false,
      expandOnInit: false,
    });

    accordionInstances.push(accordion);
  });

  // Optional: Listen to accordion events for analytics or other purposes
  document.addEventListener('accordion:open', (e) => {
    // console.log('Accordion opened:', e.detail);
  });

  document.addEventListener('accordion:close', (e) => {
    // console.log('Accordion closed:', e.detail);
  });

  // Store instances globally for debugging (optional)
  if (typeof window !== 'undefined') {
    window.accordionInstances = accordionInstances;
  }
}

/**
 * Initialize on DOM ready
 */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAccordions);
} else {
  initAccordions();
}
