const DB_NAME = "pixelcraft-offline";
const STORE = "pending_messages";
const DB_VERSION = 1;

function openDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains(STORE)) {
        db.createObjectStore(STORE, { keyPath: "id" });
      }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

function txStore(db, mode) {
  return db.transaction(STORE, mode).objectStore(STORE);
}

export async function enqueuePendingMessage(entry) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const req = txStore(db, "readwrite").put({
      id: entry.id ?? crypto.randomUUID(),
      conversation_id: entry.conversation_id ?? null,
      recipient_id: entry.recipient_id ?? null,
      body: entry.body,
      reply_to_id: entry.reply_to_id ?? null,
      mentions: entry.mentions ?? [],
      created_at: entry.created_at ?? new Date().toISOString(),
      retries: entry.retries ?? 0,
    });
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

export async function listPendingMessages() {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const req = txStore(db, "readonly").getAll();
    req.onsuccess = () => {
      const items = (req.result ?? []).sort(
        (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
      );
      resolve(items);
    };
    req.onerror = () => reject(req.error);
  });
}

export async function removePendingMessage(id) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const req = txStore(db, "readwrite").delete(id);
    req.onsuccess = () => resolve();
    req.onerror = () => reject(req.error);
  });
}

export async function bumpPendingRetry(id) {
  const db = await openDb();
  const store = txStore(db, "readwrite");
  return new Promise((resolve, reject) => {
    const getReq = store.get(id);
    getReq.onsuccess = () => {
      const row = getReq.result;
      if (!row) {
        resolve(null);
        return;
      }
      row.retries = (row.retries ?? 0) + 1;
      const putReq = store.put(row);
      putReq.onsuccess = () => resolve(row);
      putReq.onerror = () => reject(putReq.error);
    };
    getReq.onerror = () => reject(getReq.error);
  });
}

export function isOfflineError(error) {
  if (typeof navigator !== "undefined" && !navigator.onLine) {
    return true;
  }
  const code = error?.code ?? error?.message ?? "";
  return (
    error?.message === "Network Error" ||
    code === "ERR_NETWORK" ||
    code === "ECONNABORTED"
  );
}
