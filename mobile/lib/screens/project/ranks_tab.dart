import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/panel_api_extensions.dart';
import '../../models/extras.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';

class RanksTab extends StatefulWidget {
  const RanksTab({
    super.key,
    required this.workspace,
  });

  final ProjectWorkspace workspace;

  @override
  State<RanksTab> createState() => _RanksTabState();
}

class _RanksTabState extends State<RanksTab> {
  List<ProjectRank> _ranks = [];
  List<RankDashboardEntry> _dashboard = [];
  bool _loading = true;
  int _view = 0;

  String get _slug => widget.workspace.project.slug;
  bool get _canEdit => widget.workspace.canManageRanks;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final api = context.read<AuthSession>().api;
      final ranks = await api.fetchRanks(_slug);
      final dashboard = await api.fetchRankDashboard(_slug);
      if (!mounted) return;
      setState(() {
        _ranks = ranks;
        _dashboard = dashboard;
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _createRank() async {
    final nameController = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Nouveau rank'),
        content: TextField(controller: nameController, decoration: const InputDecoration(labelText: 'Nom')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Créer')),
        ],
      ),
    );
    if (ok != true || nameController.text.trim().isEmpty) return;

    await context.read<AuthSession>().api.createRank(
          projectSlug: _slug,
          name: nameController.text.trim(),
          color: '#7c5cff',
        );
    await _load();
  }

  Future<void> _editRank(ProjectRank rank) async {
    final nameController = TextEditingController(text: rank.name);
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Modifier le rank'),
        content: TextField(controller: nameController, decoration: const InputDecoration(labelText: 'Nom')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Enregistrer')),
        ],
      ),
    );
    if (ok != true || nameController.text.trim().isEmpty) return;

    await context.read<AuthSession>().api.updateRank(
          projectSlug: _slug,
          rankId: rank.id,
          fields: {'name': nameController.text.trim()},
        );
    await _load();
  }

  Future<void> _deleteRank(ProjectRank rank) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer le rank ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (ok != true) return;

    await context.read<AuthSession>().api.deleteRank(projectSlug: _slug, rankId: rank.id);
    await _load();
  }

  Future<void> _manageMembers(ProjectRank rank) async {
    await showModalBottomSheet<void>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: const Text('Ajouter un membre'),
              trailing: const Icon(Icons.add),
            ),
            ...widget.workspace.teamMembers.map(
              (m) => ListTile(
                title: Text(m.name),
                subtitle: Text(m.email),
                onTap: () async {
                  await context.read<AuthSession>().api.addRankMember(
                        projectSlug: _slug,
                        rankId: rank.id,
                        userId: m.id,
                      );
                  if (context.mounted) Navigator.pop(context);
                  await _load();
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Color _parseColor(String hex) {
    final value = hex.replaceFirst('#', '');
    if (value.length == 6) {
      return Color(int.parse('FF$value', radix: 16));
    }
    return Colors.deepPurple;
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: SegmentedButton<int>(
            segments: const [
              ButtonSegment(value: 0, label: Text('Ranks'), icon: Icon(Icons.groups)),
              ButtonSegment(value: 1, label: Text('Stats'), icon: Icon(Icons.analytics_outlined)),
            ],
            selected: {_view},
            onSelectionChanged: (value) => setState(() => _view = value.first),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _load,
            child: _view == 0 ? _buildRanksList() : _buildDashboard(),
          ),
        ),
      ],
    );
  }

  Widget _buildRanksList() {
    if (_ranks.isEmpty) {
      return ListView(
        children: [
          const SizedBox(height: 120),
          const Center(child: Text('Aucun rank')),
          if (_canEdit)
            Center(
              child: FilledButton(onPressed: _createRank, child: const Text('Créer un rank')),
            ),
        ],
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: _ranks.length,
      itemBuilder: (context, index) {
        final rank = _ranks[index];
        return Card(
          margin: const EdgeInsets.only(bottom: 12),
          child: ListTile(
            leading: CircleAvatar(
              backgroundColor: _parseColor(rank.color),
              child: Text(rank.name.isNotEmpty ? rank.name[0] : '?'),
            ),
            title: Text(rank.name),
            subtitle: Text(
              '${rank.membersCount} membres · ${rank.openTasks} tâches'
              '${rank.responsibleName != null ? ' · ${rank.responsibleName}' : ''}',
            ),
            trailing: _canEdit
                ? PopupMenuButton<String>(
                    itemBuilder: (context) => const [
                      PopupMenuItem(value: 'edit', child: Text('Modifier')),
                      PopupMenuItem(value: 'members', child: Text('Membres')),
                      PopupMenuItem(value: 'delete', child: Text('Supprimer')),
                    ],
                    onSelected: (value) {
                      switch (value) {
                        case 'edit':
                          _editRank(rank);
                        case 'members':
                          _manageMembers(rank);
                        case 'delete':
                          _deleteRank(rank);
                      }
                    },
                  )
                : rank.managesBugs
                    ? const Icon(Icons.bug_report_outlined)
                    : null,
          ),
        );
      },
    );
  }

  Widget _buildDashboard() {
    if (_dashboard.isEmpty) {
      return ListView(children: const [SizedBox(height: 120), Center(child: Text('Aucune stat'))]);
    }

    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: _dashboard.length,
      itemBuilder: (context, index) {
        final entry = _dashboard[index];
        final stats = entry.stats;
        return Card(
          margin: const EdgeInsets.only(bottom: 12),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(entry.name, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _StatChip('Ouvertes', '${stats['open_tasks'] ?? 0}'),
                    _StatChip('Retard', '${stats['overdue_tasks'] ?? 0}'),
                    _StatChip('Bugs', '${stats['open_bugs'] ?? 0}'),
                    _StatChip('Vélocité', '${stats['velocity'] ?? 0}'),
                    _StatChip('SLA', '${stats['sla_breached'] ?? 0}'),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _StatChip extends StatelessWidget {
  const _StatChip(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Chip(label: Text('$label : $value'));
  }
}
