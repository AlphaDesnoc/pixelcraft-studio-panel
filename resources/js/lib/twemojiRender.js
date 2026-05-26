import twemoji from "twemoji";

export const TWEMOJI_OPTIONS = {
  base: "https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/",
  folder: "svg",
  ext: ".svg",
  className: "twemoji",
};

function normalizeEmoji(emoji) {
  if (!emoji) {
    return "";
  }
  return emoji.indexOf("\u200d") < 0 ? emoji.replace(/\uFE0F/g, "") : emoji;
}

export function emojiToUrl(emoji) {
  const codepoint = twemoji.convert.toCodePoint(normalizeEmoji(emoji));

  return `${TWEMOJI_OPTIONS.base}${TWEMOJI_OPTIONS.folder}/${codepoint}${TWEMOJI_OPTIONS.ext}`;
}

export function parseEmojis(text) {
  if (!text) {
    return "";
  }

  return twemoji.parse(text, TWEMOJI_OPTIONS);
}

export function parseEmojisInHtml(html) {
  if (!html) {
    return "";
  }

  if (typeof document === "undefined") {
    return html;
  }

  const container = document.createElement("div");
  container.innerHTML = html;
  twemoji.parse(container, TWEMOJI_OPTIONS);

  return container.innerHTML;
}

export function escapeHtml(text) {
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

export function renderMessageBody(message) {
  const body = message?.body ?? "";
  if (!body.trim()) {
    return "";
  }

  if (message.body_html) {
    return parseEmojisInHtml(message.body_html);
  }

  const escaped = escapeHtml(body).replace(/\n/g, "<br>");

  return parseEmojis(escaped);
}
