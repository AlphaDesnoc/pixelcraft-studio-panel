import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/extras.dart';
import '../../services/auth_session.dart';

class RanksTab extends StatefulWidget {
  const RanksTab({
    super.key,
    required this.projectSlug,
    required this.canEdit,
  });

  final String projectSlug;
  final bool canEdit;

  @override
  State<RanksTab> createState() => _RanksTabState();
}

class _RanksTabState extends State<RanksTab> {
  List<ProjectRank> _ranks = [];
  List<RankDashboardEntry> _dashboard = [];
  bool _loading = true;
  int _view = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final api = context.read<AuthSession>().api;
      final ranks = await api.fetchRanks(widget.projectSlug);
      final dashboard = await api.fetchRankDashboard(widget.projectSlug);
      if (!mounted) return;
      setState(() {
        _ranks = ranks;
        _dashboard = dashboard;
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    return Column(
      children: [
        SegmentedButton<int>(
          segments: const [
            ButtonSegment(value: 0, label: Text('Ranks'), icon: Icon(Icons.groups)),
            ButtonSegment(value: 1, label: Text('Stats'), icon: Icon(Icons.analytics_outlined)),
          ],
          selected: {_view},
          onSelectionChanged: (value) => setState(() => _view = value.first),
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
      return ListView(children: const [SizedBox(height: 120), Center(child: Text('Aucun rank'))]);
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
            trailing: rank.managesBugs ? const Icon(Icons.bug_report_outlined) : null,
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

  Color _parseColor(String hex) {
    final value = hex.replaceFirst('#', '');
    if (value.length == 6) {
      return Color(int.parse('FF$value', radix: 16));
    }
    return Colors.deepPurple;
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
