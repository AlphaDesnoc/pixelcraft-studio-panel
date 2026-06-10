// Catégorisation partagée des fichiers pour l'aperçu et l'affichage.

const TEXT_EXTENSIONS = [
  "txt",
  "md",
  "markdown",
  "csv",
  "log",
  "json",
  "xml",
  "yml",
  "yaml",
  "ini",
  "env",
  "conf",
  "js",
  "ts",
  "jsx",
  "tsx",
  "vue",
  "css",
  "scss",
  "html",
  "php",
  "py",
  "rb",
  "go",
  "rs",
  "java",
  "c",
  "cpp",
  "h",
  "sh",
  "bat",
  "sql",
];

export function fileExtension(name) {
  const idx = (name ?? "").lastIndexOf(".");
  return idx >= 0 ? name.slice(idx + 1).toLowerCase() : "";
}

// Retourne 'image' | 'video' | 'audio' | 'pdf' | 'text' | null
export function fileKind(node) {
  if (!node || node.type === "folder") return null;
  const mime = node.mime ?? "";
  const ext = fileExtension(node.name);

  if (mime.startsWith("image/")) return "image";
  if (mime.startsWith("video/")) return "video";
  if (mime.startsWith("audio/")) return "audio";
  if (mime.includes("pdf") || ext === "pdf") return "pdf";

  if (
    mime.startsWith("text/") ||
    mime.includes("json") ||
    mime.includes("xml") ||
    mime.includes("javascript") ||
    TEXT_EXTENSIONS.includes(ext)
  ) {
    return "text";
  }

  return null;
}

export function isViewable(node) {
  return fileKind(node) !== null;
}
