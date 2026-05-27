import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/panel_api_extensions.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';
import '../../services/realtime_service.dart';
import '../../services/reverb_service.dart';
import '../../widgets/task_detail_sheet.dart';

class KanbanTab extends StatefulWidget {
  const KanbanTab({
    super.key,
    required this.workspace,
    required this.onChanged,
  });

  final ProjectWorkspace workspace;
  final Future<void> Function() onChanged;

  @override
  State<KanbanTab> createState() => _KanbanTabState();
}

class _KanbanTabState extends State<KanbanTab> {
  LiveChannelSubscription? _liveSubscription;

  bool get _canWrite => widget.workspace.canWrite('kanban');
  String get _slug => widget.workspace.project.slug;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _subscribeLive());
  }

  void _subscribeLive() {
    if (!mounted) return;
    final userId = context.read<AuthSession>().user?.id;
    _liveSubscription = context.read<RealtimeService>().subscribeProjectKanban(
          projectId: widget.workspace.project.id,
          currentUserId: userId,
          onUpdated: () {
            if (mounted) unawaited(widget.onChanged());
          },
        );
  }

  @override
  void dispose() {
    _liveSubscription?.dispose();
    super.dispose();
  }

  Future<void> _createTask(KanbanList list) async {
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
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Créer')),
        ],
      ),
    );
    if (created != true || titleController.text.trim().isEmpty) return;

    await context.read<AuthSession>().api.createTask(
          projectSlug: _slug,
          listId: list.id,
          title: titleController.text.trim(),
        );
    await widget.onChanged();
  }

  Future<void> _renameList(KanbanList list) async {
    final controller = TextEditingController(text: list.name);
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Renommer la colonne'),
        content: TextField(controller: controller, decoration: const InputDecoration(labelText: 'Nom')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Enregistrer')),
        ],
      ),
    );
    if (ok != true || controller.text.trim().isEmpty) return;

    await context.read<AuthSession>().api.updateList(
          projectSlug: _slug,
          listId: list.id,
          fields: {'name': controller.text.trim()},
        );
    await widget.onChanged();
  }

  Future<void> _deleteList(KanbanList list) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer la colonne ?'),
        content: Text('Supprimer « ${list.name} » ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (ok != true) return;

    await context.read<AuthSession>().api.deleteList(projectSlug: _slug, listId: list.id);
    await widget.onChanged();
  }

  Future<void> _addColumn() async {
    final controller = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Nouvelle colonne'),
        content: TextField(controller: controller, decoration: const InputDecoration(labelText: 'Nom')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Créer')),
        ],
      ),
    );
    if (ok != true || controller.text.trim().isEmpty) return;

    await context.read<AuthSession>().api.createList(
          projectSlug: _slug,
          name: controller.text.trim(),
        );
    await widget.onChanged();
  }

  Future<void> _moveTask(KanbanTask task, KanbanList targetList) async {
    if (task.listId == targetList.id) return;
    final order = [...targetList.tasks.map((t) => t.id), task.id];
    await context.read<AuthSession>().api.moveTask(
          projectSlug: _slug,
          taskId: task.id,
          listId: targetList.id,
          order: order,
        );
    await widget.onChanged();
  }

  Color _parseColor(String hex) {
    final value = hex.replaceFirst('#', '');
    if (value.length == 6) {
      return Color(int.parse('FF$value', radix: 16));
    }
    return Colors.blueGrey;
  }

  @override
  Widget build(BuildContext context) {
    if (widget.workspace.lists.isEmpty) {
      return Center(
        child: _canWrite
            ? FilledButton(onPressed: _addColumn, child: const Text('Créer une colonne'))
            : const Text('Aucune colonne Kanban'),
      );
    }

    return Stack(
      children: [
        RefreshIndicator(
          onRefresh: widget.onChanged,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.all(12),
            children: widget.workspace.lists.map((list) {
              return SizedBox(
                width: 280,
                child: DragTarget<KanbanTask>(
                  onWillAcceptWithDetails: (_) => _canWrite,
                  onAcceptWithDetails: (details) => _moveTask(details.data, list),
                  builder: (context, candidate, rejected) {
                    final highlight = candidate.isNotEmpty;
                    return Card(
                      margin: const EdgeInsets.only(right: 12),
                      color: highlight
                          ? Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.3)
                          : null,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Padding(
                            padding: const EdgeInsets.fromLTRB(12, 12, 4, 12),
                            child: Row(
                              children: [
                                Container(
                                  width: 10,
                                  height: 10,
                                  decoration: BoxDecoration(
                                    color: _parseColor(list.color),
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(list.name, style: Theme.of(context).textTheme.titleSmall),
                                ),
                                Text('${list.tasks.length}'),
                                if (_canWrite)
                                  PopupMenuButton<String>(
                                    itemBuilder: (context) => const [
                                      PopupMenuItem(value: 'rename', child: Text('Renommer')),
                                      PopupMenuItem(value: 'delete', child: Text('Supprimer')),
                                    ],
                                    onSelected: (value) {
                                      if (value == 'rename') {
                                        _renameList(list);
                                      } else {
                                        _deleteList(list);
                                      }
                                    },
                                  ),
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
                                return _TaskCard(
                                  task: task,
                                  canDrag: _canWrite,
                                  onDragStarted: () {},
                                  onDragEnded: () {},
                                  onTap: () => showTaskDetailSheet(
                                    context: context,
                                    workspace: widget.workspace,
                                    task: task,
                                    onChanged: widget.onChanged,
                                  ),
                                );
                              },
                            ),
                          ),
                          if (_canWrite)
                            TextButton.icon(
                              onPressed: () => _createTask(list),
                              icon: const Icon(Icons.add),
                              label: const Text('Ajouter'),
                            ),
                        ],
                      ),
                    );
                  },
                ),
              );
            }).toList(),
          ),
        ),
        if (_canWrite)
          Positioned(
            right: 16,
            bottom: 16,
            child: FloatingActionButton(
              onPressed: _addColumn,
              child: const Icon(Icons.view_column_outlined),
            ),
          ),
      ],
    );
  }
}

class _TaskCard extends StatelessWidget {
  const _TaskCard({
    required this.task,
    required this.canDrag,
    required this.onDragStarted,
    required this.onDragEnded,
    required this.onTap,
  });

  final KanbanTask task;
  final bool canDrag;
  final VoidCallback onDragStarted;
  final VoidCallback onDragEnded;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final card = Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(child: Text(task.title, style: Theme.of(context).textTheme.titleSmall)),
                  if (task.isOverdue)
                    const Icon(Icons.warning_amber, color: Colors.orange, size: 16),
                  if (task.isBlocked)
                    const Icon(Icons.block, color: Colors.red, size: 16),
                ],
              ),
              if (task.tags.isNotEmpty) ...[
                const SizedBox(height: 6),
                Wrap(
                  spacing: 4,
                  runSpacing: 4,
                  children: task.tags
                      .map((t) => Chip(label: Text(t.name), visualDensity: VisualDensity.compact))
                      .toList(),
                ),
              ],
              if (task.checklistProgress['total'] != null &&
                  (task.checklistProgress['total'] ?? 0) > 0) ...[
                const SizedBox(height: 6),
                LinearProgressIndicator(
                  value: (task.checklistProgress['done'] ?? 0) /
                      (task.checklistProgress['total'] ?? 1),
                ),
                Text(
                  '${task.checklistProgress['done']}/${task.checklistProgress['total']} checklist',
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              ],
              if (task.dueDate != null)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text('Échéance ${task.dueDate}', style: Theme.of(context).textTheme.labelSmall),
                ),
            ],
          ),
        ),
      ),
    );

    if (!canDrag) return card;

    return LongPressDraggable<KanbanTask>(
      data: task,
      onDragStarted: onDragStarted,
      onDragEnd: (_) => onDragEnded(),
      feedback: Material(
        elevation: 4,
        child: SizedBox(width: 240, child: card),
      ),
      childWhenDragging: Opacity(opacity: 0.4, child: card),
      child: card,
    );
  }
}
