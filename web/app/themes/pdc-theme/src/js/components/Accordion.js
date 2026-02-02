/**
 * Accordion Component
 *
 * A reusable, accessible accordion component with full keyboard support
 * and smooth animations.
 *
 * Features:
 * - Single item open at a time
 * - Smooth height animations (300ms)
 * - Full ARIA support
 * - Keyboard navigation (Tab, Enter, Space, Escape, Arrow keys)
 * - Automatic height calculation
 * - Mobile-friendly
 *
 * @example
 * const accordion = new Accordion(element, {
 *   duration: 300,
 *   allowMultiple: false,
 *   expandOnInit: false
 * });
 */
export class Accordion {
  /**
   * Create an accordion instance
   * @param {HTMLElement} element - The accordion container element
   * @param {Object} options - Configuration options
   * @param {number} options.duration - Animation duration in ms (default: 300)
   * @param {boolean} options.allowMultiple - Allow multiple items open (default: false)
   * @param {boolean} options.expandOnInit - Expand first item on init (default: false)
   */
  constructor(element, options = {}) {
    this.element = element;
    this.options = {
      duration: options.duration || 300,
      allowMultiple: options.allowMultiple || false,
      expandOnInit: options.expandOnInit || false,
    };

    this.items = [];
    this.init();
  }

  /**
   * Initialize the accordion
   */
  init() {
    // Find all accordion items
    const itemElements = this.element.querySelectorAll('[data-accordion-item]');

    itemElements.forEach((itemElement, index) => {
      const trigger = itemElement.querySelector('[data-accordion-trigger]');
      const content = itemElement.querySelector('[data-accordion-content]');
      const icon = itemElement.querySelector('[data-accordion-icon]');

      if (!trigger || !content) {
        console.warn('Accordion item missing trigger or content:', itemElement);
        return;
      }

      const item = {
        element: itemElement,
        trigger,
        content,
        icon,
        index,
        isOpen: false,
      };

      this.items.push(item);
      this.bindEvents(item);
    });

    // Optionally expand first item
    if (this.options.expandOnInit && this.items.length > 0) {
      this.open(this.items[0]);
    }
  }

  /**
   * Bind events for an accordion item
   * @param {Object} item - The accordion item object
   */
  bindEvents(item) {
    // Click event
    item.trigger.addEventListener('click', (e) => {
      e.preventDefault();
      this.toggle(item);
    });

    // Keyboard events
    item.trigger.addEventListener('keydown', (e) => {
      this.handleKeydown(e, item);
    });
  }

  /**
   * Handle keyboard navigation
   * @param {KeyboardEvent} e - The keyboard event
   * @param {Object} item - The current accordion item
   */
  handleKeydown(e, item) {
    const { key } = e;

    switch (key) {
      case 'Enter':
      case ' ': // Space
        e.preventDefault();
        this.toggle(item);
        break;

      case 'Escape':
        e.preventDefault();
        if (item.isOpen) {
          this.close(item);
        }
        break;

      case 'ArrowDown':
        e.preventDefault();
        this.focusNextItem(item.index);
        break;

      case 'ArrowUp':
        e.preventDefault();
        this.focusPrevItem(item.index);
        break;

      case 'Home':
        e.preventDefault();
        this.focusFirstItem();
        break;

      case 'End':
        e.preventDefault();
        this.focusLastItem();
        break;
    }
  }

  /**
   * Toggle an accordion item
   * @param {Object} item - The accordion item to toggle
   */
  toggle(item) {
    if (item.isOpen) {
      this.close(item);
    } else {
      this.open(item);
    }
  }

  /**
   * Open an accordion item
   * @param {Object} item - The accordion item to open
   */
  open(item) {
    // Close other items if allowMultiple is false
    if (!this.options.allowMultiple) {
      this.items.forEach((otherItem) => {
        if (otherItem !== item && otherItem.isOpen) {
          this.close(otherItem);
        }
      });
    }

    item.isOpen = true;

    // Update ARIA
    item.trigger.setAttribute('aria-expanded', 'true');

    // Rotate icon
    if (item.icon) {
      item.icon.style.transform = 'rotate(180deg)';
    }

    // Add active state to item
    item.element.classList.add('is-active');

    // Calculate and animate height
    const contentInner = item.content.querySelector('.accordion-inner');
    const height = contentInner.offsetHeight;

    item.content.style.maxHeight = `${height}px`;

    // Dispatch custom event
    this.dispatchEvent('accordion:open', { item });
  }

  /**
   * Close an accordion item
   * @param {Object} item - The accordion item to close
   */
  close(item) {
    item.isOpen = false;

    // Update ARIA
    item.trigger.setAttribute('aria-expanded', 'false');

    // Rotate icon back
    if (item.icon) {
      item.icon.style.transform = 'rotate(0deg)';
    }

    // Remove active state
    item.element.classList.remove('is-active');

    // Animate height to 0
    item.content.style.maxHeight = '0';

    // Dispatch custom event
    this.dispatchEvent('accordion:close', { item });
  }

  /**
   * Focus the next accordion item
   * @param {number} currentIndex - The current item index
   */
  focusNextItem(currentIndex) {
    const nextIndex = (currentIndex + 1) % this.items.length;
    this.items[nextIndex].trigger.focus();
  }

  /**
   * Focus the previous accordion item
   * @param {number} currentIndex - The current item index
   */
  focusPrevItem(currentIndex) {
    const prevIndex = (currentIndex - 1 + this.items.length) % this.items.length;
    this.items[prevIndex].trigger.focus();
  }

  /**
   * Focus the first accordion item
   */
  focusFirstItem() {
    if (this.items.length > 0) {
      this.items[0].trigger.focus();
    }
  }

  /**
   * Focus the last accordion item
   */
  focusLastItem() {
    if (this.items.length > 0) {
      this.items[this.items.length - 1].trigger.focus();
    }
  }

  /**
   * Dispatch a custom event
   * @param {string} eventName - The event name
   * @param {Object} detail - Event detail data
   */
  dispatchEvent(eventName, detail = {}) {
    const event = new CustomEvent(eventName, {
      bubbles: true,
      detail: { accordion: this, ...detail },
    });
    this.element.dispatchEvent(event);
  }

  /**
   * Destroy the accordion instance
   */
  destroy() {
    this.items.forEach((item) => {
      // Remove event listeners (clone and replace to remove all listeners)
      const newTrigger = item.trigger.cloneNode(true);
      item.trigger.parentNode.replaceChild(newTrigger, item.trigger);

      // Reset styles
      item.content.style.maxHeight = '';
      if (item.icon) {
        item.icon.style.transform = '';
      }

      // Reset ARIA
      item.trigger.setAttribute('aria-expanded', 'false');
      item.element.classList.remove('is-active');
    });

    this.items = [];
  }
}
