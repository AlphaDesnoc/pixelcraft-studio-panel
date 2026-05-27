import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/panel_api.dart';
import '../models/conversation.dart';
import '../models/panel_notification.dart';
import '../models/project.dart';
import '../services/auth_session.dart';
import '../services/realtime_service.dart';
import '../utils/format.dart';
import 'admin_screen.dart';
import 'chat_screen.dart';
import 'login_screen.dart';
import 'project_screen.dart';
import 'search_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthSession>().user;

    return Scaffold(
      appBar: AppBar(
        title: Text(switch (_index) {
          0 => 'Dashboard',
          1 => 'Mes tâches',
          2 => 'Messages',
          3 => 'Notifications',
          _ => 'Compte',
        }),
        actions: [
          if (user?.isAdmin == true)
            IconButton(
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const AdminScreen()),
                );
              },
              icon: const Icon(Icons.admin_panel_settings_outlined),
              tooltip: 'Administration',
            ),
          IconButton(
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const SearchScreen()),
              );
            },
            icon: const Icon(Icons.search),
          ),
          if (user != null)
            Padding(
              padding: const EdgeInsets.only(right: 12),
              child: Center(
                child: Text(
                  user.name,
                  style: Theme.of(context).textTheme.labelLarge,
                ),
              ),
            ),
        ],
      ),
      body: IndexedStack(
        index: _index,
        children: const [
          _DashboardTab(),
          MyTasksTab(),
          _MessagesTab(),
          _NotificationsTab(),
          _ProfileTab(),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (value) => setState(() => _index = value),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            selectedIcon: Icon(Icons.dashboard),
            label: 'Dashboard',
          ),
          NavigationDestination(
            icon: Icon(Icons.task_alt_outlined),
            selectedIcon: Icon(Icons.task_alt),
            label: 'Tâches',
          ),
          NavigationDestination(
            icon: Icon(Icons.chat_bubble_outline),
            selectedIcon: Icon(Icons.chat_bubble),
            label: 'Messages',
          ),
          NavigationDestination(
            icon: Icon(Icons.notifications_outlined),
            selectedIcon: Icon(Icons.notifications),
            label: 'Notifs',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: 'Compte',
          ),
        ],
      ),
    );
  }
}

class _DashboardTab extends StatefulWidget {
  const _DashboardTab();

  @override
  State<_DashboardTab> createState() => _DashboardTabState();
}

class _DashboardTabState extends State<_DashboardTab> {
  ProjectsResponse? _data;
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
      final data = await context.read<AuthSession>().api.fetchProjects();
      if (!mounted) return;
      setState(() => _data = data);
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
      return _ErrorState(message: _error!, onRetry: _load);
    }

    final data = _data!;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              _StatCard(label: 'Projets', value: '${data.stats.projects}'),
              _StatCard(label: 'Tâches', value: '${data.stats.tasks}'),
              _StatCard(label: 'Terminées', value: '${data.stats.completed}'),
              _StatCard(label: 'En retard', value: '${data.stats.overdue}'),
            ],
          ),
          const SizedBox(height: 24),
          Text(
            'Projets',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 12),
          ...data.projects.map((project) => _ProjectTile(project: project)),
        ],
      ),
    );
  }
}

class _MessagesTab extends StatefulWidget {
  const _MessagesTab();

  @override
  State<_MessagesTab> createState() => _MessagesTabState();
}

class _MessagesTabState extends State<_MessagesTab> {
  List<Conversation> _conversations = [];
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
      final data =
          await context.read<AuthSession>().api.fetchConversations();
      if (!mounted) return;
      setState(() => _conversations = data.conversations);
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
      return _ErrorState(message: _error!, onRetry: _load);
    }

    if (_conversations.isEmpty) {
      return const Center(child: Text('Aucune conversation'));
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        itemCount: _conversations.length,
        separatorBuilder: (context, index) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final conversation = _conversations[index];
          final participant = conversation.participant;
          final preview = conversation.lastMessage?.body ?? 'Aucun message';

          return ListTile(
            leading: CircleAvatar(
              child: Text(initialsFromName(participant?.name ?? '?')),
            ),
            title: Text(participant?.name ?? 'Conversation'),
            subtitle: Text(
              preview,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            trailing: conversation.unreadCount > 0
                ? CircleAvatar(
                    radius: 12,
                    backgroundColor: Theme.of(context).colorScheme.primary,
                    child: Text(
                      '${conversation.unreadCount}',
                      style: const TextStyle(fontSize: 11),
                    ),
                  )
                : Text(formatRelativeTime(conversation.lastMessageAt)),
            onTap: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => ChatScreen(conversation: conversation),
                ),
              );
              _load();
            },
          );
        },
      ),
    );
  }
}

class _NotificationsTab extends StatefulWidget {
  const _NotificationsTab();

  @override
  State<_NotificationsTab> createState() => _NotificationsTabState();
}

class _NotificationsTabState extends State<_NotificationsTab> {
  List<PanelNotification> _notifications = [];
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
      final data =
          await context.read<AuthSession>().api.fetchNotifications();
      if (!mounted) return;
      setState(() => _notifications = data.notifications);
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = error.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _markAllRead() async {
    await context.read<AuthSession>().api.markAllNotificationsRead();
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null) {
      return _ErrorState(message: _error!, onRetry: _load);
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: Column(
        children: [
          if (_notifications.any((n) => n.isUnread))
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: _markAllRead,
                child: const Text('Tout marquer lu'),
              ),
            ),
          Expanded(
            child: _notifications.isEmpty
                ? ListView(
                    children: const [
                      SizedBox(height: 120),
                      Center(child: Text('Aucune notification')),
                    ],
                  )
                : ListView.separated(
                    itemCount: _notifications.length,
                    separatorBuilder: (context, index) => const Divider(height: 1),
                    itemBuilder: (context, index) {
                      final notification = _notifications[index];
                      return ListTile(
                        title: Text(notification.title),
                        subtitle: Text(notification.body),
                        trailing: notification.isUnread
                            ? const Icon(Icons.circle, size: 10)
                            : null,
                        onTap: () async {
                          if (notification.isUnread) {
                            await context
                                .read<AuthSession>()
                                .api
                                .markNotificationRead(notification.id);
                            await _load();
                          }
                        },
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

class _ProfileTab extends StatefulWidget {
  const _ProfileTab();

  @override
  State<_ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<_ProfileTab> {
  Map<String, bool> _preferences = {};
  Map<String, String> _labels = {};
  bool _loadingPrefs = true;

  @override
  void initState() {
    super.initState();
    _loadPreferences();
  }

  Future<void> _loadPreferences() async {
    try {
      final data =
          await context.read<AuthSession>().api.fetchNotificationPreferences();
      if (!mounted) return;
      setState(() {
        _preferences = data.preferences;
        _labels = data.labels;
        _loadingPrefs = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loadingPrefs = false);
    }
  }

  Future<void> _togglePref(String key, bool value) async {
    final next = Map<String, bool>.from(_preferences)..[key] = value;
    setState(() => _preferences = next);
    await context.read<AuthSession>().api.updateNotificationPreferences(next);
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthSession>().user;
    if (user == null) {
      return const SizedBox.shrink();
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(
                  radius: 28,
                  child: Text(initialsFromName(user.name)),
                ),
                const SizedBox(height: 12),
                Text(user.name, style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 4),
                Text(user.email),
                const SizedBox(height: 8),
                Text('Rôle : ${user.role}${user.isAdmin ? ' (admin)' : ''}'),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text('Notifications', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        if (_loadingPrefs)
          const Center(child: CircularProgressIndicator())
        else
          ..._labels.entries.map(
            (entry) => SwitchListTile(
              title: Text(entry.value),
              value: _preferences[entry.key] ?? true,
              onChanged: (value) => _togglePref(entry.key, value),
            ),
          ),
        const SizedBox(height: 24),
        OutlinedButton(
          onPressed: () async {
            context.read<RealtimeService>().stop();
            await context.read<AuthSession>().logout();
            if (!context.mounted) return;
            Navigator.of(context).pushAndRemoveUntil(
              MaterialPageRoute(builder: (_) => const LoginScreen()),
              (_) => false,
            );
          },
          child: const Text('Se déconnecter'),
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 160,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: Theme.of(context).textTheme.labelLarge),
              const SizedBox(height: 8),
              Text(value, style: Theme.of(context).textTheme.headlineSmall),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProjectTile extends StatelessWidget {
  const _ProjectTile({required this.project});

  final PanelProject project;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        title: Text(project.name),
        subtitle: Text(
          '${project.tasksDone}/${project.tasksTotal} tâches · '
          '${project.membersCount} membres',
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => ProjectScreen(slug: project.slug),
            ),
          );
        },
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: const Text('Réessayer')),
          ],
        ),
      ),
    );
  }
}
