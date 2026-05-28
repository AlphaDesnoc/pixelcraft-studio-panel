import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/panel_api_extensions.dart';
import '../../models/extras.dart';
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
    this.focusMode = false,
    this.initialTaskId,
  });

  final ProjectWorkspace workspace;
  final Future<void> Function() onChanged;
  final bool focusMode;
  final int? initialTaskId;

  @override
  State<KanbanTab> createState() => _KanbanTabState();
}

class _KanbanTabState extends State<KanbanTab> {
  LiveChannelSubscription? _liveSubscription;

  int? _filterAssigneeId;
  String? _filterPriority;
  final Set<int> _filterTagIds = {};
  bool _showArchived = false;
  String _searchQuery = '';
  bool _swimlaneByAssignee = false;
  List<KanbanSavedView> _savedViews = [];
  Timer? _presenceTimer;
  bool _openedInitialTask = false;

  bool get _canWrite => widget.workspace.canWrite('kanban');
  String get _slug => widget.workspace.project.slug;
  bool get _hasActiveFilters =>
      _filterAssigneeId != null ||
      _filterPriority != null ||
      _filterTagIds.isNotEmpty ||
      _showArchived ||
      _searchQuery.isNotEmpty;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _subscribeLive();
      _loadSavedViews();
      _startPresenceHeartbeat();
      _maybeOpenInitialTask();
    });
  }

  void _maybeOpenInitialTask() {
    final taskId = widget.initialTaskId;
    if (taskId == null || _openedInitialTask || !mounted) return;
    for (final list in widget.workspace.lists) {
      for (final task in list.tasks) {
        if (task.id == taskId) {
          _openedInitialTask = true;
          showTaskDetailSheet(
            context: context,
            workspace: widget.workspace,
            task: task,
            onChanged: widget.onChanged,
          );
          return;
        }
      }
    }
  }

  Future<void> _loadSavedViews() async {
    try {
      final views =
          await context.read<AuthSession>().api.fetchKanbanSavedViews(_slug);
      if (mounted) setState(() => _savedViews = views);
    } catch (_) {}
  }

  void _startPresenceHeartbeat() {
    _presenceTimer?.cancel();
    _sendPresence();
    _presenceTimer = Timer.periodic(const Duration(seconds: 45), (_) => _sendPresence());
  }

  Future<void> _sendPresence() async {
    try {
      await context.read<AuthSession>().api.sendPresenceHeartbeat(
            projectSlug: _slug,
            context: 'kanban',
          );
    } catch (_) {}
  }

  void _applySavedView(KanbanSavedView view) {
    final filters = view.filters;
    setState(() {
      final assignee = filters['assigneeId'];
      _filterAssigneeId = assignee == null || '$assignee'.isEmpty
          ? null
          : int.tryParse('$assignee');
      final priority = filters['priority'];
      _filterPriority =
          priority == null || '$priority'.isEmpty ? null : '$priority';
      _searchQuery = (filters['search'] as String? ?? '').trim();
      _showArchived = filters['showArchived'] == true;
      _swimlaneByAssignee = filters['swimlaneByAssignee'] == true;
      _filterTagIds
        ..clear()
        ..addAll(
          (filters['tagIds'] as List<dynamic>? ?? [])
              .map((e) => int.tryParse('$e'))
              .whereType<int>(),
        );
    });
  }

  List<({String key, String label, List<KanbanTask> tasks})> _swimlaneGroups(
    List<KanbanTask> tasks,
  ) {
    final groups = <String, List<KanbanTask>>{};
    for (final task in tasks) {
      final key = task.assigneeId?.toString() ?? 'none';
      groups.putIfAbsent(key, () => []).add(task);
    }

    final ordered = <({String key, String label, List<KanbanTask> tasks})>[];
    if (groups.containsKey('none')) {
      ordered.add((key: 'none', label: 'Non assigné', tasks: groups.remove('none')!));
    }
    for (final member in widget.workspace.members) {
      final key = member.id.toString();
      if (groups.containsKey(key)) {
        ordered.add((key: key, label: member.name, tasks: groups.remove(key)!));
      }
    }
    for (final entry in groups.entries) {
      ordered.add((
        key: entry.key,
        label: 'Membre #${entry.key}',
        tasks: entry.value,
      ));
    }
    return ordered;
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
    _presenceTimer?.cancel();
    _liveSubscription?.dispose();
    super.dispose();
  }

  Future<void> _createTask(KanbanList list) async {
    final titleController = TextEditingController();
    final descriptionController = TextEditingController();
    final templates = widget.workspace.taskTemplates;
    int? selectedTemplateId;

    final created = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            title: const Text('Nouvelle tâche'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (templates.isNotEmpty) ...[
                    DropdownButtonFormField<int?>(
                      value: selectedTemplateId,
                      decoration: const InputDecoration(labelText: 'Modèle'),
                      items: [
                        const DropdownMenuItem<int?>(value: null, child: Text('Aucun')),
                        ...templates.map(
                          (t) => DropdownMenuItem<int?>(
                            value: t['id'] as int?,
                            child: Text(t['name'] as String? ?? t['title'] as String? ?? 'Modèle'),
                          ),
                        ),
                      ],
                      onChanged: (value) {
                        setDialogState(() {
                          selectedTemplateId = value;
                          if (value == null) return;
                          final tpl = templates.firstWhere((t) => t['id'] == value);
                          titleController.text = tpl['title'] as String? ?? '';
                          descriptionController.text = tpl['description'] as String? ?? '';
                        });
                      },
                    ),
                    const SizedBox(height: 12),
                  ],
                  TextField(
                    controller: titleController,
                    autofocus: true,
                    decoration: const InputDecoration(labelText: 'Titre'),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: descriptionController,
                    decoration: const InputDecoration(labelText: 'Description'),
                    minLines: 1,
                    maxLines: 3,
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
              FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Créer')),
            ],
          );
        },
      ),
    );
    if (created != true || titleController.text.trim().isEmpty) return;

    final api = context.read<AuthSession>().api;
    var task = await api.createTask(
      projectSlug: _slug,
      listId: list.id,
      title: titleController.text.trim(),
      description: descriptionController.text.trim().isEmpty
          ? null
          : descriptionController.text.trim(),
    );
    if (selectedTemplateId != null) {
      task = await api.applyTaskTemplate(
        projectSlug: _slug,
        taskId: task.id,
        templateId: selectedTemplateId!,
      );
    }
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

  bool _matchesFilters(KanbanTask task) {
    final archived = task.archivedAt != null;
    if (_showArchived) {
      if (!archived) return false;
    } else if (archived) {
      return false;
    }
    if (_filterAssigneeId != null && task.assigneeId != _filterAssigneeId) return false;
    if (_filterPriority != null && task.priority != _filterPriority) return false;
    if (_filterTagIds.isNotEmpty &&
        !_filterTagIds.every((id) => task.tags.any((t) => t.id == id))) {
      return false;
    }
    if (_searchQuery.isNotEmpty &&
        !task.title.toLowerCase().contains(_searchQuery.toLowerCase())) {
      return false;
    }
    return true;
  }

  List<KanbanList> get _filteredLists {
    return widget.workspace.lists
        .map(
          (list) => KanbanList(
            id: list.id,
            name: list.name,
            color: list.color,
            statusKind: list.statusKind,
            tasks: list.tasks.where(_matchesFilters).toList(),
          ),
        )
        .toList();
  }

  Future<void> _openFilters() async {
    var assigneeId = _filterAssigneeId;
    var priority = _filterPriority;
    final tagIds = Set<int>.from(_filterTagIds);
    var showArchived = _showArchived;
    var swimlane = _swimlaneByAssignee;
    final searchController = TextEditingController(text: _searchQuery);

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
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text('Filtres Kanban', style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 12),
                      TextField(
                        controller: searchController,
                        decoration: const InputDecoration(
                          labelText: 'Recherche',
                          prefixIcon: Icon(Icons.search),
                        ),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<int?>(
                        value: assigneeId,
                        decoration: const InputDecoration(labelText: 'Assigné à'),
                        items: [
                          const DropdownMenuItem<int?>(value: null, child: Text('Tous')),
                          ...widget.workspace.members.map(
                            (m) => DropdownMenuItem<int?>(value: m.id, child: Text(m.name)),
                          ),
                        ],
                        onChanged: (value) => setModalState(() => assigneeId = value),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String?>(
                        value: priority,
                        decoration: const InputDecoration(labelText: 'Priorité'),
                        items: [
                          const DropdownMenuItem<String?>(value: null, child: Text('Toutes')),
                          ...widget.workspace.priorities.entries.map(
                            (e) => DropdownMenuItem<String?>(value: e.key, child: Text(e.value)),
                          ),
                        ],
                        onChanged: (value) => setModalState(() => priority = value),
                      ),
                      const SizedBox(height: 12),
                      Text('Tags', style: Theme.of(context).textTheme.labelLarge),
                      Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: widget.workspace.tags.map((tag) {
                          final selected = tagIds.contains(tag.id);
                          return FilterChip(
                            label: Text(tag.name),
                            selected: selected,
                            onSelected: (value) {
                              setModalState(() {
                                if (value) {
                                  tagIds.add(tag.id);
                                } else {
                                  tagIds.remove(tag.id);
                                }
                              });
                            },
                          );
                        }).toList(),
                      ),
                      SwitchListTile(
                        title: const Text('Archivées uniquement'),
                        value: showArchived,
                        onChanged: (value) => setModalState(() => showArchived = value),
                      ),
                      SwitchListTile(
                        title: const Text('Swimlanes par assigné'),
                        subtitle: const Text('Regrouper les cartes par personne'),
                        value: swimlane,
                        onChanged: (value) => setModalState(() => swimlane = value),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton(
                              onPressed: () {
                                setState(() {
                                  _filterAssigneeId = null;
                                  _filterPriority = null;
                                  _filterTagIds.clear();
                                  _showArchived = false;
                                  _searchQuery = '';
                                  _swimlaneByAssignee = false;
                                });
                                Navigator.pop(context);
                              },
                              child: const Text('Réinitialiser'),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: FilledButton(
                              onPressed: () {
                                setState(() {
                                  _filterAssigneeId = assigneeId;
                                  _filterPriority = priority;
                                  _filterTagIds
                                    ..clear()
                                    ..addAll(tagIds);
                                  _showArchived = showArchived;
                                  _searchQuery = searchController.text.trim();
                                  _swimlaneByAssignee = swimlane;
                                });
                                Navigator.pop(context);
                              },
                              child: const Text('Appliquer'),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
    searchController.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final lists = _filteredLists;

    if (widget.workspace.lists.isEmpty) {
      return Center(
        child: _canWrite
            ? FilledButton(onPressed: _addColumn, child: const Text('Créer une colonne'))
            : const Text('Aucune colonne Kanban'),
      );
    }

    return Stack(
      children: [
        Column(
          children: [
            if (!widget.focusMode)
              Material(
                elevation: 1,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  child: Row(
                    children: [
                      IconButton(
                        tooltip: 'Filtres',
                        onPressed: _openFilters,
                        icon: Badge(
                          isLabelVisible: _hasActiveFilters,
                          child: const Icon(Icons.filter_list),
                        ),
                      ),
                      IconButton(
                        tooltip: 'Swimlanes',
                        onPressed: () => setState(() => _swimlaneByAssignee = !_swimlaneByAssignee),
                        icon: Icon(
                          Icons.view_stream_outlined,
                          color: _swimlaneByAssignee
                              ? Theme.of(context).colorScheme.primary
                              : null,
                        ),
                      ),
                      if (_savedViews.isNotEmpty)
                        PopupMenuButton<KanbanSavedView?>(
                          tooltip: 'Vues enregistrées',
                          icon: const Icon(Icons.bookmark_outline),
                          onSelected: (view) {
                            if (view == null) {
                              setState(() {
                                _filterAssigneeId = null;
                                _filterPriority = null;
                                _filterTagIds.clear();
                                _showArchived = false;
                                _searchQuery = '';
                                _swimlaneByAssignee = false;
                              });
                            } else {
                              _applySavedView(view);
                            }
                          },
                          itemBuilder: (context) => [
                            const PopupMenuItem<KanbanSavedView?>(
                              value: null,
                              child: Text('Vue par défaut'),
                            ),
                            ..._savedViews.map(
                              (v) => PopupMenuItem(value: v, child: Text(v.name)),
                            ),
                          ],
                        ),
                      Expanded(
                        child: Text(
                          _hasActiveFilters ? 'Filtres actifs' : 'Toutes les tâches',
                          style: Theme.of(context).textTheme.labelLarge,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (_hasActiveFilters)
                        TextButton(
                          onPressed: () => setState(() {
                            _filterAssigneeId = null;
                            _filterPriority = null;
                            _filterTagIds.clear();
                            _showArchived = false;
                            _searchQuery = '';
                          }),
                          child: const Text('Effacer'),
                        ),
                    ],
                  ),
                ),
              ),
            Expanded(
              child: RefreshIndicator(
                onRefresh: widget.onChanged,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.all(12),
                  children: lists.map((list) {
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
                            child: _swimlaneByAssignee
                                ? ListView(
                                    padding: const EdgeInsets.all(8),
                                    children: [
                                      for (final group in _swimlaneGroups(list.tasks)) ...[
                                        Padding(
                                          padding: const EdgeInsets.only(bottom: 4, top: 4),
                                          child: Text(
                                            group.label,
                                            style: Theme.of(context).textTheme.labelSmall,
                                          ),
                                        ),
                                        for (final task in group.tasks)
                                          _TaskCard(
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
                                          ),
                                      ],
                                    ],
                                  )
                                : ListView.builder(
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
            ),
          ],
        ),
        if (_canWrite && !widget.focusMode)
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
