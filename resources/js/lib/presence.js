export function sortPresenceUsers(users) {
  return [...users].sort((a, b) => (a.name ?? "").localeCompare(b.name ?? ""));
}

export function bindPresenceHandlers(channel, onlineUsers) {
  channel
    .here((users) => {
      onlineUsers.value = sortPresenceUsers(users);
    })
    .joining((user) => {
      if (!onlineUsers.value.some((u) => u.id === user.id)) {
        onlineUsers.value = sortPresenceUsers([...onlineUsers.value, user]);
      }
    })
    .leaving((user) => {
      onlineUsers.value = onlineUsers.value.filter((u) => u.id !== user.id);
    });
}
