import { computed, ref, watch } from "vue";

const STORAGE_KEY = "panel:focus-mode";
const focusMode = ref(false);

if (typeof window !== "undefined") {
  focusMode.value = localStorage.getItem(STORAGE_KEY) === "1";
  if (focusMode.value) {
    document.documentElement.classList.add("panel-focus-mode");
  }
}

watch(focusMode, (value) => {
  if (typeof window === "undefined") return;
  localStorage.setItem(STORAGE_KEY, value ? "1" : "0");
  document.documentElement.classList.toggle("panel-focus-mode", value);
});

export function useFocusMode() {
  const isFocusMode = computed(() => focusMode.value);

  function toggleFocusMode() {
    focusMode.value = !focusMode.value;
  }

  function setFocusMode(value) {
    focusMode.value = Boolean(value);
  }

  return {
    isFocusMode,
    toggleFocusMode,
    setFocusMode,
  };
}
