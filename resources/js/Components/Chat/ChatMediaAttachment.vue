<script setup>
import { FileText, Film, Paperclip } from "lucide-vue-next";
import { isPdfAttachment, isVideoAttachment } from "@/lib/attachments.js";

defineProps({
  attachment: { type: Object, required: true },
});
</script>

<template>
  <div class="overflow-hidden rounded-lg border border-border/60 bg-background/40">
    <video
      v-if="isVideoAttachment(attachment)"
      :src="attachment.url"
      controls
      playsinline
      preload="metadata"
      class="max-h-72 w-full bg-black"
    />
    <iframe
      v-else-if="isPdfAttachment(attachment)"
      :src="attachment.url"
      :title="attachment.original_name ?? 'PDF'"
      class="h-72 w-full bg-muted/20"
    />
    <a
      v-else
      :href="attachment.url"
      target="_blank"
      rel="noopener noreferrer"
      download
      class="inline-flex items-center gap-1 px-2 py-1.5 text-xs text-primary hover:underline"
    >
      <Paperclip class="h-3 w-3" />
      {{ attachment.original_name }}
    </a>

    <div
      v-if="isVideoAttachment(attachment) || isPdfAttachment(attachment)"
      class="flex items-center justify-between gap-2 border-t border-border/50 px-2 py-1.5"
    >
      <span class="inline-flex min-w-0 items-center gap-1 truncate text-[11px] text-muted-foreground">
        <Film v-if="isVideoAttachment(attachment)" class="h-3 w-3 shrink-0" />
        <FileText v-else class="h-3 w-3 shrink-0" />
        <span class="truncate">{{ attachment.original_name }}</span>
      </span>
      <a
        :href="attachment.url"
        target="_blank"
        rel="noopener noreferrer"
        class="shrink-0 text-[11px] text-primary hover:underline"
      >
        Ouvrir
      </a>
    </div>
  </div>
</template>
