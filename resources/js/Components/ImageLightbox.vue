<script setup>
import { computed, onUnmounted, watch } from "vue";
import {
  ChevronLeft,
  ChevronRight,
  Download,
  ExternalLink,
  X,
} from "lucide-vue-next";

const props = defineProps({
  open: { type: Boolean, default: false },
  images: { type: Array, default: () => [] },
  index: { type: Number, default: 0 },
});

const emits = defineEmits(["update:open", "update:index"]);

const current = computed(() => props.images[props.index] ?? null);
const hasMultiple = computed(() => props.images.length > 1);
const hasPrevious = computed(() => props.index > 0);
const hasNext = computed(() => props.index < props.images.length - 1);
const counterLabel = computed(() =>
  hasMultiple.value ? `${props.index + 1} / ${props.images.length}` : "",
);

function close() {
  emits("update:open", false);
}

function goPrevious() {
  if (hasPrevious.value) {
    emits("update:index", props.index - 1);
  }
}

function goNext() {
  if (hasNext.value) {
    emits("update:index", props.index + 1);
  }
}

function onBackdropClick(event) {
  if (event.target === event.currentTarget) {
    close();
  }
}

function onKeydown(event) {
  if (!props.open) {
    return;
  }

  if (event.key === "Escape") {
    event.preventDefault();
    close();
    return;
  }

  if (event.key === "ArrowLeft") {
    event.preventDefault();
    goPrevious();
    return;
  }

  if (event.key === "ArrowRight") {
    event.preventDefault();
    goNext();
  }
}

watch(
  () => props.open,
  (isOpen) => {
    document.body.style.overflow = isOpen ? "hidden" : "";
  },
);

onUnmounted(() => {
  document.body.style.overflow = "";
  window.removeEventListener("keydown", onKeydown);
});

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      window.addEventListener("keydown", onKeydown);
      return;
    }

    window.removeEventListener("keydown", onKeydown);
  },
  { immediate: true },
);
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open && current"
        class="fixed inset-0 z-[100] flex flex-col bg-black/90 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        :aria-label="current.name"
        @click="onBackdropClick"
      >
        <header
          class="flex shrink-0 items-center gap-3 border-b border-white/10 px-4 py-3 text-white"
          @click.stop
        >
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ current.name }}</p>
            <p v-if="counterLabel" class="text-xs text-white/60">
              {{ counterLabel }}
            </p>
          </div>

          <a
            :href="current.url"
            download
            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-white/80 transition-colors hover:bg-white/10 hover:text-white"
            title="Télécharger"
            @click.stop
          >
            <Download class="h-4 w-4" />
          </a>
          <a
            :href="current.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-white/80 transition-colors hover:bg-white/10 hover:text-white"
            title="Ouvrir dans un nouvel onglet"
            @click.stop
          >
            <ExternalLink class="h-4 w-4" />
          </a>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-white/80 transition-colors hover:bg-white/10 hover:text-white"
            title="Fermer"
            @click.stop="close"
          >
            <X class="h-5 w-5" />
          </button>
        </header>

        <div class="relative flex min-h-0 flex-1 items-center justify-center p-4">
          <button
            v-if="hasPrevious"
            type="button"
            class="absolute left-3 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70"
            title="Image précédente"
            @click.stop="goPrevious"
          >
            <ChevronLeft class="h-6 w-6" />
          </button>

          <img
            :key="current.url"
            :src="current.url"
            :alt="current.name"
            class="max-h-full max-w-full select-none object-contain"
            draggable="false"
            @click.stop
          />

          <button
            v-if="hasNext"
            type="button"
            class="absolute right-3 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70"
            title="Image suivante"
            @click.stop="goNext"
          >
            <ChevronRight class="h-6 w-6" />
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
