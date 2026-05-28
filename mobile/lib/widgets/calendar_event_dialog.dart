import 'package:flutter/material.dart';

import '../models/workspace.dart';

class CalendarEventFormResult {
  const CalendarEventFormResult({
    required this.title,
    this.description,
    this.recurrence,
    this.recurrenceWeekdays = const [],
    this.recurrenceUntil,
    this.reminderMinutes,
    this.editScope = 'series',
    this.deleteScope,
  });

  final String title;
  final String? description;
  final String? recurrence;
  final List<int> recurrenceWeekdays;
  final String? recurrenceUntil;
  final int? reminderMinutes;
  final String editScope;
  final String? deleteScope;
}

Future<CalendarEventFormResult?> showCalendarEventDialog({
  required BuildContext context,
  WorkspaceEvent? master,
  WorkspaceEvent? occurrence,
  bool deleteMode = false,
}) {
  final isEdit = master != null;
  final isOccurrence = occurrence?.occurrenceDate != null && master?.recurrence != null;

  final titleController = TextEditingController(
    text: (occurrence ?? master)?.title ?? '',
  );
  final descriptionController = TextEditingController(
    text: (occurrence ?? master)?.description ?? '',
  );

  String? recurrence = master?.recurrence;
  var weekdays = List<int>.from(master?.recurrenceWeekdays ?? []);
  String? recurrenceUntil = master?.recurrenceUntil;
  int? reminderMinutes = master?.reminderMinutes;
  var editScope = isOccurrence ? 'occurrence' : 'series';
  var deleteScope = 'occurrence';

  const weekdayLabels = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];

  return showDialog<CalendarEventFormResult>(
    context: context,
    builder: (context) {
      return StatefulBuilder(
        builder: (context, setState) {
          return AlertDialog(
            title: Text(
              deleteMode
                  ? 'Supprimer l\'événement'
                  : isEdit
                      ? 'Modifier l\'événement'
                      : 'Nouvel événement',
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (deleteMode && isOccurrence) ...[
                    RadioListTile<String>(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Cette occurrence'),
                      value: 'occurrence',
                      groupValue: deleteScope,
                      onChanged: (value) => setState(() => deleteScope = value!),
                    ),
                    RadioListTile<String>(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Toute la série'),
                      value: 'series',
                      groupValue: deleteScope,
                      onChanged: (value) => setState(() => deleteScope = value!),
                    ),
                  ] else if (!deleteMode) ...[
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
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String?>(
                      value: recurrence,
                      decoration: const InputDecoration(labelText: 'Répétition'),
                      items: const [
                        DropdownMenuItem(value: null, child: Text('Aucune')),
                        DropdownMenuItem(value: 'daily', child: Text('Quotidienne')),
                        DropdownMenuItem(value: 'weekly', child: Text('Hebdomadaire')),
                        DropdownMenuItem(value: 'monthly', child: Text('Mensuelle')),
                      ],
                      onChanged: (value) => setState(() => recurrence = value),
                    ),
                    if (recurrence == 'weekly') ...[
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 4,
                        children: List.generate(7, (index) {
                          final selected = weekdays.contains(index);
                          return FilterChip(
                            label: Text(weekdayLabels[index]),
                            selected: selected,
                            onSelected: (value) {
                              setState(() {
                                if (value) {
                                  weekdays = [...weekdays, index]..sort();
                                } else if (weekdays.length > 1) {
                                  weekdays = weekdays.where((d) => d != index).toList();
                                }
                              });
                            },
                          );
                        }),
                      ),
                    ],
                    if (recurrence != null)
                      TextField(
                        decoration: const InputDecoration(
                          labelText: 'Répéter jusqu\'au (AAAA-MM-JJ)',
                        ),
                        controller: TextEditingController(text: recurrenceUntil ?? ''),
                        onChanged: (value) => recurrenceUntil = value.trim().isEmpty ? null : value.trim(),
                      ),
                    DropdownButtonFormField<int?>(
                      value: reminderMinutes,
                      decoration: const InputDecoration(labelText: 'Rappel'),
                      items: const [
                        DropdownMenuItem(value: null, child: Text('Aucun')),
                        DropdownMenuItem(value: 15, child: Text('15 min avant')),
                        DropdownMenuItem(value: 30, child: Text('30 min avant')),
                        DropdownMenuItem(value: 60, child: Text('1 h avant')),
                        DropdownMenuItem(value: 1440, child: Text('1 jour avant')),
                      ],
                      onChanged: (value) => setState(() => reminderMinutes = value),
                    ),
                    if (isOccurrence) ...[
                      const SizedBox(height: 8),
                      RadioListTile<String>(
                        contentPadding: EdgeInsets.zero,
                        title: const Text('Cette occurrence'),
                        value: 'occurrence',
                        groupValue: editScope,
                        onChanged: (value) => setState(() => editScope = value!),
                      ),
                      RadioListTile<String>(
                        contentPadding: EdgeInsets.zero,
                        title: const Text('Toute la série'),
                        value: 'series',
                        groupValue: editScope,
                        onChanged: (value) => setState(() => editScope = value!),
                      ),
                    ],
                  ],
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Annuler'),
              ),
              FilledButton(
                onPressed: () {
                  if (deleteMode) {
                    Navigator.pop(
                      context,
                      CalendarEventFormResult(
                        title: master!.title,
                        deleteScope: deleteScope,
                      ),
                    );
                    return;
                  }
                  if (titleController.text.trim().isEmpty) return;
                  Navigator.pop(
                    context,
                    CalendarEventFormResult(
                      title: titleController.text.trim(),
                      description: descriptionController.text.trim(),
                      recurrence: recurrence,
                      recurrenceWeekdays: weekdays,
                      recurrenceUntil: recurrenceUntil,
                      reminderMinutes: reminderMinutes,
                      editScope: editScope,
                    ),
                  );
                },
                child: Text(deleteMode ? 'Supprimer' : 'Enregistrer'),
              ),
            ],
          );
        },
      );
    },
  );
}
