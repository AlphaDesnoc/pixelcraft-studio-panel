import 'package:flutter/material.dart';

import '../../models/workspace.dart';

class GanttTab extends StatefulWidget {
  const GanttTab({super.key, required this.workspace});

  final ProjectWorkspace workspace;

  @override
  State<GanttTab> createState() => _GanttTabState();
}

class _GanttTabState extends State<GanttTab> {
  static const _dayWidth = 32.0;
  static const _rowHeight = 48.0;
  static const _labelWidth = 150.0;

  final ScrollController _hBody = ScrollController();
  final ScrollController _hHeader = ScrollController();
  final ScrollController _vBody = ScrollController();
  final ScrollController _vLabels = ScrollController();

  bool _syncingH = false;
  bool _syncingV = false;

  @override
  void initState() {
    super.initState();
    _hBody.addListener(_syncHeaderFromBody);
    _vBody.addListener(_syncLabelsFromBody);
  }

  void _syncHeaderFromBody() {
    if (_syncingH) return;
    _syncingH = true;
    _hHeader.jumpTo(_hBody.offset);
    _syncingH = false;
  }

  void _syncLabelsFromBody() {
    if (_syncingV) return;
    _syncingV = true;
    _vLabels.jumpTo(_vBody.offset);
    _syncingV = false;
  }

  @override
  void dispose() {
    _hBody.dispose();
    _hHeader.dispose();
    _vBody.dispose();
    _vLabels.dispose();
    super.dispose();
  }

  List<KanbanTask> get _tasks {
    final tasks = <KanbanTask>[];
    for (final list in widget.workspace.lists) {
      for (final task in list.tasks) {
        if (task.archivedAt != null) continue;
        if (task.startDate != null || task.dueDate != null) {
          tasks.add(task);
        }
      }
    }
    tasks.sort((a, b) {
      final aDate = _taskStart(a);
      final bDate = _taskStart(b);
      return aDate.compareTo(bDate);
    });
    return tasks;
  }

  DateTime _parse(String? iso) {
    if (iso == null || iso.isEmpty) return DateTime.now();
    return DateTime.tryParse(iso)?.toLocal() ?? DateTime.now();
  }

  DateTime _taskStart(KanbanTask task) {
    final start = task.startDate != null ? _parse(task.startDate) : _parse(task.dueDate);
    return DateTime(start.year, start.month, start.day);
  }

  DateTime _taskEnd(KanbanTask task) {
    final end = task.dueDate != null ? _parse(task.dueDate) : _taskStart(task);
    return DateTime(end.year, end.month, end.day);
  }

  (DateTime start, DateTime end, int totalDays) _range(List<KanbanTask> tasks) {
    var start = _taskStart(tasks.first);
    var end = _taskEnd(tasks.first);
    for (final task in tasks) {
      final s = _taskStart(task);
      final e = _taskEnd(task);
      if (s.isBefore(start)) start = s;
      if (e.isAfter(end)) end = e;
    }
    start = start.subtract(const Duration(days: 2));
    end = end.add(const Duration(days: 5));
    final days = end.difference(start).inDays + 1;
    return (start, end, days);
  }

  String _listName(int listId) {
    for (final list in widget.workspace.lists) {
      if (list.id == listId) return list.name;
    }
    return '';
  }

  Color _barColor(KanbanTask task) {
    if (task.isBlocked) return Colors.red.shade400;
    if (task.isOverdue) return Colors.orange.shade600;
    return Theme.of(context).colorScheme.primary;
  }

  @override
  Widget build(BuildContext context) {
    final tasks = _tasks;
    if (tasks.isEmpty) {
      return const Center(child: Text('Aucune tâche planifiée'));
    }

    final (rangeStart, _, totalDays) = _range(tasks);
    final chartWidth = totalDays * _dayWidth;
    final chartHeight = tasks.length * _rowHeight;
    final today = DateTime.now();
    final todayIndex = DateTime(today.year, today.month, today.day)
        .difference(rangeStart)
        .inDays;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
          child: Text(
            '${tasks.length} tâches · glissez horizontalement',
            style: Theme.of(context).textTheme.labelMedium,
          ),
        ),
        SizedBox(
          height: 36,
          child: Row(
            children: [
              SizedBox(
                width: _labelWidth,
                child: const Center(child: Text('Tâche', style: TextStyle(fontWeight: FontWeight.w600))),
              ),
              Expanded(
                child: SingleChildScrollView(
                  controller: _hHeader,
                  scrollDirection: Axis.horizontal,
                  physics: const NeverScrollableScrollPhysics(),
                  child: SizedBox(
                    width: chartWidth,
                    child: Row(
                      children: List.generate(totalDays, (index) {
                        final day = rangeStart.add(Duration(days: index));
                        final isWeekend = day.weekday == DateTime.saturday || day.weekday == DateTime.sunday;
                        final isToday = index == todayIndex;
                        return Container(
                          width: _dayWidth,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            border: Border(
                              right: BorderSide(color: Theme.of(context).dividerColor),
                            ),
                            color: isToday
                                ? Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.35)
                                : isWeekend
                                    ? Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.4)
                                    : null,
                          ),
                          child: Text(
                            '${day.day}/${day.month}',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: isToday ? FontWeight.bold : FontWeight.normal,
                            ),
                          ),
                        );
                      }),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
        const Divider(height: 1),
        Expanded(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: _labelWidth,
                child: ListView.builder(
                  controller: _vLabels,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: tasks.length,
                  itemBuilder: (context, index) {
                    final task = tasks[index];
                    return SizedBox(
                      height: _rowHeight,
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(task.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.labelLarge),
                            Text(_listName(task.listId), maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.labelSmall),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
              Expanded(
                child: SingleChildScrollView(
                  controller: _hBody,
                  scrollDirection: Axis.horizontal,
                  child: SingleChildScrollView(
                    controller: _vBody,
                    child: SizedBox(
                      width: chartWidth,
                      height: chartHeight,
                      child: Stack(
                        children: [
                          ...List.generate(totalDays, (index) {
                            final day = rangeStart.add(Duration(days: index));
                            final isWeekend = day.weekday == DateTime.saturday || day.weekday == DateTime.sunday;
                            return Positioned(
                              left: index * _dayWidth,
                              top: 0,
                              bottom: 0,
                              width: _dayWidth,
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  border: Border(
                                    right: BorderSide(color: Theme.of(context).dividerColor.withValues(alpha: 0.5)),
                                  ),
                                  color: isWeekend
                                      ? Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.25)
                                      : null,
                                ),
                              ),
                            );
                          }),
                          if (todayIndex >= 0 && todayIndex < totalDays)
                            Positioned(
                              left: todayIndex * _dayWidth + _dayWidth / 2 - 1,
                              top: 0,
                              bottom: 0,
                              width: 2,
                              child: ColoredBox(color: Theme.of(context).colorScheme.error.withValues(alpha: 0.7)),
                            ),
                          ...List.generate(tasks.length, (index) {
                            final task = tasks[index];
                            final start = _taskStart(task);
                            final end = _taskEnd(task);
                            final leftDays = start.difference(rangeStart).inDays;
                            final spanDays = end.difference(start).inDays + 1;
                            return Positioned(
                              left: leftDays * _dayWidth + 2,
                              top: index * _rowHeight + 10,
                              width: (spanDays * _dayWidth - 4).clamp(8, chartWidth),
                              height: _rowHeight - 20,
                              child: Tooltip(
                                message: '${task.title}\n${task.startDate ?? '—'} → ${task.dueDate ?? '—'}',
                                child: Material(
                                  color: _barColor(task),
                                  borderRadius: BorderRadius.circular(6),
                                  child: Padding(
                                    padding: const EdgeInsets.symmetric(horizontal: 6),
                                    child: Align(
                                      alignment: Alignment.centerLeft,
                                      child: Text(
                                        task.title,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(color: Colors.white, fontSize: 11),
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            );
                          }),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
