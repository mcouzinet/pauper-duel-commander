// Self-check for src/lib/treemap.ts (Node 22.18+ strips the types itself).
//   node scripts/check-treemap.mjs
import assert from 'node:assert/strict';
import { squarify } from '../src/lib/treemap.ts';

const values = [12, 10, 6, 6, 6, 5, 5, 5, 5, 4, 4, 4, 4, 3, 3, 2, 2, 2, 1, 1, 1, 1, 90];
const W = 1232;
const H = 540;
const boxes = squarify(values.map((value, i) => ({ value, data: i })), W, H);
const total = values.reduce((s, v) => s + v, 0);
const near = (a, b) => Math.abs(a - b) < 1e-6 * W * H;

assert.equal(boxes.length, values.length, 'one box per value');
for (const box of boxes) {
  // Area proportional to the value.
  assert.ok(near(box.w * box.h, (values[box.data] / total) * W * H), `area of #${box.data}`);
  // Inside the frame.
  assert.ok(box.x >= -1e-9 && box.y >= -1e-9 && box.x + box.w <= W + 1e-6 && box.y + box.h <= H + 1e-6, `#${box.data} inside`);
}
// No two boxes overlap.
for (let i = 0; i < boxes.length; i++) {
  for (let j = i + 1; j < boxes.length; j++) {
    const a = boxes[i];
    const b = boxes[j];
    const overlapW = Math.min(a.x + a.w, b.x + b.w) - Math.max(a.x, b.x);
    const overlapH = Math.min(a.y + a.h, b.y + b.h) - Math.max(a.y, b.y);
    assert.ok(overlapW <= 1e-6 || overlapH <= 1e-6, `#${a.data} and #${b.data} overlap`);
  }
}
// Together they fill the frame.
assert.ok(near(boxes.reduce((s, b) => s + b.w * b.h, 0), W * H), 'frame filled');
assert.deepEqual(squarify([], W, H), [], 'nothing to draw');

console.log(`treemap ok: ${boxes.length} boxes`);
