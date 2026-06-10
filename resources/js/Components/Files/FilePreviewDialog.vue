<script setup>
import { computed, onUnmounted, ref, watch } from "vue";
import {
  ChevronLeft,
  ChevronRight,
  Download,
  ExternalLink,
  FileWarning,
  Loader2,
  Minus,
  Plus,
  RotateCcw,
  X,
} from "lucide-vue-next";
import hljs from "highlight.js/lib/common";
import "highlight.js/styles/github-dark.css";
import { marked } from "marked";
import DOMPurify from "dompurify";
import { fileExtension, fileKind } from "./fileKind.js";

const props = defineProps({
  open: { type: Boolean, default: false },
  files: { type: Array, default: () => [] },
  index: { type: Number, default: 0 },
  projectSlug: { type: String, required: true },
});

const emits = defineEmits(["update:open", "update:index"]);

const node = computed(() => props.files[props.index] ?? null);
const kind = computed(() => fileKind(node.value));

const hasPrevious = computed(() => props.index > 0);
const hasNext = computed(() => props.index < props.files.length - 1);
const counterLabel = computed(() =>
  props.files.length > 1 ? `${props.index + 1} / ${props.files.length}` : "",
);

const previewUrl = computed(() =>
  node.value
    ? route("projects.files.preview", [props.projectSlug, node.value.id])
    : null,
);
const downloadUrl = computed(() =>
  node.value
    ? route("projects.files.download", [props.projectSlug, node.value.id])
    : null,
);

const loading = ref(false);
const errored = ref(false);

const isMarkdown = computed(() =>
  ["md", "markdown"].includes(fileExtension(node.value?.name)),
);

// --- Texte / code / markdown ---
const textHtml = ref("");
const markdownHtml = ref("");

async function loadText() {
  if (!previewUrl.value) return;
  loading.value = true;
  errored.value = false;
  textHtml.value = "";
  markdownHtml.value = "";
  try {
    const res = await fetch(previewUrl.value, { headers: { Accept: "text/plain" } });
    if (!res.ok) throw new Error("HTTP " + res.status);
    let content = await res.text();
    if (content.length > 500_000) {
      content = content.slice(0, 500_000) + "\n\n… (fichier tronqué)";
    }
    if (isMarkdown.value) {
      markdownHtml.value = DOMPurify.sanitize(marked.parse(content));
    } else {
      const ext = fileExtension(node.value?.name);
      const lang = hljs.getLanguage(ext) ? ext : null;
      textHtml.value = lang
        ? hljs.highlight(content, { language: lang }).value
        : hljs.highlightAuto(content).value;
    }
  } catch (e) {
    errored.value = true;
  } finally {
    loading.value = false;
  }
}

// --- Zoom image ---
const scale = ref(1);
const offset = ref({ x: 0, y: 0 });
const panning = ref(false);
const panStart = ref({ x: 0, y: 0 });

function resetZoom() {
  scale.value = 1;
  offset.value = { x: 0, y: 0 };
}

function zoomBy(delta) {
  const next = Math.min(5, Math.max(1, scale.value + delta));
  scale.value = next;
  if (next === 1) offset.value = { x: 0, y: 0 };
}

function onWheel(e) {
  if (kind.value !== "image") return;
  e.preventDefault();
  zoomBy(e.deltaY < 0 ? 0.2 : -0.2);
}

function onPanStart(e) {
  if (scale.value <= 1) return;
  panning.value = true;
  panStart.value = { x: e.clientX - offset.value.x, y: e.clientY - offset.value.y };
}

function onPanMove(e) {
  if (!panning.value) return;
  offset.value = { x: e.clientX - panStart.value.x, y: e.clientY - panStart.value.y };
}

function onPanEnd() {
  panning.value = false;
}

const imageStyle = computed(() => ({
  transform: `translate(${offset.value.x}px, ${offset.value.y}px) scale(${scale.value})`,
  cursor: scale.value > 1 ? (panning.value ? "grabbing" : "grab") : "default",
}));

// --- Cycle de vie au changement de fichier ---
function onMediaLoad() {
  loading.value = false;
}
function onMediaError() {
  loading.value = false;
  errored.value = true;
}

watch(
  [() => props.open, node],
  ([isOpen]) => {
    if (!isOpen || !node.value) return;
    resetZoom();
    errored.value = false;
    if (kind.value === "text") {
      loadText();
    } else if (kind.value === "image" || kind.value === "pdf") {
      loading.value = true;
    } else {
      loading.value = false;
    }
  },
  { immediate: true },
);

function close() {
  emits("update:open", false);
}
function goPrevious() {
  if (hasPrevious.value) emits("update:index", props.index - 1);
}
function goNext() {
  if (hasNext.value) emits("update:index", props.index + 1);
}

function onBackdropClick(event) {
  if (event.target === event.currentTarget) close();
}

function onKeydown(event) {
  if (!props.open) return;
  if (event.key === "Escape") {
    event.preventDefault();
    close();
  } else if (event.key === "ArrowLeft") {
    event.preventDefault();
    goPrevious();
  } else if (event.key === "ArrowRight") {
    event.preventDefault();
    goNext();
  }
}

watch(
  () => props.open,
  (isOpen) => {
    document.body.style.overflow = isOpen ? "hidden" : "";
    if (isOpen) {
      window.addEventListener("keydown", onKeydown);
    } else {
      window.removeEventListener("keydown", onKeydown);
    }
  },
  { immediate: true },
);

onUnmounted(() => {
  document.body.style.overflow = "";
  window.removeEventListener("keydown", onKeydown);
});
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
        v-if="open && node"
        class="fixed inset-0 z-[100] flex flex-col bg-black/90 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        :aria-label="node.name"
        @click="onBackdropClick"
      >
        <header
          class="flex shrink-0 items-center gap-3 border-b border-white/10 px-4 py-3 text-white"
          @click.stop
        >
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ node.name }}</p>
            <p v-if="counterLabel" class="text-xs text-white/60">{{ counterLabel }}</p>
          </div>

          <template v-if="kind === 'image'">
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-md text-white/80 transition-colors hover:bg-white/10 hover:text-white disabled:opacity-30"
              title="Dézoomer"
              :disabled="scale <= 1"
              @click.stop="zoomBy(-0.2)"
            >
              <Minus class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-md text-white/80 transition-colors hover:bg-white/10 hover:text-white disabled:opacity-30"
              title="Zoomer"
              :disabled="scale >= 5"
              @click.stop="zoomBy(0.2)"
            >
              <Plus class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-md text-white/80 transition-colors hover:bg-white/10 hover:text-white disabled:opacity-30"
              title="Réinitialiser le zoom"
              :disabled="scale === 1 && offset.x === 0 && offset.y === 0"
              @click.stop="resetZoom"
            >
              <RotateCcw class="h-4 w-4" />
            </button>
            <span class="mx-1 h-5 w-px bg-white/15" />
          </template>

          <a
            :href="downloadUrl"
            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-white/80 transition-colors hover:bg-white/10 hover:text-white"
            title="Télécharger"
            @click.stop
          >
            <Download class="h-4 w-4" />
          </a>
          <a
            :href="previewUrl"
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

        <div class="relative flex min-h-0 flex-1 items-center justify-center p-2 sm:p-4">
          <button
            v-if="hasPrevious"
            type="button"
            class="absolute left-3 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70"
            title="Précédent"
            @click.stop="goPrevious"
          >
            <ChevronLeft class="h-6 w-6" />
          </button>

          <!-- Spinner -->
          <div
            v-if="loading"
            class="pointer-events-none absolute inset-0 flex items-center justify-center"
          >
            <Loader2 class="h-8 w-8 animate-spin text-white/70" />
          </div>

          <!-- Erreur / non prévisualisable -->
          <div
            v-if="errored || kind === null"
            class="flex flex-col items-center gap-4 text-center text-white/80"
            @click.stop
          >
            <FileWarning class="h-12 w-12 text-white/50" />
            <p class="text-sm">
              {{ kind === null ? "Aperçu indisponible pour ce type de fichier." : "Impossible d'afficher l'aperçu." }}
            </p>
            <a
              :href="downloadUrl"
              class="inline-flex items-center gap-2 rounded-md bg-white/10 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-white/20"
              @click.stop
            >
              <Download class="h-4 w-4" />
              Télécharger
            </a>
          </div>

          <!-- Image -->
          <img
            v-else-if="kind === 'image'"
            :key="previewUrl"
            :src="previewUrl"
            :alt="node.name"
            class="max-h-full max-w-full select-none object-contain transition-transform duration-75"
            :style="imageStyle"
            draggable="false"
            @load="onMediaLoad"
            @error="onMediaError"
            @wheel="onWheel"
            @mousedown.stop="onPanStart"
            @mousemove="onPanMove"
            @mouseup="onPanEnd"
            @mouseleave="onPanEnd"
            @click.stop
          />

          <!-- Vidéo -->
          <video
            v-else-if="kind === 'video'"
            :key="previewUrl"
            :src="previewUrl"
            controls
            autoplay
            class="max-h-full max-w-full rounded-lg bg-black"
            @loadeddata="onMediaLoad"
            @error="onMediaError"
            @click.stop
          />

          <!-- Audio -->
          <div
            v-else-if="kind === 'audio'"
            class="w-full max-w-md rounded-xl bg-white/5 p-6"
            @click.stop
          >
            <audio
              :key="previewUrl"
              :src="previewUrl"
              controls
              autoplay
              class="w-full"
              @loadeddata="onMediaLoad"
              @error="onMediaError"
            />
          </div>

          <!-- PDF -->
          <iframe
            v-else-if="kind === 'pdf'"
            :key="previewUrl"
            :src="previewUrl"
            :title="node.name"
            class="h-full w-full rounded-lg border-0 bg-white"
            @load="onMediaLoad"
            @click.stop
          />

          <!-- Markdown -->
          <div
            v-else-if="kind === 'text' && isMarkdown"
            v-show="!loading"
            class="mx-auto h-full w-full max-w-3xl overflow-auto rounded-lg bg-card p-6"
            @click.stop
          >
            <div class="markdown-body" v-html="markdownHtml" />
          </div>

          <!-- Texte / code -->
          <div
            v-else-if="kind === 'text'"
            v-show="!loading"
            class="h-full w-full overflow-auto rounded-lg bg-[#0d1117] p-4"
            @click.stop
          >
            <pre class="text-xs leading-relaxed"><code class="hljs" v-html="textHtml" /></pre>
          </div>

          <button
            v-if="hasNext"
            type="button"
            class="absolute right-3 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70"
            title="Suivant"
            @click.stop="goNext"
          >
            <ChevronRight class="h-6 w-6" />
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style>
.markdown-body {
  color: hsl(var(--foreground));
  font-size: 0.9rem;
  line-height: 1.7;
}
.markdown-body h1,
.markdown-body h2,
.markdown-body h3 {
  font-weight: 600;
  margin: 1.2em 0 0.6em;
  line-height: 1.3;
}
.markdown-body h1 {
  font-size: 1.6em;
  border-bottom: 1px solid hsl(var(--border));
  padding-bottom: 0.3em;
}
.markdown-body h2 {
  font-size: 1.3em;
  border-bottom: 1px solid hsl(var(--border));
  padding-bottom: 0.2em;
}
.markdown-body h3 {
  font-size: 1.1em;
}
.markdown-body p {
  margin: 0.6em 0;
}
.markdown-body ul,
.markdown-body ol {
  margin: 0.6em 0;
  padding-left: 1.5em;
  list-style: revert;
}
.markdown-body a {
  color: hsl(var(--primary));
  text-decoration: underline;
}
.markdown-body code {
  background: hsl(var(--muted));
  padding: 0.15em 0.35em;
  border-radius: 0.25rem;
  font-size: 0.85em;
}
.markdown-body pre {
  background: #0d1117;
  color: #e6edf3;
  padding: 1em;
  border-radius: 0.5rem;
  overflow-x: auto;
  margin: 0.8em 0;
}
.markdown-body pre code {
  background: transparent;
  padding: 0;
}
.markdown-body blockquote {
  border-left: 3px solid hsl(var(--border));
  padding-left: 1em;
  color: hsl(var(--muted-foreground));
  margin: 0.8em 0;
}
.markdown-body table {
  border-collapse: collapse;
  margin: 0.8em 0;
}
.markdown-body th,
.markdown-body td {
  border: 1px solid hsl(var(--border));
  padding: 0.4em 0.7em;
}
.markdown-body img {
  max-width: 100%;
}
.markdown-body hr {
  border: none;
  border-top: 1px solid hsl(var(--border));
  margin: 1.2em 0;
}
</style>
