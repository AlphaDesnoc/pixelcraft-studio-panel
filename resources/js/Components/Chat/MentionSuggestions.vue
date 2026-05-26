<script setup>
import { nextTick, ref, watch } from "vue";

const props = defineProps({
  suggestions: { type: Array, default: () => [] },
  activeIndex: { type: Number, default: 0 },
});

const emits = defineEmits(["select"]);

const listRef = ref(null);

function scrollActiveIntoView() {
  const list = listRef.value;
  if (!list) {
    return;
  }

  const activeItem = list.querySelector(
    `[data-mention-index="${props.activeIndex}"]`,
  );
  activeItem?.scrollIntoView({ block: "nearest" });
}

watch(
  () => props.activeIndex,
  () => {
    nextTick(scrollActiveIntoView);
  },
);

watch(
  () => props.suggestions,
  () => {
    nextTick(scrollActiveIntoView);
  },
  { deep: true },
);
</script>

<template>
  <ul
    ref="listRef"
    class="absolute bottom-full left-0 right-0 z-20 mb-1 max-h-48 overflow-y-auto rounded-lg border border-border bg-popover py-1 shadow-lg"
    role="listbox"
  >
    <li
      v-for="(member, index) in suggestions"
      :key="member.id"
      role="option"
      :data-mention-index="index"
      :aria-selected="index === activeIndex"
    >
      <button
        type="button"
        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors"
        :class="
          index === activeIndex
            ? 'bg-primary/15 text-foreground'
            : 'text-foreground hover:bg-muted/60'
        "
        @mousedown.prevent="emits('select', member)"
      >
        <span
          class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/15 text-[11px] font-semibold text-primary"
        >
          {{ member.name?.charAt(0) ?? "?" }}
        </span>
        <span class="min-w-0 flex-1">
          <span class="block truncate font-medium">@{{ member.pseudo }}</span>
          <span class="block truncate text-xs text-muted-foreground">
            {{ member.name }}
          </span>
        </span>
      </button>
    </li>
  </ul>
</template>
