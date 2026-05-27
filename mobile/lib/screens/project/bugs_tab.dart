import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/panel_api.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';

class BugsTab extends StatelessWidget {
  const BugsTab({
    super.key,
    required this.projectSlug,
    required this.bugs,
    required this.canReport,
    required this.canManage,
    required this.statuses,
    required this.priorities,
    required this.onChanged,
  });

  final String projectSlug;
  final List<WorkspaceBug> bugs;
  final bool canReport;
  final bool canManage;
  final Map<String, String> statuses;
  final Map<String, String> priorities;
  final Future<void> Function() onChanged;

  Future<void> _reportBug(BuildContext context) async {
    final api = context.read<AuthSession>().api;
    final titleController = TextEditingController();
    final descriptionController = TextEditingController();

    final created = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Signaler un bug'),
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
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Signaler'),
          ),
        ],
      ),
    );

    if (created != true || titleController.text.trim().isEmpty) return;

    await api.createBug(
          projectSlug: projectSlug,
          title: titleController.text.trim(),
          description: descriptionController.text.trim(),
        );
    await onChanged();
  }

  Future<void> _updateStatus(
    PanelApi api,
    WorkspaceBug bug,
    String status,
  ) async {
    await api.updateBug(
          projectSlug: projectSlug,
          bugId: bug.id,
          fields: {
            'title': bug.title,
            'description': bug.description,
            'status': status,
          },
        );
    await onChanged();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: onChanged,
        child: bugs.isEmpty
            ? ListView(
                children: const [
                  SizedBox(height: 120),
                  Center(child: Text('Aucun bug')),
                ],
              )
            : ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: bugs.length,
                itemBuilder: (context, index) {
                  final bug = bugs[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(bug.title),
                      subtitle: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${priorities[bug.priority] ?? bug.priority} · '
                            '${statuses[bug.status] ?? bug.status}',
                          ),
                          if (bug.assigneeName != null)
                            Text('Assigné : ${bug.assigneeName}'),
                        ],
                      ),
                      trailing: bug.isSlaBreached
                          ? const Icon(Icons.warning, color: Colors.red)
                          : null,
                      onTap: canManage
                          ? () => _showStatusSheet(context, bug)
                          : null,
                    ),
                  );
                },
              ),
      ),
      floatingActionButton: canReport
          ? FloatingActionButton(
              onPressed: () => _reportBug(context),
              child: const Icon(Icons.add),
            )
          : null,
    );
  }

  Future<void> _showStatusSheet(BuildContext context, WorkspaceBug bug) async {
    final api = context.read<AuthSession>().api;
    final status = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: statuses.entries
              .map(
                (entry) => ListTile(
                  title: Text(entry.value),
                  onTap: () => Navigator.pop(context, entry.key),
                ),
              )
              .toList(),
        ),
      ),
    );

    if (status == null) return;
    await _updateStatus(api, bug, status);
  }
}
