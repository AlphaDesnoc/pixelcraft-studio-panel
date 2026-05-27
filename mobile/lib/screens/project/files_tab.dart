import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../api/panel_api_extensions.dart';
import '../../config/app_config.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';

class FilesTab extends StatefulWidget {
  const FilesTab({
    super.key,
    required this.workspace,
    required this.onChanged,
  });

  final ProjectWorkspace workspace;
  final Future<void> Function() onChanged;

  @override
  State<FilesTab> createState() => _FilesTabState();
}

class _FilesTabState extends State<FilesTab> {
  final List<int?> _parentStack = [null];

  bool get _canWrite => widget.workspace.canWrite('files');
  String get _slug => widget.workspace.project.slug;
  int? get _currentParentId => _parentStack.last;

  List<WorkspaceFileNode> get _currentNodes {
    return widget.workspace.fileNodes
        .where((n) => n.parentId == _currentParentId)
        .toList()
      ..sort((a, b) {
        if (a.type == b.type) return a.name.compareTo(b.name);
        return a.type == 'folder' ? -1 : 1;
      });
  }

  void _enterFolder(WorkspaceFileNode node) {
    setState(() => _parentStack.add(node.id));
  }

  void _goUp() {
    if (_parentStack.length <= 1) return;
    setState(() => _parentStack.removeLast());
  }

  Future<void> _createFolder() async {
    final controller = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Nouveau dossier'),
        content: TextField(controller: controller, decoration: const InputDecoration(labelText: 'Nom')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Créer')),
        ],
      ),
    );
    if (ok != true || controller.text.trim().isEmpty) return;

    await context.read<AuthSession>().api.createFileFolder(
          projectSlug: _slug,
          name: controller.text.trim(),
          parentId: _currentParentId,
        );
    await widget.onChanged();
  }

  Future<void> _uploadFile() async {
    final result = await FilePicker.platform.pickFiles(withData: false);
    if (result == null || result.files.isEmpty) return;
    final file = result.files.first;
    if (file.path == null) return;

    await context.read<AuthSession>().api.uploadFile(
          projectSlug: _slug,
          filePath: file.path!,
          fileName: file.name,
          parentId: _currentParentId,
        );
    await widget.onChanged();
  }

  Future<void> _deleteNode(WorkspaceFileNode node) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer ?'),
        content: Text('Supprimer « ${node.name} » ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (ok != true) return;

    await context.read<AuthSession>().api.deleteFileNode(
          projectSlug: _slug,
          nodeId: node.id,
        );
    await widget.onChanged();
  }

  Future<void> _openFile(WorkspaceFileNode node) async {
    if (node.url == null) return;
    final uri = Uri.parse('${AppConfig.panelBaseUrl}${node.url}');
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    final nodes = _currentNodes;

    return Scaffold(
      body: Column(
        children: [
          if (_parentStack.length > 1)
            ListTile(
              leading: const Icon(Icons.arrow_back),
              title: const Text('Dossier parent'),
              onTap: _goUp,
            ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: widget.onChanged,
              child: nodes.isEmpty
                  ? ListView(
                      children: const [
                        SizedBox(height: 120),
                        Center(child: Text('Dossier vide')),
                      ],
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.all(12),
                      itemCount: nodes.length,
                      separatorBuilder: (context, index) => const Divider(height: 1),
                      itemBuilder: (context, index) {
                        final node = nodes[index];
                        final isFolder = node.type == 'folder';
                        return ListTile(
                          leading: Icon(isFolder ? Icons.folder : Icons.insert_drive_file_outlined),
                          title: Text(node.name),
                          subtitle: node.size != null ? Text('${node.size} o') : null,
                          trailing: _canWrite
                              ? IconButton(
                                  icon: const Icon(Icons.delete_outline),
                                  onPressed: () => _deleteNode(node),
                                )
                              : null,
                          onTap: () {
                            if (isFolder) {
                              _enterFolder(node);
                            } else {
                              _openFile(node);
                            }
                          },
                        );
                      },
                    ),
            ),
          ),
        ],
      ),
      floatingActionButton: _canWrite
          ? Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                FloatingActionButton(
                  heroTag: 'folder',
                  onPressed: _createFolder,
                  child: const Icon(Icons.create_new_folder_outlined),
                ),
                const SizedBox(height: 12),
                FloatingActionButton(
                  heroTag: 'upload',
                  onPressed: _uploadFile,
                  child: const Icon(Icons.upload_file),
                ),
              ],
            )
          : null,
    );
  }
}
