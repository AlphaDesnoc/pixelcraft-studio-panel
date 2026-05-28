<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import { Bell, CheckCheck } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { unreadNotifications } from "@/composables/useSiteRealtime.js";
import {
  fetchNotifications,
  loadingNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  notifications,
} from "@/composables/useNotifications.js";

const open = ref(false);
const rootRef = ref(null);

const badgeLabel = computed(() => {
  const count = unreadNotifications.value;
  if (count <= 0) return null;
  return count > 9 ? "9+" : String(count);
});

function formatTime(iso) {
  if (!iso) return "";
  const date = new Date(iso);
  const diffMs = date.getTime() - Date.now();
  const rtf = new Intl.RelativeTimeFormat("fr", { numeric: "auto" });
  const minutes = Math.round(diffMs / 60000);
  if (Math.abs(minutes) < 60) return rtf.format(minutes, "minute");
  const hours = Math.round(minutes / 60);
  if (Math.abs(hours) < 24) return rtf.format(hours, "hour");
  const days = Math.round(hours / 24);
  return rtf.format(days, "day");
}

async function toggleOpen() {
  open.value = !open.value;
  if (open.value && notifications.value.length === 0) {
    await fetchNotifications();
  }
}

async function handleOpen() {
  if (notifications.value.length === 0) {
    await fetchNotifications();
  }
}

async function handleClick(notification) {
  if (!notification.read_at) {
    await markNotificationRead(notification.id);
  }
  open.value = false;
  if (notification.url) {
    router.visit(notification.url);
  }
}

async function handleMarkAllRead() {
  await markAllNotificationsRead();
}

function onDocumentClick(event) {
  if (!rootRef.value?.contains(event.target)) {
    open.value = false;
  }
}

onMounted(() => {
  document.addEventListener("click", onDocumentClick);
  handleOpen();
});

onUnmounted(() => {
  document.removeEventListener("click", onDocumentClick);
});
</script>

<template>
  <div ref="rootRef" class="relative">
    <button
      type="button"
      class="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
      aria-label="Notifications"
      @click.stop="toggleOpen"
    >
      <Bell class="h-5 w-5" />
      <span
        v-if="badgeLabel"
        class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-primary-foreground"
      >
        {{ badgeLabel }}
      </span>
    </button>

    <div
      v-if="open"
      class="absolute right-0 top-full z-50 mt-2 w-[min(100vw-2rem,360px)] overflow-hidden rounded-xl border border-border bg-card shadow-lg"
      @click.stop
    >
      <div class="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
        <p class="text-sm font-semibold text-foreground">Notifications</p>
        <div class="flex items-center gap-2">
          <Link
            :href="route('notifications.index')"
            class="text-xs font-medium text-primary hover:underline"
            @click="open = false"
          >
            Voir tout
          </Link>
          <Button
            v-if="unreadNotifications > 0"
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 gap-1 px-2 text-xs"
            @click="handleMarkAllRead"
          >
            <CheckCheck class="h-3.5 w-3.5" />
            Tout lire
          </Button>
        </div>
      </div>

      <div class="max-h-[min(60vh,420px)] overflow-y-auto">
        <div
          v-if="loadingNotifications && notifications.length === 0"
          class="px-4 py-8 text-center text-sm text-muted-foreground"
        >
          Chargement…
        </div>
        <button
          v-for="notification in notifications"
          :key="notification.id"
          type="button"
          class="flex w-full flex-col gap-0.5 border-b border-border/40 px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-muted/40"
          :class="{ 'bg-primary/5': !notification.read_at }"
          @click="handleClick(notification)"
        >
          <div class="flex items-start justify-between gap-2">
            <p class="text-sm font-medium text-foreground">
              {{ notification.title }}
            </p>
            <span
              v-if="!notification.read_at"
              class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary"
            />
          </div>
          <p v-if="notification.body" class="text-xs text-muted-foreground">
            {{ notification.body }}
          </p>
          <p class="text-[11px] text-muted-foreground/80">
            {{ formatTime(notification.created_at) }}
          </p>
        </button>
        <p
          v-if="!loadingNotifications && notifications.length === 0"
          class="px-4 py-8 text-center text-sm text-muted-foreground"
        >
          Aucune notification
        </p>
      </div>
    </div>
  </div>
</template>
