import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/panel_api_extensions.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';
import '../../utils/format.dart';

class HistoryTab extends StatefulWidget {
  const HistoryTab({super.key, required this.workspace});

  final ProjectWorkspace workspace;

  @override
  State<HistoryTab> createState() => _HistoryTabState();
}

class _HistoryTabState extends State<HistoryTab> {
  List<ActivityLogEntry> _logs = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final api = context.read<AuthSession>().api;
      final logs = await api.fetchProjectActivityLogs(widget.workspace.project.slug);
      if (!mounted) return;
      setState(() => _logs = logs);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_logs.isEmpty) {
      return const Center(child: Text('Aucune activité enregistrée'));
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.all(12),
        itemCount: _logs.length,
        separatorBuilder: (context, index) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final log = _logs[index];
          return ListTile(
            title: Text(log.message.isNotEmpty ? log.message : 'Activité #${log.id}'),
            subtitle: Text(
              [
                if (log.userName != null) log.userName,
                if (log.createdAt != null) formatRelativeTime(log.createdAt!),
              ].whereType<String>().join(' · '),
            ),
            dense: true,
          );
        },
      ),
    );
  }
}
