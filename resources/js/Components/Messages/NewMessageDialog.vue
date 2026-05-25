<script setup>
import { computed, ref, watch } from "vue";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";

const props = defineProps({
  open: { type: Boolean, required: true },
  contacts: { type: Array, default: () => [] },
  conversations: { type: Array, default: () => [] },
});

const emits = defineEmits(["update:open", "select"]);

const search = ref("");
const selected = ref("");

watch(
  () => props.open,
  (open) => {
    if (!open) return;
    search.value = "";
    selected.value = "";
  },
);

const filteredContacts = computed(() => {
  const q = search.value.trim().toLowerCase();
  const existingIds = new Set(
    props.conversations.map((c) => c.participant?.id).filter(Boolean),
  );

  return props.contacts.filter((contact) => {
    if (existingIds.has(contact.id)) {
      return false;
    }
    if (!q) {
      return true;
    }
    return (
      contact.name.toLowerCase().includes(q) ||
      contact.email.toLowerCase().includes(q)
    );
  });
});

const existingContacts = computed(() => {
  const q = search.value.trim().toLowerCase();
  return props.conversations.filter((conv) => {
    const p = conv.participant;
    if (!p) return false;
    if (!q) return true;
    return p.name.toLowerCase().includes(q) || p.email.toLowerCase().includes(q);
  });
});

function close() {
  emits("update:open", false);
}

function pickContact(contactId) {
  emits("select", { recipientId: contactId });
  close();
}

function pickConversation(conversationId) {
  emits("select", { conversationId });
  close();
}
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="max-w-md">
      <DialogHeader>
        <DialogTitle>Nouveau message</DialogTitle>
      </DialogHeader>

      <div class="flex flex-col gap-3">
        <input
          v-model="search"
          type="search"
          placeholder="Rechercher un contact…"
          class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none focus:ring-2 focus:ring-ring"
        />

        <div v-if="existingContacts.length" class="flex flex-col gap-1">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            Conversations existantes
          </p>
          <button
            v-for="conv in existingContacts"
            :key="conv.id"
            type="button"
            class="flex items-center gap-2 rounded-lg px-2 py-2 text-left text-sm transition-colors hover:bg-muted/60"
            @click="pickConversation(conv.id)"
          >
            <span
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-semibold text-primary"
            >
              {{ conv.participant?.name?.charAt(0) ?? "?" }}
            </span>
            <span class="min-w-0 flex-1 truncate font-medium">
              {{ conv.participant?.name }}
            </span>
          </button>
        </div>

        <div class="flex flex-col gap-1">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            Contacts
          </p>
          <div
            v-if="!filteredContacts.length && !existingContacts.length"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            Aucun contact disponible.
          </div>
          <button
            v-for="contact in filteredContacts"
            :key="contact.id"
            type="button"
            class="flex items-center gap-2 rounded-lg px-2 py-2 text-left text-sm transition-colors hover:bg-muted/60"
            @click="pickContact(contact.id)"
          >
            <span
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-semibold text-primary"
            >
              {{ contact.name.charAt(0) }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate font-medium">{{ contact.name }}</span>
              <span class="block truncate text-xs text-muted-foreground">
                {{ contact.email }}
              </span>
            </span>
          </button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
