import { ref, toValue, watch } from "vue";

export function useMessageDraft(storageKey) {
  const draft = ref("");

  function resolveKey() {
    return toValue(storageKey);
  }

  function load() {
    const key = resolveKey();
    if (!key || typeof localStorage === "undefined") {
      draft.value = "";
      return;
    }

    draft.value = localStorage.getItem(key) ?? "";
  }

  function persist(value) {
    const key = resolveKey();
    if (!key || typeof localStorage === "undefined") {
      return;
    }

    const trimmed = value?.trim() ?? "";
    if (!trimmed) {
      localStorage.removeItem(key);
      return;
    }

    localStorage.setItem(key, value);
  }

  function clear() {
    const key = resolveKey();
    if (key && typeof localStorage !== "undefined") {
      localStorage.removeItem(key);
    }
    draft.value = "";
  }

  watch(
    draft,
    (value) => {
      persist(value);
    },
    { flush: "post" },
  );

  watch(
    () => resolveKey(),
    () => load(),
    { immediate: true },
  );

  return { draft, clear, load };
}
