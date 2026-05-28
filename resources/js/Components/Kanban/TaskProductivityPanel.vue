<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { AlarmClock, Bell, PauseCircle } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";

const props = defineProps({
  projectSlug: { type: String, required: true },
  taskId: { type: Number, required: true },
  readOnly: { type: Boolean, default: false },
});

const timerRunning = ref(false);
const reminderAt = ref("");

function startTimer() {
  router.post(route("projects.tasks.timer.start", [props.projectSlug, props.taskId]), {}, {
    preserveScroll: true,
    onSuccess: () => { timerRunning.value = true; },
  });
}

function stopTimer() {
  router.post(route("projects.tasks.timer.stop", [props.projectSlug, props.taskId]), {}, {
    preserveScroll: true,
    only: ["lists"],
    onSuccess: () => { timerRunning.value = false; },
  });
}

function snooze(duration) {
  router.post(route("projects.tasks.snooze.store", [props.projectSlug, props.taskId]), { duration }, {
    preserveScroll: true,
    only: ["lists"],
  });
}

function setReminder() {
  if (!reminderAt.value) return;
  router.post(route("projects.tasks.reminders.store", [props.projectSlug, props.taskId]), {
    remind_at: reminderAt.value,
  }, { preserveScroll: true });
  reminderAt.value = "";
}
</script>

<template>
  <div v-if="!readOnly" class="flex flex-col gap-2 rounded-lg border border-border/60 bg-muted/10 p-3">
    <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Productivité</p>
    <div class="flex flex-wrap gap-1.5">
      <Button v-if="!timerRunning" type="button" size="sm" variant="outline" class="h-7 gap-1 text-xs" @click="startTimer">
        <AlarmClock class="h-3 w-3" /> Démarrer chrono
      </Button>
      <Button v-else type="button" size="sm" variant="default" class="h-7 gap-1 text-xs" @click="stopTimer">
        <PauseCircle class="h-3 w-3" /> Arrêter chrono
      </Button>
      <Button type="button" size="sm" variant="outline" class="h-7 text-xs" @click="snooze('1d')">+1 jour</Button>
      <Button type="button" size="sm" variant="outline" class="h-7 text-xs" @click="snooze('1w')">+1 semaine</Button>
    </div>
    <div class="flex gap-1.5">
      <Input v-model="reminderAt" type="datetime-local" class="h-8 flex-1 text-xs" />
      <Button type="button" size="sm" variant="outline" class="h-8 gap-1 text-xs" @click="setReminder">
        <Bell class="h-3 w-3" /> Rappel
      </Button>
    </div>
  </div>
</template>
