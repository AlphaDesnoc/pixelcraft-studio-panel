<script setup>
defineProps({
  tabs: { type: Array, required: true },
  active: { type: String, required: true },
});

const emit = defineEmits(["update:active"]);
</script>

<template>
  <div class="border-b border-border">
    <nav class="-mb-px flex flex-wrap gap-x-1 gap-y-0">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="relative inline-flex items-center gap-1.5 px-3 py-2.5 text-sm font-medium transition-colors"
        :class="
          active === tab.key
            ? 'text-foreground'
            : 'text-muted-foreground hover:text-foreground'
        "
        @click="emit('update:active', tab.key)"
      >
        <span>{{ tab.label }}</span>
        <span
          v-if="tab.count !== undefined"
          class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground"
        >
          {{ tab.count }}
        </span>
        <span
          v-if="active === tab.key"
          class="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-primary"
        />
      </button>
    </nav>
  </div>
</template>
