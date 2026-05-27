<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { Download, ExternalLink, Monitor } from "lucide-vue-next";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";

const props = defineProps({
  variant: {
    type: String,
    default: "card",
    validator: (value) => ["card", "sidebar"].includes(value),
  },
});

const page = usePage();

const downloadUrl = computed(() => page.props.desktop?.downloadUrl ?? "");
const isDesktop = computed(() => Boolean(page.props.desktop?.isDesktop));
const isMobileApp = computed(() => Boolean(page.props.mobile?.isMobileApp));
const showPromo = computed(
  () => !isDesktop.value && !isMobileApp.value && Boolean(downloadUrl.value),
);
</script>

<template>
  <a
    v-if="showPromo && variant === 'sidebar'"
    :href="downloadUrl"
    target="_blank"
    rel="noopener noreferrer"
    class="group flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-[13px] font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
  >
    <Monitor
      class="h-4 w-4 shrink-0 text-muted-foreground group-hover:text-foreground"
    />
    <span class="truncate">App desktop</span>
    <ExternalLink class="ml-auto h-3.5 w-3.5 shrink-0 opacity-60" />
  </a>

  <Card v-else-if="showPromo && variant === 'card'">
    <CardHeader>
      <CardTitle class="flex items-center gap-2">
        <Monitor class="h-5 w-5 text-primary" />
        Application desktop
      </CardTitle>
      <CardDescription>
        Installez PixelCraft Panel sur Windows pour une fenêtre dédiée,
        les notifications système et les mises à jour automatiques.
      </CardDescription>
    </CardHeader>
    <CardContent>
      <a
        :href="downloadUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90"
      >
        <Download class="h-4 w-4" />
        Télécharger pour Windows
        <ExternalLink class="h-3.5 w-3.5 opacity-80" />
      </a>
      <p class="mt-3 text-xs text-muted-foreground">
        Les mises à jour sont installées automatiquement après la première
        installation.
      </p>
    </CardContent>
  </Card>
</template>
