import '../config/app_config.dart';
import '../models/conversation.dart';
import '../models/direct_message.dart';
import '../models/panel_notification.dart';
import '../models/extras.dart';
import '../models/my_task.dart';
import '../models/project.dart';
import '../models/workspace.dart';
import '../models/user.dart';
import 'api_client.dart';

class LoginResult {
  const LoginResult.authenticated({required this.token, required this.user})
      : twoFactorRequired = false,
        loginToken = null;

  const LoginResult.twoFactorRequired({required this.loginToken})
      : twoFactorRequired = true,
        token = null,
        user = null;

  final bool twoFactorRequired;
  final String? loginToken;
  final String? token;
  final PanelUser? user;
}

class ProjectsResponse {
  const ProjectsResponse({required this.projects, required this.stats});

  final List<PanelProject> projects;
  final DashboardStats stats;
}

class ConversationsResponse {
  const ConversationsResponse({
    required this.conversations,
    required this.contacts,
  });

  final List<Conversation> conversations;
  final List<ConversationParticipant> contacts;
}

class PanelApi {
  PanelApi({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  ApiClient get client => _client;

  Future<LoginResult> login({
    required String email,
    required String password,
  }) {
    return _client.guard(() async {
      final data = await _client.postJson('/login', data: {
        'email': email,
        'password': password,
        'device_name': AppConfig.deviceName,
      });

      if (data['two_factor_required'] == true) {
        return LoginResult.twoFactorRequired(
          loginToken: data['login_token'] as String,
        );
      }

      final token = data['token'] as String;
      final user = PanelUser.fromJson(data['user'] as Map<String, dynamic>);
      await _client.saveToken(token);
      return LoginResult.authenticated(token: token, user: user);
    });
  }

  Future<LoginResult> verifyTwoFactor({
    required String loginToken,
    required String code,
  }) {
    return _client.guard(() async {
      final data = await _client.postJson('/two-factor-challenge', data: {
        'login_token': loginToken,
        'code': code,
        'device_name': AppConfig.deviceName,
      });

      final token = data['token'] as String;
      final user = PanelUser.fromJson(data['user'] as Map<String, dynamic>);
      await _client.saveToken(token);
      return LoginResult.authenticated(token: token, user: user);
    });
  }

  Future<PanelUser> fetchUser() {
    return _client.guard(() async {
      final data = await _client.getJson('/user');
      return PanelUser.fromJson(data['user'] as Map<String, dynamic>);
    });
  }

  Future<void> logout() {
    return _client.guard(() async {
      try {
        await _client.postJson('/logout');
      } finally {
        await _client.clearToken();
      }
    });
  }

  Future<ProjectsResponse> fetchProjects() {
    return _client.guard(() async {
      final data = await _client.getJson('/projects');
      final projects = (data['projects'] as List<dynamic>? ?? [])
          .map((item) => PanelProject.fromJson(item as Map<String, dynamic>))
          .toList();
      final stats = DashboardStats.fromJson(
        data['stats'] as Map<String, dynamic>? ?? {},
      );
      return ProjectsResponse(projects: projects, stats: stats);
    });
  }

  Future<ConversationsResponse> fetchConversations() {
    return _client.guard(() async {
      final data = await _client.getJson('/conversations');
      final conversations = (data['conversations'] as List<dynamic>? ?? [])
          .map((item) => Conversation.fromJson(item as Map<String, dynamic>))
          .toList();
      final contacts = (data['contacts'] as List<dynamic>? ?? [])
          .map(
            (item) => ConversationParticipant.fromJson(
              item as Map<String, dynamic>,
            ),
          )
          .toList();
      return ConversationsResponse(
        conversations: conversations,
        contacts: contacts,
      );
    });
  }

  Future<List<DirectMessage>> fetchMessages(int conversationId) {
    return _client.guard(() async {
      final data =
          await _client.getJson('/conversations/$conversationId/messages');
      return (data['messages'] as List<dynamic>? ?? [])
          .map((item) => DirectMessage.fromJson(item as Map<String, dynamic>))
          .toList();
    });
  }

  Future<void> markConversationRead(int conversationId) {
    return _client.guard(() async {
      await _client.postJson('/conversations/$conversationId/read');
    });
  }

  Future<DirectMessage> sendMessage({
    required int conversationId,
    required String body,
  }) {
    return _client.guard(() async {
      final data = await _client.postJson('/messages', data: {
        'conversation_id': conversationId,
        'body': body,
      });
      return DirectMessage.fromJson(
        data['message'] as Map<String, dynamic>,
      );
    });
  }

  Future<({List<PanelNotification> notifications, int unreadCount})>
      fetchNotifications() {
    return _client.guard(() async {
      final data = await _client.getJson('/notifications');
      final notifications = (data['notifications'] as List<dynamic>? ?? [])
          .map(
            (item) =>
                PanelNotification.fromJson(item as Map<String, dynamic>),
          )
          .toList();
      return (
        notifications: notifications,
        unreadCount: data['unread_count'] as int? ?? 0,
      );
    });
  }

  Future<void> markNotificationRead(int notificationId) {
    return _client.guard(() async {
      await _client.postJson('/notifications/$notificationId/read');
    });
  }

  Future<void> markAllNotificationsRead() {
    return _client.guard(() async {
      await _client.postJson('/notifications/read-all');
    });
  }

  Future<List<MyTask>> fetchMyTasks() {
    return _client.guard(() async {
      final data = await _client.getJson('/my-tasks');
      return (data['tasks'] as List<dynamic>? ?? [])
          .map((item) => MyTask.fromJson(item as Map<String, dynamic>))
          .toList();
    });
  }

  Future<List<SearchResult>> search(String query) {
    return _client.guard(() async {
      final data = await _client.getJson('/search', queryParameters: {'q': query});
      return (data['results'] as List<dynamic>? ?? [])
          .map((item) => SearchResult.fromJson(item as Map<String, dynamic>))
          .toList();
    });
  }

  Future<ProjectWorkspace> fetchProjectWorkspace(
    String slug, {
    String? space,
  }) {
    return _client.guard(() async {
      final data = await _client.getJson(
        '/projects/$slug',
        queryParameters: space != null ? {'space': space} : null,
      );
      return ProjectWorkspace.fromJson(data);
    });
  }

  Future<KanbanTask> createTask({
    required String projectSlug,
    required int listId,
    required String title,
    String? description,
    String? priority,
  }) {
    return _client.guard(() async {
      final data = await _client.postJson('/projects/$projectSlug/tasks', data: {
        'list_id': listId,
        'title': title,
        'description': ?description,
        'priority': ?priority,
      });
      return KanbanTask.fromJson(data['task'] as Map<String, dynamic>);
    });
  }

  Future<KanbanTask> updateTask({
    required String projectSlug,
    required int taskId,
    Map<String, dynamic> fields = const {},
  }) {
    return _client.guard(() async {
      final data =
          await _client.putJson('/projects/$projectSlug/tasks/$taskId', data: fields);
      return KanbanTask.fromJson(data['task'] as Map<String, dynamic>);
    });
  }

  Future<void> moveTask({
    required String projectSlug,
    required int taskId,
    required int listId,
    required List<int> order,
  }) {
    return _client.guard(() async {
      await _client.postJson('/projects/$projectSlug/tasks/$taskId/move', data: {
        'list_id': listId,
        'order': order,
      });
    });
  }

  Future<void> deleteTask({
    required String projectSlug,
    required int taskId,
  }) {
    return _client.guard(() async {
      await _client.deleteJson('/projects/$projectSlug/tasks/$taskId');
    });
  }

  Future<List<WorkspaceChatMessage>> fetchChatMessages(String projectSlug) {
    return _client.guard(() async {
      final data = await _client.getJson('/projects/$projectSlug/chat/messages');
      return (data['messages'] as List<dynamic>? ?? [])
          .map(
            (item) =>
                WorkspaceChatMessage.fromJson(item as Map<String, dynamic>),
          )
          .toList();
    });
  }

  Future<WorkspaceChatMessage> sendChatMessage({
    required String projectSlug,
    required String body,
  }) {
    return _client.guard(() async {
      final data =
          await _client.postJson('/projects/$projectSlug/chat/messages', data: {
        'body': body,
      });
      return WorkspaceChatMessage.fromJson(
        data['message'] as Map<String, dynamic>,
      );
    });
  }

  Future<WorkspaceNote> createNote({
    required String projectSlug,
    required String title,
    String? content,
  }) {
    return _client.guard(() async {
      final data = await _client.postJson('/projects/$projectSlug/notes', data: {
        'title': title,
        'content': ?content,
      });
      return WorkspaceNote.fromJson(data['note'] as Map<String, dynamic>);
    });
  }

  Future<WorkspaceNote> updateNote({
    required String projectSlug,
    required int noteId,
    required String title,
    String? content,
  }) {
    return _client.guard(() async {
      final data =
          await _client.putJson('/projects/$projectSlug/notes/$noteId', data: {
        'title': title,
        'content': ?content,
      });
      return WorkspaceNote.fromJson(data['note'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteNote({
    required String projectSlug,
    required int noteId,
  }) {
    return _client.guard(() async {
      await _client.deleteJson('/projects/$projectSlug/notes/$noteId');
    });
  }

  Future<WorkspaceEvent> createEvent({
    required String projectSlug,
    required String title,
    required String startAt,
    required String endAt,
    String? description,
    String? recurrence,
    List<int> recurrenceWeekdays = const [],
    String? recurrenceUntil,
    int? reminderMinutes,
  }) {
    return _client.guard(() async {
      final data = await _client.postJson('/projects/$projectSlug/events', data: {
        'title': title,
        'start_at': startAt,
        'end_at': endAt,
        'description': ?description,
        'recurrence': ?recurrence,
        'recurrence_weekdays': recurrence == 'weekly' ? recurrenceWeekdays : null,
        'recurrence_until': ?recurrenceUntil,
        'reminder_minutes': ?reminderMinutes,
      });
      return WorkspaceEvent.fromJson(data['event'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteEvent({
    required String projectSlug,
    required int eventId,
    String? deleteScope,
    String? occurrenceDate,
  }) {
    return _client.guard(() async {
      final query = <String, String>{};
      if (deleteScope != null) query['delete_scope'] = deleteScope;
      if (occurrenceDate != null) query['occurrence_date'] = occurrenceDate;
      final suffix = query.isEmpty
          ? ''
          : '?${query.entries.map((e) => '${e.key}=${Uri.encodeComponent(e.value)}').join('&')}';
      await _client.deleteJson('/projects/$projectSlug/events/$eventId$suffix');
    });
  }

  Future<WorkspaceBug> createBug({
    required String projectSlug,
    required String title,
    String? description,
    String? priority,
  }) {
    return _client.guard(() async {
      final data = await _client.postJson('/projects/$projectSlug/bugs', data: {
        'title': title,
        'description': ?description,
        'priority': ?priority,
      });
      return WorkspaceBug.fromJson(data['bug'] as Map<String, dynamic>);
    });
  }

  Future<WorkspaceBug> updateBug({
    required String projectSlug,
    required int bugId,
    required Map<String, dynamic> fields,
  }) {
    return _client.guard(() async {
      final data =
          await _client.putJson('/projects/$projectSlug/bugs/$bugId', data: fields);
      return WorkspaceBug.fromJson(data['bug'] as Map<String, dynamic>);
    });
  }

  Future<WorkspaceBug> linkBugTask({
    required String projectSlug,
    required int bugId,
    required int taskId,
  }) {
    return _client.guard(() async {
      final data = await _client.postJson(
        '/projects/$projectSlug/bugs/$bugId/link-task',
        data: {'task_id': taskId},
      );
      return WorkspaceBug.fromJson(data['bug'] as Map<String, dynamic>);
    });
  }

  Future<Map<String, dynamic>> createTaskFromBug({
    required String projectSlug,
    required int bugId,
  }) {
    return _client.guard(() async {
      return await _client.postJson(
        '/projects/$projectSlug/bugs/$bugId/create-task',
        data: {},
      );
    });
  }

  Future<WorkspaceSheet> updateSheet({
    required String projectSlug,
    required int sheetId,
    Map<String, dynamic>? data,
    String? name,
  }) {
    return _client.guard(() async {
      final payload = <String, dynamic>{
        'data': ?data,
        'name': ?name,
      };
      final response =
          await _client.putJson('/projects/$projectSlug/sheets/$sheetId', data: payload);
      return WorkspaceSheet.fromJson(response['sheet'] as Map<String, dynamic>);
    });
  }

  Future<WorkspaceSheet> createSheet({
    required String projectSlug,
    String? name,
  }) {
    return _client.guard(() async {
      final response = await _client.postJson('/projects/$projectSlug/sheets', data: {
        'name': ?name,
      });
      return WorkspaceSheet.fromJson(response['sheet'] as Map<String, dynamic>);
    });
  }

  Future<List<ProjectRank>> fetchRanks(String projectSlug) {
    return _client.guard(() async {
      final data = await _client.getJson('/projects/$projectSlug/ranks');
      return (data['ranks'] as List<dynamic>? ?? [])
          .map((e) => ProjectRank.fromJson(e as Map<String, dynamic>))
          .toList();
    });
  }

  Future<List<RankDashboardEntry>> fetchRankDashboard(String projectSlug) {
    return _client.guard(() async {
      final data = await _client.getJson('/projects/$projectSlug/ranks/dashboard');
      return (data['ranks'] as List<dynamic>? ?? [])
          .map((e) => RankDashboardEntry.fromJson(e as Map<String, dynamic>))
          .toList();
    });
  }

  Future<List<TeamMember>> fetchTeam(String projectSlug) {
    return _client.guard(() async {
      final data = await _client.getJson('/projects/$projectSlug/team');
      return (data['teamMembers'] as List<dynamic>? ?? [])
          .map((e) => TeamMember.fromJson(e as Map<String, dynamic>))
          .toList();
    });
  }

  Future<void> addTeamMember({
    required String projectSlug,
    required int userId,
    String role = 'member',
  }) {
    return _client.guard(() async {
      await _client.postJson('/projects/$projectSlug/members', data: {
        'user_id': userId,
        'role': role,
      });
    });
  }

  Future<void> removeTeamMember({
    required String projectSlug,
    required int userId,
  }) {
    return _client.guard(() async {
      await _client.deleteJson('/projects/$projectSlug/members/$userId');
    });
  }

  Future<void> heartbeat() {
    return _client.guard(() async {
      await _client.postJson('/realtime/heartbeat');
    });
  }

  Future<RealtimeSyncResult> syncRealtime({String? since}) {
    return _client.guard(() async {
      final data = await _client.getJson(
        '/realtime/sync',
        queryParameters: since != null ? {'since': since} : null,
      );
      return RealtimeSyncResult.fromJson(data);
    });
  }

  Future<void> registerPushToken({
    required String platform,
    required String token,
  }) {
    return _client.guard(() async {
      await _client.postJson('/push-tokens', data: {
        'platform': platform,
        'token': token,
        'device_name': AppConfig.deviceName,
      });
    });
  }

  Future<({Map<String, bool> preferences, Map<String, String> labels})>
      fetchNotificationPreferences() {
    return _client.guard(() async {
      final data = await _client.getJson('/profile/notifications');
      final prefsRaw = data['preferences'] as Map<String, dynamic>? ?? {};
      final labelsRaw = data['labels'] as Map<String, dynamic>? ?? {};
      return (
        preferences: prefsRaw.map((k, v) => MapEntry(k, v == true)),
        labels: labelsRaw.map((k, v) => MapEntry(k, v.toString())),
      );
    });
  }

  Future<Map<String, bool>> updateNotificationPreferences(
    Map<String, bool> preferences,
  ) {
    return _client.guard(() async {
      final data = await _client.putJson('/profile/notifications', data: {
        'preferences': preferences,
      });
      final prefsRaw = data['preferences'] as Map<String, dynamic>? ?? {};
      return prefsRaw.map((k, v) => MapEntry(k, v == true));
    });
  }

  Future<List<AdminUser>> fetchAdminUsers() {
    return _client.guard(() async {
      final data = await _client.getJson('/admin/users');
      return (data['users'] as List<dynamic>? ?? [])
          .map((e) => AdminUser.fromJson(e as Map<String, dynamic>))
          .toList();
    });
  }

  Future<List<AdminProject>> fetchAdminProjects() {
    return _client.guard(() async {
      final data = await _client.getJson('/admin/projects');
      return (data['projects'] as List<dynamic>? ?? [])
          .map((e) => AdminProject.fromJson(e as Map<String, dynamic>))
          .toList();
    });
  }

  Future<void> toggleAdminUserActive(int userId) {
    return _client.guard(() async {
      await _client.postJson('/admin/users/$userId/toggle-active');
    });
  }

  Future<RealtimeConfig> fetchRealtimeConfig() {
    return _client.guard(() async {
      final data = await _client.getJson('/realtime/config');
      final reverb = data['reverb'] as Map<String, dynamic>? ?? {};
      return RealtimeConfig(
        echoAvailable: data['echo_available'] == true,
        authEndpoint: data['auth_endpoint'] as String? ??
            '${AppConfig.panelBaseUrl}/api/v1/broadcasting/auth',
        key: reverb['key'] as String? ?? '',
        host: reverb['host'] as String? ?? '',
        port: reverb['port'] as int? ?? 443,
        scheme: reverb['scheme'] as String? ?? 'https',
      );
    });
  }
}

class RealtimeConfig {
  const RealtimeConfig({
    required this.echoAvailable,
    required this.authEndpoint,
    required this.key,
    required this.host,
    required this.port,
    required this.scheme,
  });

  final bool echoAvailable;
  final String authEndpoint;
  final String key;
  final String host;
  final int port;
  final String scheme;

  bool get useTls => scheme == 'https';
}
