/**
 * Card image hover preview
 * Shows a floating card image when hovering over elements with class "card-hover-trigger"
 * The image URL is read from the data-card-image attribute.
 */
let preview: HTMLDivElement | null = null;

function getPreview(): HTMLDivElement {
  if (!preview) {
    preview = document.createElement('div');
    preview.id = 'card-preview';
    preview.style.cssText = 'position:fixed;z-index:10000;pointer-events:none;display:none;transition:opacity 0.15s;';
    preview.innerHTML = '<img style="width:250px;height:auto;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.5);" />';
    document.body.appendChild(preview);
  }
  return preview;
}

function showPreview(imgUrl: string) {
  const el = getPreview();
  const img = el.querySelector('img') as HTMLImageElement;
  img.src = imgUrl;
  el.style.display = 'block';
}

function hidePreview() {
  const el = getPreview();
  el.style.display = 'none';
}

function movePreview(e: MouseEvent) {
  const el = getPreview();
  const x = e.clientX + 15;
  const y = e.clientY + 15;
  // Keep within viewport
  const maxX = window.innerWidth - 270;
  const maxY = window.innerHeight - 370;
  el.style.left = `${Math.min(x, maxX)}px`;
  el.style.top = `${Math.min(y, maxY)}px`;
}

// Attach to all triggers
document.querySelectorAll('.card-hover-trigger').forEach(el => {
  const imgUrl = (el as HTMLElement).dataset.cardImage;
  if (!imgUrl) return;

  el.addEventListener('mouseenter', () => showPreview(imgUrl));
  el.addEventListener('mouseleave', hidePreview);
  el.addEventListener('mousemove', (e) => movePreview(e as MouseEvent));
});
