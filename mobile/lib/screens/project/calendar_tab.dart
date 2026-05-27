import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/workspace.dart';
import '../../services/auth_session.dart';
import '../../utils/format.dart';

class CalendarTab extends StatelessWidget {
  const CalendarTab({
    super.key,
    required this.projectSlug,
    required this.events,
    required this.canWrite,
    required this.onChanged,
  });

  final String projectSlug;
  final List<WorkspaceEvent> events;
  final bool canWrite;
  final Future<void> Function() onChanged;

  Future<void> _createEvent(BuildContext context) async {
    final titleController = TextEditingController();
    final now = DateTime.now();
    final start = now.toIso8601String();
    final end = now.add(const Duration(hours: 1)).toIso8601String();

    final created = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Nouvel événement'),
        content: TextField(
          controller: titleController,
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

    await context.read<AuthSession>().api.createEvent(
          projectSlug: projectSlug,
          title: titleController.text.trim(),
          startAt: start,
          endAt: end,
        );
    await onChanged();
  }

  @override
  Widget build(BuildContext context) {
    final sorted = List<WorkspaceEvent>.from(events)
      ..sort((a, b) => (a.startAt ?? '').compareTo(b.startAt ?? ''));

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: onChanged,
        child: sorted.isEmpty
            ? ListView(
                children: const [
                  SizedBox(height: 120),
                  Center(child: Text('Aucun événement')),
                ],
              )
            : ListView.separated(
                padding: const EdgeInsets.all(12),
                itemCount: sorted.length,
                separatorBuilder: (_, __) => const SizedBox(height: 8),
                itemBuilder: (context, index) {
                  final event = sorted[index];
                  return Card(
                    child: ListTile(
                      leading: CircleAvatar(
                        backgroundColor: _parseColor(event.color),
                        child: const Icon(Icons.event, size: 18),
                      ),
                      title: Text(event.title),
                      subtitle: Text(formatRelativeTime(event.startAt)),
                    ),
                  );
                },
              ),
      ),
      floatingActionButton: canWrite
          ? FloatingActionButton(
              onPressed: () => _createEvent(context),
              child: const Icon(Icons.add),
            )
          : null,
    );
  }

  Color _parseColor(String hex) {
    final value = hex.replaceFirst('#', '');
    if (value.length == 6) {
      return Color(int.parse('FF$value', radix: 16));
    }
    return ThemeData.dark().colorScheme.primary;
  }
}
