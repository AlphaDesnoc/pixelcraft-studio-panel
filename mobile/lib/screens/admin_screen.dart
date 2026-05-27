import 'dart:io';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../api/panel_api_extensions.dart';
import '../models/extras.dart';
import '../services/auth_session.dart';

class AdminScreen extends StatefulWidget {
  const AdminScreen({super.key});

  @override
  State<AdminScreen> createState() => _AdminScreenState();
}

class _AdminScreenState extends State<AdminScreen> {
  int _tab = 0;
  List<AdminUser> _users = [];
  List<AdminProject> _projects = [];
  List<Map<String, dynamic>> _auditLogs = [];
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
      final audit = await api.fetchAdminAudit();
      if (!mounted) return;
      setState(() {
        _users = users;
        _projects = projects;
        _auditLogs = audit;
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _exportAudit() async {
    final bytes = await context.read<AuthSession>().api.downloadExport('/export/audit');
    final dir = await getTemporaryDirectory();
    final file = File('${dir.path}/audit.csv');
    await file.writeAsBytes(bytes);
    await Share.shareXFiles([XFile(file.path)], text: 'Export audit');
  }

  Future<void> _showUserForm({AdminUser? user}) async {
    final isEdit = user != null;
    final nameController = TextEditingController(text: user?.name ?? '');
    final pseudoController = TextEditingController(text: user?.email.split('@').first ?? '');
    final passwordController = TextEditingController();
    var role = user?.role ?? 'member';

    final saved = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(isEdit ? 'Modifier utilisateur' : 'Nouvel utilisateur'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(controller: nameController, decoration: const InputDecoration(labelText: 'Nom')),
                TextField(controller: pseudoController, decoration: const InputDecoration(labelText: 'Pseudo')),
                TextField(
                  controller: passwordController,
                  obscureText: true,
                  decoration: InputDecoration(
                    labelText: isEdit ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe',
                  ),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  value: role,
                  decoration: const InputDecoration(labelText: 'Rôle'),
                  items: const [
                    DropdownMenuItem(value: 'member', child: Text('Membre')),
                    DropdownMenuItem(value: 'admin', child: Text('Admin')),
                  ],
                  onChanged: (value) {
                    if (value != null) setDialogState(() => role = value);
                  },
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Enregistrer')),
          ],
        ),
      ),
    );

    if (saved != true) return;

    final api = context.read<AuthSession>().api;
    if (user != null) {
      await api.updateAdminUser(
        userId: user.id,
        name: nameController.text.trim(),
        pseudo: pseudoController.text.trim(),
        role: role,
        password: passwordController.text.trim().isEmpty ? null : passwordController.text.trim(),
      );
    } else {
      await api.createAdminUser(
        name: nameController.text.trim(),
        pseudo: pseudoController.text.trim(),
        password: passwordController.text.trim(),
        role: role,
      );
    }
    await _load();
  }

  Future<void> _deleteUser(AdminUser user) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer l\'utilisateur ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (ok != true) return;

    await context.read<AuthSession>().api.deleteAdminUser(user.id);
    await _load();
  }

  Future<void> _showProjectForm({AdminProject? project}) async {
    final isEdit = project != null;
    final nameController = TextEditingController(text: project?.name ?? '');
    final descriptionController = TextEditingController();
    var status = project?.status ?? 'active';

    final saved = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(isEdit ? 'Modifier projet' : 'Nouveau projet'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: nameController, decoration: const InputDecoration(labelText: 'Nom')),
              TextField(
                controller: descriptionController,
                decoration: const InputDecoration(labelText: 'Description'),
              ),
              DropdownButtonFormField<String>(
                value: status,
                decoration: const InputDecoration(labelText: 'Statut'),
                items: const [
                  DropdownMenuItem(value: 'active', child: Text('Actif')),
                  DropdownMenuItem(value: 'archived', child: Text('Archivé')),
                ],
                onChanged: (value) {
                  if (value != null) setDialogState(() => status = value);
                },
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Enregistrer')),
          ],
        ),
      ),
    );

    if (saved != true || nameController.text.trim().isEmpty) return;

    final api = context.read<AuthSession>().api;
    if (project != null) {
      await api.updateAdminProject(
        projectId: project.id,
        name: nameController.text.trim(),
        description: descriptionController.text.trim(),
        status: status,
      );
    } else {
      await api.createAdminProject(
        name: nameController.text.trim(),
        description: descriptionController.text.trim(),
        status: status,
      );
    }
    await _load();
  }

  Future<void> _deleteProject(AdminProject project) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer le projet ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (ok != true) return;

    await context.read<AuthSession>().api.deleteAdminProject(project.id);
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Administration'),
        actions: [
          if (_tab == 2)
            IconButton(onPressed: _exportAudit, icon: const Icon(Icons.download_outlined)),
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
                      ButtonSegment(value: 2, label: Text('Audit')),
                    ],
                    selected: {_tab},
                    onSelectionChanged: (value) => setState(() => _tab = value.first),
                  ),
                ),
                Expanded(
                  child: switch (_tab) {
                    0 => _usersList(),
                    1 => _projectsList(),
                    _ => _auditList(),
                  },
                ),
              ],
            ),
      floatingActionButton: _tab == 0
          ? FloatingActionButton(onPressed: () => _showUserForm(), child: const Icon(Icons.person_add))
          : _tab == 1
              ? FloatingActionButton(onPressed: () => _showProjectForm(), child: const Icon(Icons.add))
              : null,
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
                : Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Switch(
                        value: user.isActive,
                        onChanged: (_) async {
                          await context.read<AuthSession>().api.toggleAdminUserActive(user.id);
                          await _load();
                        },
                      ),
                      PopupMenuButton<String>(
                        itemBuilder: (context) => const [
                          PopupMenuItem(value: 'edit', child: Text('Modifier')),
                          PopupMenuItem(value: 'delete', child: Text('Supprimer')),
                        ],
                        onSelected: (value) {
                          if (value == 'edit') {
                            _showUserForm(user: user);
                          } else {
                            _deleteUser(user);
                          }
                        },
                      ),
                    ],
                  ),
            onTap: () => _showUserForm(user: user),
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
              '${project.status} · ${project.membersCount} membres · ${project.tasksCount} tâches',
            ),
            trailing: PopupMenuButton<String>(
              itemBuilder: (context) => const [
                PopupMenuItem(value: 'edit', child: Text('Modifier')),
                PopupMenuItem(value: 'delete', child: Text('Supprimer')),
              ],
              onSelected: (value) {
                if (value == 'edit') {
                  _showProjectForm(project: project);
                } else {
                  _deleteProject(project);
                }
              },
            ),
            onTap: () => _showProjectForm(project: project),
          );
        },
      ),
    );
  }

  Widget _auditList() {
    return RefreshIndicator(
      onRefresh: _load,
      child: _auditLogs.isEmpty
          ? ListView(children: const [SizedBox(height: 120), Center(child: Text('Aucun log'))])
          : ListView.separated(
              itemCount: _auditLogs.length,
              separatorBuilder: (context, index) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final log = _auditLogs[index];
                return ListTile(
                  title: Text('${log['action'] ?? ''}'),
                  subtitle: Text('${log['user_name'] ?? ''} · ${log['created_at'] ?? ''}'),
                );
              },
            ),
    );
  }
}
