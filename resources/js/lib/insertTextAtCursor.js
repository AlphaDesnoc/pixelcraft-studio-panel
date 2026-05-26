import { nextTick } from "vue";

/**
 * Insère du texte à la position du curseur dans un textarea.
 */
export async function insertTextAtCursor(textarea, text, textRef) {
  if (!textarea || text == null || text === "") {
    return;
  }

  const start = textarea.selectionStart ?? textRef.value.length;
  const end = textarea.selectionEnd ?? start;
  const value = textRef.value ?? "";

  textRef.value = value.slice(0, start) + text + value.slice(end);

  const nextPos = start + text.length;
  await nextTick();
  textarea.focus();
  textarea.setSelectionRange(nextPos, nextPos);
}
