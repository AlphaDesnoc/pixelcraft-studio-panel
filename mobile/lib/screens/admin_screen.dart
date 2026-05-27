import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/extras.dart';
import '../../services/auth_session.dart';

class AdminScreen extends StatefulWidget {
  const AdminScreen({super.key});

  @override
  State<AdminScreen> createState() => _AdminScreenState();
}

class _AdminScreenState extends State<AdminScreen> {
  int _tab = 0;
  List<AdminUser> _users = [];
  List<AdminProject> _projects = [];
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
      final users = await api.fetchAdminUsers();
      final projects = await api.fetchAdminProjects();
      if (!mounted) return;
      setState(() {
        _users = users;
        _projects = projects;
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Administration'),
        actions: [
          IconButton(onPressed: _load, icon: const Icon(Icons.refresh)),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: SegmentedButton<int>(
                    segments: const [
                      ButtonSegment(value: 0, label: Text('Utilisateurs')),
                      ButtonSegment(value: 1, label: Text('Projets')),
                    ],
                    selected: {_tab},
                    onSelectionChanged: (value) =>
                        setState(() => _tab = value.first),
                  ),
                ),
                Expanded(
                  child: _tab == 0 ? _usersList() : _projectsList(),
                ),
              ],
            ),
    );
  }

  Widget _usersList() {
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        itemCount: _users.length,
        separatorBuilder: (context, index) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final user = _users[index];
          return ListTile(
            title: Text(user.name),
            subtitle: Text('${user.email} · ${user.role} · ${user.projectsCount} projets'),
            trailing: user.isAdmin
                ? const Chip(label: Text('Admin'))
                : Switch(
                    value: user.isActive,
                    onChanged: (_) async {
                      await context
                          .read<AuthSession>()
                          .api
                          .toggleAdminUserActive(user.id);
                      await _load();
                    },
                  ),
          );
        },
      ),
    );
  }

  Widget _projectsList() {
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        itemCount: _projects.length,
        separatorBuilder: (context, index) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final project = _projects[index];
          return ListTile(
            title: Text(project.name),
            subtitle: Text(
              '${project.status} · ${project.membersCount} membres · '
              '${project.tasksCount} tâches',
            ),
          );
        },
      ),
    );
  }
}
