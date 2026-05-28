import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/panel_api_extensions.dart';
import '../../models/extras.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';
import '../../utils/project_permissions.dart';

const _roleLabels = {
  'owner': 'Propriétaire',
  'manager': 'Gestionnaire',
  'member': 'Membre',
};

const _assignableRoles = ['member', 'manager'];

const _featureKeys = [
  ('kanban', 'Kanban'),
  ('calendar', 'Calendrier'),
  ('gantt', 'Gantt'),
  ('notes', 'Notes'),
  ('spreadsheet', 'Tableur'),
  ('files', 'Fichiers'),
  ('chat', 'Chat'),
  ('bugs', 'Bugs'),
  ('team', 'Équipe'),
];

class TeamTab extends StatefulWidget {
  const TeamTab({
    super.key,
    required this.workspace,
    required this.onChanged,
  });

  final ProjectWorkspace workspace;
  final Future<void> Function() onChanged;

  @override
  State<TeamTab> createState() => _TeamTabState();
}

class _TeamTabState extends State<TeamTab> {
  late List<TeamMember> _members;
  bool _loading = false;

  String get _slug => widget.workspace.project.slug;
  bool get _canManage => widget.workspace.canManageTeam;

  @override
  void initState() {
    super.initState();
    _members = List.of(widget.workspace.teamMembers);
    _refresh();
  }

  Future<void> _refresh() async {
    setState(() => _loading = true);
    try {
      final members = await context.read<AuthSession>().api.fetchTeam(_slug);
      if (!mounted) return;
      setState(() => _members = members);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _roleLabel(String role) => _roleLabels[role] ?? role;

  Map<String, bool> _effectivePermissions(TeamMember member) {
    final base = <String, bool>{};
    for (final (key, _) in _featureKeys) {
      base[key] = true;
      base['${key}_write'] = true;
    }
    if (member.permissions.isNotEmpty) {
      base.addAll(member.permissions);
    }
    return base;
  }

  Future<void> _addMember() async {
    if (widget.workspace.teamCandidates.isEmpty) return;

    final candidate = await showModalBottomSheet<WorkspaceMember>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: widget.workspace.teamCandidates
              .map(
                (c) => ListTile(
                  title: Text(c.name),
                  subtitle: Text(c.email),
                  onTap: () => Navigator.pop(context, c),
                ),
              )
              .toList(),
        ),
      ),
    );
    if (candidate == null) return;

    await context.read<AuthSession>().api.addTeamMember(
          projectSlug: _slug,
          userId: candidate.id,
        );
    await _refresh();
    await widget.onChanged();
  }

  Future<void> _changeRole(TeamMember member) async {
    final role = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: _assignableRoles
              .map(
                (r) => ListTile(
                  title: Text(_roleLabel(r)),
                  onTap: () => Navigator.pop(context, r),
                ),
              )
              .toList(),
        ),
      ),
    );
    if (role == null) return;

    await context.read<AuthSession>().api.updateTeamMember(
          projectSlug: _slug,
          userId: member.id,
          role: role,
        );
    await _refresh();
    await widget.onChanged();
  }

  Future<void> _editPermissions(TeamMember member) async {
    final perms = Map<String, bool>.from(_effectivePermissions(member));

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return SafeArea(
              child: Padding(
                padding: EdgeInsets.only(
                  left: 16,
                  right: 16,
                  top: 16,
                  bottom: MediaQuery.viewInsetsOf(context).bottom + 16,
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Accès — ${member.name}',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 12),
                    Text('Préréglages', style: Theme.of(context).textTheme.labelLarge),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: permissionPresets.map((preset) {
                        return ActionChip(
                          label: Text(preset.label),
                          onPressed: () {
                            final next = permissionsForPreset(preset.id);
                            if (next != null) {
                              setModalState(() => perms
                                ..clear()
                                ..addAll(next));
                            }
                          },
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 16),
                    ..._featureKeys.map((entry) {
                      final key = entry.$1;
                      final label = entry.$2;
                      final writeKey = '${key}_write';
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                          child: Row(
                            children: [
                              Expanded(child: Text(label)),
                              FilterChip(
                                label: const Text('Lecture'),
                                selected: perms[key] ?? true,
                                onSelected: (value) {
                                  setModalState(() {
                                    perms[key] = value;
                                    if (!value) perms[writeKey] = false;
                                  });
                                },
                              ),
                              const SizedBox(width: 6),
                              FilterChip(
                                label: const Text('Écriture'),
                                selected: perms[writeKey] ?? true,
                                onSelected: (value) {
                                  setModalState(() {
                                    perms[writeKey] = value;
                                    if (value) perms[key] = true;
                                  });
                                },
                              ),
                            ],
                          ),
                        ),
                      );
                    }),
                    FilledButton(
                      onPressed: () async {
                        await context.read<AuthSession>().api.updateTeamPermissions(
                              projectSlug: _slug,
                              userId: member.id,
                              permissions: perms,
                            );
                        if (context.mounted) Navigator.pop(context);
                        await _refresh();
                        await widget.onChanged();
                      },
                      child: const Text('Enregistrer'),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _removeMember(TeamMember member) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Retirer le membre ?'),
        content: Text('Retirer ${member.name} du projet ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Retirer')),
        ],
      ),
    );
    if (confirmed != true) return;

    await context.read<AuthSession>().api.removeTeamMember(
          projectSlug: _slug,
          userId: member.id,
        );
    await _refresh();
    await widget.onChanged();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _members.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView.separated(
          padding: const EdgeInsets.all(12),
          itemCount: _members.length,
          separatorBuilder: (context, index) => const SizedBox(height: 8),
          itemBuilder: (context, index) {
            final member = _members[index];
            return Card(
              child: ListTile(
                leading: CircleAvatar(child: Text(member.name.isNotEmpty ? member.name[0] : '?')),
                title: Text(member.name),
                subtitle: Text('${member.email}\n${_roleLabel(member.role)}'),
                isThreeLine: true,
                trailing: _canManage && !member.isOwner
                    ? PopupMenuButton<String>(
                        itemBuilder: (context) => const [
                          PopupMenuItem(value: 'role', child: Text('Changer le rôle')),
                          PopupMenuItem(value: 'permissions', child: Text('Permissions')),
                          PopupMenuItem(value: 'remove', child: Text('Retirer')),
                        ],
                        onSelected: (value) {
                          switch (value) {
                            case 'role':
                              _changeRole(member);
                            case 'permissions':
                              _editPermissions(member);
                            case 'remove':
                              _removeMember(member);
                          }
                        },
                      )
                    : member.isOwner
                        ? const Chip(label: Text('Owner'))
                        : null,
              ),
            );
          },
        ),
      ),
      floatingActionButton: _canManage && widget.workspace.teamCandidates.isNotEmpty
          ? FloatingActionButton(
              onPressed: _addMember,
              child: const Icon(Icons.person_add_outlined),
            )
          : null,
    );
  }
}
