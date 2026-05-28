/** @param {Record<string, boolean>|null|undefined} perms */
export function canReadFeature(perms, feature) {
  if (!perms || Object.keys(perms).length === 0) {
    return true;
  }
  return perms[feature] !== false;
}

/** @param {Record<string, boolean>|null|undefined} perms */
export function canWriteFeature(perms, feature) {
  if (!canReadFeature(perms, feature)) {
    return false;
  }
  if (!perms || Object.keys(perms).length === 0) {
    return true;
  }
  const writeKey = `${feature}_write`;
  return perms[writeKey] !== false;
}

export function writeKeyFor(feature) {
  return `${feature}_write`;
}

export const MEMBER_FEATURE_KEYS = Object.freeze([
  "kanban",
  "calendar",
  "gantt",
  "notes",
  "spreadsheet",
  "files",
  "chat",
  "bugs",
  "team",
]);

/** @returns {Record<string, boolean>} */
export function fullPermissions(read = true, write = true) {
  return Object.fromEntries(
    MEMBER_FEATURE_KEYS.flatMap((key) => [
      [key, read],
      [writeKeyFor(key), read && write],
    ]),
  );
}

export const PERMISSION_PRESETS = Object.freeze([
  {
    id: "readonly",
    label: "Lecture seule",
    description: "Consultation de tous les modules, sans modification.",
    permissions: fullPermissions(true, false),
  },
  {
    id: "editor",
    label: "Éditeur",
    description: "Accès complet sauf gestion de l'équipe.",
    permissions: {
      ...fullPermissions(true, true),
      team: false,
      team_write: false,
    },
  },
  {
    id: "bug_moderator",
    label: "Modérateur bugs",
    description: "Kanban et bugs en écriture, reste en lecture.",
    permissions: {
      ...fullPermissions(true, false),
      kanban: true,
      kanban_write: true,
      bugs: true,
      bugs_write: true,
      chat: true,
      chat_write: true,
    },
  },
]);

/** @param {string} presetId */
export function permissionsForPreset(presetId) {
  const preset = PERMISSION_PRESETS.find((item) => item.id === presetId);
  return preset ? { ...preset.permissions } : null;
}
