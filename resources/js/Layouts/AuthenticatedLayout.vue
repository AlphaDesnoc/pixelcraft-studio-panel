<script setup>
import { onMounted, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import AppSidebar from "@/Components/AppSidebar.vue";
import { initSiteRealtime, setUnreadCount } from "@/composables/useSiteRealtime.js";

const page = usePage();

function bootstrapRealtime() {
  const user = page.props.auth?.user;
  if (!user?.id) return;
  initSiteRealtime(user.id, page.props.sidebar?.unread_messages ?? 0);
}

onMounted(bootstrapRealtime);

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
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <AppSidebar />

    <div class="md:pl-64">
      <header v-if="$slots.header" class="px-6 pt-8 sm:px-8">
        <slot name="header" />
      </header>

      <main class="px-6 pb-12 pt-6 sm:px-8">
        <slot />
      </main>
    </div>
  </div>
</template>
