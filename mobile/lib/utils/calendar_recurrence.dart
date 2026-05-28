import '../models/workspace.dart';

DateTime _startOfDay(DateTime date) =>
    DateTime(date.year, date.month, date.day);

String _dateKey(DateTime date) {
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year}-$month-$day';
}

WorkspaceEventException? _exceptionForDay(
  List<WorkspaceEventException> exceptions,
  DateTime day,
) {
  final key = _dateKey(day);
  for (final exception in exceptions) {
    if (exception.occurrenceDate == key) {
      return exception;
    }
  }
  return null;
}

bool _shouldOccurOnDay(
  DateTime day,
  WorkspaceEvent event,
  DateTime seriesStart,
) {
  final dayStart = _startOfDay(day);
  final seriesStartDay = _startOfDay(seriesStart);
  if (dayStart.isBefore(seriesStartDay)) {
    return false;
  }

  switch (event.recurrence) {
    case 'daily':
      return true;
    case 'weekly':
      final weekdays = event.recurrenceWeekdays.isNotEmpty
          ? event.recurrenceWeekdays
          : [seriesStart.weekday % 7];
      return weekdays.contains(dayStart.weekday % 7);
    case 'monthly':
      return dayStart.day == seriesStartDay.day;
    default:
      return false;
  }
}

DateTime _occurrenceDateTime(DateTime originalStart, DateTime dayDate) {
  final day = _startOfDay(dayDate);
  return DateTime(
    day.year,
    day.month,
    day.day,
    originalStart.hour,
    originalStart.minute,
    originalStart.second,
    originalStart.millisecond,
    originalStart.microsecond,
  );
}

List<WorkspaceEvent> expandEventsForDay(
  List<WorkspaceEvent> events,
  DateTime day,
) {
  final results = <WorkspaceEvent>[];

  for (final event in events) {
    if (event.recurrence == null) {
      final start = event.startAtDate;
      if (start == null) continue;
      if (start.year == day.year &&
          start.month == day.month &&
          start.day == day.day) {
        results.add(event);
      }
      continue;
    }

    final seriesStart = event.startAtDate;
    final seriesEnd = event.endAtDate;
    if (seriesStart == null || seriesEnd == null) continue;

    final until = event.recurrenceUntilDate ??
        DateTime(seriesStart.year + 2, seriesStart.month, seriesStart.day);
    final dayStart = _startOfDay(day);
    if (dayStart.isAfter(_startOfDay(until))) continue;

    if (!_shouldOccurOnDay(day, event, seriesStart)) continue;

    final dateKey = _dateKey(day);
    final exception = _exceptionForDay(event.exceptions, day);
    if (exception?.type == 'cancelled') continue;

    var occurrenceStart = _occurrenceDateTime(seriesStart, day);
    var duration = seriesEnd.difference(seriesStart);
    var occurrenceEnd = occurrenceStart.add(duration);
    var title = event.title;
    var description = event.description;
    var color = event.color;
    var allDay = event.allDay;

    if (exception?.type == 'modified') {
      if (exception!.startAt != null) {
        occurrenceStart = DateTime.parse(exception.startAt!).toLocal();
      }
      if (exception.endAt != null) {
        occurrenceEnd = DateTime.parse(exception.endAt!).toLocal();
      }
      if (exception.title != null) title = exception.title!;
      if (exception.description != null) description = exception.description;
      if (exception.color != null) color = exception.color!;
      if (exception.allDay != null) allDay = exception.allDay!;
    }

    results.add(
      WorkspaceEvent(
        id: event.id,
        title: title,
        description: description,
        startAt: occurrenceStart.toIso8601String(),
        endAt: occurrenceEnd.toIso8601String(),
        allDay: allDay,
        color: color,
        recurrence: event.recurrence,
        recurrenceWeekdays: event.recurrenceWeekdays,
        recurrenceUntil: event.recurrenceUntil,
        seriesId: event.id,
        occurrenceDate: dateKey,
        reminderMinutes: event.reminderMinutes,
        exceptions: event.exceptions,
      ),
    );
  }

  return results;
}
