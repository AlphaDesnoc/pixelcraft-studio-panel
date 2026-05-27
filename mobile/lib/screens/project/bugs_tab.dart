import 'dart:io';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../api/panel_api_extensions.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';
import '../../utils/format.dart';

class BugsTab extends StatelessWidget {
  const BugsTab({
    super.key,
    required this.workspace,
    required this.onChanged,
  });

  final ProjectWorkspace workspace;
  final Future<void> Function() onChanged;

  bool get _canReport => workspace.canReportBugs;
  String get _slug => workspace.project.slug;

  Future<void> _reportBug(BuildContext context) async {
    final titleController = TextEditingController();
    final descriptionController = TextEditingController();

    final created = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Signaler un bug'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: titleController, decoration: const InputDecoration(labelText: 'Titre')),
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
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Signaler')),
        ],
      ),
    );

    if (created != true || titleController.text.trim().isEmpty) return;

    await context.read<AuthSession>().api.createBug(
          projectSlug: _slug,
          title: titleController.text.trim(),
          description: descriptionController.text.trim(),
        );
    await onChanged();
  }

  Future<void> _exportBugs(BuildContext context) async {
    final bytes = await context.read<AuthSession>().api.downloadExport('/export/bugs/$_slug');
    final dir = await getTemporaryDirectory();
    final file = File('${dir.path}/bugs-$_slug.csv');
    await file.writeAsBytes(bytes);
    await Share.shareXFiles([XFile(file.path)], text: 'Export bugs');
  }

  Future<void> _openBugDetail(BuildContext context, WorkspaceBug bug) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => _BugDetailSheet(
        workspace: workspace,
        bug: bug,
        onChanged: onChanged,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(
        children: [
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: () => _exportBugs(context),
              icon: const Icon(Icons.download_outlined),
              label: const Text('Exporter'),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: onChanged,
              child: workspace.bugs.isEmpty
            ? ListView(
                children: const [
                  SizedBox(height: 120),
                  Center(child: Text('Aucun bug')),
                ],
              )
            : ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: workspace.bugs.length,
                itemBuilder: (context, index) {
                  final bug = workspace.bugs[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(bug.title),
                      subtitle: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${workspace.bugPriorities[bug.priority] ?? bug.priority} · '
                            '${workspace.bugStatuses[bug.status] ?? bug.status}',
                          ),
                          if (bug.assigneeName != null) Text('Assigné : ${bug.assigneeName}'),
                        ],
                      ),
                      trailing: bug.isSlaBreached
                          ? const Icon(Icons.warning, color: Colors.red)
                          : null,
                      onTap: () => _openBugDetail(context, bug),
                    ),
                  );
                },
              ),
            ),
          ),
        ],
      ),
      floatingActionButton: _canReport
          ? FloatingActionButton(onPressed: () => _reportBug(context), child: const Icon(Icons.add))
          : null,
    );
  }
}

class _BugDetailSheet extends StatefulWidget {
  const _BugDetailSheet({
    required this.workspace,
    required this.bug,
    required this.onChanged,
  });

  final ProjectWorkspace workspace;
  final WorkspaceBug bug;
  final Future<void> Function() onChanged;

  @override
  State<_BugDetailSheet> createState() => _BugDetailSheetState();
}

class _BugDetailSheetState extends State<_BugDetailSheet> {
  late WorkspaceBug _bug;
  List<BugMessage> _messages = [];
  bool _loadingMessages = true;
  final _messageController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _bug = widget.bug;
    _loadMessages();
  }

  @override
  void dispose() {
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _loadMessages() async {
    setState(() => _loadingMessages = true);
    try {
      final messages = await context.read<AuthSession>().api.fetchBugMessages(
            projectSlug: widget.workspace.project.slug,
            bugId: _bug.id,
          );
      if (mounted) setState(() => _messages = messages);
    } finally {
      if (mounted) setState(() => _loadingMessages = false);
    }
  }

  Future<void> _postMessage() async {
    final body = _messageController.text.trim();
    if (body.isEmpty) return;
    final message = await context.read<AuthSession>().api.postBugMessage(
          projectSlug: widget.workspace.project.slug,
          bugId: _bug.id,
          body: body,
        );
    _messageController.clear();
    setState(() => _messages = [..._messages, message]);
  }

  Future<void> _updateBug({String? priority, int? assigneeId}) async {
    final updated = await context.read<AuthSession>().api.updateBug(
          projectSlug: widget.workspace.project.slug,
          bugId: _bug.id,
          fields: {
            'title': _bug.title,
            'description': _bug.description,
            if (priority != null) 'priority': priority,
            if (assigneeId != null) 'assignee_id': assigneeId,
          },
        );
    setState(() => _bug = updated);
    await widget.onChanged();
  }

  Future<void> _deleteBug() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer le bug ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (ok != true) return;

    await context.read<AuthSession>().api.deleteBug(
          projectSlug: widget.workspace.project.slug,
          bugId: _bug.id,
        );
    if (mounted) Navigator.pop(context);
    await widget.onChanged();
  }

  @override
  Widget build(BuildContext context) {
    final canManage = widget.workspace.canManageBugs || _bug.canManage;

    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.9,
      minChildSize: 0.5,
      maxChildSize: 0.98,
      builder: (context, scrollController) {
        return Material(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  children: [
                    Expanded(child: Text(_bug.title, style: Theme.of(context).textTheme.titleMedium)),
                    if (canManage)
                      IconButton(
                        icon: const Icon(Icons.delete_outline),
                        onPressed: _deleteBug,
                      ),
                    IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
                  ],
                ),
              ),
              if (_bug.description != null)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: Text(_bug.description!),
                  ),
                ),
              if (canManage) ...[
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    children: [
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          value: _bug.priority,
                          decoration: const InputDecoration(labelText: 'Priorité'),
                          items: widget.workspace.bugPriorities.entries
                              .map((e) => DropdownMenuItem(value: e.key, child: Text(e.value)))
                              .toList(),
                          onChanged: (value) {
                            if (value != null) _updateBug(priority: value);
                          },
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: DropdownButtonFormField<int?>(
                          value: null,
                          decoration: const InputDecoration(labelText: 'Assigner à'),
                          items: [
                            const DropdownMenuItem<int?>(value: null, child: Text('Choisir…')),
                            ...widget.workspace.members.map(
                              (m) => DropdownMenuItem<int?>(value: m.id, child: Text(m.name)),
                            ),
                          ],
                          onChanged: (value) {
                            if (value != null) _updateBug(assigneeId: value);
                          },
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const Divider(height: 1),
              Expanded(
                child: _loadingMessages
                    ? const Center(child: CircularProgressIndicator())
                    : ListView.builder(
                        controller: scrollController,
                        padding: const EdgeInsets.all(16),
                        itemCount: _messages.length,
                        itemBuilder: (context, index) {
                          final message = _messages[index];
                          return Card(
                            margin: const EdgeInsets.only(bottom: 8),
                            child: ListTile(
                              title: Text(message.body),
                              subtitle: Text(
                                [
                                  if (message.userName != null) message.userName,
                                  if (message.createdAt != null) formatRelativeTime(message.createdAt),
                                ].whereType<String>().join(' · '),
                              ),
                            ),
                          );
                        },
                      ),
              ),
              if (canManage || widget.workspace.canReportBugs)
                SafeArea(
                  top: false,
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _messageController,
                            decoration: const InputDecoration(hintText: 'Message…'),
                            onSubmitted: (_) => _postMessage(),
                          ),
                        ),
                        IconButton(icon: const Icon(Icons.send), onPressed: _postMessage),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}
