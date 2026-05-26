import { nextTick } from "vue";

export function resolveTextareaElement(textareaRef) {
  const target = textareaRef?.value ?? textareaRef;
  if (!target) {
    return null;
  }
  if (target instanceof HTMLTextAreaElement) {
    return target;
  }
  return target.$el instanceof HTMLTextAreaElement ? target.$el : target.$el ?? target;
}

/**
 * Insère du texte à la position du curseur dans un textarea.
 */
export async function insertTextAtCursor(textareaRef, text, textRef) {
  if (text == null || text === "") {
    return;
  }

  const textarea = resolveTextareaElement(textareaRef);
  if (!textarea) {
    textRef.value = `${textRef.value ?? ""}${text}`;
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
