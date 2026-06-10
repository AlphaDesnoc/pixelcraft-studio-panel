<script setup>
import { nextTick, ref, watch } from "vue";
import { AlertTriangle } from "lucide-vue-next";
import { confirmState, resolveConfirm } from "@/composables/useConfirm.js";

const confirmBtn = ref(null);

function onConfirm() {
  resolveConfirm(true);
}
function onCancel() {
  resolveConfirm(false);
}
function onKeydown(e) {
  if (e.key === "Escape") {
    e.preventDefault();
    onCancel();
  } else if (e.key === "Enter") {
    e.preventDefault();
    onConfirm();
  }
}

watch(
  () => confirmState.open,
  async (open) => {
    if (open) {
      await nextTick();
      confirmBtn.value?.focus();
    }
  },
);
</script>

<template>
  <Teleport to="body">
    <Transition name="confirm-fade">
      <div
        v-if="confirmState.open"
        class="pointer-events-auto fixed inset-0 z-[200] flex items-center justify-center p-4"
        @keydown="onKeydown"
      >
        <!-- Fond -->
        <div
          class="absolute inset-0 bg-black/60 backdrop-blur-sm"
          @click="onCancel"
        />

        <!-- Boîte -->
        <div
          role="alertdialog"
          aria-modal="true"
          class="confirm-box relative w-full max-w-sm overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
        >
          <div class="flex gap-3 p-5">
            <span
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
              :class="confirmState.variant === 'danger'
                ? 'bg-rose-500/15 text-rose-500'
                : 'bg-primary/15 text-primary'"
            >
              <AlertTriangle class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
              <h2 class="text-base font-semibold text-foreground">
                {{ confirmState.title }}
              </h2>
              <p class="mt-1 text-sm text-muted-foreground">
                {{ confirmState.message }}
              </p>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-border bg-muted/30 px-5 py-3">
            <button
              type="button"
              class="inline-flex h-9 items-center rounded-md border border-border bg-background px-4 text-sm font-medium text-foreground transition-colors hover:bg-muted"
              @click="onCancel"
            >
              {{ confirmState.cancelLabel }}
            </button>
            <button
              ref="confirmBtn"
              type="button"
              class="inline-flex h-9 items-center rounded-md px-4 text-sm font-semibold text-white transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-card"
              :class="confirmState.variant === 'danger'
                ? 'bg-rose-500 hover:bg-rose-600 focus:ring-rose-500'
                : 'bg-primary hover:bg-primary/90 focus:ring-primary'"
              @click="onConfirm"
            >
              {{ confirmState.confirmLabel }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.confirm-fade-enter-active,
.confirm-fade-leave-active {
  transition: opacity 0.15s ease;
}
.confirm-fade-enter-from,
.confirm-fade-leave-to {
  opacity: 0;
}
.confirm-fade-enter-active .confirm-box,
.confirm-fade-leave-active .confirm-box {
  transition: transform 0.15s ease;
}
.confirm-fade-enter-from .confirm-box,
.confirm-fade-leave-to .confirm-box {
  transform: scale(0.96);
}
</style>
