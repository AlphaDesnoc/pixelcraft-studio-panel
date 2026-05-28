<script setup>
import { Switch } from "@/Components/ui/switch";
import { writeKeyFor } from "@/lib/projectPermissions.js";

const props = defineProps({
  modules: { type: Array, required: true },
  permissions: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:permissions"]);

function setPermission(key, checked) {
  if (props.disabled) return;
  const next = { ...props.permissions, [key]: checked };
  if (key.endsWith("_write") && checked) {
    next[key.replace(/_write$/, "")] = true;
  }
  if (!key.endsWith("_write") && !checked) {
    next[writeKeyFor(key)] = false;
  }
  emit("update:permissions", next);
}
</script>

<template>
  <div class="overflow-hidden rounded-lg border border-border/50 bg-background/30">
    <div
      class="grid grid-cols-[minmax(0,1fr)_4.5rem_4.5rem] items-center gap-2 border-b border-border/40 bg-muted/20 px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground"
    >
      <span>Module</span>
      <span class="text-center">Lecture</span>
      <span class="text-center">Écriture</span>
    </div>
    <div class="divide-y divide-border/30">
      <div
        v-for="row in modules"
        :key="row.key"
        class="grid grid-cols-[minmax(0,1fr)_4.5rem_4.5rem] items-center gap-2 px-3 py-2.5 transition-colors hover:bg-muted/10"
      >
        <span class="text-sm text-foreground">{{ row.label }}</span>
        <div class="flex justify-center">
          <Switch
            :model-value="permissions[row.key]"
            :disabled="disabled"
            class="scale-90"
            @update:model-value="setPermission(row.key, $event)"
          />
        </div>
        <div class="flex justify-center">
          <Switch
            :model-value="permissions[writeKeyFor(row.key)]"
            :disabled="disabled || !permissions[row.key]"
            class="scale-90"
            @update:model-value="setPermission(writeKeyFor(row.key), $event)"
          />
        </div>
      </div>
    </div>
  </div>
</template>
