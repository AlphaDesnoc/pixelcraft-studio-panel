import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/panel_api_extensions.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';

class NotesTab extends StatelessWidget {
  const NotesTab({
    super.key,
    required this.workspace,
    required this.onChanged,
  });

  final ProjectWorkspace workspace;
  final Future<void> Function() onChanged;

  bool get _canWrite => workspace.canWrite('notes');
  String get _slug => workspace.project.slug;

  Future<void> _createNote(BuildContext context) async {
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
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Créer')),
        ],
      ),
    );

    if (created != true || titleController.text.trim().isEmpty) return;

    await context.read<AuthSession>().api.createNote(
          projectSlug: _slug,
          title: titleController.text.trim(),
          content: contentController.text.trim(),
        );
    await onChanged();
  }

  Future<void> _openNote(BuildContext context, WorkspaceNote note) async {
    final api = context.read<AuthSession>().api;
    final titleController = TextEditingController(text: note.title);
    final contentController = TextEditingController(text: note.content ?? '');

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text('Note', style: Theme.of(context).textTheme.titleMedium),
                ),
                IconButton(
                  tooltip: note.pinned ? 'Désépingler' : 'Épingler',
                  onPressed: _canWrite
                      ? () async {
                          await api.toggleNotePin(projectSlug: _slug, noteId: note.id);
                          if (context.mounted) Navigator.pop(context);
                          await onChanged();
                        }
                      : null,
                  icon: Icon(note.pinned ? Icons.push_pin : Icons.push_pin_outlined),
                ),
                if (_canWrite)
                  IconButton(
                    tooltip: 'Supprimer',
                    onPressed: () async {
                      final ok = await showDialog<bool>(
                        context: context,
                        builder: (context) => AlertDialog(
                          title: const Text('Supprimer la note ?'),
                          actions: [
                            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
                            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
                          ],
                        ),
                      );
                      if (ok != true) return;
                      await api.deleteNote(projectSlug: _slug, noteId: note.id);
                      if (context.mounted) Navigator.pop(context);
                      await onChanged();
                    },
                    icon: const Icon(Icons.delete_outline),
                  ),
              ],
            ),
            TextField(
              controller: titleController,
              readOnly: !_canWrite,
              decoration: const InputDecoration(labelText: 'Titre'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: contentController,
              readOnly: !_canWrite,
              minLines: 3,
              maxLines: 8,
              decoration: const InputDecoration(labelText: 'Contenu'),
            ),
            const SizedBox(height: 16),
            if (_canWrite)
              FilledButton(
                onPressed: () async {
                  await api.updateNote(
                    projectSlug: _slug,
                    noteId: note.id,
                    title: titleController.text.trim(),
                    content: contentController.text.trim(),
                  );
                  if (context.mounted) Navigator.pop(context);
                  await onChanged();
                },
                child: const Text('Enregistrer'),
              ),
          ],
        ),
      ),
    );
  }

  Color _parseColor(String hex) {
    final value = hex.replaceFirst('#', '');
    if (value.length == 6) {
      return Color(int.parse('FF$value', radix: 16));
    }
    return Colors.amber.shade100;
  }

  @override
  Widget build(BuildContext context) {
    final sorted = List<WorkspaceNote>.from(workspace.notes)
      ..sort((a, b) {
        if (a.pinned != b.pinned) return a.pinned ? -1 : 1;
        return a.title.compareTo(b.title);
      });

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: onChanged,
        child: sorted.isEmpty
            ? ListView(
                children: const [
                  SizedBox(height: 120),
                  Center(child: Text('Aucune note')),
                ],
              )
            : ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: sorted.length,
                itemBuilder: (context, index) {
                  final note = sorted[index];
                  return Card(
                    color: _parseColor(note.color),
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(note.title),
                      subtitle: note.content != null && note.content!.isNotEmpty
                          ? Text(note.content!, maxLines: 4, overflow: TextOverflow.ellipsis)
                          : null,
                      trailing: note.pinned ? const Icon(Icons.push_pin, size: 18) : null,
                      onTap: () => _openNote(context, note),
                    ),
                  );
                },
              ),
      ),
      floatingActionButton: _canWrite
          ? FloatingActionButton(onPressed: () => _createNote(context), child: const Icon(Icons.add))
          : null,
    );
  }
}
