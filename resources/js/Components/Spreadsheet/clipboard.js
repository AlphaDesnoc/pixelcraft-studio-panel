// Sérialisation / désérialisation au format TSV (tab-separated), compatible
// avec le copier-coller de Google Sheets et Excel. Les champs contenant une
// tabulation, un retour à la ligne ou un guillemet sont entourés de guillemets
// doubles, les guillemets internes étant doublés (convention CSV/TSV).

export function serializeTable(grid) {
  return grid
    .map((row) =>
      row
        .map((value) => {
          const s = value == null ? "" : String(value);
          if (/[\t\n\r"]/.test(s)) {
            return '"' + s.replace(/"/g, '""') + '"';
          }
          return s;
        })
        .join("\t"),
    )
    .join("\n");
}

export function serializeCSV(grid) {
  return grid
    .map((row) =>
      row
        .map((value) => {
          const s = value == null ? "" : String(value);
          if (/[",\n\r]/.test(s)) {
            return '"' + s.replace(/"/g, '""') + '"';
          }
          return s;
        })
        .join(","),
    )
    .join("\r\n");
}

export function parseClipboardTable(text) {
  const rows = [];
  let row = [];
  let field = "";
  let inQuotes = false;
  let i = 0;

  const pushField = () => {
    row.push(field);
    field = "";
  };
  const pushRow = () => {
    pushField();
    rows.push(row);
    row = [];
  };

  while (i < text.length) {
    const ch = text[i];

    if (inQuotes) {
      if (ch === '"') {
        if (text[i + 1] === '"') {
          field += '"';
          i += 2;
          continue;
        }
        inQuotes = false;
        i++;
        continue;
      }
      field += ch;
      i++;
      continue;
    }

    if (ch === '"') {
      inQuotes = true;
      i++;
      continue;
    }
    if (ch === "\t") {
      pushField();
      i++;
      continue;
    }
    if (ch === "\r") {
      i++;
      continue;
    }
    if (ch === "\n") {
      pushRow();
      i++;
      continue;
    }
    field += ch;
    i++;
  }

  // Dernier champ / dernière ligne (sans créer de ligne vide superflue quand
  // le texte se termine par un saut de ligne).
  if (field !== "" || row.length > 0) {
    pushRow();
  }

  return rows;
}
