import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/workspace.dart';
import '../services/auth_session.dart';
import 'project/kanban_tab.dart';
import 'project/chat_tab.dart';
import 'project/notes_tab.dart';
import 'project/calendar_tab.dart';
import 'project/bugs_tab.dart';
import 'project/spreadsheet_tab.dart';
import 'project/team_tab.dart';
import 'project/ranks_tab.dart';
import 'project/files_tab.dart';
import 'project/overview_tab.dart';
import 'project/gantt_tab.dart';

class ProjectScreen extends StatefulWidget {
  const ProjectScreen({
    super.key,
    required this.slug,
    this.initialSpace,
  });

  final String slug;
  final String? initialSpace;

  @override
  State<ProjectScreen> createState() => _ProjectScreenState();
}

class _ProjectScreenState extends State<ProjectScreen> with SingleTickerProviderStateMixin {
  ProjectWorkspace? _workspace;
  bool _loading = true;
  String? _error;
  String? _space;
  TabController? _tabController;
  List<_ProjectTab> _tabs = [];

  @override
  void initState() {
    super.initState();
    _space = widget.initialSpace;
    _load();
  }

  @override
  void dispose() {
    _tabController?.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await context.read<AuthSession>().api.fetchProjectWorkspace(
            widget.slug,
            space: _space,
          );
      if (!mounted) return;

      final tabs = _buildTabs(data);
      _tabController?.dispose();
      _tabController = TabController(length: tabs.length, vsync: this);

      setState(() {
        _workspace = data;
        _space = data.activeSpace;
        _tabs = tabs;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<_ProjectTab> _buildTabs(ProjectWorkspace ws) {
    final tabs = <_ProjectTab>[];

    tabs.add(_ProjectTab(
      label: 'Aperçu',
      icon: Icons.dashboard_outlined,
      builder: () => OverviewTab(workspace: ws),
    ));

    if (ws.canRead('kanban')) {
      tabs.add(_ProjectTab(
        label: 'Kanban',
        icon: Icons.view_kanban_outlined,
        builder: () => KanbanTab(workspace: ws, onChanged: _load),
      ));
      tabs.add(_ProjectTab(
        label: 'Gantt',
        icon: Icons.timeline_outlined,
        builder: () => GanttTab(workspace: ws),
      ));
    }

    if (ws.canRead('chat')) {
      tabs.add(_ProjectTab(
        label: 'Chat',
        icon: Icons.forum_outlined,
        builder: () => ChatTab(workspace: ws, onChanged: _load),
      ));
    }

    if (ws.canRead('notes')) {
      tabs.add(_ProjectTab(
        label: 'Notes',
        icon: Icons.sticky_note_2_outlined,
        builder: () => NotesTab(workspace: ws, onChanged: _load),
      ));
    }

    if (ws.canRead('calendar')) {
      tabs.add(_ProjectTab(
        label: 'Calendrier',
        icon: Icons.calendar_month_outlined,
        builder: () => CalendarTab(workspace: ws, onChanged: _load),
      ));
    }

    if (ws.canRead('bugs') && (ws.bugs.isNotEmpty || ws.canReportBugs)) {
      tabs.add(_ProjectTab(
        label: 'Bugs',
        icon: Icons.bug_report_outlined,
        builder: () => BugsTab(workspace: ws, onChanged: _load),
      ));
    }

    if (ws.canRead('spreadsheet')) {
      tabs.add(_ProjectTab(
        label: 'Tableur',
        icon: Icons.grid_on_outlined,
        builder: () => SpreadsheetTab(
          projectSlug: ws.project.slug,
          sheets: ws.sheets,
          canWrite: ws.canWrite('spreadsheet'),
          onChanged: _load,
        ),
      ));
    }

    if (ws.canRead('team')) {
      tabs.add(_ProjectTab(
        label: 'Équipe',
        icon: Icons.group_outlined,
        builder: () => TeamTab(workspace: ws, onChanged: _load),
      ));
    }

    if (ws.ranks.isNotEmpty) {
      tabs.add(_ProjectTab(
        label: 'Ranks',
        icon: Icons.military_tech_outlined,
        builder: () => RanksTab(workspace: ws),
      ));
    }

    if (ws.canRead('files')) {
      tabs.add(_ProjectTab(
        label: 'Fichiers',
        icon: Icons.folder_outlined,
        builder: () => FilesTab(workspace: ws, onChanged: _load),
      ));
    }

    return tabs;
  }

  Future<void> _changeSpace(String? spaceKey) async {
    _space = spaceKey;
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _workspace == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Projet')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null && _workspace == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Projet')),
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: _load, child: const Text('Réessayer')),
            ],
          ),
        ),
      );
    }

    final ws = _workspace!;
    final controller = _tabController!;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(ws.project.name),
            Text(ws.spaceLabel, style: Theme.of(context).textTheme.labelSmall),
          ],
        ),
        actions: [
          if (ws.spaces.length > 1)
            PopupMenuButton<String?>(
              tooltip: 'Espace',
              onSelected: _changeSpace,
              itemBuilder: (context) => [
                ...ws.spaces.map(
                  (space) => PopupMenuItem<String?>(value: space.key, child: Text(space.label)),
                ),
                ...ws.ranks.map(
                  (rank) => PopupMenuItem<String?>(value: rank.key, child: Text(rank.label)),
                ),
              ],
              icon: const Icon(Icons.layers_outlined),
            ),
          IconButton(onPressed: _load, icon: const Icon(Icons.refresh)),
        ],
        bottom: TabBar(
          controller: controller,
          isScrollable: true,
          tabs: _tabs
              .map((tab) => Tab(icon: Icon(tab.icon, size: 20), text: tab.label))
              .toList(),
        ),
      ),
      body: TabBarView(
        controller: controller,
        children: _tabs.map((tab) => tab.builder()).toList(),
      ),
    );
  }
}

class _ProjectTab {
  const _ProjectTab({
    required this.label,
    required this.icon,
    required this.builder,
  });

  final String label;
  final IconData icon;
  final Widget Function() builder;
}
