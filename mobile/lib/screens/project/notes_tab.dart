import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/workspace.dart';
import '../../services/auth_session.dart';

class NotesTab extends StatelessWidget {
  const NotesTab({
    super.key,
    required this.projectSlug,
    required this.notes,
    required this.canWrite,
    required this.onChanged,
  });

  final String projectSlug;
  final List<WorkspaceNote> notes;
  final bool canWrite;
  final Future<void> Function() onChanged;

  Future<void> _createNote(BuildContext context) async {
    final api = context.read<AuthSession>().api;
    final titleController = TextEditingController();
    final contentController = TextEditingController();

    final created = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Nouvelle note'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: titleController,
              decoration: const InputDecoration(labelText: 'Titre'),
            ),
            TextField(
              controller: contentController,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Contenu'),
            ),
          ],
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

    await api.createNote(
          projectSlug: projectSlug,
          title: titleController.text.trim(),
          content: contentController.text.trim(),
        );
    await onChanged();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: onChanged,
        child: notes.isEmpty
            ? ListView(
                children: const [
                  SizedBox(height: 120),
                  Center(child: Text('Aucune note')),
                ],
              )
            : ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: notes.length,
                itemBuilder: (context, index) {
                  final note = notes[index];
                  return Card(
                    color: _parseColor(note.color),
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(note.title),
                      subtitle: note.content != null && note.content!.isNotEmpty
                          ? Text(
                              note.content!,
                              maxLines: 4,
                              overflow: TextOverflow.ellipsis,
                            )
                          : null,
                      trailing: note.pinned
                          ? const Icon(Icons.push_pin, size: 18)
                          : null,
                    ),
                  );
                },
              ),
      ),
      floatingActionButton: canWrite
          ? FloatingActionButton(
              onPressed: () => _createNote(context),
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
    return Colors.amber.shade100;
  }
}
