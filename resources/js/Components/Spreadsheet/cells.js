export function colLettersToIndex(letters) {
  let n = 0;
  for (const ch of letters.toUpperCase()) {
    n = n * 26 + (ch.charCodeAt(0) - 64);
  }
  return n - 1;
}

export function indexToColLetters(idx) {
  let n = idx + 1;
  let s = "";
  while (n > 0) {
    const r = (n - 1) % 26;
    s = String.fromCharCode(65 + r) + s;
    n = Math.floor((n - 1) / 26);
  }
  return s;
}

export function cellKey(col, row) {
  return indexToColLetters(col) + (row + 1);
}

export function parseCellRef(ref) {
  const m = /^\$?([A-Z]+)\$?(\d+)$/i.exec(ref);
  if (!m) return null;
  return { col: colLettersToIndex(m[1]), row: parseInt(m[2], 10) - 1 };
}

export function normalizeRange(a, b) {
  return {
    r1: Math.min(a.row, b.row),
    r2: Math.max(a.row, b.row),
    c1: Math.min(a.col, b.col),
    c2: Math.max(a.col, b.col),
  };
}

export function selectionLabel(anchor, focus) {
  if (!anchor) return "";
  if (!focus || (anchor.row === focus.row && anchor.col === focus.col)) {
    return cellKey(anchor.col, anchor.row);
  }
  const r = normalizeRange(anchor, focus);
  return `${cellKey(r.c1, r.r1)}:${cellKey(r.c2, r.r2)}`;
}

export function selectionCount(anchor, focus) {
  if (!anchor) return 0;
  if (!focus) return 1;
  const r = normalizeRange(anchor, focus);
  return (r.r2 - r.r1 + 1) * (r.c2 - r.c1 + 1);
}

export function* iterSelection(anchor, focus) {
  const target = focus ?? anchor;
  const r = normalizeRange(anchor, target);
  for (let row = r.r1; row <= r.r2; row++) {
    for (let col = r.c1; col <= r.c2; col++) {
      yield { row, col };
    }
  }
}
