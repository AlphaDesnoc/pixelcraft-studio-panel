import { ref } from "vue";

export const floatingMessengerOpen = ref(
  typeof localStorage !== "undefined" &&
    localStorage.getItem("floating-messenger-open") === "1",
);

export function setFloatingMessengerOpen(value) {
  floatingMessengerOpen.value = Boolean(value);
  if (typeof localStorage !== "undefined") {
    localStorage.setItem("floating-messenger-open", value ? "1" : "0");
  }
}

export function toggleFloatingMessenger() {
  setFloatingMessengerOpen(!floatingMessengerOpen.value);
}

export function openFloatingMessenger() {
  setFloatingMessengerOpen(true);
}

export function closeFloatingMessenger() {
  setFloatingMessengerOpen(false);
}
