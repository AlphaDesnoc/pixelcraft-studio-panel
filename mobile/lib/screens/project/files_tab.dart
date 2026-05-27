import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../config/app_config.dart';
import '../../models/workspace.dart';

class FilesTab extends StatelessWidget {
  const FilesTab({super.key, required this.fileNodes});

  final List<WorkspaceFileNode> fileNodes;

  Future<void> _openFile(WorkspaceFileNode node) async {
    if (node.url == null) return;
    final uri = Uri.parse('${AppConfig.panelBaseUrl}${node.url}');
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    final folders = fileNodes.where((n) => n.type == 'folder').toList();
    final files = fileNodes.where((n) => n.type != 'folder').toList();

    if (fileNodes.isEmpty) {
      return const Center(child: Text('Aucun fichier'));
    }

    return ListView(
      padding: const EdgeInsets.all(12),
      children: [
        if (folders.isNotEmpty) ...[
          Text('Dossiers', style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: 8),
          ...folders.map(
            (node) => ListTile(
              leading: const Icon(Icons.folder),
              title: Text(node.name),
            ),
          ),
          const SizedBox(height: 16),
        ],
        Text('Fichiers', style: Theme.of(context).textTheme.titleSmall),
        const SizedBox(height: 8),
        ...files.map(
          (node) => ListTile(
            leading: const Icon(Icons.insert_drive_file_outlined),
            title: Text(node.name),
            subtitle: node.size != null ? Text('${node.size} o') : null,
            onTap: () => _openFile(node),
          ),
        ),
      ],
    );
  }
}
