<script setup>
import { onMounted, onUnmounted, ref } from "vue";
import axios from "axios";
import { Eye } from "lucide-vue-next";

const props = defineProps({
  projectSlug: { type: String, required: true },
  context: { type: String, default: "kanban" },
  taskId: { type: Number, default: null },
});

const viewers = ref([]);
let timer = null;

async function ping() {
  await axios.post(route("projects.presence.store", props.projectSlug), {
    context: props.context,
    task_id: props.taskId,
  });
  const { data } = await axios.get(route("projects.presence.index", props.projectSlug));
  viewers.value = data.viewers ?? [];
}

onMounted(() => {
  ping();
  timer = setInterval(ping, 25000);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});
</script>

<template>
  <div v-if="viewers.length" class="flex flex-wrap items-center gap-2 rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
    <Eye class="h-3.5 w-3.5" />
    <span v-for="v in viewers" :key="`${v.user_id}-${v.context}-${v.task_id}`" class="rounded-full bg-primary/10 px-2 py-0.5 text-foreground">
      {{ v.user_name }} · {{ v.context }}{{ v.task_id ? ` #${v.task_id}` : "" }}
    </span>
  </div>
</template>
