import 'package:dio/dio.dart';

import '../models/attachment.dart';
import '../models/direct_message.dart';
import '../models/extras.dart';
import '../models/workspace.dart';
import 'panel_api.dart';

extension PanelApiMessaging on PanelApi {
  Future<DirectMessage> sendDirectMessage({
    int? conversationId,
    int? recipientId,
    required String body,
    int? replyToId,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/messages', data: {
        if (conversationId != null) 'conversation_id': conversationId,
        if (recipientId != null) 'recipient_id': recipientId,
        'body': body,
        if (replyToId != null) 'reply_to_id': replyToId,
      });
      return DirectMessage.fromJson(data['message'] as Map<String, dynamic>);
    });
  }

  Future<WorkspaceChatMessage> sendProjectChatMessage({
    required String projectSlug,
    required String body,
    int? replyToId,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/projects/$projectSlug/chat/messages', data: {
        'body': body,
        if (replyToId != null) 'reply_to_id': replyToId,
      });
      return WorkspaceChatMessage.fromJson(
        data['message'] as Map<String, dynamic>,
      );
    });
  }
}

extension PanelApiProfile on PanelApi {
  Future<void> updatePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) {
    return client.guard(() async {
      await client.putJson('/profile/password', data: {
        'current_password': currentPassword,
        'password': password,
        'password_confirmation': passwordConfirmation,
      });
    });
  }

  Future<String> updateTheme(String themePreference) {
    return client.guard(() async {
      final data = await client.putJson('/profile/theme', data: {
        'theme_preference': themePreference,
      });
      return data['theme_preference'] as String? ?? themePreference;
    });
  }

  Future<String> setupTwoFactor() {
    return client.guard(() async {
      final data = await client.postJson('/profile/two-factor/setup');
      return data['otpauth_uri'] as String? ?? '';
    });
  }

  Future<List<String>> confirmTwoFactor(String code) {
    return client.guard(() async {
      final data = await client.postJson('/profile/two-factor/confirm', data: {
        'code': code,
      });
      return (data['recovery_codes'] as List<dynamic>? ?? [])
          .map((e) => e.toString())
          .toList();
    });
  }

  Future<void> disableTwoFactor() {
    return client.guard(() async {
      await client.deleteJson('/profile/two-factor');
    });
  }

  Future<List<int>> downloadExport(String path) {
    return client.guard(() => client.downloadBytes(path));
  }

  Future<DirectMessage> sendConversationAttachment({
    required int conversationId,
    required String filePath,
    required String fileName,
    int? replyToId,
  }) {
    return client.guard(() async {
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(filePath, filename: fileName),
        if (replyToId != null) 'reply_to_id': replyToId,
      });
      final data = await client.postMultipart(
        '/conversations/$conversationId/attachments',
        data: form,
      );
      return DirectMessage.fromJson(data['message'] as Map<String, dynamic>);
    });
  }

  Future<List<MessageReaction>> toggleDirectMessageReaction({
    required int messageId,
    required String emoji,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/messages/$messageId/reactions', data: {
        'emoji': emoji,
      });
      return (data['reactions'] as List<dynamic>? ?? [])
          .map((e) => MessageReaction.fromJson(e as Map<String, dynamic>))
          .toList();
    });
  }
}

extension PanelApiAdmin on PanelApi {
  Future<List<Map<String, dynamic>>> fetchAdminAudit({
    String? action,
    int? userId,
  }) {
    return client.guard(() async {
      final data = await client.getJson('/admin/audit', queryParameters: {
        if (action != null) 'action': action,
        if (userId != null) 'user_id': userId,
      });
      return (data['logs'] as List<dynamic>? ?? [])
          .map((e) => e as Map<String, dynamic>)
          .toList();
    });
  }

  Future<AdminUser> createAdminUser({
    required String name,
    required String pseudo,
    required String password,
    required String role,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/admin/users', data: {
        'name': name,
        'pseudo': pseudo,
        'password': password,
        'role': role,
      });
      return AdminUser.fromJson(data['user'] as Map<String, dynamic>);
    });
  }

  Future<AdminUser> updateAdminUser({
    required int userId,
    required String name,
    required String pseudo,
    required String role,
    String? password,
  }) {
    return client.guard(() async {
      final data = await client.putJson('/admin/users/$userId', data: {
        'name': name,
        'pseudo': pseudo,
        'role': role,
        if (password != null && password.isNotEmpty) 'password': password,
      });
      return AdminUser.fromJson(data['user'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteAdminUser(int userId) {
    return client.guard(() async {
      await client.deleteJson('/admin/users/$userId');
    });
  }

  Future<AdminProject> createAdminProject({
    required String name,
    String? description,
    required String status,
    int? templateId,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/admin/projects', data: {
        'name': name,
        'description': description,
        'status': status,
        if (templateId != null) 'template_id': templateId,
      });
      return AdminProject.fromJson(data['project'] as Map<String, dynamic>);
    });
  }

  Future<AdminProject> updateAdminProject({
    required int projectId,
    required String name,
    String? description,
    required String status,
  }) {
    return client.guard(() async {
      final data = await client.putJson('/admin/projects/$projectId', data: {
        'name': name,
        'description': description,
        'status': status,
      });
      return AdminProject.fromJson(data['project'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteAdminProject(int projectId) {
    return client.guard(() async {
      await client.deleteJson('/admin/projects/$projectId');
    });
  }
}

extension PanelApiWorkspace on PanelApi {
  String _slug(String projectSlug) => projectSlug;

  Future<KanbanList> createList({
    required String projectSlug,
    required String name,
    String? color,
    String? statusKind,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/projects/${_slug(projectSlug)}/lists', data: {
        'name': name,
        if (color != null) 'color': color,
        if (statusKind != null) 'status_kind': statusKind,
      });
      return KanbanList.fromJson(data['list'] as Map<String, dynamic>);
    });
  }

  Future<KanbanList> updateList({
    required String projectSlug,
    required int listId,
    required Map<String, dynamic> fields,
  }) {
    return client.guard(() async {
      final data =
          await client.putJson('/projects/${_slug(projectSlug)}/lists/$listId', data: fields);
      return KanbanList.fromJson(data['list'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteList({
    required String projectSlug,
    required int listId,
  }) {
    return client.guard(() async {
      await client.deleteJson('/projects/${_slug(projectSlug)}/lists/$listId');
    });
  }

  Future<void> reorderLists({
    required String projectSlug,
    required List<int> order,
  }) {
    return client.guard(() async {
      await client.postJson('/projects/${_slug(projectSlug)}/lists/reorder', data: {
        'order': order,
      });
    });
  }

  Future<KanbanTask> duplicateTask({
    required String projectSlug,
    required int taskId,
  }) {
    return client.guard(() async {
      final data =
          await client.postJson('/projects/${_slug(projectSlug)}/tasks/$taskId/duplicate');
      return KanbanTask.fromJson(data['task'] as Map<String, dynamic>);
    });
  }

  Future<void> archiveTask({
    required String projectSlug,
    required int taskId,
  }) {
    return client.guard(() async {
      await client.postJson('/projects/${_slug(projectSlug)}/tasks/$taskId/archive');
    });
  }

  Future<void> unarchiveTask({
    required String projectSlug,
    required int taskId,
  }) {
    return client.guard(() async {
      await client.postJson('/projects/${_slug(projectSlug)}/tasks/$taskId/unarchive');
    });
  }

  Future<void> syncTaskTags({
    required String projectSlug,
    required int taskId,
    required List<int> tagIds,
  }) {
    return client.guard(() async {
      await client.putJson('/projects/${_slug(projectSlug)}/tasks/$taskId/tags', data: {
        'tag_ids': tagIds,
      });
    });
  }

  Future<TaskTag> createTag({
    required String projectSlug,
    required String name,
    required String color,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/projects/${_slug(projectSlug)}/tags', data: {
        'name': name,
        'color': color,
      });
      return TaskTag.fromJson(data['tag'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteTag({
    required String projectSlug,
    required int tagId,
  }) {
    return client.guard(() async {
      await client.deleteJson('/projects/${_slug(projectSlug)}/tags/$tagId');
    });
  }

  Future<TaskComment> addTaskComment({
    required String projectSlug,
    required int taskId,
    required String body,
  }) {
    return client.guard(() async {
      final data = await client.postJson(
        '/projects/${_slug(projectSlug)}/tasks/$taskId/comments',
        data: {'body': body},
      );
      return TaskComment.fromJson(data['comment'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteTaskComment({
    required String projectSlug,
    required int taskId,
    required int commentId,
  }) {
    return client.guard(() async {
      await client.deleteJson(
        '/projects/${_slug(projectSlug)}/tasks/$taskId/comments/$commentId',
      );
    });
  }

  Future<PanelAttachment> uploadTaskAttachment({
    required String projectSlug,
    required int taskId,
    required String filePath,
    required String fileName,
  }) {
    return client.guard(() async {
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(filePath, filename: fileName),
      });
      final data = await client.postMultipart(
        '/projects/${_slug(projectSlug)}/tasks/$taskId/attachments',
        data: form,
      );
      return PanelAttachment.fromJson(data['attachment'] as Map<String, dynamic>);
    });
  }

  Future<TaskChecklist> createChecklist({
    required String projectSlug,
    required int taskId,
    required String name,
  }) {
    return client.guard(() async {
      final data = await client.postJson(
        '/projects/${_slug(projectSlug)}/tasks/$taskId/checklists',
        data: {'name': name},
      );
      return TaskChecklist.fromJson(data['checklist'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteChecklist({
    required String projectSlug,
    required int taskId,
    required int checklistId,
  }) {
    return client.guard(() async {
      await client.deleteJson(
        '/projects/${_slug(projectSlug)}/tasks/$taskId/checklists/$checklistId',
      );
    });
  }

  Future<TaskChecklistItem> addChecklistItem({
    required String projectSlug,
    required int taskId,
    required int checklistId,
    required String content,
  }) {
    return client.guard(() async {
      final data = await client.postJson(
        '/projects/${_slug(projectSlug)}/tasks/$taskId/checklists/$checklistId/items',
        data: {'content': content},
      );
      return TaskChecklistItem.fromJson(data['item'] as Map<String, dynamic>);
    });
  }

  Future<TaskChecklistItem> updateChecklistItem({
    required String projectSlug,
    required int taskId,
    required int checklistId,
    required int itemId,
    required Map<String, dynamic> fields,
  }) {
    return client.guard(() async {
      final data = await client.putJson(
        '/projects/${_slug(projectSlug)}/tasks/$taskId/checklists/$checklistId/items/$itemId',
        data: fields,
      );
      return TaskChecklistItem.fromJson(data['item'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteChecklistItem({
    required String projectSlug,
    required int taskId,
    required int checklistId,
    required int itemId,
  }) {
    return client.guard(() async {
      await client.deleteJson(
        '/projects/${_slug(projectSlug)}/tasks/$taskId/checklists/$checklistId/items/$itemId',
      );
    });
  }

  Future<void> reorderChecklistItems({
    required String projectSlug,
    required int taskId,
    required int checklistId,
    required List<int> order,
  }) {
    return client.guard(() async {
      await client.postJson(
        '/projects/${_slug(projectSlug)}/tasks/$taskId/checklists/$checklistId/items/reorder',
        data: {'order': order},
      );
    });
  }

  Future<WorkspaceChatMessage> updateChatMessage({
    required String projectSlug,
    required int messageId,
    required String body,
  }) {
    return client.guard(() async {
      final data = await client.putJson(
        '/projects/${_slug(projectSlug)}/chat/messages/$messageId',
        data: {'body': body},
      );
      return WorkspaceChatMessage.fromJson(data['message'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteChatMessage({
    required String projectSlug,
    required int messageId,
  }) {
    return client.guard(() async {
      await client.deleteJson('/projects/${_slug(projectSlug)}/chat/messages/$messageId');
    });
  }

  Future<void> pinChatMessage({
    required String projectSlug,
    required int messageId,
  }) {
    return client.guard(() async {
      await client.postJson('/projects/${_slug(projectSlug)}/chat/messages/$messageId/pin');
    });
  }

  Future<List<MessageReaction>> toggleChatReaction({
    required String projectSlug,
    required int messageId,
    required String emoji,
  }) {
    return client.guard(() async {
      final data = await client.postJson(
        '/projects/${_slug(projectSlug)}/chat/messages/$messageId/reactions',
        data: {'emoji': emoji},
      );
      return (data['reactions'] as List<dynamic>? ?? [])
          .map((e) => MessageReaction.fromJson(e as Map<String, dynamic>))
          .toList();
    });
  }

  Future<WorkspaceChatMessage> uploadChatAttachment({
    required String projectSlug,
    required String filePath,
    required String fileName,
    int? replyToId,
  }) {
    return client.guard(() async {
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(filePath, filename: fileName),
        if (replyToId != null) 'reply_to_id': replyToId,
      });
      final data = await client.postMultipart(
        '/projects/${_slug(projectSlug)}/chat/attachments',
        data: form,
      );
      return WorkspaceChatMessage.fromJson(data['message'] as Map<String, dynamic>);
    });
  }

  Future<WorkspaceEvent> updateEvent({
    required String projectSlug,
    required int eventId,
    required Map<String, dynamic> fields,
  }) {
    return client.guard(() async {
      final data =
          await client.putJson('/projects/${_slug(projectSlug)}/events/$eventId', data: fields);
      return WorkspaceEvent.fromJson(data['event'] as Map<String, dynamic>);
    });
  }

  Future<void> toggleNotePin({
    required String projectSlug,
    required int noteId,
  }) {
    return client.guard(() async {
      await client.postJson('/projects/${_slug(projectSlug)}/notes/$noteId/pin');
    });
  }

  Future<void> deleteSheet({
    required String projectSlug,
    required int sheetId,
  }) {
    return client.guard(() async {
      await client.deleteJson('/projects/${_slug(projectSlug)}/sheets/$sheetId');
    });
  }

  Future<void> reorderSheets({
    required String projectSlug,
    required List<int> order,
  }) {
    return client.guard(() async {
      await client.postJson('/projects/${_slug(projectSlug)}/sheets/reorder', data: {
        'order': order,
      });
    });
  }

  Future<WorkspaceFileNode> createFileFolder({
    required String projectSlug,
    required String name,
    int? parentId,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/projects/${_slug(projectSlug)}/files/folder', data: {
        'name': name,
        if (parentId != null) 'parent_id': parentId,
      });
      return WorkspaceFileNode.fromJson(data['node'] as Map<String, dynamic>);
    });
  }

  Future<WorkspaceFileNode> uploadFile({
    required String projectSlug,
    required String filePath,
    required String fileName,
    int? parentId,
  }) {
    return client.guard(() async {
      final form = FormData.fromMap({
        'file': await MultipartFile.fromFile(filePath, filename: fileName),
        if (parentId != null) 'parent_id': parentId,
      });
      final data = await client.postMultipart(
        '/projects/${_slug(projectSlug)}/files/upload',
        data: form,
      );
      return WorkspaceFileNode.fromJson(data['node'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteFileNode({
    required String projectSlug,
    required int nodeId,
  }) {
    return client.guard(() async {
      await client.deleteJson('/projects/${_slug(projectSlug)}/files/$nodeId');
    });
  }

  Future<void> updateTeamMember({
    required String projectSlug,
    required int userId,
    required String role,
  }) {
    return client.guard(() async {
      await client.putJson('/projects/${_slug(projectSlug)}/members/$userId', data: {
        'role': role,
      });
    });
  }

  Future<void> updateTeamPermissions({
    required String projectSlug,
    required int userId,
    required Map<String, bool> permissions,
  }) {
    return client.guard(() async {
      await client.putJson(
        '/projects/${_slug(projectSlug)}/members/$userId/permissions',
        data: {'permissions': permissions},
      );
    });
  }

  Future<List<ActivityLogEntry>> fetchProjectActivityLogs(String projectSlug) {
    return client.guard(() async {
      final data = await client.getJson('/projects/${_slug(projectSlug)}/activity-logs');
      return (data['logs'] as List<dynamic>? ?? [])
          .map((e) => ActivityLogEntry.fromJson(e as Map<String, dynamic>))
          .toList();
    });
  }

  Future<ProjectRank> createRank({
    required String projectSlug,
    required String name,
    required String color,
    bool managesBugs = false,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/projects/${_slug(projectSlug)}/ranks', data: {
        'name': name,
        'color': color,
        'manages_bugs': managesBugs,
      });
      return ProjectRank.fromJson(data['rank'] as Map<String, dynamic>);
    });
  }

  Future<ProjectRank> updateRank({
    required String projectSlug,
    required int rankId,
    required Map<String, dynamic> fields,
  }) {
    return client.guard(() async {
      final data =
          await client.putJson('/projects/${_slug(projectSlug)}/ranks/$rankId', data: fields);
      return ProjectRank.fromJson(data['rank'] as Map<String, dynamic>);
    });
  }

  Future<void> deleteRank({
    required String projectSlug,
    required int rankId,
  }) {
    return client.guard(() async {
      await client.deleteJson('/projects/${_slug(projectSlug)}/ranks/$rankId');
    });
  }

  Future<void> addRankMember({
    required String projectSlug,
    required int rankId,
    required int userId,
  }) {
    return client.guard(() async {
      await client.postJson('/projects/${_slug(projectSlug)}/ranks/$rankId/members', data: {
        'user_id': userId,
      });
    });
  }

  Future<void> removeRankMember({
    required String projectSlug,
    required int rankId,
    required int userId,
  }) {
    return client.guard(() async {
      await client.deleteJson(
        '/projects/${_slug(projectSlug)}/ranks/$rankId/members/$userId',
      );
    });
  }

  Future<void> deleteBug({
    required String projectSlug,
    required int bugId,
  }) {
    return client.guard(() async {
      await client.deleteJson('/projects/${_slug(projectSlug)}/bugs/$bugId');
    });
  }

  Future<List<BugMessage>> fetchBugMessages({
    required String projectSlug,
    required int bugId,
  }) {
    return client.guard(() async {
      final data =
          await client.getJson('/projects/${_slug(projectSlug)}/bugs/$bugId/messages');
      return (data['messages'] as List<dynamic>? ?? [])
          .map((e) => BugMessage.fromJson(e as Map<String, dynamic>))
          .toList();
    });
  }

  Future<BugMessage> postBugMessage({
    required String projectSlug,
    required int bugId,
    required String body,
  }) {
    return client.guard(() async {
      final data = await client.postJson(
        '/projects/${_slug(projectSlug)}/bugs/$bugId/messages',
        data: {'body': body},
      );
      return BugMessage.fromJson(data['message'] as Map<String, dynamic>);
    });
  }
}

extension PanelApiProductivity on PanelApi {
  Future<Map<String, dynamic>> fetchTaskTimerStatus({
    required String projectSlug,
    required int taskId,
  }) {
    return client.guard(() async {
      return await client.getJson('/projects/$projectSlug/tasks/$taskId/timer');
    });
  }

  Future<KanbanTask> startTaskTimer({
    required String projectSlug,
    required int taskId,
  }) {
    return client.guard(() async {
      final data =
          await client.postJson('/projects/$projectSlug/tasks/$taskId/timer/start');
      return KanbanTask.fromJson(data['task'] as Map<String, dynamic>);
    });
  }

  Future<KanbanTask> stopTaskTimer({
    required String projectSlug,
    required int taskId,
  }) {
    return client.guard(() async {
      final data =
          await client.postJson('/projects/$projectSlug/tasks/$taskId/timer/stop');
      return KanbanTask.fromJson(data['task'] as Map<String, dynamic>);
    });
  }

  Future<KanbanTask> snoozeTask({
    required String projectSlug,
    required int taskId,
    required String duration,
    DateTime? until,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/projects/$projectSlug/tasks/$taskId/snooze', data: {
        'duration': duration,
        if (until != null) 'until': until.toUtc().toIso8601String(),
      });
      return KanbanTask.fromJson(data['task'] as Map<String, dynamic>);
    });
  }

  Future<KanbanTask> clearTaskSnooze({
    required String projectSlug,
    required int taskId,
  }) {
    return client.guard(() async {
      final data = await client.deleteJson('/projects/$projectSlug/tasks/$taskId/snooze');
      return KanbanTask.fromJson(data['task'] as Map<String, dynamic>);
    });
  }

  Future<Map<String, dynamic>> createTaskReminder({
    required String projectSlug,
    required int taskId,
    required DateTime remindAt,
  }) {
    return client.guard(() async {
      return await client.postJson('/projects/$projectSlug/tasks/$taskId/reminders', data: {
        'remind_at': remindAt.toUtc().toIso8601String(),
      });
    });
  }

  Future<void> deleteTaskReminder({
    required String projectSlug,
    required int taskId,
    required int reminderId,
  }) {
    return client.guard(() async {
      await client.deleteJson(
        '/projects/$projectSlug/tasks/$taskId/reminders/$reminderId',
      );
    });
  }

  Future<int> bulkTasks({
    required String projectSlug,
    required String action,
    required List<int> taskIds,
    int? assigneeId,
    int? tagId,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/projects/$projectSlug/tasks/bulk', data: {
        'action': action,
        'task_ids': taskIds,
        if (assigneeId != null) 'assignee_id': assigneeId,
        if (tagId != null) 'tag_id': tagId,
      });
      return data['updated'] as int? ?? taskIds.length;
    });
  }

  Future<List<KanbanSavedView>> fetchKanbanSavedViews(String projectSlug) {
    return client.guard(() async {
      final data = await client.getJson('/projects/$projectSlug/kanban/views');
      return (data['views'] as List<dynamic>? ?? [])
          .map((e) => KanbanSavedView.fromJson(e as Map<String, dynamic>))
          .toList();
    });
  }

  Future<KanbanSavedView> createKanbanSavedView({
    required String projectSlug,
    required String name,
    required Map<String, dynamic> filters,
    bool isShared = true,
  }) {
    return client.guard(() async {
      final data = await client.postJson('/projects/$projectSlug/kanban/views', data: {
        'name': name,
        'filters': filters,
        'is_shared': isShared,
      });
      return KanbanSavedView.fromJson(data['view'] as Map<String, dynamic>);
    });
  }

  Future<KanbanTask> applyTaskTemplate({
    required String projectSlug,
    required int taskId,
    required int templateId,
  }) {
    return client.guard(() async {
      final data = await client.postJson(
        '/projects/$projectSlug/tasks/$taskId/templates/apply',
        data: {'template_id': templateId},
      );
      return KanbanTask.fromJson(data['task'] as Map<String, dynamic>);
    });
  }

  Future<KanbanTask> updateTaskDependencies({
    required String projectSlug,
    required int taskId,
    required List<int> dependencyIds,
  }) {
    return client.guard(() async {
      final data = await client.putJson('/projects/$projectSlug/tasks/$taskId', data: {
        'dependency_ids': dependencyIds,
      });
      return KanbanTask.fromJson(data['task'] as Map<String, dynamic>);
    });
  }

  Future<void> sendPresenceHeartbeat({
    required String projectSlug,
    required String context,
    int? taskId,
  }) {
    return client.guard(() async {
      await client.postJson('/projects/$projectSlug/presence', data: {
        'context': context,
        if (taskId != null) 'task_id': taskId,
      });
    });
  }

  Future<AdminPortfolioData> fetchAdminPortfolio() {
    return client.guard(() async {
      final data = await client.getJson('/admin/portfolio');
      return AdminPortfolioData.fromJson(data);
    });
  }
}
