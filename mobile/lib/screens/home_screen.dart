import 'dart:async';

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
import 'profile_screen.dart';
import 'project_screen.dart';
import 'search_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _index = 0;
  int _unreadMessages = 0;
  int _unreadNotifications = 0;

  @override
  void initState() {
    super.initState();
    _loadBadges();
    context.read<RealtimeService>().addListener(_onRealtime);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<RealtimeService>().ensureNotificationPermission();
    });
  }

  @override
  void dispose() {
    context.read<RealtimeService>().removeListener(_onRealtime);
    super.dispose();
  }

  void _onRealtime() {
    final realtime = context.read<RealtimeService>();
    setState(() {
      _unreadMessages = realtime.unreadMessages;
      _unreadNotifications = realtime.unreadNotifications;
    });
  }

  Future<void> _loadBadges() async {
    try {
      final api = context.read<AuthSession>().api;
      final conversations = await api.fetchConversations();
      final notifications = await api.fetchNotifications();
      if (!mounted) return;
      setState(() {
        _unreadMessages = conversations.conversations.fold<int>(
          0,
          (sum, c) => sum + c.unreadCount,
        );
        _unreadNotifications = notifications.unreadCount;
      });
    } catch (_) {}
  }

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
          if (context.watch<RealtimeService>().isLive)
            const Padding(
              padding: EdgeInsets.only(right: 8),
              child: Icon(Icons.circle, color: Colors.greenAccent, size: 10),
            ),
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
                child: Text(user.name, style: Theme.of(context).textTheme.labelLarge),
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
          ProfileScreen(),
        ],
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: NavigationBar(
          selectedIndex: _index,
          onDestinationSelected: (value) {
            setState(() => _index = value);
            if (value == 2 || value == 3) _loadBadges();
          },
          destinations: [
          const NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            selectedIcon: Icon(Icons.dashboard),
            label: 'Dashboard',
          ),
          const NavigationDestination(
            icon: Icon(Icons.task_alt_outlined),
            selectedIcon: Icon(Icons.task_alt),
            label: 'Tâches',
          ),
          NavigationDestination(
            icon: _BadgeIcon(
              icon: Icons.chat_bubble_outline,
              count: _unreadMessages,
            ),
            selectedIcon: _BadgeIcon(
              icon: Icons.chat_bubble,
              count: _unreadMessages,
            ),
            label: 'Messages',
          ),
          NavigationDestination(
            icon: _BadgeIcon(
              icon: Icons.notifications_outlined,
              count: _unreadNotifications,
            ),
            selectedIcon: _BadgeIcon(
              icon: Icons.notifications,
              count: _unreadNotifications,
            ),
            label: 'Notifs',
          ),
          const NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: 'Compte',
          ),
        ],
        ),
      ),
    );
  }
}

class _BadgeIcon extends StatelessWidget {
  const _BadgeIcon({required this.icon, required this.count});

  final IconData icon;
  final int count;

  @override
  Widget build(BuildContext context) {
    return Badge(
      isLabelVisible: count > 0,
      label: Text(count > 99 ? '99+' : '$count'),
      child: Icon(icon),
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
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return _ErrorState(message: _error!, onRetry: _load);

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
          Text('Projets', style: Theme.of(context).textTheme.titleMedium),
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
  List<ConversationParticipant> _contacts = [];
  bool _loading = true;
  String? _error;
  StreamSubscription<Map<String, dynamic>>? _liveEvents;

  @override
  void initState() {
    super.initState();
    _load();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final user = context.read<AuthSession>().user;
      if (user != null) {
        context.read<RealtimeService>().subscribeInbox(user.id);
      }
      _liveEvents = context.read<RealtimeService>().directMessageEvents.listen((_) {
        _load();
      });
    });
  }

  @override
  void dispose() {
    _liveEvents?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await context.read<AuthSession>().api.fetchConversations();
      if (!mounted) return;
      setState(() {
        _conversations = data.conversations;
        _contacts = data.contacts;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return _ErrorState(message: _error!, onRetry: _load);

    return Scaffold(
      body: _conversations.isEmpty
          ? RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                children: const [
                  SizedBox(height: 120),
                  Center(child: Text('Aucune conversation')),
                ],
              ),
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView.separated(
                itemCount: _conversations.length,
                separatorBuilder: (context, index) => const Divider(height: 1),
                itemBuilder: (context, index) {
                  final conversation = _conversations[index];
                  final participant = conversation.participant;
                  final preview = conversation.lastMessage?.body ?? 'Aucun message';

                  return ListTile(
                    leading: CircleAvatar(child: Text(initialsFromName(participant?.name ?? '?'))),
                    title: Text(participant?.name ?? 'Conversation'),
                    subtitle: Text(preview, maxLines: 1, overflow: TextOverflow.ellipsis),
                    trailing: conversation.unreadCount > 0
                        ? CircleAvatar(
                            radius: 12,
                            backgroundColor: Theme.of(context).colorScheme.primary,
                            child: Text('${conversation.unreadCount}', style: const TextStyle(fontSize: 11)),
                          )
                        : Text(formatRelativeTime(conversation.lastMessageAt)),
                    onTap: () async {
                      await Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => ChatScreen(
                            conversation: conversation,
                            contacts: _contacts,
                          ),
                        ),
                      );
                      _load();
                    },
                  );
                },
              ),
            ),
      floatingActionButton: _contacts.isNotEmpty
          ? FloatingActionButton(
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => NewConversationScreen(contacts: _contacts),
                  ),
                ).then((_) => _load());
              },
              child: const Icon(Icons.edit_outlined),
            )
          : null,
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
  StreamSubscription<PanelNotification>? _notificationSub;

  @override
  void initState() {
    super.initState();
    _load();
    _notificationSub = context.read<RealtimeService>().notificationEvents.listen(
      (notification) {
        if (!mounted) return;
        setState(() {
          _notifications = [
            notification,
            ..._notifications.where((n) => n.id != notification.id),
          ];
        });
      },
    );
  }

  @override
  void dispose() {
    _notificationSub?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await context.read<AuthSession>().api.fetchNotifications();
      if (!mounted) return;
      setState(() => _notifications = data.notifications);
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _markAllRead() async {
    await context.read<AuthSession>().api.markAllNotificationsRead();
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return _ErrorState(message: _error!, onRetry: _load);

    return RefreshIndicator(
      onRefresh: _load,
      child: Column(
        children: [
          if (_notifications.any((n) => n.isUnread))
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(onPressed: _markAllRead, child: const Text('Tout marquer lu')),
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
                        trailing: notification.isUnread ? const Icon(Icons.circle, size: 10) : null,
                        onTap: () async {
                          if (notification.isUnread) {
                            await context.read<AuthSession>().api.markNotificationRead(notification.id);
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
        subtitle: Text('${project.tasksDone}/${project.tasksTotal} tâches · ${project.membersCount} membres'),
        trailing: const Icon(Icons.chevron_right),
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => ProjectScreen(slug: project.slug)),
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
