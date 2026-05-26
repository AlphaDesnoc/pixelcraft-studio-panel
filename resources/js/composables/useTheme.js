import { ref, watch } from "vue";
import axios from "axios";
import { usePage } from "@inertiajs/vue3";

const STORAGE_KEY = "theme_preference";
const VALID = ["light", "dark", "system"];

function resolveEffective(preference) {
  if (preference === "system") {
    return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  }
  return preference === "light" ? "light" : "dark";
}

export function applyTheme(preference) {
  const root = document.documentElement;
  root.classList.remove("light", "dark");
  root.classList.add(resolveEffective(preference));
}

export function initTheme(userPreference) {
  const stored = localStorage.getItem(STORAGE_KEY);
  const pref = VALID.includes(stored) ? stored : userPreference || "dark";
  applyTheme(pref);
  return pref;
}

let systemListener = null;

function bindSystemListener(onChange) {
  if (systemListener) return;
  systemListener = window.matchMedia("(prefers-color-scheme: dark)");
  systemListener.addEventListener("change", onChange);
}

export function useTheme() {
  const page = usePage();
  const preference = ref(
    localStorage.getItem(STORAGE_KEY) ||
      page.props.auth?.user?.theme_preference ||
      "dark",
  );

  function syncFromUser() {
    const serverPref = page.props.auth?.user?.theme_preference;
    if (serverPref && VALID.includes(serverPref) && !localStorage.getItem(STORAGE_KEY)) {
      preference.value = serverPref;
      applyTheme(serverPref);
    }
  }

  function setTheme(next) {
    if (!VALID.includes(next)) return;
    preference.value = next;
    localStorage.setItem(STORAGE_KEY, next);
    applyTheme(next);

    const user = page.props.auth?.user;
    if (user?.id) {
      axios.put(route("profile.theme"), { theme_preference: next }).catch(() => {});
    }
  }

  function cycleTheme() {
    const order = ["light", "dark", "system"];
    const idx = order.indexOf(preference.value);
    setTheme(order[(idx + 1) % order.length]);
  }

  bindSystemListener(() => {
    if (preference.value === "system") {
      applyTheme("system");
    }
  });

  watch(
    () => page.props.auth?.user?.theme_preference,
    () => syncFromUser(),
  );

  return {
    preference,
    setTheme,
    cycleTheme,
    applyTheme,
  };
}
