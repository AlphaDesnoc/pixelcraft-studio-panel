<script setup>
import { ref, watch } from "vue";
import axios from "axios";
import { Check, Copy, Link2 } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";

const props = defineProps({
  open: { type: Boolean, required: true },
  projectSlug: { type: String, required: true },
  node: { type: Object, default: null },
});

const emits = defineEmits(["update:open"]);

const expiryOptions = [
  { value: "", label: "Permanent" },
  { value: 60, label: "1 heure" },
  { value: 1440, label: "1 jour" },
  { value: 10080, label: "7 jours" },
  { value: 43200, label: "30 jours" },
];

const expiresIn = ref("");
const url = ref("");
const loading = ref(false);
const error = ref("");
const copied = ref(false);

watch(
  () => props.open,
  (open) => {
    if (open) {
      expiresIn.value = "";
      url.value = "";
      error.value = "";
      copied.value = false;
      generate();
    }
  },
);

async function generate() {
  if (!props.node) return;
  loading.value = true;
  error.value = "";
  copied.value = false;
  try {
    const { data } = await axios.post(
      route("projects.files.share", [props.projectSlug, props.node.id]),
      expiresIn.value ? { expires_in: Number(expiresIn.value) } : {},
    );
    url.value = data.url;
  } catch (e) {
    error.value = e?.response?.data?.message || "Impossible de générer le lien.";
  } finally {
    loading.value = false;
  }
}

async function copy() {
  try {
    await navigator.clipboard.writeText(url.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
  } catch {
    // ignore
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <Link2 class="h-4 w-4" />
          Lien de partage
        </DialogTitle>
      </DialogHeader>

      <p v-if="node" class="truncate text-sm text-muted-foreground" :title="node.name">
        {{ node.name }}
      </p>

      <div class="flex flex-col gap-1.5">
        <label class="text-xs font-medium text-muted-foreground">Expiration</label>
        <select
          v-model="expiresIn"
          class="h-9 rounded-md border border-border bg-background px-2 text-sm text-foreground outline-none focus:border-primary"
          @change="generate"
        >
          <option v-for="opt in expiryOptions" :key="opt.label" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <input
          :value="loading ? 'Génération…' : url"
          readonly
          class="h-9 flex-1 rounded-md border border-border bg-muted/40 px-2 text-xs text-foreground outline-none"
        />
        <Button size="sm" :disabled="!url || loading" class="gap-1.5" @click="copy">
          <component :is="copied ? Check : Copy" class="h-3.5 w-3.5" />
          {{ copied ? "Copié" : "Copier" }}
        </Button>
      </div>

      <p v-if="error" class="text-xs text-rose-400">{{ error }}</p>
      <p class="text-xs text-muted-foreground">
        Toute personne disposant de ce lien pourra consulter le fichier, sans connexion.
      </p>

      <div class="flex justify-end pt-1">
        <Button variant="outline" size="sm" @click="emits('update:open', false)">
          Fermer
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
