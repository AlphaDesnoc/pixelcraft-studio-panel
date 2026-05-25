<script setup>
import { Link } from "@inertiajs/vue3";
import { cn } from "@/lib/utils";

const props = defineProps({
  href: { type: String, required: true },
  active: { type: Boolean, required: false, default: false },
  method: { type: String, required: false, default: "get" },
  as: { type: String, required: false, default: "a" },
  badge: { type: [Number, String], default: null },
});
</script>

<template>
  <Link
    :href="props.href"
    :method="props.method"
    :as="props.as"
    :class="
      cn(
        'group flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-[13px] font-medium transition-colors',
        active
          ? 'bg-primary/15 text-foreground ring-1 ring-primary/30'
          : 'text-muted-foreground hover:bg-muted hover:text-foreground',
      )
    "
  >
    <span
      :class="
        cn(
          'flex h-4 w-4 shrink-0 items-center justify-center [&_svg]:h-4 [&_svg]:w-4',
          active ? 'text-primary' : 'text-muted-foreground group-hover:text-foreground',
        )
      "
    >
      <slot name="icon" />
    </span>
    <span class="truncate">
      <slot />
    </span>
    <span
      v-if="badge"
      class="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-bold text-primary-foreground"
    >
      {{ Number(badge) > 9 ? "9+" : badge }}
    </span>
  </Link>
</template>
