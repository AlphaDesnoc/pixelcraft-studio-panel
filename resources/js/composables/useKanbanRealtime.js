import { onMounted, onUnmounted, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";

function cloneLists(lists) {
  return lists.map((l) => ({
    ...l,
    tasks: (l.tasks ?? []).map((t) => ({ ...t })),
  }));
}

function applyMoved(lists, payload) {
  const { task_id: taskId, list_id: listId, order, old_list_id: oldListId, task } = payload;
  let movedTask = null;

  for (const list of lists) {
    const idx = list.tasks.findIndex((t) => t.id === taskId);
    if (idx >= 0) {
      movedTask = { ...list.tasks[idx], ...(task ?? {}) };
      list.tasks.splice(idx, 1);
    }
  }

  if (!movedTask && task) {
    movedTask = { ...task };
  }
  if (!movedTask) {
    return lists;
  }

  movedTask.list_id = listId;
  const target = lists.find((l) => l.id === listId);
  if (!target) {
    return lists;
  }

  const orderIds = order ?? [];
  if (orderIds.length) {
    target.tasks = orderIds
      .map((id) => {
        if (id === taskId) {
          return movedTask;
        }
        return (
          target.tasks.find((t) => t.id === id)
          ?? lists.flatMap((l) => l.tasks).find((t) => t.id === id)
        );
      })
      .filter(Boolean);
  } else {
    target.tasks.push(movedTask);
  }

  if (oldListId && oldListId !== listId) {
    const oldList = lists.find((l) => l.id === oldListId);
    if (oldList) {
      oldList.tasks = oldList.tasks.filter((t) => t.id !== taskId);
    }
  }

  return lists;
}

function applyUpdated(lists, payload) {
  const task = payload.task;
  if (!task?.id) {
    return lists;
  }

  for (const list of lists) {
    const idx = list.tasks.findIndex((t) => t.id === task.id);
    if (idx >= 0) {
      if (task.list_id && task.list_id !== list.id) {
        list.tasks.splice(idx, 1);
        const target = lists.find((l) => l.id === task.list_id);
        if (target) {
          target.tasks.push({ ...task });
        }
      } else {
        list.tasks[idx] = { ...list.tasks[idx], ...task };
      }
      return lists;
    }
  }

  const target = lists.find((l) => l.id === task.list_id);
  if (target) {
    target.tasks.push({ ...task });
  }

  return lists;
}

function applyCreated(lists, payload) {
  const task = payload.task;
  if (!task?.id) {
    return lists;
  }
  const target = lists.find((l) => l.id === (payload.list_id ?? task.list_id));
  if (!target || target.tasks.some((t) => t.id === task.id)) {
    return lists;
  }
  target.tasks.push({ ...task });
  return lists;
}

function applyDeleted(lists, payload) {
  const taskId = payload.task_id;
  for (const list of lists) {
    list.tasks = list.tasks.filter((t) => t.id !== taskId);
  }
  return lists;
}

function applyArchived(lists, payload) {
  return applyUpdated(lists, {
    task: {
      id: payload.task_id,
      archived_at: new Date().toISOString(),
    },
    list_id: payload.list_id,
  });
}

export function useKanbanRealtime(projectIdRef, localListsRef, options = {}) {
  const connected = ref(false);
  const page = usePage();
  let channel = null;

  function currentUserId() {
    return page.props.auth?.user?.id ?? null;
  }

  function handleEvent(event) {
    const actorId = event?.actor_id ?? event?.actorId;
    if (actorId && Number(actorId) === Number(currentUserId())) {
      return;
    }

    const action = event?.action;
    const payload = event?.payload ?? {};
    let lists = cloneLists(localListsRef.value);

    switch (action) {
      case "moved":
        lists = applyMoved(lists, payload);
        break;
      case "updated":
        lists = applyUpdated(lists, payload);
        break;
      case "created":
        lists = applyCreated(lists, payload);
        break;
      case "deleted":
        lists = applyDeleted(lists, payload);
        break;
      case "archived":
        lists = applyArchived(lists, payload);
        break;
      default:
        router.reload({ only: ["lists", "stats", "progress", "byStatus", "byPriority"] });
        return;
    }

    localListsRef.value = lists;
    options.onApplied?.(action, payload);
  }

  function subscribe(projectId) {
    unsubscribe();
    if (!window.Echo || !projectId) {
      return;
    }

    channel = window.Echo.private(`project-kanban.${projectId}`);
    channel
      .listen(".TaskKanbanUpdated", handleEvent)
      .listen("TaskKanbanUpdated", handleEvent)
      .error(() => {
        connected.value = false;
      });

    connected.value = true;
  }

  function unsubscribe() {
    if (channel && projectIdRef?.value) {
      window.Echo?.leave(`project-kanban.${projectIdRef.value}`);
    }
    channel = null;
    connected.value = false;
  }

  onMounted(() => {
    if (projectIdRef?.value) {
      subscribe(projectIdRef.value);
    }
  });

  watch(
    () => projectIdRef?.value,
    (id, prev) => {
      if (id === prev) {
        return;
      }
      if (id) {
        subscribe(id);
      } else {
        unsubscribe();
      }
    },
  );

  onUnmounted(unsubscribe);

  return { connected, subscribe, unsubscribe };
}
