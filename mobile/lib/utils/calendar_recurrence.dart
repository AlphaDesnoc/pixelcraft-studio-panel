import '../models/workspace.dart';

DateTime _startOfDay(DateTime date) =>
    DateTime(date.year, date.month, date.day);

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

    final occurrenceStart = _occurrenceDateTime(seriesStart, day);
    final duration = seriesEnd.difference(seriesStart);
    results.add(
      WorkspaceEvent(
        id: event.id,
        title: event.title,
        description: event.description,
        startAt: occurrenceStart.toIso8601String(),
        endAt: occurrenceStart.add(duration).toIso8601String(),
        allDay: event.allDay,
        color: event.color,
        recurrence: event.recurrence,
        recurrenceWeekdays: event.recurrenceWeekdays,
        recurrenceUntil: event.recurrenceUntil,
        seriesId: event.id,
      ),
    );
  }

  return results;
}
