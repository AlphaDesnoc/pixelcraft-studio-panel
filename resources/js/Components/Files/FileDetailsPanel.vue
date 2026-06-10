<script setup>
import { computed } from "vue";
import {
  Download,
  Eye,
  File,
  FileArchive,
  FileAudio,
  FileImage,
  FileText,
  FileVideo,
  Folder,
  Link2,
  X,
} from "lucide-vue-next";
import { isViewable } from "./fileKind.js";

const props = defineProps({
  node: { type: Object, default: null },
  projectSlug: { type: String, required: true },
});

const emits = defineEmits(["close", "preview", "share"]);

const isFolder = computed(() => props.node?.type === "folder");
const isImage = computed(() => (props.node?.mime ?? "").startsWith("image/"));

const Icon = computed(() => {
  const mime = props.node?.mime ?? "";
  if (isFolder.value) return Folder;
  if (mime.startsWith("image/")) return FileImage;
  if (mime.startsWith("video/")) return FileVideo;
  if (mime.startsWith("audio/")) return FileAudio;
  if (mime.includes("zip") || mime.includes("rar") || mime.includes("7z"))
    return FileArchive;
  if (mime.startsWith("text/") || mime.includes("pdf") || mime.includes("document"))
    return FileText;
  return File;
});

const sizeLabel = computed(() => {
  const bytes = props.node?.size;
  if (!bytes) return isFolder.value ? "—" : "0 B";
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
  return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`;
});

function fmtDate(iso) {
  if (!iso) return "—";
  try {
    return new Date(iso).toLocaleString("fr-FR", {
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch {
    return "—";
  }
}

const downloadUrl = computed(() =>
  props.node ? route("projects.files.download", [props.projectSlug, props.node.id]) : "#",
);
</script>

<template>
  <aside
    v-if="node"
    class="flex w-64 shrink-0 flex-col gap-3 rounded-xl border border-border bg-card/40 p-4"
  >
    <div class="flex items-start justify-between gap-2">
      <h3 class="text-sm font-semibold text-foreground">Détails</h3>
      <button
        type="button"
        class="text-muted-foreground hover:text-foreground"
        title="Fermer"
        @click="emits('close')"
      >
        <X class="h-4 w-4" />
      </button>
    </div>

    <div class="flex items-center justify-center rounded-lg bg-muted/40 p-4">
      <img
        v-if="isImage && node.url"
        :src="node.url"
        :alt="node.name"
        class="max-h-32 max-w-full rounded object-contain"
      />
      <component v-else :is="Icon" :size="64" :stroke-width="1.5" class="text-muted-foreground" />
    </div>

    <p class="break-all text-sm font-medium text-foreground" :title="node.name">
      {{ node.name }}
    </p>

    <dl class="flex flex-col gap-2 text-xs">
      <div class="flex justify-between gap-2">
        <dt class="text-muted-foreground">Type</dt>
        <dd class="text-right text-foreground">
          {{ isFolder ? "Dossier" : node.mime || "Fichier" }}
        </dd>
      </div>
      <div class="flex justify-between gap-2">
        <dt class="text-muted-foreground">Taille</dt>
        <dd class="text-foreground">{{ sizeLabel }}</dd>
      </div>
      <div class="flex justify-between gap-2">
        <dt class="text-muted-foreground">Ajouté le</dt>
        <dd class="text-right text-foreground">{{ fmtDate(node.created_at) }}</dd>
      </div>
      <div class="flex justify-between gap-2">
        <dt class="text-muted-foreground">Modifié le</dt>
        <dd class="text-right text-foreground">{{ fmtDate(node.updated_at) }}</dd>
      </div>
      <div v-if="node.uploader" class="flex justify-between gap-2">
        <dt class="text-muted-foreground">Par</dt>
        <dd class="text-right text-foreground">{{ node.uploader.name }}</dd>
      </div>
    </dl>

    <div v-if="!isFolder" class="mt-auto flex flex-col gap-1.5 pt-2">
      <button
        v-if="isViewable(node)"
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
        @click="emits('preview', node)"
      >
        <Eye class="h-3.5 w-3.5" />
        Aperçu
      </button>
      <a
        :href="downloadUrl"
        class="inline-flex items-center justify-center gap-2 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
      >
        <Download class="h-3.5 w-3.5" />
        Télécharger
      </a>
      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-md border border-border px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
        @click="emits('share', node)"
      >
        <Link2 class="h-3.5 w-3.5" />
        Lien de partage
      </button>
    </div>
  </aside>
</template>
