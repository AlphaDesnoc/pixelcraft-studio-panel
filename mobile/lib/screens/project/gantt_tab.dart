import 'package:flutter/material.dart';

import '../../models/workspace.dart';

class GanttTab extends StatelessWidget {
  const GanttTab({super.key, required this.workspace});

  final ProjectWorkspace workspace;

  List<KanbanTask> get _tasks {
    final tasks = <KanbanTask>[];
    for (final list in workspace.lists) {
      tasks.addAll(list.tasks);
    }
    tasks.sort((a, b) {
      final aDate = a.startDate ?? a.dueDate ?? '';
      final bDate = b.startDate ?? b.dueDate ?? '';
      return aDate.compareTo(bDate);
    });
    return tasks;
  }

  String _listName(int listId) {
    for (final list in workspace.lists) {
      if (list.id == listId) return list.name;
    }
    return 'Liste';
  }

  @override
  Widget build(BuildContext context) {
    final tasks = _tasks;

    if (tasks.isEmpty) {
      return const Center(child: Text('Aucune tâche planifiée'));
    }

    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: tasks.length,
      itemBuilder: (context, index) {
        final task = tasks[index];
        final blocked = task.isBlocked;
        final deps = task.dependencyIds;

        return Card(
          margin: const EdgeInsets.only(bottom: 12),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        task.title,
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                    ),
                    if (blocked)
                      const Chip(
                        label: Text('Bloquée'),
                        visualDensity: VisualDensity.compact,
                      ),
                    if (task.isOverdue)
                      const Icon(Icons.warning_amber, color: Colors.orange, size: 18),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  _listName(task.listId),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
                if (task.startDate != null || task.dueDate != null) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.date_range, size: 16),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          [
                            if (task.startDate != null) 'Début ${task.startDate}',
                            if (task.dueDate != null) 'Fin ${task.dueDate}',
                          ].join(' · '),
                        ),
                      ),
                    ],
                  ),
                ],
                if (deps.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 6,
                    children: [
                      const Text('Dépend de :'),
                      ...deps.map(
                        (id) => Chip(
                          label: Text('#$id'),
                          visualDensity: VisualDensity.compact,
                        ),
                      ),
                    ],
                  ),
                ],
                if (task.progress > 0) ...[
                  const SizedBox(height: 8),
                  LinearProgressIndicator(value: task.progress / 100),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}
