/// Mirrors [resources/js/lib/projectPermissions.js].
library;

const memberFeatureKeys = [
  'kanban',
  'calendar',
  'gantt',
  'notes',
  'spreadsheet',
  'files',
  'chat',
  'bugs',
  'team',
];

String writeKeyFor(String feature) => '${feature}_write';

Map<String, bool> fullPermissions({bool read = true, bool write = true}) {
  return {
    for (final key in memberFeatureKeys) ...{
      key: read,
      writeKeyFor(key): read && write,
    },
  };
}

class PermissionPreset {
  const PermissionPreset({
    required this.id,
    required this.label,
    required this.description,
    required this.permissions,
  });

  final String id;
  final String label;
  final String description;
  final Map<String, bool> permissions;
}

final permissionPresets = [
  PermissionPreset(
    id: 'readonly',
    label: 'Lecture seule',
    description: 'Consultation de tous les modules, sans modification.',
    permissions: fullPermissions(read: true, write: false),
  ),
  PermissionPreset(
    id: 'editor',
    label: 'Éditeur',
    description: 'Accès complet sauf gestion de l\'équipe.',
    permissions: {
      ...fullPermissions(read: true, write: true),
      'team': false,
      writeKeyFor('team'): false,
    },
  ),
  PermissionPreset(
    id: 'bug_moderator',
    label: 'Modérateur bugs',
    description: 'Kanban et bugs en écriture, reste en lecture.',
    permissions: {
      ...fullPermissions(read: true, write: false),
      'kanban': true,
      writeKeyFor('kanban'): true,
      'bugs': true,
      writeKeyFor('bugs'): true,
      'chat': true,
      writeKeyFor('chat'): true,
    },
  ),
];

Map<String, bool>? permissionsForPreset(String presetId) {
  for (final preset in permissionPresets) {
    if (preset.id == presetId) {
      return Map<String, bool>.from(preset.permissions);
    }
  }
  return null;
}

bool canReadFeature(Map<String, bool>? perms, String feature) {
  if (perms == null || perms.isEmpty) return true;
  return perms[feature] != false;
}

bool canWriteFeature(Map<String, bool>? perms, String feature) {
  if (!canReadFeature(perms, feature)) return false;
  if (perms == null || perms.isEmpty) return true;
  return perms[writeKeyFor(feature)] != false;
}
