import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/workspace.dart';
import '../../services/auth_session.dart';

class KanbanTab extends StatelessWidget {
  const KanbanTab({
    super.key,
    required this.workspace,
    required this.onChanged,
  });

  final ProjectWorkspace workspace;
  final Future<void> Function() onChanged;

  Future<void> _createTask(BuildContext context, KanbanList list) async {
    final titleController = TextEditingController();
    final created = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Nouvelle tâche'),
        content: TextField(
          controller: titleController,
          autofocus: true,
          decoration: const InputDecoration(labelText: 'Titre'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Créer'),
          ),
        ],
      ),
    );

    if (created != true || titleController.text.trim().isEmpty) return;

    await context.read<AuthSession>().api.createTask(
          projectSlug: workspace.project.slug,
          listId: list.id,
          title: titleController.text.trim(),
        );
    await onChanged();
  }

  Future<void> _openTask(BuildContext context, KanbanTask task) async {
    final titleController = TextEditingController(text: task.title);
    final descriptionController = TextEditingController(text: task.description);

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: titleController,
              decoration: const InputDecoration(labelText: 'Titre'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: descriptionController,
              minLines: 2,
              maxLines: 5,
              decoration: const InputDecoration(labelText: 'Description'),
            ),
            const SizedBox(height: 16),
            if (workspace.canWrite('kanban'))
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Enregistrer'),
              ),
          ],
        ),
      ),
    );

    if (saved != true || !workspace.canWrite('kanban')) return;

    await context.read<AuthSession>().api.updateTask(
          projectSlug: workspace.project.slug,
          taskId: task.id,
          fields: {
            'title': titleController.text.trim(),
            'description': descriptionController.text.trim(),
          },
        );
    await onChanged();
  }

  @override
  Widget build(BuildContext context) {
    if (workspace.lists.isEmpty) {
      return const Center(child: Text('Aucune colonne Kanban'));
    }

    return RefreshIndicator(
      onRefresh: onChanged,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.all(12),
        children: workspace.lists.map((list) {
          return SizedBox(
            width: 280,
            child: Card(
              margin: const EdgeInsets.only(right: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            list.name,
                            style: Theme.of(context).textTheme.titleSmall,
                          ),
                        ),
                        Text('${list.tasks.length}'),
                      ],
                    ),
                  ),
                  const Divider(height: 1),
                  Expanded(
                    child: ListView.builder(
                      padding: const EdgeInsets.all(8),
                      itemCount: list.tasks.length,
                      itemBuilder: (context, index) {
                        final task = list.tasks[index];
                        return Card(
                          margin: const EdgeInsets.only(bottom: 8),
                          child: ListTile(
                            dense: true,
                            title: Text(task.title),
                            subtitle: task.dueDate != null
                                ? Text('Échéance : ${task.dueDate}')
                                : null,
                            trailing: task.isOverdue
                                ? const Icon(Icons.warning_amber, color: Colors.orange)
                                : null,
                            onTap: () => _openTask(context, task),
                          ),
                        );
                      },
                    ),
                  ),
                  if (workspace.canWrite('kanban'))
                    TextButton.icon(
                      onPressed: () => _createTask(context, list),
                      icon: const Icon(Icons.add),
                      label: const Text('Ajouter'),
                    ),
                ],
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}
