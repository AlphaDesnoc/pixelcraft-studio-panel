// Transformations pures sur la map de cellules d'une feuille.
// La map est un objet { "A1": { v, bg?, fg?, b?, i? }, ... }.
// Ces fonctions renvoient toujours une nouvelle map (pas de mutation en place)
// et ajustent les références des formules lorsque des lignes/colonnes bougent.

import { cellKey, colLettersToIndex, indexToColLetters, parseCellRef } from "./cells.js";

const REF_RE = /(\$?)([A-Za-z]+)(\$?)(\d+)/g;

// Réécrit les références d'une formule via fn({col,row,absCol,absRow}).
// fn renvoie {col,row} décalé, ou null pour invalider la référence (#REF!).
export function remapRefs(value, fn) {
  if (typeof value !== "string" || !value.startsWith("=")) return value;
  return (
    "=" +
    value.slice(1).replace(REF_RE, (m, ad, colL, ar, rowD) => {
      const col = colLettersToIndex(colL);
      const row = parseInt(rowD, 10) - 1;
      const res = fn({ col, row, absCol: Boolean(ad), absRow: Boolean(ar) });
      if (!res) return "#REF!";
      return (
        (ad ? "$" : "") +
        indexToColLetters(res.col) +
        (ar ? "$" : "") +
        (res.row + 1)
      );
    })
  );
}

function mapCells(data, keyFn, valueFn) {
  const next = {};
  for (const [key, cell] of Object.entries(data)) {
    const ref = parseCellRef(key);
    if (!ref) continue;
    const moved = keyFn(ref); // {col,row} | null  (null = cellule supprimée)
    if (!moved) continue;
    const newCell = valueFn ? { ...cell, v: valueFn(cell.v) } : { ...cell };
    next[cellKey(moved.col, moved.row)] = newCell;
  }
  return next;
}

export function insertRows(data, at, count = 1) {
  const shiftRef = (r) => (r.row >= at ? { ...r, row: r.row + count } : r);
  return mapCells(
    data,
    (ref) => (ref.row >= at ? { col: ref.col, row: ref.row + count } : ref),
    (v) => remapRefs(v, (r) => shiftRef(r)),
  );
}

export function deleteRows(data, at, count = 1) {
  return mapCells(
    data,
    (ref) => {
      if (ref.row >= at && ref.row < at + count) return null; // dans la zone supprimée
      return ref.row >= at + count ? { col: ref.col, row: ref.row - count } : ref;
    },
    (v) =>
      remapRefs(v, (r) => {
        if (r.row >= at && r.row < at + count) return null;
        return r.row >= at + count ? { ...r, row: r.row - count } : r;
      }),
  );
}

export function insertCols(data, at, count = 1) {
  return mapCells(
    data,
    (ref) => (ref.col >= at ? { col: ref.col + count, row: ref.row } : ref),
    (v) =>
      remapRefs(v, (r) => (r.col >= at ? { ...r, col: r.col + count } : r)),
  );
}

export function deleteCols(data, at, count = 1) {
  return mapCells(
    data,
    (ref) => {
      if (ref.col >= at && ref.col < at + count) return null;
      return ref.col >= at + count ? { col: ref.col - count, row: ref.row } : ref;
    },
    (v) =>
      remapRefs(v, (r) => {
        if (r.col >= at && r.col < at + count) return null;
        return r.col >= at + count ? { ...r, col: r.col - count } : r;
      }),
  );
}

// Réordonne les lignes selon `oldToNew` (oldRow -> newRow). Les colonnes ne
// bougent pas. Utilisé par le tri. Les références de formules ne sont pas
// réécrites (le tri déplace les blocs de cellules tels quels).
export function reorderRows(data, oldToNew) {
  const next = {};
  for (const [key, cell] of Object.entries(data)) {
    const ref = parseCellRef(key);
    if (!ref) continue;
    const newRow = oldToNew[ref.row];
    next[cellKey(ref.col, newRow == null ? ref.row : newRow)] = { ...cell };
  }
  return next;
}

// Décale les références relatives d'une formule de (dCol, dRow) — utilisé par
// la recopie incrémentée pour propager une formule.
export function shiftFormulaRefs(value, dCol, dRow) {
  return remapRefs(value, ({ col, row, absCol, absRow }) => ({
    col: absCol ? col : col + dCol,
    row: absRow ? row : row + dRow,
  }));
}

// Construit les valeurs de recopie pour UNE ligne/série source vers `length`
// cellules. Numérique pur → progression arithmétique ; formule → références
// décalées ; sinon → répétition cyclique.
// `step` est le décalage (col,row) appliqué à chaque pas pour les formules.
export function buildFillSeries(source, length, step) {
  if (!source.length || length <= 0) return [];

  const allNumeric =
    source.length >= 1 &&
    source.every((v) => v !== "" && v != null && /^-?[0-9]*[.,]?[0-9]+$/.test(String(v).trim()));

  if (allNumeric) {
    const nums = source.map((v) => parseFloat(String(v).replace(",", ".")));
    let delta = 1;
    if (nums.length >= 2) {
      delta = nums[nums.length - 1] - nums[nums.length - 2];
    } else {
      delta = 0; // une seule valeur numérique → simple copie
    }
    const out = [];
    let last = nums[nums.length - 1];
    for (let k = 0; k < length; k++) {
      last += delta;
      const rounded = Math.round(last * 1e10) / 1e10;
      out.push(String(rounded));
    }
    return out;
  }

  const out = [];
  for (let k = 0; k < length; k++) {
    const src = source[k % source.length];
    if (typeof src === "string" && src.startsWith("=")) {
      const reps = Math.floor(k / source.length) + 1;
      out.push(shiftFormulaRefs(src, step.col * reps, step.row * reps));
    } else {
      out.push(src);
    }
  }
  return out;
}
