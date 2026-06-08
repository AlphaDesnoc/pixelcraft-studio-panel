<script setup>
import { computed } from "vue";
import {
  DropdownMenuRoot,
  DropdownMenuTrigger,
  DropdownMenuPortal,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
} from "reka-ui";
import { Globe, Eye, Check, ChevronsUpDown, Layers } from "lucide-vue-next";

const props = defineProps({
  modelValue: { type: String, default: "global" },
  spaces: { type: Array, default: () => [] },
  ranks: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue"]);

function iconFor(icon) {
  if (icon === "globe") return Globe;
  if (icon === "eye") return Eye;
  return null;
}

const activeItem = computed(() => {
  const space = props.spaces.find((s) => s.key === props.modelValue);
  if (space) return { ...space, kind: "space" };
  const rank = props.ranks.find((r) => r.key === props.modelValue);
  if (rank) return { ...rank, kind: "rank" };
  return { key: props.modelValue, label: props.modelValue, kind: "space" };
});

function select(key) {
  if (key !== props.modelValue) emit("update:modelValue", key);
}
</script>

<template>
  <DropdownMenuRoot>
    <DropdownMenuTrigger
      class="group flex w-full max-w-xs items-center gap-2.5 rounded-lg border border-border bg-card/60 px-3 py-2 text-left shadow-sm transition-colors hover:border-primary/40 hover:bg-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background data-[state=open]:border-primary/50 data-[state=open]:bg-card"
    >
      <span
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary"
      >
        <component :is="iconFor(activeItem.icon)" v-if="activeItem.kind === 'space'" class="h-4 w-4" />
        <Layers v-else class="h-4 w-4" />
      </span>
      <span class="flex min-w-0 flex-1 flex-col">
        <span class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
          Espace de travail
        </span>
        <span class="flex items-center gap-1.5 truncate text-sm font-medium text-foreground">
          <span
            v-if="activeItem.kind === 'rank' && activeItem.color"
            class="inline-block h-2 w-2 shrink-0 rounded-full"
            :style="{ backgroundColor: activeItem.color }"
          />
          <span class="truncate">{{ activeItem.label }}</span>
        </span>
      </span>
      <ChevronsUpDown
        class="h-4 w-4 shrink-0 text-muted-foreground transition-colors group-hover:text-foreground"
      />
    </DropdownMenuTrigger>

    <DropdownMenuPortal>
      <DropdownMenuContent
        align="start"
        :side-offset="6"
        class="z-50 w-[var(--reka-dropdown-menu-trigger-width)] min-w-[16rem] overflow-hidden rounded-lg border border-border bg-popover p-1.5 shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
      >
        <DropdownMenuLabel
          class="px-2 pb-1 pt-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
        >
          Général
        </DropdownMenuLabel>
        <DropdownMenuItem
          v-for="space in spaces"
          :key="space.key"
          class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-2 text-sm outline-none transition-colors data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
          @select="select(space.key)"
        >
          <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
            <component :is="iconFor(space.icon)" v-if="iconFor(space.icon)" class="h-3.5 w-3.5" />
          </span>
          <span class="flex-1 truncate font-medium">{{ space.label }}</span>
          <Check v-if="modelValue === space.key" class="h-4 w-4 shrink-0 text-primary" />
        </DropdownMenuItem>

        <template v-if="ranks.length">
          <DropdownMenuSeparator class="my-1.5 h-px bg-border" />
          <DropdownMenuLabel
            class="px-2 pb-1 pt-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
          >
            Ranks
          </DropdownMenuLabel>
          <DropdownMenuItem
            v-for="rank in ranks"
            :key="rank.key"
            class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-2 text-sm outline-none transition-colors data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
            @select="select(rank.key)"
          >
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-muted">
              <span
                class="inline-block h-2.5 w-2.5 rounded-full"
                :style="{ backgroundColor: rank.color || '#6366f1' }"
              />
            </span>
            <span class="flex-1 truncate font-medium">{{ rank.label }}</span>
            <Check v-if="modelValue === rank.key" class="h-4 w-4 shrink-0 text-primary" />
          </DropdownMenuItem>
        </template>
      </DropdownMenuContent>
    </DropdownMenuPortal>
  </DropdownMenuRoot>
</template>
