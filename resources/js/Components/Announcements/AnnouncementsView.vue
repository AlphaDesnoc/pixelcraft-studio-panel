<script setup>
import { computed, ref, toRef } from "vue";
import { usePage } from "@inertiajs/vue3";
import { ImagePlus, Megaphone, Send, Trash2, X } from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import ImageLightbox from "@/Components/ImageLightbox.vue";
import { useImageLightbox } from "@/composables/useImageLightbox.js";
import { useAnnouncements } from "@/composables/useAnnouncements.js";
import { confirmDialog } from "@/composables/useConfirm.js";
import { isImageAttachment } from "@/lib/attachments.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  projectId: { type: Number, required: true },
  announcements: { type: Array, default: () => [] },
  canPost: { type: Boolean, default: false },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const isAdmin = computed(() => Boolean(page.props.auth?.user?.is_admin));

const initialRef = toRef(props, "announcements");

const {
  announcements,
  sending,
  post,
  remove,
} = useAnnouncements(props.projectSlug, props.projectId, initialRef);

const {
  open: lightboxOpen,
  index: lightboxIndex,
  images: lightboxImages,
  openAttachment,
} = useImageLightbox();

const title = ref("");
const body = ref("");
const images = ref([]); // { file, url }
const fileInputRef = ref(null);
const errorMessage = ref("");
const MAX_IMAGES = 5;

const canSubmit = computed(
  () => !sending.value && (body.value.trim() !== "" || images.value.length > 0),
);

function openFilePicker() {
  fileInputRef.value?.click();
}

function onFilesSelected(event) {
  const files = Array.from(event.target.files ?? []);
  event.target.value = "";
  for (const file of files) {
    if (images.value.length >= MAX_IMAGES) break;
    if (!file.type.startsWith("image/")) continue;
    images.value.push({ file, url: URL.createObjectURL(file) });
  }
}

function removeImage(idx) {
  const [removed] = images.value.splice(idx, 1);
  if (removed?.url) URL.revokeObjectURL(removed.url);
}

function resetForm() {
  for (const image of images.value) {
    if (image.url) URL.revokeObjectURL(image.url);
  }
  title.value = "";
  body.value = "";
  images.value = [];
}

async function submit() {
  if (!canSubmit.value) return;
  errorMessage.value = "";
  try {
    await post({
      title: title.value.trim() || null,
      body: body.value.trim() || null,
      images: images.value.map((i) => i.file),
    });
    resetForm();
  } catch (error) {
    errorMessage.value =
      error?.response?.data?.message ?? "Impossible de publier l'annonce.";
  }
}

function canDelete(announcement) {
  return isAdmin.value || announcement.user?.id === currentUserId.value;
}

async function onDelete(announcement) {
  if (
    !(await confirmDialog({
      title: "Supprimer l'annonce",
      message: "Cette annonce sera définitivement supprimée pour tout le monde.",
    }))
  )
    return;
  await remove(announcement.id);
}

function imageAttachments(announcement) {
  return (announcement.attachments ?? []).filter(isImageAttachment);
}

function fileAttachments(announcement) {
  return (announcement.attachments ?? []).filter((a) => !isImageAttachment(a));
}

function formatTime(iso) {
  if (!iso) return "";
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}

function initials(name) {
  return (name ?? "?")
    .split(" ")
    .map((p) => p[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <section
      v-if="canPost"
      class="rounded-xl border border-border bg-card p-4"
    >
      <div class="mb-3 flex items-center gap-2">
        <Megaphone class="h-4 w-4 text-primary" />
        <h2 class="text-sm font-semibold text-foreground">Publier une annonce</h2>
      </div>

      <div class="flex flex-col gap-3">
        <Input
          v-model="title"
          maxlength="160"
          placeholder="Titre (optionnel)"
        />
        <Textarea
          v-model="body"
          rows="3"
          maxlength="5000"
          class="resize-none"
          placeholder="Votre message… Tous les membres du projet seront notifiés."
        />

        <div v-if="images.length" class="flex flex-wrap gap-2">
          <div
            v-for="(image, idx) in images"
            :key="idx"
            class="relative h-20 w-20 overflow-hidden rounded-lg border border-border"
          >
            <img :src="image.url" alt="" class="h-full w-full object-cover" />
            <button
              type="button"
              class="absolute right-0.5 top-0.5 rounded-full bg-black/60 p-0.5 text-white hover:bg-black/80"
              aria-label="Retirer l'image"
              @click="removeImage(idx)"
            >
              <X class="h-3 w-3" />
            </button>
          </div>
        </div>

        <p v-if="errorMessage" class="text-xs text-rose-400">{{ errorMessage }}</p>

        <div class="flex items-center justify-between gap-2">
          <input
            ref="fileInputRef"
            type="file"
            accept="image/*"
            multiple
            class="hidden"
            @change="onFilesSelected"
          />
          <Button
            type="button"
            variant="outline"
            size="sm"
            class="gap-1.5"
            :disabled="images.length >= MAX_IMAGES"
            @click="openFilePicker"
          >
            <ImagePlus class="h-4 w-4" />
            Ajouter une image
          </Button>
          <Button
            type="button"
            size="sm"
            class="gap-1.5"
            :disabled="!canSubmit"
            @click="submit"
          >
            <Send class="h-4 w-4" />
            Publier
          </Button>
        </div>
      </div>
    </section>

    <div
      v-if="announcements.length === 0"
      class="flex min-h-[220px] flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card/30 px-8 py-12 text-center"
    >
      <Megaphone class="h-6 w-6 text-muted-foreground/60" />
      <p class="mt-2 text-sm font-medium">Aucune annonce pour le moment</p>
      <p class="mt-1 text-xs text-muted-foreground">
        Les annonces publiées ici notifient tous les membres du projet.
      </p>
    </div>

    <article
      v-for="announcement in announcements"
      :key="announcement.id"
      class="rounded-xl border border-border bg-card p-4"
    >
      <header class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-2.5">
          <Avatar
            :src="announcement.user?.avatar_url ?? ''"
            :fallback="initials(announcement.user?.name)"
            size="sm"
          />
          <div>
            <p class="text-sm font-medium text-foreground">
              {{ announcement.user?.name ?? "Inconnu" }}
            </p>
            <p class="text-xs text-muted-foreground">
              {{ formatTime(announcement.created_at) }}
            </p>
          </div>
        </div>
        <button
          v-if="canDelete(announcement)"
          type="button"
          class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-rose-400"
          aria-label="Supprimer l'annonce"
          title="Supprimer"
          @click="onDelete(announcement)"
        >
          <Trash2 class="h-4 w-4" />
        </button>
      </header>

      <h3
        v-if="announcement.title"
        class="mt-3 text-base font-semibold text-foreground"
      >
        {{ announcement.title }}
      </h3>

      <p
        v-if="announcement.body"
        class="mt-2 whitespace-pre-line text-sm text-foreground/90"
      >
        {{ announcement.body }}
      </p>

      <div
        v-if="imageAttachments(announcement).length"
        class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3"
      >
        <button
          v-for="attachment in imageAttachments(announcement)"
          :key="attachment.id"
          type="button"
          class="aspect-video overflow-hidden rounded-lg border border-border"
          @click="openAttachment(attachment, imageAttachments(announcement))"
        >
          <img
            :src="attachment.url"
            :alt="attachment.original_name"
            class="h-full w-full object-cover transition-transform hover:scale-105"
          />
        </button>
      </div>

      <div
        v-if="fileAttachments(announcement).length"
        class="mt-3 flex flex-col gap-1"
      >
        <a
          v-for="attachment in fileAttachments(announcement)"
          :key="attachment.id"
          :href="attachment.url"
          target="_blank"
          rel="noopener noreferrer"
          download
          class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
        >
          {{ attachment.original_name }}
        </a>
      </div>
    </article>

    <ImageLightbox
      v-model:open="lightboxOpen"
      v-model:index="lightboxIndex"
      :images="lightboxImages"
    />
  </div>
</template>
