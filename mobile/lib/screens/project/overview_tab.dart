import 'package:flutter/material.dart';

import '../../models/workspace.dart';
import '../../utils/format.dart';

class OverviewTab extends StatelessWidget {
  const OverviewTab({super.key, required this.workspace});

  final ProjectWorkspace workspace;

  @override
  Widget build(BuildContext context) {
    final stats = workspace.stats;

    return RefreshIndicator(
      onRefresh: () async {},
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Progression', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          LinearProgressIndicator(value: workspace.progress / 100),
          const SizedBox(height: 8),
          Text('${workspace.progress}% terminé'),
          const SizedBox(height: 24),
          Text('Statistiques', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _StatChip(label: 'Tâches', value: '${stats['tasks_total'] ?? 0}'),
              _StatChip(label: 'Terminées', value: '${stats['tasks_done'] ?? 0}'),
              _StatChip(label: 'En cours', value: '${stats['tasks_in_progress'] ?? 0}'),
              _StatChip(label: 'En retard', value: '${stats['tasks_overdue'] ?? 0}'),
              _StatChip(label: 'Membres', value: '${stats['members'] ?? 0}'),
              _StatChip(label: 'Bugs ouverts', value: '${stats['open_bugs'] ?? 0}'),
            ],
          ),
          if (workspace.byStatus.isNotEmpty) ...[
            const SizedBox(height: 24),
            Text('Par statut', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            ...workspace.byStatus.map(
              (item) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(
                  children: [
                    Expanded(child: Text(item.label)),
                    Chip(label: Text('${item.count}')),
                  ],
                ),
              ),
            ),
          ],
          if (workspace.byPriority.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text('Par priorité', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            ...workspace.byPriority.map(
              (item) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(
                  children: [
                    Expanded(child: Text(item.label)),
                    Chip(label: Text('${item.count}')),
                  ],
                ),
              ),
            ),
          ],
          if (workspace.activityLogs.isNotEmpty) ...[
            const SizedBox(height: 24),
            Text('Activité récente', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            ...workspace.activityLogs.take(20).map(
              (log) => Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  title: Text(log.message),
                  subtitle: Text(
                    [
                      if (log.userName != null) log.userName,
                      if (log.createdAt != null) formatRelativeTime(log.createdAt),
                    ].whereType<String>().join(' · '),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _StatChip extends StatelessWidget {
  const _StatChip({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Chip(label: Text('$label : $value'));
  }
}
