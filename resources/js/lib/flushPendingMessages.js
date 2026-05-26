import axios from "axios";
import {
  bumpPendingRetry,
  enqueuePendingMessage,
  isOfflineError,
  listPendingMessages,
  removePendingMessage,
} from "@/lib/offlineMessageQueue.js";

export async function flushPendingMessages({ onSent, onFailed } = {}) {
  if (typeof navigator !== "undefined" && !navigator.onLine) {
    return { flushed: 0, remaining: (await listPendingMessages()).length };
  }

  const pending = await listPendingMessages();
  let flushed = 0;

  for (const item of pending) {
    try {
      const payload = {
        body: item.body,
        conversation_id: item.conversation_id ?? undefined,
        recipient_id: item.recipient_id ?? undefined,
        reply_to_id: item.reply_to_id ?? undefined,
        mentions: item.mentions?.length ? item.mentions : undefined,
      };

      const { data } = await axios.post(route("messages.store"), payload);
      await removePendingMessage(item.id);
      flushed += 1;
      onSent?.(data, item);
    } catch (error) {
      if (isOfflineError(error)) {
        break;
      }
      await bumpPendingRetry(item.id);
      onFailed?.(error, item);
    }
  }

  const remaining = (await listPendingMessages()).length;
  return { flushed, remaining };
}

export { enqueuePendingMessage, isOfflineError, listPendingMessages };
