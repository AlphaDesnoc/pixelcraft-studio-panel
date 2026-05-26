<script setup>
import { useForm } from "@inertiajs/vue3";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import { Button } from "@/Components/ui/button";

const props = defineProps({
  preferences: { type: Object, required: true },
  types: { type: Array, default: () => [] },
});

const form = useForm({
  preferences: { ...props.preferences },
});

function submit() {
  form.put(route("profile.notifications.update"), {
    preserveScroll: true,
  });
}
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="submit">
    <div
      v-for="item in types"
      :key="item.type"
      class="flex items-start justify-between gap-4 border-t border-border/60 pt-4 first:border-t-0 first:pt-0"
    >
      <div class="min-w-0">
        <Label :for="`notif-${item.type}`" class="text-sm font-medium">
          {{ item.label }}
        </Label>
      </div>
      <Switch
        :id="`notif-${item.type}`"
        :model-value="form.preferences[item.type]"
        @update:model-value="(value) => (form.preferences[item.type] = value)"
      />
    </div>

    <div class="flex items-center gap-3 pt-2">
      <Button type="submit" :disabled="form.processing">
        {{ form.processing ? "Enregistrement…" : "Enregistrer" }}
      </Button>
      <p v-if="form.recentlySuccessful" class="text-xs text-emerald-400">
        Préférences enregistrées.
      </p>
    </div>
  </form>
</template>
