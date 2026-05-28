<script setup>
import { ref, watch } from "vue";
import { Search, X } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Select } from "@/Components/ui/select";

const props = defineProps({
  members: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
});

const emits = defineEmits(["update:modelValue", "search"]);

const open = ref(false);
const local = ref({
  q: "",
  author_id: "",
  from: "",
  to: "",
});

watch(
  () => props.modelValue,
  (value) => {
    local.value = {
      q: value?.q ?? "",
      author_id: value?.author_id ?? "",
      from: value?.from ?? "",
      to: value?.to ?? "",
    };
  },
  { immediate: true, deep: true },
);

function apply() {
  const payload = {
    q: local.value.q.trim(),
    author_id: local.value.author_id || null,
    from: local.value.from || null,
    to: local.value.to || null,
  };
  emits("update:modelValue", payload);
  emits("search", payload);
}

function reset() {
  local.value = { q: "", author_id: "", from: "", to: "" };
  apply();
}
</script>

<template>
  <div class="flex flex-col gap-2 border-b border-border/60 bg-card/40 px-3 py-2">
    <div class="flex items-center gap-2">
      <button
        type="button"
        class="inline-flex h-8 flex-1 items-center gap-2 rounded-md border border-border bg-background/40 px-2 text-sm text-muted-foreground hover:bg-muted/40"
        @click="open = !open"
      >
        <Search class="h-4 w-4 shrink-0" />
        <span class="truncate">
          {{ local.q || "Rechercher dans le chat…" }}
        </span>
      </button>
      <Button
        v-if="local.q || local.author_id || local.from || local.to"
        type="button"
        size="icon"
        variant="ghost"
        class="h-8 w-8"
        @click="reset"
      >
        <X class="h-4 w-4" />
      </Button>
    </div>

    <form
      v-if="open"
      class="grid grid-cols-1 gap-2 sm:grid-cols-2"
      @submit.prevent="apply"
    >
      <Input v-model="local.q" placeholder="Mot-clé" />
      <Select v-model="local.author_id">
        <option value="">Tous les auteurs</option>
        <option v-for="member in members" :key="member.id" :value="member.id">
          {{ member.name }}
        </option>
      </Select>
      <Input v-model="local.from" type="date" />
      <Input v-model="local.to" type="date" />
      <Button type="submit" class="sm:col-span-2 h-9">Appliquer</Button>
    </form>
  </div>
</template>
