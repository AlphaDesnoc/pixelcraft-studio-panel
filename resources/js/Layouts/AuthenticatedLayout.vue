<script setup>
import { onMounted, onUnmounted, watch, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import AppSidebar from "@/Components/AppSidebar.vue";
import NotificationBell from "@/Components/Notifications/NotificationBell.vue";
import GlobalSearchModal from "@/Components/Search/GlobalSearchModal.vue";
import { Search } from "lucide-vue-next";
import {
  initSiteRealtime,
  setUnreadCount,
  setUnreadNotificationsCount,
} from "@/composables/useSiteRealtime.js";
import {
  initNotifications,
  teardownNotifications,
} from "@/composables/useNotifications.js";

const page = usePage();

const searchOpen = ref(false);

function onGlobalKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && (e.key === "k" || e.key === "K")) {
    e.preventDefault();
    searchOpen.value = true;
  }
}

function bootstrapRealtime() {
  const user = page.props.auth?.user;
  if (!user?.id) return;
  initSiteRealtime(user.id, page.props.sidebar?.unread_messages ?? 0);
  initNotifications(user.id, page.props.sidebar?.unread_notifications ?? 0);
}

onMounted(() => {
  bootstrapRealtime();
  window.addEventListener("keydown", onGlobalKeydown);
});

onUnmounted(() => {
  teardownNotifications();
  window.removeEventListener("keydown", onGlobalKeydown);
});

watch(
  () => page.props.auth?.user?.id,
  () => bootstrapRealtime(),
);

watch(
  () => page.props.sidebar?.unread_messages,
  (count) => {
    if (typeof count === "number") {
      setUnreadCount(count);
    }
  },
);

watch(
  () => page.props.sidebar?.unread_notifications,
  (count) => {
    if (typeof count === "number") {
      setUnreadNotificationsCount(count);
    }
  },
);
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <AppSidebar />

    <div
      class="fixed inset-x-0 top-0 z-30 flex h-14 items-center justify-end gap-3 border-b border-border/60 bg-background/95 px-6 backdrop-blur md:left-64"
    >
      <button
        type="button"
        class="inline-flex h-9 items-center gap-2 rounded-full border border-border/80 bg-muted/40 px-3 text-[11px] font-medium text-muted-foreground shadow-sm backdrop-blur transition-colors hover:bg-muted hover:text-foreground"
        aria-label="Rechercher"
        title="Recherche globale (Ctrl + K)"
        @click="searchOpen = true"
      >
        <Search class="h-4 w-4 shrink-0" />
        Rechercher
        <kbd
          class="hidden rounded-md border border-border bg-background px-1.5 py-0.5 text-[10px] font-semibold uppercase text-muted-foreground sm:inline-flex"
        >
          Ctrl+K
        </kbd>
      </button>
      <NotificationBell />
    </div>

    <div class="md:pl-64">
      <div class="h-14" aria-hidden="true" />

      <header v-if="$slots.header" class="px-6 pt-8 sm:px-8">
        <slot name="header" />
      </header>

      <main class="px-6 pb-12 pt-6 sm:px-8">
        <slot />
      </main>
    </div>

    <GlobalSearchModal v-model:open="searchOpen" />
  </div>
</template>
