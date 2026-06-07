import { reactive } from "vue";

// Dialogue de confirmation global (remplace window.confirm) : un seul
// <ConfirmDialog /> est monté dans app.js ; n'importe quel composant appelle
// `await confirmDialog(...)` qui résout à true (confirmé) ou false (annulé).

export const confirmState = reactive({
  open: false,
  title: "",
  message: "",
  confirmLabel: "Supprimer",
  cancelLabel: "Annuler",
  variant: "danger", // "danger" | "default"
  _resolve: null,
});

/**
 * @param {string|object} options  Message, ou { title, message, confirmLabel, cancelLabel, variant }
 * @returns {Promise<boolean>}
 */
export function confirmDialog(options = {}) {
  const opts = typeof options === "string" ? { message: options } : options;

  confirmState.title = opts.title ?? "Confirmer la suppression";
  confirmState.message =
    opts.message ?? "Cette action est définitive et irréversible.";
  confirmState.confirmLabel = opts.confirmLabel ?? "Supprimer";
  confirmState.cancelLabel = opts.cancelLabel ?? "Annuler";
  confirmState.variant = opts.variant ?? "danger";
  confirmState.open = true;

  return new Promise((resolve) => {
    confirmState._resolve = resolve;
  });
}

export function resolveConfirm(result) {
  if (!confirmState.open) return;
  confirmState.open = false;
  const resolve = confirmState._resolve;
  confirmState._resolve = null;
  if (resolve) resolve(result);
}
