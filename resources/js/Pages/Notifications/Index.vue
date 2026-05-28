<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { Bell, CheckCheck } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import {
  markAllNotificationsRead,
  markNotificationRead,
} from "@/composables/useNotifications.js";

const props = defineProps({
  notifications: { type: Object, required: true },
  unread_count: { type: Number, default: 0 },
});

const items = props.notifications?.data ?? [];

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

async function handleClick(notification) {
  if (!notification.read_at) {
    await markNotificationRead(notification.id);
  }
  if (notification.url) {
    router.visit(notification.url);
  }
}

async function handleMarkAllRead() {
  await markAllNotificationsRead();
  router.reload({ only: ["notifications", "unread_count"] });
}
</script>

<template>
  <Head title="Notifications" />

  <AuthenticatedLayout>
    <div class="mx-auto flex max-w-2xl flex-col gap-5">
      <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
            <Bell class="h-5 w-5 text-primary" />
            Centre de notifications
          </h1>
          <p class="mt-1 text-sm text-muted-foreground">
            {{ unread_count }} non lue{{ unread_count > 1 ? "s" : "" }}
          </p>
        </div>
        <Button
          v-if="unread_count > 0"
          type="button"
          variant="outline"
          size="sm"
          class="gap-1.5"
          @click="handleMarkAllRead"
        >
          <CheckCheck class="h-3.5 w-3.5" />
          Tout marquer comme lu
        </Button>
      </header>

      <div class="overflow-hidden rounded-xl border border-border bg-card">
        <button
          v-for="notification in items"
          :key="notification.id"
          type="button"
          class="flex w-full flex-col gap-0.5 border-b border-border/40 px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-muted/40"
          :class="{ 'bg-primary/5': !notification.read_at }"
          @click="handleClick(notification)"
        >
          <div class="flex items-start justify-between gap-2">
            <p class="text-sm font-medium text-foreground">{{ notification.title }}</p>
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

        <p v-if="!items.length" class="px-4 py-12 text-center text-sm text-muted-foreground">
          Aucune notification
        </p>
      </div>

      <nav
        v-if="notifications.last_page > 1"
        class="flex flex-wrap items-center justify-center gap-1"
      >
        <Link
          v-for="link in notifications.links"
          :key="`${link.label}-${link.url}`"
          :href="link.url ?? '#'"
          preserve-scroll
          class="rounded-md px-2.5 py-1 text-xs"
          :class="
            link.active
              ? 'bg-primary text-primary-foreground'
              : link.url
                ? 'text-muted-foreground hover:bg-muted'
                : 'pointer-events-none text-muted-foreground/40'
          "
          v-html="link.label"
        />
      </nav>
    </div>
  </AuthenticatedLayout>
</template>
