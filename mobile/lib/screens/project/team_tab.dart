import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/panel_api_extensions.dart';
import '../../models/extras.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';

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
    const roles = ['member', 'admin', 'viewer'];
    final role = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: roles
              .map(
                (r) => ListTile(
                  title: Text(r),
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
          separatorBuilder: (context, index) => const Divider(height: 1),
          itemBuilder: (context, index) {
            final member = _members[index];
            return ListTile(
              leading: CircleAvatar(child: Text(member.name.isNotEmpty ? member.name[0] : '?')),
              title: Text(member.name),
              subtitle: Text('${member.email} · ${member.role}'),
              trailing: _canManage && !member.isOwner
                  ? PopupMenuButton<String>(
                      itemBuilder: (context) => const [
                        PopupMenuItem(value: 'role', child: Text('Changer le rôle')),
                        PopupMenuItem(value: 'remove', child: Text('Retirer')),
                      ],
                      onSelected: (value) {
                        if (value == 'role') {
                          _changeRole(member);
                        } else {
                          _removeMember(member);
                        }
                      },
                    )
                  : member.isOwner
                      ? const Chip(label: Text('Owner'))
                      : null,
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
