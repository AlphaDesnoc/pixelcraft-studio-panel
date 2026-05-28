import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/panel_api_extensions.dart';
import '../models/workspace.dart';
import '../services/auth_session.dart';
import '../utils/format.dart';
import 'chat_bubble.dart';
import 'media_preview_dialog.dart';

Future<void> showTaskDetailSheet({
  required BuildContext context,
  required ProjectWorkspace workspace,
  required KanbanTask task,
  required Future<void> Function() onChanged,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => TaskDetailSheet(
      workspace: workspace,
      task: task,
      onChanged: onChanged,
    ),
  );
}

class TaskDetailSheet extends StatefulWidget {
  const TaskDetailSheet({
    super.key,
    required this.workspace,
    required this.task,
    required this.onChanged,
  });

  final ProjectWorkspace workspace;
  final KanbanTask task;
  final Future<void> Function() onChanged;

  @override
  State<TaskDetailSheet> createState() => _TaskDetailSheetState();
}

class _TaskDetailSheetState extends State<TaskDetailSheet> {
  late TextEditingController _titleController;
  late TextEditingController _descriptionController;
  late String _priority;
  int? _assigneeId;
  DateTime? _dueDate;
  DateTime? _startDate;
  late Set<int> _selectedTagIds;
  late KanbanTask _task;
  String? _recurrenceRule;
  final _estimatedController = TextEditingController();
  final _loggedController = TextEditingController();
  bool _saving = false;
  final _commentController = TextEditingController();
  final _checklistNameController = TextEditingController();
  final _checklistItemController = TextEditingController();
  late Set<int> _selectedDependencyIds;
  bool _timerRunning = false;
  String? _timerStartedAt;
  int? _selectedTemplateId;

  bool get _canWrite => widget.workspace.canWrite('kanban');
  String get _slug => widget.workspace.project.slug;

  @override
  void initState() {
    super.initState();
    _task = widget.task;
    _titleController = TextEditingController(text: _task.title);
    _descriptionController = TextEditingController(text: _task.description ?? '');
    _priority = _task.priority;
    _assigneeId = _task.assigneeId;
    _dueDate = _parseDate(_task.dueDate);
    _startDate = _parseDate(_task.startDate);
    _selectedTagIds = _task.tags.map((t) => t.id).toSet();
    _recurrenceRule = _task.recurrenceRule;
    _estimatedController.text = _task.estimatedMinutes?.toString() ?? '';
    _loggedController.text = _task.loggedMinutes?.toString() ?? '';
    _selectedDependencyIds = _task.dependencyIds.toSet();
    unawaited(_loadTimerStatus());
  }

  Future<void> _loadTimerStatus() async {
    try {
      final status = await context.read<AuthSession>().api.fetchTaskTimerStatus(
            projectSlug: _slug,
            taskId: _task.id,
          );
      if (!mounted) return;
      setState(() {
        _timerRunning = status['running'] as bool? ?? false;
        final entry = status['entry'];
        _timerStartedAt = entry is Map ? entry['started_at'] as String? : null;
        final logged = status['logged_minutes'];
        if (logged is int) {
          _loggedController.text = logged.toString();
        }
      });
    } catch (_) {}
  }

  Future<void> _toggleTimer() async {
    final api = context.read<AuthSession>().api;
    _task = _timerRunning
        ? await api.stopTaskTimer(projectSlug: _slug, taskId: _task.id)
        : await api.startTaskTimer(projectSlug: _slug, taskId: _task.id);
    await _loadTimerStatus();
    await widget.onChanged();
    if (mounted) setState(() {});
  }

  Future<void> _snooze(String duration) async {
    _task = await context.read<AuthSession>().api.snoozeTask(
          projectSlug: _slug,
          taskId: _task.id,
          duration: duration,
        );
    await widget.onChanged();
    if (mounted) setState(() {});
  }

  Future<void> _pickReminder() async {
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime.now().add(const Duration(days: 1)),
      firstDate: DateTime.now(),
      lastDate: DateTime(2100),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: const TimeOfDay(hour: 9, minute: 0),
    );
    if (time == null || !mounted) return;
    final remindAt = DateTime(date.year, date.month, date.day, time.hour, time.minute);
    await context.read<AuthSession>().api.createTaskReminder(
          projectSlug: _slug,
          taskId: _task.id,
          remindAt: remindAt,
        );
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Rappel programmé')),
      );
    }
  }

  Future<void> _saveDependencies() async {
    if (!_canWrite) return;
    _task = await context.read<AuthSession>().api.updateTaskDependencies(
          projectSlug: _slug,
          taskId: _task.id,
          dependencyIds: _selectedDependencyIds.toList(),
        );
    setState(() {});
    await widget.onChanged();
  }

  Future<void> _applyTemplate() async {
    if (_selectedTemplateId == null) return;
    _task = await context.read<AuthSession>().api.applyTaskTemplate(
          projectSlug: _slug,
          taskId: _task.id,
          templateId: _selectedTemplateId!,
        );
    _titleController.text = _task.title;
    _descriptionController.text = _task.description ?? '';
    _priority = _task.priority;
    setState(() {});
    await widget.onChanged();
  }

  Future<void> _editDependencies() async {
    final options = _allProjectTasks.where((t) => t.id != _task.id).toList();
    final selected = Set<int>.from(_selectedDependencyIds);

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('Dépendances', style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 12),
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxHeight: 320),
                      child: ListView(
                        shrinkWrap: true,
                        children: options.map((task) {
                          final checked = selected.contains(task.id);
                          return CheckboxListTile(
                            value: checked,
                            title: Text(task.title),
                            onChanged: (value) {
                              setModalState(() {
                                if (value == true) {
                                  selected.add(task.id);
                                } else {
                                  selected.remove(task.id);
                                }
                              });
                            },
                          );
                        }).toList(),
                      ),
                    ),
                    FilledButton(
                      onPressed: () async {
                        setState(() => _selectedDependencyIds = selected);
                        Navigator.pop(context);
                        await _saveDependencies();
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

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _commentController.dispose();
    _checklistNameController.dispose();
    _checklistItemController.dispose();
    _estimatedController.dispose();
    _loggedController.dispose();
    super.dispose();
  }

  int? _parseMinutes(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty) return null;
    return int.tryParse(trimmed);
  }

  List<KanbanTask> get _allProjectTasks {
    final tasks = <KanbanTask>[];
    for (final list in widget.workspace.lists) {
      tasks.addAll(list.tasks);
    }
    return tasks;
  }

  String _dependencyLabel(int taskId) {
    for (final task in _allProjectTasks) {
      if (task.id == taskId) return task.title;
    }
    return 'Tâche #$taskId';
  }

  DateTime? _parseDate(String? iso) {
    if (iso == null || iso.isEmpty) return null;
    return DateTime.tryParse(iso)?.toLocal();
  }

  String? _formatDate(DateTime? date) {
    if (date == null) return null;
    return DateTime(date.year, date.month, date.day).toIso8601String().split('T').first;
  }

  Future<void> _saveFields() async {
    if (!_canWrite || _saving) return;
    setState(() => _saving = true);
    try {
      final api = context.read<AuthSession>().api;
      _task = await api.updateTask(
        projectSlug: _slug,
        taskId: _task.id,
        fields: {
          'title': _titleController.text.trim(),
          'description': _descriptionController.text.trim(),
          'priority': _priority,
          'assignee_id': _assigneeId,
          'due_date': _formatDate(_dueDate),
          'start_date': _formatDate(_startDate),
          'recurrence_rule': _recurrenceRule,
          'estimated_minutes': _parseMinutes(_estimatedController.text),
          'logged_minutes': _parseMinutes(_loggedController.text),
        },
      );
      await api.syncTaskTags(
        projectSlug: _slug,
        taskId: _task.id,
        tagIds: _selectedTagIds.toList(),
      );
      await widget.onChanged();
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _pickDate({required bool isDue}) async {
    final initial = isDue ? _dueDate : _startDate;
    final picked = await showDatePicker(
      context: context,
      initialDate: initial ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
    );
    if (picked == null) return;
    setState(() {
      if (isDue) {
        _dueDate = picked;
      } else {
        _startDate = picked;
      }
    });
    await _saveFields();
  }

  Future<void> _addComment() async {
    final body = _commentController.text.trim();
    if (body.isEmpty) return;
    final api = context.read<AuthSession>().api;
    final comment = await api.addTaskComment(
      projectSlug: _slug,
      taskId: _task.id,
      body: body,
    );
    _commentController.clear();
    setState(() => _task = _task.copyWith(comments: [..._task.comments, comment]));
    await widget.onChanged();
  }

  Future<void> _deleteComment(TaskComment comment) async {
    await context.read<AuthSession>().api.deleteTaskComment(
          projectSlug: _slug,
          taskId: _task.id,
          commentId: comment.id,
        );
    setState(() {
      _task = KanbanTask(
        id: _task.id,
        listId: _task.listId,
        title: _task.title,
        description: _task.description,
        priority: _task.priority,
        status: _task.status,
        dueDate: _task.dueDate,
        startDate: _task.startDate,
        isOverdue: _task.isOverdue,
        assigneeId: _task.assigneeId,
        position: _task.position,
        progress: _task.progress,
        archivedAt: _task.archivedAt,
        dependencyIds: _task.dependencyIds,
        isBlocked: _task.isBlocked,
        tags: _task.tags,
        checklists: _task.checklists,
        comments: _task.comments.where((c) => c.id != comment.id).toList(),
        attachments: _task.attachments,
        checklistProgress: _task.checklistProgress,
      );
    });
    await widget.onChanged();
  }

  Future<void> _uploadAttachment() async {
    final result = await FilePicker.platform.pickFiles(withData: false);
    if (result == null || result.files.isEmpty) return;
    final file = result.files.first;
    if (file.path == null) return;

    final attachment = await context.read<AuthSession>().api.uploadTaskAttachment(
          projectSlug: _slug,
          taskId: _task.id,
          filePath: file.path!,
          fileName: file.name,
        );
    setState(() {
      _task = KanbanTask(
        id: _task.id,
        listId: _task.listId,
        title: _task.title,
        description: _task.description,
        priority: _task.priority,
        status: _task.status,
        dueDate: _task.dueDate,
        startDate: _task.startDate,
        isOverdue: _task.isOverdue,
        assigneeId: _task.assigneeId,
        position: _task.position,
        progress: _task.progress,
        archivedAt: _task.archivedAt,
        dependencyIds: _task.dependencyIds,
        isBlocked: _task.isBlocked,
        tags: _task.tags,
        checklists: _task.checklists,
        comments: _task.comments,
        attachments: [..._task.attachments, attachment],
        checklistProgress: _task.checklistProgress,
      );
    });
    await widget.onChanged();
  }

  Future<void> _createChecklist() async {
    final name = _checklistNameController.text.trim();
    if (name.isEmpty) return;
    final api = context.read<AuthSession>().api;
    final checklist = await api.createChecklist(
      projectSlug: _slug,
      taskId: _task.id,
      name: name,
    );
    _checklistNameController.clear();
    setState(() {
      _task = KanbanTask(
        id: _task.id,
        listId: _task.listId,
        title: _task.title,
        description: _task.description,
        priority: _task.priority,
        status: _task.status,
        dueDate: _task.dueDate,
        startDate: _task.startDate,
        isOverdue: _task.isOverdue,
        assigneeId: _task.assigneeId,
        position: _task.position,
        progress: _task.progress,
        archivedAt: _task.archivedAt,
        dependencyIds: _task.dependencyIds,
        isBlocked: _task.isBlocked,
        tags: _task.tags,
        checklists: [..._task.checklists, checklist],
        comments: _task.comments,
        attachments: _task.attachments,
        checklistProgress: _task.checklistProgress,
      );
    });
    await widget.onChanged();
  }

  Future<void> _toggleChecklistItem(TaskChecklist checklist, TaskChecklistItem item) async {
    final updated = await context.read<AuthSession>().api.updateChecklistItem(
          projectSlug: _slug,
          taskId: _task.id,
          checklistId: checklist.id,
          itemId: item.id,
          fields: {'is_done': !item.isDone},
        );
    _replaceChecklistItem(checklist.id, updated);
    await widget.onChanged();
  }

  Future<void> _addChecklistItem(TaskChecklist checklist) async {
    final content = _checklistItemController.text.trim();
    if (content.isEmpty) return;
    final item = await context.read<AuthSession>().api.addChecklistItem(
          projectSlug: _slug,
          taskId: _task.id,
          checklistId: checklist.id,
          content: content,
        );
    _checklistItemController.clear();
    setState(() {
      final next = _task.checklists.map((cl) {
        if (cl.id != checklist.id) return cl;
        return TaskChecklist(id: cl.id, name: cl.name, items: [...cl.items, item]);
      }).toList();
      _task = KanbanTask(
        id: _task.id,
        listId: _task.listId,
        title: _task.title,
        description: _task.description,
        priority: _task.priority,
        status: _task.status,
        dueDate: _task.dueDate,
        startDate: _task.startDate,
        isOverdue: _task.isOverdue,
        assigneeId: _task.assigneeId,
        position: _task.position,
        progress: _task.progress,
        archivedAt: _task.archivedAt,
        dependencyIds: _task.dependencyIds,
        isBlocked: _task.isBlocked,
        tags: _task.tags,
        checklists: next,
        comments: _task.comments,
        attachments: _task.attachments,
        checklistProgress: _task.checklistProgress,
      );
    });
    await widget.onChanged();
  }

  Future<void> _deleteChecklistItem(TaskChecklist checklist, TaskChecklistItem item) async {
    await context.read<AuthSession>().api.deleteChecklistItem(
          projectSlug: _slug,
          taskId: _task.id,
          checklistId: checklist.id,
          itemId: item.id,
        );
    setState(() {
      final next = _task.checklists.map((cl) {
        if (cl.id != checklist.id) return cl;
        return TaskChecklist(
          id: cl.id,
          name: cl.name,
          items: cl.items.where((i) => i.id != item.id).toList(),
        );
      }).toList();
      _task = KanbanTask(
        id: _task.id,
        listId: _task.listId,
        title: _task.title,
        description: _task.description,
        priority: _task.priority,
        status: _task.status,
        dueDate: _task.dueDate,
        startDate: _task.startDate,
        isOverdue: _task.isOverdue,
        assigneeId: _task.assigneeId,
        position: _task.position,
        progress: _task.progress,
        archivedAt: _task.archivedAt,
        dependencyIds: _task.dependencyIds,
        isBlocked: _task.isBlocked,
        tags: _task.tags,
        checklists: next,
        comments: _task.comments,
        attachments: _task.attachments,
        checklistProgress: _task.checklistProgress,
      );
    });
    await widget.onChanged();
  }

  Future<void> _reorderChecklistItems(
    TaskChecklist checklist,
    int oldIndex,
    int newIndex,
  ) async {
    if (oldIndex == newIndex) return;
    var targetIndex = newIndex;
    if (oldIndex < newIndex) targetIndex -= 1;

    final items = List<TaskChecklistItem>.from(checklist.items);
    final moved = items.removeAt(oldIndex);
    items.insert(targetIndex, moved);

    final api = context.read<AuthSession>().api;
    await api.reorderChecklistItems(
      projectSlug: _slug,
      taskId: _task.id,
      checklistId: checklist.id,
      order: items.map((item) => item.id).toList(),
    );

    setState(() {
      final next = _task.checklists.map((cl) {
        if (cl.id != checklist.id) return cl;
        return TaskChecklist(id: cl.id, name: cl.name, items: items);
      }).toList();
      _task = KanbanTask(
        id: _task.id,
        listId: _task.listId,
        title: _task.title,
        description: _task.description,
        priority: _task.priority,
        status: _task.status,
        dueDate: _task.dueDate,
        startDate: _task.startDate,
        isOverdue: _task.isOverdue,
        assigneeId: _task.assigneeId,
        position: _task.position,
        progress: _task.progress,
        archivedAt: _task.archivedAt,
        dependencyIds: _task.dependencyIds,
        isBlocked: _task.isBlocked,
        tags: _task.tags,
        checklists: next,
        comments: _task.comments,
        attachments: _task.attachments,
        checklistProgress: _task.checklistProgress,
      );
    });
  }

  void _replaceChecklistItem(int checklistId, TaskChecklistItem updated) {
    setState(() {
      final next = _task.checklists.map((cl) {
        if (cl.id != checklistId) return cl;
        return TaskChecklist(
          id: cl.id,
          name: cl.name,
          items: cl.items.map((i) => i.id == updated.id ? updated : i).toList(),
        );
      }).toList();
      _task = KanbanTask(
        id: _task.id,
        listId: _task.listId,
        title: _task.title,
        description: _task.description,
        priority: _task.priority,
        status: _task.status,
        dueDate: _task.dueDate,
        startDate: _task.startDate,
        isOverdue: _task.isOverdue,
        assigneeId: _task.assigneeId,
        position: _task.position,
        progress: _task.progress,
        archivedAt: _task.archivedAt,
        dependencyIds: _task.dependencyIds,
        isBlocked: _task.isBlocked,
        tags: _task.tags,
        checklists: next,
        comments: _task.comments,
        attachments: _task.attachments,
        checklistProgress: _task.checklistProgress,
      );
    });
  }

  Future<void> _runAction(String action) async {
    final api = context.read<AuthSession>().api;
    switch (action) {
      case 'duplicate':
        await api.duplicateTask(projectSlug: _slug, taskId: _task.id);
      case 'archive':
        await api.archiveTask(projectSlug: _slug, taskId: _task.id);
      case 'delete':
        final ok = await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Supprimer la tâche ?'),
            actions: [
              TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
              FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
            ],
          ),
        );
        if (ok != true) return;
        await api.deleteTask(projectSlug: _slug, taskId: _task.id);
        if (mounted) Navigator.pop(context);
      case 'move':
        final listId = await showModalBottomSheet<int>(
          context: context,
          builder: (context) => SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: widget.workspace.lists
                  .map(
                    (list) => ListTile(
                      title: Text(list.name),
                      onTap: () => Navigator.pop(context, list.id),
                    ),
                  )
                  .toList(),
            ),
          ),
        );
        if (listId == null) return;
        final target = widget.workspace.lists.firstWhere((l) => l.id == listId);
        await api.moveTask(
          projectSlug: _slug,
          taskId: _task.id,
          listId: listId,
          order: [...target.tasks.map((t) => t.id), _task.id],
        );
    }
    await widget.onChanged();
    if (mounted && action != 'delete') Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.92,
      minChildSize: 0.5,
      maxChildSize: 0.98,
      builder: (context, scrollController) {
        return Material(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Row(
                  children: [
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close),
                    ),
                    Expanded(
                      child: Text(
                        'Détail tâche',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                    ),
                    if (_canWrite)
                      PopupMenuButton<String>(
                        onSelected: _runAction,
                        itemBuilder: (context) => const [
                          PopupMenuItem(value: 'duplicate', child: Text('Dupliquer')),
                          PopupMenuItem(value: 'archive', child: Text('Archiver')),
                          PopupMenuItem(value: 'move', child: Text('Déplacer')),
                          PopupMenuItem(value: 'delete', child: Text('Supprimer')),
                        ],
                      ),
                    if (_saving)
                      const Padding(
                        padding: EdgeInsets.all(12),
                        child: SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      ),
                  ],
                ),
              ),
              const Divider(height: 1),
              Expanded(
                child: ListView(
                  controller: scrollController,
                  padding: const EdgeInsets.all(16),
                  children: [
                    TextField(
                      controller: _titleController,
                      readOnly: !_canWrite,
                      decoration: const InputDecoration(labelText: 'Titre'),
                      onSubmitted: (_) => _saveFields(),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _descriptionController,
                      readOnly: !_canWrite,
                      minLines: 2,
                      maxLines: 6,
                      decoration: const InputDecoration(labelText: 'Description'),
                      onSubmitted: (_) => _saveFields(),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      value: _priority,
                      decoration: const InputDecoration(labelText: 'Priorité'),
                      items: widget.workspace.priorities.entries
                          .map(
                            (e) => DropdownMenuItem(value: e.key, child: Text(e.value)),
                          )
                          .toList(),
                      onChanged: !_canWrite
                          ? null
                          : (value) async {
                              if (value == null) return;
                              setState(() => _priority = value);
                              await _saveFields();
                            },
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int?>(
                      value: _assigneeId,
                      decoration: const InputDecoration(labelText: 'Assigné à'),
                      items: [
                        const DropdownMenuItem<int?>(value: null, child: Text('Non assigné')),
                        ...widget.workspace.members.map(
                          (m) => DropdownMenuItem<int?>(value: m.id, child: Text(m.name)),
                        ),
                      ],
                      onChanged: !_canWrite
                          ? null
                          : (value) async {
                              setState(() => _assigneeId = value);
                              await _saveFields();
                            },
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: !_canWrite ? null : () => _pickDate(isDue: false),
                            icon: const Icon(Icons.play_arrow_outlined),
                            label: Text(_startDate != null ? 'Début ${_formatDate(_startDate)}' : 'Date début'),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: !_canWrite ? null : () => _pickDate(isDue: true),
                            icon: const Icon(Icons.event),
                            label: Text(_dueDate != null ? 'Échéance ${_formatDate(_dueDate)}' : 'Date échéance'),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String?>(
                      value: _recurrenceRule,
                      decoration: const InputDecoration(labelText: 'Récurrence'),
                      items: const [
                        DropdownMenuItem<String?>(value: null, child: Text('Aucune')),
                        DropdownMenuItem(value: 'weekly', child: Text('Hebdomadaire')),
                        DropdownMenuItem(value: 'monthly', child: Text('Mensuelle')),
                      ],
                      onChanged: !_canWrite
                          ? null
                          : (value) async {
                              setState(() => _recurrenceRule = value);
                              await _saveFields();
                            },
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: !_canWrite ? null : _toggleTimer,
                            icon: Icon(_timerRunning ? Icons.stop_circle_outlined : Icons.timer_outlined),
                            label: Text(_timerRunning ? 'Arrêter chrono' : 'Démarrer chrono'),
                          ),
                        ),
                        if (_timerRunning && _timerStartedAt != null)
                          Expanded(
                            child: Text(
                              'Depuis $_timerStartedAt',
                              style: Theme.of(context).textTheme.labelSmall,
                              textAlign: TextAlign.end,
                            ),
                          ),
                      ],
                    ),
                    if (_canWrite) ...[
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        children: [
                          OutlinedButton(
                            onPressed: () => _snooze('1d'),
                            child: const Text('Reporter 1j'),
                          ),
                          OutlinedButton(
                            onPressed: () => _snooze('1w'),
                            child: const Text('Reporter 1 sem.'),
                          ),
                          OutlinedButton.icon(
                            onPressed: _pickReminder,
                            icon: const Icon(Icons.notifications_outlined, size: 18),
                            label: const Text('Rappel'),
                          ),
                        ],
                      ),
                    ],
                    if (widget.workspace.taskTemplates.isNotEmpty && _canWrite) ...[
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: DropdownButtonFormField<int?>(
                              value: _selectedTemplateId,
                              decoration: const InputDecoration(labelText: 'Modèle de tâche'),
                              items: [
                                const DropdownMenuItem<int?>(value: null, child: Text('Choisir…')),
                                ...widget.workspace.taskTemplates.map(
                                  (t) => DropdownMenuItem<int?>(
                                    value: t['id'] as int?,
                                    child: Text(t['name'] as String? ?? t['title'] as String? ?? 'Modèle'),
                                  ),
                                ),
                              ],
                              onChanged: (value) => setState(() => _selectedTemplateId = value),
                            ),
                          ),
                          IconButton(
                            tooltip: 'Appliquer le modèle',
                            onPressed: _selectedTemplateId == null ? null : _applyTemplate,
                            icon: const Icon(Icons.layers_outlined),
                          ),
                        ],
                      ),
                    ],
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _estimatedController,
                            readOnly: !_canWrite,
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(labelText: 'Temps estimé (min)'),
                            onSubmitted: (_) => _saveFields(),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: TextField(
                            controller: _loggedController,
                            readOnly: !_canWrite,
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(labelText: 'Temps loggé (min)'),
                            onSubmitted: (_) => _saveFields(),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: Text('Dépendances', style: Theme.of(context).textTheme.titleSmall),
                        ),
                        if (_canWrite)
                          TextButton(
                            onPressed: _editDependencies,
                            child: Text(
                              _selectedDependencyIds.isEmpty ? 'Ajouter' : 'Modifier',
                            ),
                          ),
                      ],
                    ),
                    if (_task.isBlocked)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          'Bloquée — dépendances non terminées',
                          style: TextStyle(color: Theme.of(context).colorScheme.error),
                        ),
                      ),
                    if (_selectedDependencyIds.isEmpty)
                      Text(
                        'Aucune dépendance',
                        style: Theme.of(context).textTheme.bodySmall,
                      )
                    else
                      ..._selectedDependencyIds.map(
                        (id) => ListTile(
                          contentPadding: EdgeInsets.zero,
                          dense: true,
                          leading: const Icon(Icons.link, size: 18),
                          title: Text(_dependencyLabel(id)),
                        ),
                      ),
                    if (_canWrite) ...[
                      const SizedBox(height: 12),
                      FilledButton(onPressed: _saveFields, child: const Text('Enregistrer')),
                    ],
                    const SizedBox(height: 20),
                    Text('Tags', style: Theme.of(context).textTheme.titleSmall),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: widget.workspace.tags.map((tag) {
                        final selected = _selectedTagIds.contains(tag.id);
                        return FilterChip(
                          label: Text(tag.name),
                          selected: selected,
                          onSelected: !_canWrite
                              ? null
                              : (value) async {
                                  setState(() {
                                    if (value) {
                                      _selectedTagIds.add(tag.id);
                                    } else {
                                      _selectedTagIds.remove(tag.id);
                                    }
                                  });
                                  await _saveFields();
                                },
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 20),
                    Text('Checklists', style: Theme.of(context).textTheme.titleSmall),
                    ..._task.checklists.map(
                      (checklist) => Card(
                        margin: const EdgeInsets.only(top: 8),
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(checklist.name, style: Theme.of(context).textTheme.labelLarge),
                              if (_canWrite)
                                ReorderableListView(
                                  shrinkWrap: true,
                                  physics: const NeverScrollableScrollPhysics(),
                                  onReorder: (oldIndex, newIndex) =>
                                      _reorderChecklistItems(checklist, oldIndex, newIndex),
                                  children: [
                                    for (final item in checklist.items)
                                      CheckboxListTile(
                                        key: ValueKey(item.id),
                                        contentPadding: EdgeInsets.zero,
                                        value: item.isDone,
                                        onChanged: (_) => _toggleChecklistItem(checklist, item),
                                        title: Text(item.content),
                                        secondary: IconButton(
                                          icon: const Icon(Icons.delete_outline, size: 18),
                                          onPressed: () => _deleteChecklistItem(checklist, item),
                                        ),
                                      ),
                                  ],
                                )
                              else
                                ...checklist.items.map(
                                  (item) => CheckboxListTile(
                                    contentPadding: EdgeInsets.zero,
                                    value: item.isDone,
                                    onChanged: null,
                                    title: Text(item.content),
                                  ),
                                ),
                              if (_canWrite)
                                Row(
                                  children: [
                                    Expanded(
                                      child: TextField(
                                        controller: _checklistItemController,
                                        decoration: InputDecoration(
                                          hintText: 'Nouvel élément (${checklist.name})',
                                          isDense: true,
                                        ),
                                        onSubmitted: (_) => _addChecklistItem(checklist),
                                      ),
                                    ),
                                    IconButton(
                                      icon: const Icon(Icons.add),
                                      onPressed: () => _addChecklistItem(checklist),
                                    ),
                                  ],
                                ),
                            ],
                          ),
                        ),
                      ),
                    ),
                    if (_canWrite) ...[
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Expanded(
                            child: TextField(
                              controller: _checklistNameController,
                              decoration: const InputDecoration(
                                hintText: 'Nom checklist',
                                isDense: true,
                              ),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.playlist_add),
                            onPressed: _createChecklist,
                          ),
                        ],
                      ),
                    ],
                    const SizedBox(height: 20),
                    Text('Commentaires', style: Theme.of(context).textTheme.titleSmall),
                    ..._task.comments.map(
                      (comment) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(comment.body),
                        subtitle: Text(
                          [
                            if (comment.userName != null) comment.userName,
                            if (comment.createdAt != null) formatRelativeTime(comment.createdAt),
                          ].whereType<String>().join(' · '),
                        ),
                        trailing: _canWrite
                            ? IconButton(
                                icon: const Icon(Icons.delete_outline),
                                onPressed: () => _deleteComment(comment),
                              )
                            : null,
                      ),
                    ),
                    if (_canWrite)
                      Row(
                        children: [
                          Expanded(
                            child: TextField(
                              controller: _commentController,
                              decoration: const InputDecoration(hintText: 'Ajouter un commentaire…'),
                              onSubmitted: (_) => _addComment(),
                            ),
                          ),
                          IconButton(icon: const Icon(Icons.send), onPressed: _addComment),
                        ],
                      ),
                    const SizedBox(height: 20),
                    Text('Pièces jointes', style: Theme.of(context).textTheme.titleSmall),
                    ..._task.attachments.map(
                      (a) {
                        final url = chatAttachmentUrl(a.url);
                        return ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: chatIsImageAttachment(a)
                              ? ClipRRect(
                                  borderRadius: BorderRadius.circular(6),
                                  child: Image.network(
                                    url,
                                    width: 40,
                                    height: 40,
                                    fit: BoxFit.cover,
                                    errorBuilder: (context, error, stackTrace) =>
                                        const Icon(Icons.image),
                                  ),
                                )
                              : Icon(
                                  MediaPreviewDialog.isPreviewable(a.mimeType, a.originalName)
                                      ? Icons.play_circle_outline
                                      : Icons.attach_file,
                                ),
                          title: Text(a.originalName),
                          subtitle: Text('${a.size} o'),
                          onTap: url.isEmpty ||
                                  !MediaPreviewDialog.isPreviewable(a.mimeType, a.originalName)
                              ? null
                              : () async {
                                  final token =
                                      await context.read<AuthSession>().api.client.readToken();
                                  if (!context.mounted) return;
                                  await MediaPreviewDialog.show(
                                    context,
                                    url: url,
                                    name: a.originalName,
                                    mimeType: a.mimeType,
                                    token: token,
                                  );
                                },
                        );
                      },
                    ),
                    if (_canWrite)
                      OutlinedButton.icon(
                        onPressed: _uploadAttachment,
                        icon: const Icon(Icons.upload_file),
                        label: const Text('Ajouter un fichier'),
                      ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
