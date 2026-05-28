import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:table_calendar/table_calendar.dart';

import '../../api/panel_api_extensions.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';
import '../../utils/calendar_recurrence.dart';
import '../../widgets/calendar_event_dialog.dart';

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

  String _occurrenceDate(WorkspaceEvent event) {
    if (event.occurrenceDate != null) return event.occurrenceDate!;
    final start = event.startAtDate;
    if (start == null) return '';
    return '${start.year}-${start.month.toString().padLeft(2, '0')}-${start.day.toString().padLeft(2, '0')}';
  }

  Future<void> _showEventForm({WorkspaceEvent? event}) async {
    final master = event == null ? null : _resolveMasterEvent(event);
    final occurrence = event?.seriesId != null || event?.occurrenceDate != null ? event : null;

    final result = await showCalendarEventDialog(
      context: context,
      master: master,
      occurrence: occurrence,
    );
    if (result == null) return;

    final api = context.read<AuthSession>().api;
    final start = occurrence?.startAtDate ?? master?.startAtDate ?? _selectedDay ?? DateTime.now();
    final end = occurrence?.endAtDate ?? master?.endAtDate ?? start.add(const Duration(hours: 1));

    if (master != null) {
      final fields = <String, dynamic>{
        'title': result.title,
        'description': result.description ?? '',
        'start_at': start.toIso8601String(),
        'end_at': end.toIso8601String(),
        'recurrence': result.recurrence,
        'recurrence_weekdays': result.recurrence == 'weekly' ? result.recurrenceWeekdays : null,
        'recurrence_until': result.recurrenceUntil,
        'reminder_minutes': result.reminderMinutes,
      };
      if (result.editScope == 'occurrence' && occurrence != null) {
        fields['edit_scope'] = 'occurrence';
        fields['occurrence_date'] = _occurrenceDate(occurrence);
      }
      await api.updateEvent(projectSlug: _slug, eventId: master.id, fields: fields);
    } else {
      await api.createEvent(
        projectSlug: _slug,
        title: result.title,
        startAt: start.toIso8601String(),
        endAt: end.toIso8601String(),
        description: result.description,
        recurrence: result.recurrence,
        recurrenceWeekdays: result.recurrenceWeekdays,
        recurrenceUntil: result.recurrenceUntil,
        reminderMinutes: result.reminderMinutes,
      );
    }
    await widget.onChanged();
  }

  Future<void> _deleteEvent(WorkspaceEvent event) async {
    final master = _resolveMasterEvent(event);
    final occurrence = event.seriesId != null || event.occurrenceDate != null ? event : null;

    final result = await showCalendarEventDialog(
      context: context,
      master: master,
      occurrence: occurrence,
      deleteMode: true,
    );
    if (result == null) return;

    await context.read<AuthSession>().api.deleteEvent(
          projectSlug: _slug,
          eventId: master.id,
          deleteScope: result.deleteScope,
          occurrenceDate: result.deleteScope == 'occurrence' && occurrence != null
              ? _occurrenceDate(occurrence)
              : null,
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
                              if (event.reminderMinutes != null)
                                'Rappel ${event.reminderMinutes} min',
                              if (event.description?.isNotEmpty == true)
                                event.description!,
                            ].join(' · '),
                          ),
                          onTap: _canWrite ? () => _showEventForm(event: event) : null,
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
