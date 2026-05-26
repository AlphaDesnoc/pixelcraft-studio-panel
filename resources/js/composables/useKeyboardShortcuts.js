import { onMounted, onUnmounted } from "vue";

const ESCAPE_EVENT = "app:escape";

export function dispatchEscape() {
  window.dispatchEvent(new CustomEvent(ESCAPE_EVENT));
}

export function onEscape(handler) {
  window.addEventListener(ESCAPE_EVENT, handler);
  return () => window.removeEventListener(ESCAPE_EVENT, handler);
}

export function useKeyboardShortcuts(options = {}) {
  const { onCtrlEnter, onEscape: escapeHandler } = options;

  function handleKeydown(event) {
    if (event.key === "Escape") {
      if (escapeHandler) {
        escapeHandler(event);
      } else {
        dispatchEscape();
      }
    }

    if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
      onCtrlEnter?.(event);
    }
  }

  onMounted(() => {
    window.addEventListener("keydown", handleKeydown);
  });

  onUnmounted(() => {
    window.removeEventListener("keydown", handleKeydown);
  });
}
