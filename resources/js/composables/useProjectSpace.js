export function spacePayload(activeSpace, rankId) {
  return {
    space: activeSpace ?? "global",
    rank_id: rankId ?? null,
  };
}

export function spaceOnlyProps() {
  return [
    "lists",
    "events",
    "notes",
    "sheets",
    "fileNodes",
    "stats",
    "progress",
    "byStatus",
    "byPriority",
    "activeSpace",
    "activeRankId",
    "spaceLabel",
    "canReportBugs",
    "canManageBugs",
    "bugs",
    "bugRanks",
    "chatMessages",
    "teamMembers",
    "teamCandidates",
    "canManageTeam",
    "memberRoles",
  ];
}
