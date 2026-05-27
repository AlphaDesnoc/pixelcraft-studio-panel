import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/my_task.dart';
import '../services/auth_session.dart';
import 'project_screen.dart';

class SearchScreen extends StatefulWidget {
  const SearchScreen({super.key});

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  final _controller = TextEditingController();
  List<SearchResult> _results = [];
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _search() async {
    final query = _controller.text.trim();
    if (query.length < 2) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final results = await context.read<AuthSession>().api.search(query);
      if (!mounted) return;
      setState(() => _results = results);
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = error.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  void _openResult(SearchResult result) {
    if (result.type == 'project') {
      final slug = _extractSlug(result.url);
      if (slug != null) {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ProjectScreen(slug: slug),
          ),
        );
      }
      return;
    }

    if (result.type == 'task' || result.type == 'bug' || result.type == 'chat') {
      final slug = _extractProjectSlug(result.url);
      if (slug != null) {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ProjectScreen(slug: slug),
          ),
        );
      }
    }
  }

  String? _extractSlug(String? url) {
    if (url == null) return null;
    final uri = Uri.tryParse(url);
    if (uri == null) return null;
    final segments = uri.pathSegments;
    final index = segments.indexOf('projects');
    if (index >= 0 && index + 1 < segments.length) {
      return segments[index + 1];
    }
    return null;
  }

  String? _extractProjectSlug(String? url) => _extractSlug(url);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _controller,
          autofocus: true,
          decoration: const InputDecoration(
            hintText: 'Rechercher…',
            border: InputBorder.none,
          ),
          onSubmitted: (_) => _search(),
        ),
        actions: [
          IconButton(onPressed: _search, icon: const Icon(Icons.search)),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView.separated(
                  itemCount: _results.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final result = _results[index];
                    return ListTile(
                      leading: Icon(_iconForType(result.type)),
                      title: Text(result.label),
                      subtitle: result.meta != null ? Text(result.meta!) : null,
                      onTap: () => _openResult(result),
                    );
                  },
                ),
    );
  }

  IconData _iconForType(String type) {
    return switch (type) {
      'project' => Icons.folder_outlined,
      'task' => Icons.task_alt,
      'bug' => Icons.bug_report_outlined,
      'chat' => Icons.chat_outlined,
      'member' => Icons.person_outline,
      _ => Icons.search,
    };
  }
}

class MyTasksTab extends StatefulWidget {
  const MyTasksTab({super.key});

  @override
  State<MyTasksTab> createState() => _MyTasksTabState();
}

class _MyTasksTabState extends State<MyTasksTab> {
  List<MyTask> _tasks = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final tasks = await context.read<AuthSession>().api.fetchMyTasks();
      if (!mounted) return;
      setState(() => _tasks = tasks);
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = error.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(onPressed: _load, child: const Text('Réessayer')),
          ],
        ),
      );
    }

    if (_tasks.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          children: const [
            SizedBox(height: 120),
            Center(child: Text('Aucune tâche assignée')),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        itemCount: _tasks.length,
        separatorBuilder: (_, __) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final task = _tasks[index];
          return ListTile(
            title: Text(task.title),
            subtitle: Text(
              [
                if (task.project != null) task.project!.name,
                if (task.listName != null) task.listName,
                if (task.dueDate != null) 'Échéance ${task.dueDate}',
              ].join(' · '),
            ),
            trailing: task.isOverdue
                ? const Icon(Icons.warning_amber, color: Colors.orange)
                : null,
            onTap: task.project != null
                ? () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => ProjectScreen(slug: task.project!.slug),
                      ),
                    );
                  }
                : null,
          );
        },
      ),
    );
  }
}
