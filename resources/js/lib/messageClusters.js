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

    // Un chapitre (séparateur) coupe systématiquement les groupes : le message
    // qui suit un chapitre réaffiche donc avatar + nom.
    const prevBreaks =
      !previous || previous.type === "chapter" || (previous.user?.id ?? null) !== userId;
    const nextBreaks =
      !next || next.type === "chapter" || (next.user?.id ?? null) !== userId;

    map.set(message.id, {
      isMine: userId != null && userId === currentUserId,
      clusterStart: prevBreaks,
      clusterEnd: nextBreaks,
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
