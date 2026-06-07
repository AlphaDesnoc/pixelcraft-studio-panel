import { cellKey, colLettersToIndex, indexToColLetters } from "./cells.js";

class FormulaError extends Error {
  constructor(code) {
    super(code);
    this.code = code;
  }
}

function tokenize(input) {
  const tokens = [];
  let i = 0;
  while (i < input.length) {
    const ch = input[i];

    if (/\s/.test(ch)) {
      i++;
      continue;
    }

    if (ch === '"') {
      let j = i + 1;
      while (j < input.length && input[j] !== '"') j++;
      if (j >= input.length) throw new FormulaError("#ERREUR!");
      tokens.push({ type: "str", value: input.slice(i + 1, j) });
      i = j + 1;
      continue;
    }

    if (/[0-9]/.test(ch) || (ch === "." && /[0-9]/.test(input[i + 1] ?? ""))) {
      const m = /^[0-9]+(\.[0-9]+)?|^\.[0-9]+/.exec(input.slice(i));
      tokens.push({ type: "num", value: parseFloat(m[0]) });
      i += m[0].length;
      continue;
    }

    if (/[A-Za-z_]/.test(ch)) {
      const m = /^[A-Za-z_][A-Za-z0-9_]*/.exec(input.slice(i));
      const word = m[0];
      i += word.length;

      const cellMatch = /^([A-Za-z]+)(\d+)$/.exec(word);
      if (cellMatch) {
        const col = colLettersToIndex(cellMatch[1]);
        const row = parseInt(cellMatch[2], 10) - 1;
        if (input[i] === ":") {
          const m2 = /^:([A-Za-z]+)(\d+)/.exec(input.slice(i));
          if (m2) {
            const col2 = colLettersToIndex(m2[1]);
            const row2 = parseInt(m2[2], 10) - 1;
            tokens.push({
              type: "range",
              from: { col, row },
              to: { col: col2, row: row2 },
            });
            i += m2[0].length;
            continue;
          }
        }
        tokens.push({ type: "cell", col, row });
        continue;
      }

      tokens.push({ type: "ident", name: word.toUpperCase() });
      continue;
    }

    const two = input.slice(i, i + 2);
    if (["<=", ">=", "<>"].includes(two)) {
      tokens.push({ type: "op", value: two });
      i += 2;
      continue;
    }
    if ("+-*/^%=<>".includes(ch)) {
      tokens.push({ type: "op", value: ch });
      i++;
      continue;
    }

    if (ch === "(") {
      tokens.push({ type: "lparen" });
      i++;
      continue;
    }
    if (ch === ")") {
      tokens.push({ type: "rparen" });
      i++;
      continue;
    }
    if (ch === "," || ch === ";") {
      tokens.push({ type: "comma" });
      i++;
      continue;
    }

    throw new FormulaError("#ERREUR!");
  }
  return tokens;
}

function parse(tokens) {
  let pos = 0;
  const peek = () => tokens[pos];
  const eat = (type, value) => {
    const t = tokens[pos];
    if (!t || t.type !== type || (value !== undefined && t.value !== value)) {
      throw new FormulaError("#ERREUR!");
    }
    pos++;
    return t;
  };

  function parseCompare() {
    let left = parseAdd();
    while (
      peek() &&
      peek().type === "op" &&
      ["=", "<>", "<", ">", "<=", ">="].includes(peek().value)
    ) {
      const op = eat("op").value;
      const right = parseAdd();
      left = { type: "binop", op, left, right };
    }
    return left;
  }

  function parseAdd() {
    let left = parseMul();
    while (peek() && peek().type === "op" && ["+", "-"].includes(peek().value)) {
      const op = eat("op").value;
      const right = parseMul();
      left = { type: "binop", op, left, right };
    }
    return left;
  }

  function parseMul() {
    let left = parsePow();
    while (peek() && peek().type === "op" && ["*", "/"].includes(peek().value)) {
      const op = eat("op").value;
      const right = parsePow();
      left = { type: "binop", op, left, right };
    }
    return left;
  }

  function parsePow() {
    const left = parseUnary();
    if (peek() && peek().type === "op" && peek().value === "^") {
      eat("op");
      const right = parsePow();
      return { type: "binop", op: "^", left, right };
    }
    return left;
  }

  function parseUnary() {
    if (peek() && peek().type === "op" && (peek().value === "-" || peek().value === "+")) {
      const op = eat("op").value;
      return { type: "unary", op, value: parseUnary() };
    }
    return parsePostfix();
  }

  function parsePostfix() {
    let node = parsePrimary();
    while (peek() && peek().type === "op" && peek().value === "%") {
      eat("op");
      node = { type: "unary", op: "%", value: node };
    }
    return node;
  }

  function parsePrimary() {
    const t = peek();
    if (!t) throw new FormulaError("#ERREUR!");
    if (t.type === "num") {
      pos++;
      return { type: "num", value: t.value };
    }
    if (t.type === "str") {
      pos++;
      return { type: "str", value: t.value };
    }
    if (t.type === "cell") {
      pos++;
      return { type: "cell", col: t.col, row: t.row };
    }
    if (t.type === "range") {
      pos++;
      return { type: "range", from: t.from, to: t.to };
    }
    if (t.type === "lparen") {
      eat("lparen");
      const e = parseCompare();
      eat("rparen");
      return e;
    }
    if (t.type === "ident") {
      const name = eat("ident").name;
      if (peek() && peek().type === "lparen") {
        eat("lparen");
        const args = [];
        if (peek() && peek().type !== "rparen") {
          args.push(parseCompare());
          while (peek() && peek().type === "comma") {
            eat("comma");
            args.push(parseCompare());
          }
        }
        eat("rparen");
        return { type: "func", name, args };
      }
      if (name === "TRUE" || name === "VRAI") return { type: "num", value: 1 };
      if (name === "FALSE" || name === "FAUX") return { type: "num", value: 0 };
      throw new FormulaError("#NOM?");
    }
    throw new FormulaError("#ERREUR!");
  }

  const ast = parseCompare();
  if (pos < tokens.length) throw new FormulaError("#ERREUR!");
  return ast;
}

function toNumber(v) {
  if (typeof v === "number") return v;
  if (typeof v === "boolean") return v ? 1 : 0;
  if (typeof v === "string") {
    const trimmed = v.trim();
    if (trimmed === "") return 0;
    const n = parseFloat(trimmed.replace(",", "."));
    if (isNaN(n)) throw new FormulaError("#VALEUR!");
    return n;
  }
  if (v == null) return 0;
  throw new FormulaError("#VALEUR!");
}

function rawCellNumber(raw) {
  if (typeof raw !== "string") return null;
  const t = raw.trim();
  if (t === "") return null;
  if (!/^-?[0-9]*[\.,]?[0-9]+$/.test(t)) return null;
  const n = parseFloat(t.replace(",", "."));
  return isNaN(n) ? null : n;
}

function getCellValue(col, row, ctx) {
  const key = cellKey(col, row);
  if (ctx.evaluating.has(key)) throw new FormulaError("#CIRC!");
  const cell = ctx.cellsMap[key];
  if (!cell || cell.v == null || cell.v === "") return "";
  const raw = cell.v;
  if (typeof raw === "string" && raw.startsWith("=")) {
    ctx.evaluating.add(key);
    try {
      const tokens = tokenize(raw.slice(1));
      const ast = parse(tokens);
      return evaluate(ast, ctx);
    } finally {
      ctx.evaluating.delete(key);
    }
  }
  const num = rawCellNumber(typeof raw === "string" ? raw : String(raw));
  if (num !== null) return num;
  return raw;
}

function expandRange(node, ctx) {
  const values = [];
  const r1 = Math.min(node.from.row, node.to.row);
  const r2 = Math.max(node.from.row, node.to.row);
  const c1 = Math.min(node.from.col, node.to.col);
  const c2 = Math.max(node.from.col, node.to.col);
  for (let r = r1; r <= r2; r++) {
    for (let c = c1; c <= c2; c++) {
      values.push(getCellValue(c, r, ctx));
    }
  }
  return values;
}

function flatten(args, ctx) {
  const out = [];
  for (const a of args) {
    if (a && a.type === "range") out.push(...expandRange(a, ctx));
    else out.push(evaluate(a, ctx));
  }
  return out;
}

// Lit un nœud "range" sous forme de grille 2D (pour RECHERCHEV).
function rangeGrid(node, ctx) {
  if (!node || node.type !== "range") throw new FormulaError("#REF!");
  const r1 = Math.min(node.from.row, node.to.row);
  const r2 = Math.max(node.from.row, node.to.row);
  const c1 = Math.min(node.from.col, node.to.col);
  const c2 = Math.max(node.from.col, node.to.col);
  const grid = [];
  for (let r = r1; r <= r2; r++) {
    const row = [];
    for (let c = c1; c <= c2; c++) row.push(getCellValue(c, r, ctx));
    grid.push(row);
  }
  return grid;
}

function parseDateValue(v) {
  if (v instanceof Date) return v;
  const s = String(v).trim();
  let m = /^(\d{4})-(\d{1,2})-(\d{1,2})/.exec(s);
  if (m) return new Date(+m[1], +m[2] - 1, +m[3]);
  m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})/.exec(s);
  if (m) return new Date(+m[3], +m[2] - 1, +m[1]);
  const d = new Date(s);
  return isNaN(d.getTime()) ? null : d;
}

function numericValues(args, ctx) {
  return flatten(args, ctx)
    .map((v) => {
      if (v === "" || v == null) return null;
      if (typeof v === "number") return v;
      const n = parseFloat(String(v).replace(",", "."));
      return isNaN(n) ? null : n;
    })
    .filter((v) => v !== null);
}

const FUNCS = {
  SUM(args, ctx) {
    return numericValues(args, ctx).reduce((s, v) => s + v, 0);
  },
  AVERAGE(args, ctx) {
    const nums = numericValues(args, ctx);
    if (!nums.length) throw new FormulaError("#DIV/0!");
    return nums.reduce((s, v) => s + v, 0) / nums.length;
  },
  MIN(args, ctx) {
    const nums = numericValues(args, ctx);
    return nums.length ? Math.min(...nums) : 0;
  },
  MAX(args, ctx) {
    const nums = numericValues(args, ctx);
    return nums.length ? Math.max(...nums) : 0;
  },
  COUNT(args, ctx) {
    return numericValues(args, ctx).length;
  },
  COUNTA(args, ctx) {
    return flatten(args, ctx).filter((v) => v !== "" && v != null).length;
  },
  IF(args, ctx) {
    if (args.length < 2) throw new FormulaError("#ERREUR!");
    const cond = evaluate(args[0], ctx);
    const truthy =
      cond !== 0 && cond !== "" && cond != null && cond !== false;
    if (truthy) return evaluate(args[1], ctx);
    return args[2] ? evaluate(args[2], ctx) : 0;
  },
  AND(args, ctx) {
    const vals = flatten(args, ctx);
    return vals.every((v) => v !== 0 && v !== "" && v != null && v !== false) ? 1 : 0;
  },
  OR(args, ctx) {
    const vals = flatten(args, ctx);
    return vals.some((v) => v !== 0 && v !== "" && v != null && v !== false) ? 1 : 0;
  },
  NOT(args, ctx) {
    const v = evaluate(args[0], ctx);
    return v === 0 || v === "" || v == null || v === false ? 1 : 0;
  },
  ABS(args, ctx) {
    return Math.abs(toNumber(evaluate(args[0], ctx)));
  },
  ROUND(args, ctx) {
    const v = toNumber(evaluate(args[0], ctx));
    const d = args[1] ? toNumber(evaluate(args[1], ctx)) : 0;
    const f = Math.pow(10, d);
    return Math.round(v * f) / f;
  },
  INT(args, ctx) {
    return Math.floor(toNumber(evaluate(args[0], ctx)));
  },
  MOD(args, ctx) {
    const a = toNumber(evaluate(args[0], ctx));
    const b = toNumber(evaluate(args[1], ctx));
    if (b === 0) throw new FormulaError("#DIV/0!");
    return a - b * Math.floor(a / b);
  },
  POWER(args, ctx) {
    return Math.pow(
      toNumber(evaluate(args[0], ctx)),
      toNumber(evaluate(args[1], ctx)),
    );
  },
  SQRT(args, ctx) {
    const v = toNumber(evaluate(args[0], ctx));
    if (v < 0) throw new FormulaError("#NOMBRE!");
    return Math.sqrt(v);
  },
  CONCAT(args, ctx) {
    return flatten(args, ctx)
      .map((v) => (v == null ? "" : String(v)))
      .join("");
  },
  UPPER(args, ctx) {
    return String(evaluate(args[0], ctx) ?? "").toUpperCase();
  },
  LOWER(args, ctx) {
    return String(evaluate(args[0], ctx) ?? "").toLowerCase();
  },
  LEN(args, ctx) {
    return String(evaluate(args[0], ctx) ?? "").length;
  },
  TRIM(args, ctx) {
    return String(evaluate(args[0], ctx) ?? "").trim();
  },
  NOW() {
    return new Date().toLocaleString("fr-FR");
  },
  TODAY() {
    return new Date().toLocaleDateString("fr-FR");
  },
  PI() {
    return Math.PI;
  },
  VLOOKUP(args, ctx) {
    if (args.length < 3) throw new FormulaError("#ERREUR!");
    const key = evaluate(args[0], ctx);
    const grid = rangeGrid(args[1], ctx);
    const index = Math.trunc(toNumber(evaluate(args[2], ctx)));
    if (index < 1) throw new FormulaError("#VALEUR!");
    for (const row of grid) {
      const first = row[0];
      const same =
        first === key ||
        String(first).toLowerCase() === String(key).toLowerCase();
      if (same) {
        if (index > row.length) throw new FormulaError("#REF!");
        return row[index - 1] ?? "";
      }
    }
    throw new FormulaError("#N/A");
  },
  YEAR(args, ctx) {
    const d = parseDateValue(evaluate(args[0], ctx));
    if (!d) throw new FormulaError("#VALEUR!");
    return d.getFullYear();
  },
  MONTH(args, ctx) {
    const d = parseDateValue(evaluate(args[0], ctx));
    if (!d) throw new FormulaError("#VALEUR!");
    return d.getMonth() + 1;
  },
  DAY(args, ctx) {
    const d = parseDateValue(evaluate(args[0], ctx));
    if (!d) throw new FormulaError("#VALEUR!");
    return d.getDate();
  },
  DATE(args, ctx) {
    const y = Math.trunc(toNumber(evaluate(args[0], ctx)));
    const m = Math.trunc(toNumber(evaluate(args[1], ctx)));
    const d = Math.trunc(toNumber(evaluate(args[2], ctx)));
    return new Date(y, m - 1, d).toLocaleDateString("fr-FR");
  },
};

const ALIASES = {
  SOMME: "SUM",
  MOYENNE: "AVERAGE",
  NB: "COUNT",
  NBVAL: "COUNTA",
  SI: "IF",
  ET: "AND",
  OU: "OR",
  NON: "NOT",
  ARRONDI: "ROUND",
  ENT: "INT",
  MODULO: "MOD",
  PUISSANCE: "POWER",
  RACINE: "SQRT",
  CONCATENER: "CONCAT",
  CONCATENATE: "CONCAT",
  MAJUSCULE: "UPPER",
  MINUSCULE: "LOWER",
  NBCAR: "LEN",
  SUPPRESPACE: "TRIM",
  MAINTENANT: "NOW",
  AUJOURDHUI: "TODAY",
  RECHERCHEV: "VLOOKUP",
  ANNEE: "YEAR",
  MOIS: "MONTH",
  JOUR: "DAY",
};

function evaluate(node, ctx) {
  switch (node.type) {
    case "num":
      return node.value;
    case "str":
      return node.value;
    case "cell":
      return getCellValue(node.col, node.row, ctx);
    case "range":
      throw new FormulaError("#VALEUR!");
    case "unary": {
      const v = evaluate(node.value, ctx);
      if (node.op === "-") return -toNumber(v);
      if (node.op === "+") return toNumber(v);
      if (node.op === "%") return toNumber(v) / 100;
      throw new FormulaError("#ERREUR!");
    }
    case "binop": {
      const l = evaluate(node.left, ctx);
      const r = evaluate(node.right, ctx);
      switch (node.op) {
        case "+":
          return toNumber(l) + toNumber(r);
        case "-":
          return toNumber(l) - toNumber(r);
        case "*":
          return toNumber(l) * toNumber(r);
        case "/": {
          const rn = toNumber(r);
          if (rn === 0) throw new FormulaError("#DIV/0!");
          return toNumber(l) / rn;
        }
        case "^":
          return Math.pow(toNumber(l), toNumber(r));
        case "=":
          return l === r || String(l) === String(r) ? 1 : 0;
        case "<>":
          return l !== r && String(l) !== String(r) ? 1 : 0;
        case "<":
          return toNumber(l) < toNumber(r) ? 1 : 0;
        case ">":
          return toNumber(l) > toNumber(r) ? 1 : 0;
        case "<=":
          return toNumber(l) <= toNumber(r) ? 1 : 0;
        case ">=":
          return toNumber(l) >= toNumber(r) ? 1 : 0;
      }
      throw new FormulaError("#ERREUR!");
    }
    case "func": {
      const name = ALIASES[node.name] || node.name;
      const fn = FUNCS[name];
      if (!fn) throw new FormulaError("#NOM?");
      return fn(node.args, ctx);
    }
  }
  throw new FormulaError("#ERREUR!");
}

export function evalCell(raw, cellsMap, currentCell) {
  if (raw == null || raw === "") return "";
  if (typeof raw !== "string") return raw;
  if (!raw.startsWith("=")) {
    const trimmed = raw.trim();
    if (trimmed === "") return "";
    if (/^-?[0-9]*[\.,]?[0-9]+$/.test(trimmed)) {
      const n = parseFloat(trimmed.replace(",", "."));
      if (!isNaN(n)) return n;
    }
    return raw;
  }
  const expr = raw.slice(1).trim();
  if (!expr) return "";
  try {
    const tokens = tokenize(expr);
    const ast = parse(tokens);
    return evaluate(ast, {
      cellsMap,
      currentCell,
      evaluating: new Set([currentCell]),
    });
  } catch (e) {
    if (e instanceof FormulaError) return e.code;
    return "#ERREUR!";
  }
}

export function formatValue(v) {
  if (v == null) return "";
  if (typeof v === "number") {
    if (!isFinite(v)) return "#NOMBRE!";
    if (Number.isInteger(v)) return String(v);
    return String(parseFloat(v.toFixed(10)));
  }
  return String(v);
}

// Applique un format de nombre (€, %, séparateur de milliers) à une valeur
// numérique. Pour les autres valeurs, retombe sur formatValue.
export function formatCell(value, fmt) {
  if (fmt && typeof value === "number" && isFinite(value)) {
    if (fmt === "currency") {
      return new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency: "EUR",
      }).format(value);
    }
    if (fmt === "percent") {
      return new Intl.NumberFormat("fr-FR", {
        style: "percent",
        maximumFractionDigits: 2,
      }).format(value);
    }
    if (fmt === "number") {
      return new Intl.NumberFormat("fr-FR", {
        maximumFractionDigits: 2,
      }).format(value);
    }
  }
  return formatValue(value);
}

export { indexToColLetters };
