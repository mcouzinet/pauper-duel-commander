/**
 * Squarified treemap (Bruls, Huizing and van Wijk, 2000).
 *
 * Lays out values as rectangles that fill a W x H box, each with an area
 * proportional to its value, keeping them as close to square as the values
 * allow. Used by the meta mosaic, where a commander's tile is its share of the
 * players. Values are drawn in the order given: sort them first (largest
 * first) for the squarest result.
 *
 * Check: `node scripts/check-treemap.mjs`.
 */
export interface Box {
  x: number;
  y: number;
  w: number;
  h: number;
}

export function squarify<T>(items: { value: number; data: T }[], W: number, H: number): (Box & { data: T })[] {
  const total = items.reduce((sum, item) => sum + item.value, 0);
  if (total <= 0 || W <= 0 || H <= 0) return [];

  const queue = items.filter(item => item.value > 0).map(item => ({ area: (item.value / total) * W * H, data: item.data }));
  const out: (Box & { data: T })[] = [];
  let free: Box = { x: 0, y: 0, w: W, h: H };
  let row: typeof queue = [];

  const sum = (list: typeof queue) => list.reduce((s, item) => s + item.area, 0);

  /** Worst aspect ratio in a row laid along a side of this length. */
  const worst = (list: typeof queue, side: number) => {
    const s = sum(list);
    const max = Math.max(...list.map(item => item.area));
    const min = Math.min(...list.map(item => item.area));
    return Math.max((side * side * max) / (s * s), (s * s) / (side * side * min));
  };

  /** Lay a row along the shorter side of the free space, then shrink it. */
  const place = (list: typeof queue) => {
    const s = sum(list);
    if (free.w >= free.h) {
      const w = s / free.h;
      let y = free.y;
      for (const item of list) {
        const h = item.area / w;
        out.push({ x: free.x, y, w, h, data: item.data });
        y += h;
      }
      free = { x: free.x + w, y: free.y, w: free.w - w, h: free.h };
    } else {
      const h = s / free.w;
      let x = free.x;
      for (const item of list) {
        const w = item.area / h;
        out.push({ x, y: free.y, w, h, data: item.data });
        x += w;
      }
      free = { x: free.x, y: free.y + h, w: free.w, h: free.h - h };
    }
  };

  while (queue.length > 0) {
    const side = Math.min(free.w, free.h);
    const next = queue[0];
    if (row.length === 0 || worst([...row, next], side) <= worst(row, side)) {
      row.push(next);
      queue.shift();
    } else {
      place(row);
      row = [];
    }
  }
  if (row.length > 0) place(row);
  return out;
}
