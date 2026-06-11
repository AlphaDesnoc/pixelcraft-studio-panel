import { onUnmounted, ref, watch } from "vue";
import axios from "axios";

function sortAnnouncements(list) {
  return [...list].sort(
    (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
  );
}

export function useAnnouncements(projectSlug, projectId, initialRef = null) {
  const announcements = ref(sortAnnouncements(initialRef?.value ?? []));
  const loading = ref(false);
  const sending = ref(false);
  let channel = null;

  function upsert(announcement) {
    if (!announcement?.id) return;
    const existing = announcements.value.filter((a) => a.id !== announcement.id);
    announcements.value = sortAnnouncements([announcement, ...existing]);
  }

  function removeLocal(announcementId) {
    announcements.value = announcements.value.filter((a) => a.id !== announcementId);
  }

  async function fetchAnnouncements() {
    loading.value = true;
    try {
      const { data } = await axios.get(
        route("projects.announcements.index", projectSlug),
      );
      announcements.value = sortAnnouncements(data.announcements ?? []);
    } catch {
      /* ignore */
    } finally {
      loading.value = false;
    }
  }

  async function post({ title, body, images }) {
    if (sending.value) return;

    sending.value = true;
    try {
      const formData = new FormData();
      if (title) formData.append("title", title);
      if (body) formData.append("body", body);
      for (const image of images ?? []) {
        formData.append("images[]", image);
      }

      const { data } = await axios.post(
        route("projects.announcements.store", projectSlug),
        formData,
        { headers: { "Content-Type": "multipart/form-data" } },
      );
      upsert(data.announcement);
      return data.announcement;
    } finally {
      sending.value = false;
    }
  }

  async function remove(announcementId) {
    await axios.delete(
      route("projects.announcements.destroy", [projectSlug, announcementId]),
    );
    removeLocal(announcementId);
  }

  function subscribe() {
    if (!window.Echo || !projectId || channel) {
      return;
    }

    channel = window.Echo.private(`project-announcements.${projectId}`);
    channel
      .listen(".AnnouncementPosted", (event) => upsert(event?.announcement))
      .listen("AnnouncementPosted", (event) => upsert(event?.announcement))
      .listen(".AnnouncementDeleted", (event) =>
        removeLocal(event?.announcement_id ?? event?.announcementId),
      )
      .listen("AnnouncementDeleted", (event) =>
        removeLocal(event?.announcement_id ?? event?.announcementId),
      )
      .error((error) => {
        console.warn("[announcements] Echo error", error);
      });
  }

  function unsubscribe() {
    if (channel && window.Echo && projectId) {
      window.Echo.leave(`project-announcements.${projectId}`);
      channel = null;
    }
  }

  watch(
    initialRef,
    (value) => {
      announcements.value = sortAnnouncements(value ?? []);
    },
    { deep: true },
  );

  subscribe();
  onUnmounted(unsubscribe);

  return {
    announcements,
    loading,
    sending,
    fetchAnnouncements,
    post,
    remove,
  };
}
