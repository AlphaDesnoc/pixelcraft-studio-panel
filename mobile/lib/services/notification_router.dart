import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/panel_notification.dart';
import '../screens/chat_screen.dart';
import '../screens/home_screen.dart';
import '../screens/project_screen.dart';
import '../services/auth_session.dart';

class NotificationRoute {
  const NotificationRoute({
    this.projectSlug,
    this.initialTab,
    this.initialSpace,
    this.taskId,
    this.bugId,
    this.conversationId,
    this.openMessages = false,
  });

  final String? projectSlug;
  final String? initialTab;
  final String? initialSpace;
  final int? taskId;
  final int? bugId;
  final int? conversationId;
  final bool openMessages;
}

/// Parses notification [url], [type], and [data] into navigation targets.
class NotificationRouter {
  static NotificationRoute? parse(PanelNotification notification) {
    final fromUrl = _parseUrl(notification.url);
    if (fromUrl != null) return fromUrl;

    final data = notification.data;
    if (data == null || data.isEmpty) return null;

    return _parseData(notification.type, data);
  }

  static NotificationRoute? _parseUrl(String? rawUrl) {
    if (rawUrl == null || rawUrl.trim().isEmpty) return null;

    var path = rawUrl.trim();
    final schemeIndex = path.indexOf('://');
    if (schemeIndex >= 0) {
      final afterScheme = path.substring(schemeIndex + 3);
      final slash = afterScheme.indexOf('/');
      path = slash >= 0 ? afterScheme.substring(slash) : '/$afterScheme';
    }

    final uri = Uri.parse(path.startsWith('http') ? path : 'http://local$path');
    final query = uri.queryParameters;
    path = uri.path;

    if (path.startsWith('/messages')) {
      final conversationId = int.tryParse(query['c'] ?? '');
      return NotificationRoute(
        openMessages: true,
        conversationId: conversationId,
      );
    }

    final projectMatch = RegExp(r'^/projects/([^/]+)/?$').firstMatch(path);
    if (projectMatch == null) return null;

    final slug = projectMatch.group(1)!;
    final taskId = int.tryParse(query['task'] ?? '');
    final bugId = int.tryParse(query['bug'] ?? '');

    return NotificationRoute(
      projectSlug: slug,
      initialTab: query['tab'],
      initialSpace: query['space'],
      taskId: taskId,
      bugId: bugId,
    );
  }

  static NotificationRoute? _parseData(String? type, Map<String, dynamic> data) {
    final taskId = _intFrom(data['task_id']);
    final bugId = _intFrom(data['bug_id']);
    final conversationId = _intFrom(data['conversation_id'] ?? data['direct_conversation_id']);
    final slug = data['project_slug'] as String?;

    if (slug != null && slug.isNotEmpty) {
      return NotificationRoute(
        projectSlug: slug,
        initialTab: _tabForType(type, taskId: taskId, bugId: bugId),
        taskId: taskId,
        bugId: bugId,
        conversationId: conversationId,
      );
    }

    if (conversationId != null || type == 'direct_message') {
      return NotificationRoute(openMessages: true, conversationId: conversationId);
    }

    return null;
  }

  static String? _tabForType(String? type, {int? taskId, int? bugId}) {
    if (bugId != null) return 'bugs';
    if (taskId != null) return 'kanban';
    return switch (type) {
      'bug_assigned' || 'bug_sla_breach' => 'bugs',
      'chat_message' || 'chat_mention' => 'chat',
      'task_assigned' ||
      'due_tomorrow' ||
      'due_today' ||
      'overdue' ||
      'task_reminder' ||
      'task_comment_mention' =>
        'kanban',
      'calendar_reminder' => 'calendar',
      _ => null,
    };
  }

  static int? _intFrom(dynamic value) {
    if (value is int) return value;
    if (value is String) return int.tryParse(value);
    return null;
  }

  static Future<void> navigate(BuildContext context, NotificationRoute route) async {
    if (route.openMessages) {
      if (route.conversationId != null) {
        await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => _ConversationLoaderScreen(conversationId: route.conversationId!),
          ),
        );
      } else {
        await Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const HomeScreen(initialTabIndex: 2)),
          (r) => r.isFirst,
        );
      }
      return;
    }

    final slug = route.projectSlug;
    if (slug == null || slug.isEmpty) return;

    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ProjectScreen(
          slug: slug,
          initialTab: route.initialTab,
          initialSpace: route.initialSpace,
          taskId: route.taskId,
          bugId: route.bugId,
        ),
      ),
    );
  }

  static Future<void> openNotification(
    BuildContext context,
    PanelNotification notification,
  ) async {
    final route = parse(notification);
    if (route != null) {
      await navigate(context, route);
    }
  }

  static Future<void> openPayload(BuildContext context, String? payload) async {
    if (payload == null || payload.isEmpty) return;
    if (payload.startsWith('dm:')) {
      final id = int.tryParse(payload.substring(3));
      if (id != null) {
        await navigate(context, NotificationRoute(openMessages: true, conversationId: id));
      }
      return;
    }
    await openNotification(
      context,
      PanelNotification(id: 0, title: '', body: '', url: payload),
    );
  }
}

class _ConversationLoaderScreen extends StatefulWidget {
  const _ConversationLoaderScreen({required this.conversationId});

  final int conversationId;

  @override
  State<_ConversationLoaderScreen> createState() => _ConversationLoaderScreenState();
}

class _ConversationLoaderScreenState extends State<_ConversationLoaderScreen> {
  @override
  void initState() {
    super.initState();
    _open();
  }

  Future<void> _open() async {
    try {
      final data = await context.read<AuthSession>().api.fetchConversations();
      final conversation = data.conversations
          .where((c) => c.id == widget.conversationId)
          .firstOrNull;
      if (!mounted) return;
      if (conversation == null) {
        Navigator.pop(context);
        return;
      }
      await Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => ChatScreen(
            conversation: conversation,
            contacts: data.contacts,
          ),
        ),
      );
    } catch (_) {
      if (mounted) Navigator.pop(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(body: Center(child: CircularProgressIndicator()));
  }
}
