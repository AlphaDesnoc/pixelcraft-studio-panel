import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/extras.dart';
import '../../services/auth_session.dart';

class TeamTab extends StatefulWidget {
  const TeamTab({
    super.key,
    required this.projectSlug,
    required this.initialMembers,
    required this.canManage,
    required this.onChanged,
  });

  final String projectSlug;
  final List<TeamMember> initialMembers;
  final bool canManage;
  final Future<void> Function() onChanged;

  @override
  State<TeamTab> createState() => _TeamTabState();
}

class _TeamTabState extends State<TeamTab> {
  late List<TeamMember> _members;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _members = List.of(widget.initialMembers);
    _refresh();
  }

  Future<void> _refresh() async {
    setState(() => _loading = true);
    try {
      final members =
          await context.read<AuthSession>().api.fetchTeam(widget.projectSlug);
      if (!mounted) return;
      setState(() => _members = members);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _removeMember(TeamMember member) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Retirer le membre ?'),
        content: Text('Retirer ${member.name} du projet ?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Retirer'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    await context.read<AuthSession>().api.removeTeamMember(
          projectSlug: widget.projectSlug,
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

    return RefreshIndicator(
      onRefresh: _refresh,
      child: ListView.separated(
        padding: const EdgeInsets.all(12),
        itemCount: _members.length,
        separatorBuilder: (_, __) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final member = _members[index];
          return ListTile(
            leading: CircleAvatar(child: Text(member.name.isNotEmpty ? member.name[0] : '?')),
            title: Text(member.name),
            subtitle: Text('${member.email} · ${member.role}'),
            trailing: widget.canManage && !member.isOwner
                ? IconButton(
                    icon: const Icon(Icons.person_remove_outlined),
                    onPressed: () => _removeMember(member),
                  )
                : member.isOwner
                    ? const Chip(label: Text('Owner'))
                    : null,
          );
        },
      ),
    );
  }
}
