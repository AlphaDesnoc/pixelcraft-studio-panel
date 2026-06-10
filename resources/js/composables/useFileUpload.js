import { reactive } from "vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";

// Gère l'upload de fichiers/dossiers avec progression par fichier + globale.
export function useFileUpload(projectSlug, getRankId) {
  const state = reactive({
    active: false,
    error: null,
    items: [], // { name, progress, status: 'pending'|'uploading'|'done'|'error' }
    overall: 0,
  });

  function reset() {
    state.items = [];
    state.overall = 0;
    state.error = null;
    state.active = false;
  }

  function dismiss() {
    reset();
  }

  // --- Traversée d'une arborescence déposée (webkitGetAsEntry) ---
  function readAllEntries(reader) {
    return new Promise((resolve, reject) => {
      const all = [];
      const read = () => {
        reader.readEntries((batch) => {
          if (batch.length === 0) {
            resolve(all);
          } else {
            all.push(...batch);
            read();
          }
        }, reject);
      };
      read();
    });
  }

  function fileFromEntry(entry) {
    return new Promise((resolve, reject) => entry.file(resolve, reject));
  }

  async function traverseEntry(entry, path, out) {
    if (entry.isFile) {
      const file = await fileFromEntry(entry);
      out.push({ file, relativePath: path + entry.name });
    } else if (entry.isDirectory) {
      const reader = entry.createReader();
      const entries = await readAllEntries(reader);
      for (const child of entries) {
        await traverseEntry(child, path + entry.name + "/", out);
      }
    }
  }

  // Extrait la liste {file, relativePath} d'un DataTransfer (gère les dossiers).
  async function entriesFromDataTransfer(dataTransfer) {
    const items = dataTransfer.items;
    const supportsEntries =
      items && items.length > 0 && typeof items[0].webkitGetAsEntry === "function";

    if (!supportsEntries) {
      return Array.from(dataTransfer.files || []).map((file) => ({
        file,
        relativePath: null,
      }));
    }

    const entries = [];
    for (let i = 0; i < items.length; i++) {
      const entry = items[i].webkitGetAsEntry?.();
      if (entry) entries.push(entry);
    }

    const out = [];
    for (const entry of entries) {
      await traverseEntry(entry, "", out);
    }
    return out;
  }

  // --- Upload séquentiel (un fichier par requête pour la progression par fichier) ---
  async function uploadEntries(entries, parentId = null) {
    if (!entries || entries.length === 0) return;

    state.active = true;
    state.error = null;
    state.items = entries.map((e) => ({
      name: e.relativePath || e.file.name,
      progress: 0,
      status: "pending",
    }));

    const totalBytes = entries.reduce((sum, e) => sum + (e.file.size || 0), 0) || 1;
    let completedBytes = 0;

    for (let i = 0; i < entries.length; i++) {
      const entry = entries[i];
      const item = state.items[i];
      item.status = "uploading";

      const form = new FormData();
      form.append("files[]", entry.file);
      if (entry.relativePath) form.append("relative_paths[]", entry.relativePath);
      if (parentId != null) form.append("parent_id", String(parentId));
      const rankId = getRankId?.();
      if (rankId != null) form.append("rank_id", String(rankId));

      try {
        await axios.post(route("projects.files.upload", projectSlug), form, {
          headers: { "Content-Type": "multipart/form-data" },
          onUploadProgress: (e) => {
            const loaded = e.loaded || 0;
            item.progress = e.total ? Math.round((loaded / e.total) * 100) : 0;
            state.overall = Math.min(
              99,
              Math.round(((completedBytes + loaded) / totalBytes) * 100),
            );
          },
        });
        item.status = "done";
        item.progress = 100;
      } catch (err) {
        item.status = "error";
        state.error =
          err?.response?.data?.message || "Échec de l'upload d'un fichier.";
      }

      completedBytes += entry.file.size || 0;
    }

    state.overall = 100;

    // Recharge les données du projet (fichiers + quota).
    router.reload({
      only: ["fileNodes", "trashedFileNodes", "storageUsed", "storageQuota"],
      onFinish: () => {
        // Laisse la barre visible un court instant si tout est ok.
        if (!state.error) {
          setTimeout(() => {
            if (!state.active) return;
            reset();
          }, 1200);
        }
      },
    });
  }

  function uploadFileList(fileList, parentId = null) {
    const entries = Array.from(fileList || []).map((file) => ({
      file,
      relativePath: file.webkitRelativePath || null,
    }));
    return uploadEntries(entries, parentId);
  }

  return {
    uploadState: state,
    uploadEntries,
    uploadFileList,
    entriesFromDataTransfer,
    dismiss,
  };
}
