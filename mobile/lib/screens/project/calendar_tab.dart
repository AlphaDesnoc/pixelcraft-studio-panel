import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:table_calendar/table_calendar.dart';

import '../../utils/calendar_recurrence.dart';
import '../../api/panel_api_extensions.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';

class CalendarTab extends StatefulWidget {
  const CalendarTab({
    super.key,
    required this.workspace,
    required this.onChanged,
  });

  final ProjectWorkspace workspace;
  final Future<void> Function() onChanged;

  @override
  State<CalendarTab> createState() => _CalendarTabState();
}

class _CalendarTabState extends State<CalendarTab> {
  DateTime _focusedDay = DateTime.now();
  DateTime? _selectedDay;
  CalendarFormat _format = CalendarFormat.month;

  bool get _canWrite => widget.workspace.canWrite('calendar');
  String get _slug => widget.workspace.project.slug;

  DateTime? _parseEventDate(String? iso) {
    if (iso == null || iso.isEmpty) return null;
    return DateTime.tryParse(iso)?.toLocal();
  }

  List<WorkspaceEvent> _eventsForDay(DateTime day) {
    return expandEventsForDay(widget.workspace.events, day);
  }

  WorkspaceEvent _resolveMasterEvent(WorkspaceEvent event) {
    final masterId = event.seriesId ?? event.id;
    for (final candidate in widget.workspace.events) {
      if (candidate.id == masterId) {
        return candidate;
      }
    }
    return event;
  }

  Future<void> _showEventForm({WorkspaceEvent? event}) async {
    final master = event == null ? null : _resolveMasterEvent(event);
    final isEdit = master != null;
    final titleController = TextEditingController(text: master?.title ?? '');
    final descriptionController = TextEditingController(text: master?.description ?? '');
    final start = _parseEventDate(master?.startAt) ?? _selectedDay ?? DateTime.now();
    final end = _parseEventDate(master?.endAt) ?? start.add(const Duration(hours: 1));

    final saved = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(isEdit ? 'Modifier l\'événement' : 'Nouvel événement'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: titleController,
              decoration: const InputDecoration(labelText: 'Titre'),
            ),
            TextField(
              controller: descriptionController,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Description'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Enregistrer')),
        ],
      ),
    );

    if (saved != true || titleController.text.trim().isEmpty) return;

    final api = context.read<AuthSession>().api;
    if (master != null) {
      await api.updateEvent(
        projectSlug: _slug,
        eventId: master.id,
        fields: {
          'title': titleController.text.trim(),
          'description': descriptionController.text.trim(),
          'start_at': start.toIso8601String(),
          'end_at': end.toIso8601String(),
        },
      );
    } else {
      await api.createEvent(
        projectSlug: _slug,
        title: titleController.text.trim(),
        startAt: start.toIso8601String(),
        endAt: end.toIso8601String(),
        description: descriptionController.text.trim(),
      );
    }
    await widget.onChanged();
  }

  Future<void> _deleteEvent(WorkspaceEvent event) async {
    final master = _resolveMasterEvent(event);
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer l\'événement ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (ok != true) return;

    await context.read<AuthSession>().api.deleteEvent(
          projectSlug: _slug,
          eventId: master.id,
        );
    await widget.onChanged();
  }

  Color _parseColor(String hex) {
    final value = hex.replaceFirst('#', '');
    if (value.length == 6) {
      return Color(int.parse('FF$value', radix: 16));
    }
    return Theme.of(context).colorScheme.primary;
  }

  @override
  Widget build(BuildContext context) {
    final selected = _selectedDay ?? _focusedDay;
    final dayEvents = _eventsForDay(selected);

    return Column(
      children: [
        TableCalendar<WorkspaceEvent>(
          firstDay: DateTime.utc(2020),
          lastDay: DateTime.utc(2100),
          focusedDay: _focusedDay,
          selectedDayPredicate: (day) => isSameDay(_selectedDay, day),
          calendarFormat: _format,
          eventLoader: _eventsForDay,
          onDaySelected: (selectedDay, focusedDay) {
            setState(() {
              _selectedDay = selectedDay;
              _focusedDay = focusedDay;
            });
          },
          onFormatChanged: (format) => setState(() => _format = format),
          onPageChanged: (focusedDay) => _focusedDay = focusedDay,
          calendarStyle: const CalendarStyle(markersMaxCount: 3),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  'Événements du ${selected.day}/${selected.month}/${selected.year}',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
              ),
              if (_canWrite)
                IconButton(
                  tooltip: 'Ajouter',
                  onPressed: () => _showEventForm(),
                  icon: const Icon(Icons.add),
                ),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: widget.onChanged,
            child: dayEvents.isEmpty
                ? ListView(
                    children: const [
                      SizedBox(height: 40),
                      Center(child: Text('Aucun événement ce jour')),
                    ],
                  )
                : ListView.separated(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    itemCount: dayEvents.length,
                    separatorBuilder: (context, index) => const SizedBox(height: 8),
                    itemBuilder: (context, index) {
                      final event = dayEvents[index];
                      return Card(
                        child: ListTile(
                          leading: CircleAvatar(
                            backgroundColor: _parseColor(event.color),
                            child: const Icon(Icons.event, size: 18),
                          ),
                          title: Text(event.title),
                          subtitle: Text(
                            [
                              if (event.recurrence != null || event.seriesId != null)
                                'Récurrent',
                              if (event.description?.isNotEmpty == true)
                                event.description!,
                            ].join(' · '),
                          ),
                          onTap: _canWrite
                              ? () => _showEventForm(event: event)
                              : null,
                          trailing: _canWrite
                              ? IconButton(
                                  icon: const Icon(Icons.delete_outline),
                                  onPressed: () => _deleteEvent(event),
                                )
                              : null,
                        ),
                      );
                    },
                  ),
          ),
        ),
      ],
    );
  }
}
