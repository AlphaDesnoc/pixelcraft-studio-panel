/**
 * Détermine le début / fin de groupe de messages consécutifs (style WhatsApp).
 */
export function buildMessageClusters(messages, currentUserId) {
  const map = new Map();

  for (let index = 0; index < messages.length; index += 1) {
    const message = messages[index];
    const previous = messages[index - 1];
    const next = messages[index + 1];
    const userId = message.user?.id ?? null;
    const previousUserId = previous?.user?.id ?? null;
    const nextUserId = next?.user?.id ?? null;

    map.set(message.id, {
      isMine: userId != null && userId === currentUserId,
      clusterStart: userId !== previousUserId,
      clusterEnd: userId !== nextUserId,
    });
  }

  return map;
}

export function getMessageCluster(clusters, messageId) {
  return (
    clusters.get(messageId) ?? {
      isMine: false,
      clusterStart: true,
      clusterEnd: true,
    }
  );
}
