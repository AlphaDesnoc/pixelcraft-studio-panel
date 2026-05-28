<script setup>
import { computed, ref, watch, onUnmounted } from "vue";
import axios from "axios";
import {
  Loader2,
  MessageSquare,
  Search,
  User,
  Layers,
  ClipboardList,
  Bug,
  StickyNote,
  File,
  Table2,
  Mail,
} from "lucide-vue-next";

const props = defineProps({
  open: { type: Boolean, default: false },
});

const emits = defineEmits(["update:open"]);

const q = ref("");
const loading = ref(false);
const results = ref([]);
const error = ref("");

function close() {
  emits("update:open", false);
}

async function fetchResults() {
  const query = q.value.trim();
  if (query.length < 2) {
    results.value = [];
    return;
  }

  loading.value = true;
  error.value = "";
  try {
    const { data } = await axios.get(route("search.global"), {
      params: { q: query },
    });
    results.value = data.results ?? [];
  } catch {
    error.value = "La recherche a échoué.";
    results.value = [];
  } finally {
    loading.value = false;
  }
}

let timer = null;
watch(
  [q, () => props.open],
  () => {
    if (!props.open) {
      return;
    }
    clearTimeout(timer);
    timer = setTimeout(() => {
      fetchResults();
    }, 280);
  },
);

watch(
  () => props.open,
  (v) => {
    if (!v) {
      q.value = "";
      results.value = [];
      error.value = "";
    }
  },
);

const typeIcons = {
  project: Layers,
  task: ClipboardList,
  bug: Bug,
  chat: MessageSquare,
  member: User,
  note: StickyNote,
  file: File,
  sheet: Table2,
  dm: Mail,
};

const typeLabel = (t) =>
  ({
    project: "Projet",
    task: "Tâche",
    bug: "Bug",
    chat: "Chat",
    member: "Membre",
    note: "Note",
    file: "Fichier",
    sheet: "Tableur",
    dm: "Message privé",
  })[t] ?? t;

onUnmounted(() => {
  clearTimeout(timer);
});

const grouped = computed(() => {
  const map = {};
  for (const row of results.value) {
    const t = row.type ?? "misc";
    if (!map[t]) {
      map[t] = [];
    }
    map[t].push(row);
  }
  return map;
});

const sortedTypes = computed(() => Object.keys(grouped.value).sort());

function navigate(url) {
  if (url) {
    window.location.href = url;
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[100] flex justify-center px-4 py-16 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="global-search-heading"
      >
        <button
          type="button"
          class="absolute inset-0 bg-background/75"
          aria-label="Fermer la recherche"
          @click="close"
        />
        <div
          tabindex="-1"
          class="relative z-10 flex max-h-[min(520px,calc(100vh-8rem))] w-full max-w-xl flex-col overflow-hidden rounded-xl border border-border bg-card shadow-2xl outline-none"
          @keyup.escape.prevent="close"
        >
          <header class="flex items-center gap-2 border-b border-border px-3 py-2">
            <Search class="h-4 w-4 shrink-0 text-muted-foreground" />
            <input
              v-model="q"
              type="search"
              class="flex-1 border-0 bg-transparent py-2 text-sm outline-none focus:ring-0"
              placeholder="Rechercher projets, tâches, notes, fichiers…"
              autofocus
            />
            <button
              type="button"
              class="rounded-md px-2 py-1 text-[11px] text-muted-foreground hover:bg-muted"
              @click="close"
            >
              Échap
            </button>
          </header>

          <p id="global-search-heading" class="sr-only">
            Recherche globale dans le panel
          </p>

          <div class="flex-1 overflow-y-auto px-3 py-2">
            <div
              v-if="loading && q.trim().length >= 2"
              class="flex items-center justify-center gap-2 py-8 text-xs text-muted-foreground"
            >
              <Loader2 class="h-4 w-4 animate-spin" />
              Recherche…
            </div>

            <p v-else-if="error" class="py-4 text-center text-sm text-rose-400">
              {{ error }}
            </p>

            <p
              v-else-if="q.trim().length > 0 && q.trim().length < 2"
              class="py-8 text-center text-xs text-muted-foreground"
            >
              Saisissez au moins deux caractères.
            </p>

            <p
              v-else-if="!loading && q.trim().length >= 2 && !results.length"
              class="py-8 text-center text-xs text-muted-foreground"
            >
              Aucun résultat.
            </p>

            <div v-else-if="sortedTypes.length" class="space-y-4 pb-4">
              <section v-for="t in sortedTypes" :key="t">
                <h3 class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                  {{ typeLabel(t) }}
                </h3>
                <ul class="space-y-1">
                  <li v-for="(row, idx) in grouped[t]" :key="`${t}-${idx}-${row.url}`">
                    <button
                      type="button"
                      class="flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left text-xs transition-colors hover:bg-muted/60"
                      @click="navigate(row.url)"
                    >
                      <component :is="typeIcons[t] ?? Search" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" />
                      <span class="min-w-0 flex-1">
                        <span class="block font-medium text-foreground">{{ row.label }}</span>
                        <span
                          v-if="row.meta"
                          class="mt-0.5 block truncate text-[11px] text-muted-foreground"
                        >
                          {{ row.meta }}
                        </span>
                      </span>
                    </button>
                  </li>
                </ul>
              </section>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
