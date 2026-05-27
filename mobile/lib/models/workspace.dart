import 'attachment.dart';
import 'extras.dart';

class ProjectWorkspace {
  const ProjectWorkspace({
    required this.project,
    required this.activeSpace,
    required this.spaceLabel,
    required this.stats,
    required this.progress,
    required this.lists,
    required this.notes,
    required this.events,
    required this.bugs,
    required this.fileNodes,
    required this.chatMessages,
    required this.members,
    required this.myPermissions,
    required this.spaces,
    required this.ranks,
    required this.sheets,
    required this.canManageTeam,
    required this.canManageRanks,
    required this.teamMembers,
    required this.canReportBugs,
    required this.canManageBugs,
    required this.bugStatuses,
    required this.bugPriorities,
    required this.priorities,
    this.tags = const [],
    this.taskTemplates = const [],
    this.activityLogs = const [],
    this.teamCandidates = const [],
    this.byStatus = const [],
    this.byPriority = const [],
  });

  final WorkspaceProject project;
  final String activeSpace;
  final String spaceLabel;
  final Map<String, dynamic> stats;
  final int progress;
  final List<KanbanList> lists;
  final List<WorkspaceNote> notes;
  final List<WorkspaceEvent> events;
  final List<WorkspaceBug> bugs;
  final List<WorkspaceFileNode> fileNodes;
  final List<WorkspaceChatMessage> chatMessages;
  final List<WorkspaceMember> members;
  final Map<String, dynamic> myPermissions;
  final List<WorkspaceSpace> spaces;
  final List<WorkspaceRank> ranks;
  final List<WorkspaceSheet> sheets;
  final List<TeamMember> teamMembers;
  final bool canManageTeam;
  final bool canManageRanks;
  final bool canReportBugs;
  final bool canManageBugs;
  final Map<String, String> bugStatuses;
  final Map<String, String> bugPriorities;
  final Map<String, String> priorities;
  final List<TaskTag> tags;
  final List<Map<String, dynamic>> taskTemplates;
  final List<ActivityLogEntry> activityLogs;
  final List<WorkspaceMember> teamCandidates;
  final List<WorkspaceBreakdownItem> byStatus;
  final List<WorkspaceBreakdownItem> byPriority;

  factory ProjectWorkspace.fromJson(Map<String, dynamic> json) {
    return ProjectWorkspace(
      project: WorkspaceProject.fromJson(
        json['project'] as Map<String, dynamic>? ?? {},
      ),
      activeSpace: json['activeSpace'] as String? ?? 'global',
      spaceLabel: json['spaceLabel'] as String? ?? 'Global',
      stats: json['stats'] as Map<String, dynamic>? ?? {},
      progress: json['progress'] as int? ?? 0,
      lists: (json['lists'] as List<dynamic>? ?? [])
          .map((e) => KanbanList.fromJson(e as Map<String, dynamic>))
          .toList(),
      notes: (json['notes'] as List<dynamic>? ?? [])
          .map((e) => WorkspaceNote.fromJson(e as Map<String, dynamic>))
          .toList(),
      events: (json['events'] as List<dynamic>? ?? [])
          .map((e) => WorkspaceEvent.fromJson(e as Map<String, dynamic>))
          .toList(),
      bugs: (json['bugs'] as List<dynamic>? ?? [])
          .map((e) => WorkspaceBug.fromJson(e as Map<String, dynamic>))
          .toList(),
      fileNodes: (json['fileNodes'] as List<dynamic>? ?? [])
          .map((e) => WorkspaceFileNode.fromJson(e as Map<String, dynamic>))
          .toList(),
      chatMessages: (json['chatMessages'] as List<dynamic>? ?? [])
          .map((e) => WorkspaceChatMessage.fromJson(e as Map<String, dynamic>))
          .toList(),
      members: (json['members'] as List<dynamic>? ?? [])
          .map((e) => WorkspaceMember.fromJson(e as Map<String, dynamic>))
          .toList(),
      myPermissions: json['myPermissions'] as Map<String, dynamic>? ?? {},
      spaces: (json['spaces'] as List<dynamic>? ?? [])
          .map((e) => WorkspaceSpace.fromJson(e as Map<String, dynamic>))
          .toList(),
      ranks: (json['ranks'] as List<dynamic>? ?? [])
          .map((e) => WorkspaceRank.fromJson(e as Map<String, dynamic>))
          .toList(),
      sheets: (json['sheets'] as List<dynamic>? ?? [])
          .map((e) => WorkspaceSheet.fromJson(e as Map<String, dynamic>))
          .toList(),
      canManageTeam: json['canManageTeam'] as bool? ?? false,
      canManageRanks: json['canManageRanks'] as bool? ?? false,
      teamMembers: (json['teamMembers'] as List<dynamic>? ?? [])
          .map((e) => TeamMember.fromJson(e as Map<String, dynamic>))
          .toList(),
      canReportBugs: json['canReportBugs'] as bool? ?? false,
      canManageBugs: json['canManageBugs'] as bool? ?? false,
      bugStatuses: _stringMap(json['bugStatuses']),
      bugPriorities: _stringMap(json['bugPriorities']),
      priorities: _stringMap(json['priorities']),
      tags: (json['tags'] as List<dynamic>? ?? [])
          .map((e) => TaskTag.fromJson(e as Map<String, dynamic>))
          .toList(),
      taskTemplates: (json['taskTemplates'] as List<dynamic>? ?? [])
          .map((e) => e as Map<String, dynamic>)
          .toList(),
      activityLogs: (json['activityLogs'] as List<dynamic>? ?? [])
          .map((e) => ActivityLogEntry.fromJson(e as Map<String, dynamic>))
          .toList(),
      teamCandidates: (json['teamCandidates'] as List<dynamic>? ?? [])
          .map((e) => WorkspaceMember.fromJson(e as Map<String, dynamic>))
          .toList(),
      byStatus: _breakdownList(json['byStatus']),
      byPriority: _breakdownList(json['byPriority']),
    );
  }

  static List<WorkspaceBreakdownItem> _breakdownList(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .map((e) => WorkspaceBreakdownItem.fromJson(
              e is Map<String, dynamic> ? e : Map<String, dynamic>.from(e as Map),
            ))
        .toList();
  }

  bool canRead(String feature) => myPermissions[feature] != false;

  bool canWrite(String feature) {
    if (myPermissions[feature] == false) return false;
    return myPermissions['${feature}_write'] != false;
  }

  static Map<String, String> _stringMap(dynamic raw) {
    if (raw is! Map) return {};
    return raw.map((key, value) => MapEntry(key.toString(), value.toString()));
  }
}

class WorkspaceBreakdownItem {
  const WorkspaceBreakdownItem({
    required this.key,
    required this.label,
    required this.count,
  });

  final String key;
  final String label;
  final int count;

  factory WorkspaceBreakdownItem.fromJson(Map<String, dynamic> json) {
    return WorkspaceBreakdownItem(
      key: json['key'] as String? ?? '',
      label: json['label'] as String? ?? '',
      count: json['count'] as int? ?? 0,
    );
  }
}

class WorkspaceProject {
  const WorkspaceProject({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
    this.imageUrl,
  });

  final int id;
  final String name;
  final String slug;
  final String? description;
  final String? imageUrl;

  factory WorkspaceProject.fromJson(Map<String, dynamic> json) {
    return WorkspaceProject(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      description: json['description'] as String?,
      imageUrl: json['image_url'] as String?,
    );
  }
}

class WorkspaceSpace {
  const WorkspaceSpace({
    required this.key,
    required this.label,
  });

  final String key;
  final String label;

  factory WorkspaceSpace.fromJson(Map<String, dynamic> json) {
    return WorkspaceSpace(
      key: json['key'] as String? ?? '',
      label: json['label'] as String? ?? '',
    );
  }
}

class WorkspaceRank {
  const WorkspaceRank({
    required this.id,
    required this.key,
    required this.label,
    required this.color,
  });

  final int id;
  final String key;
  final String label;
  final String color;

  factory WorkspaceRank.fromJson(Map<String, dynamic> json) {
    return WorkspaceRank(
      id: json['id'] as int? ?? 0,
      key: json['key'] as String? ?? '',
      label: json['label'] as String? ?? '',
      color: json['color'] as String? ?? '#7c5cff',
    );
  }
}

class KanbanList {
  const KanbanList({
    required this.id,
    required this.name,
    required this.color,
    required this.statusKind,
    required this.tasks,
  });

  final int id;
  final String name;
  final String color;
  final String statusKind;
  final List<KanbanTask> tasks;

  factory KanbanList.fromJson(Map<String, dynamic> json) {
    return KanbanList(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      color: json['color'] as String? ?? '#64748b',
      statusKind: json['status_kind'] as String? ?? 'todo',
      tasks: (json['tasks'] as List<dynamic>? ?? [])
          .map((e) => KanbanTask.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

class KanbanTask {
  const KanbanTask({
    required this.id,
    required this.listId,
    required this.title,
    this.description,
    required this.priority,
    required this.status,
    this.dueDate,
    this.startDate,
    required this.isOverdue,
    this.assigneeId,
    this.position = 0,
    this.progress = 0,
    this.archivedAt,
    this.dependencyIds = const [],
    this.isBlocked = false,
    this.tags = const [],
    this.checklists = const [],
    this.comments = const [],
    this.attachments = const [],
    this.checklistProgress = const {'done': 0, 'total': 0},
  });

  final int id;
  final int listId;
  final String title;
  final String? description;
  final String priority;
  final String status;
  final String? dueDate;
  final String? startDate;
  final bool isOverdue;
  final int? assigneeId;
  final int position;
  final int progress;
  final String? archivedAt;
  final List<int> dependencyIds;
  final bool isBlocked;
  final List<TaskTag> tags;
  final List<TaskChecklist> checklists;
  final List<TaskComment> comments;
  final List<PanelAttachment> attachments;
  final Map<String, int> checklistProgress;

  factory KanbanTask.fromJson(Map<String, dynamic> json) {
    final cp = json['checklist_progress'];
    return KanbanTask(
      id: json['id'] as int,
      listId: json['list_id'] as int? ?? 0,
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      priority: json['priority'] as String? ?? 'medium',
      status: json['status'] as String? ?? 'todo',
      dueDate: json['due_date'] as String?,
      startDate: json['start_date'] as String?,
      isOverdue: json['is_overdue'] as bool? ?? false,
      assigneeId: json['assignee_id'] as int?,
      position: json['position'] as int? ?? 0,
      progress: json['progress'] as int? ?? 0,
      archivedAt: json['archived_at'] as String?,
      dependencyIds: (json['dependency_ids'] as List<dynamic>? ?? [])
          .map((e) => e as int)
          .toList(),
      isBlocked: json['is_blocked'] as bool? ?? false,
      tags: (json['tags'] as List<dynamic>? ?? [])
          .map((e) => TaskTag.fromJson(e as Map<String, dynamic>))
          .toList(),
      checklists: (json['checklists'] as List<dynamic>? ?? [])
          .map((e) => TaskChecklist.fromJson(e as Map<String, dynamic>))
          .toList(),
      comments: (json['comments'] as List<dynamic>? ?? [])
          .map((e) => TaskComment.fromJson(e as Map<String, dynamic>))
          .toList(),
      attachments: (json['attachments'] as List<dynamic>? ?? [])
          .map((e) => PanelAttachment.fromJson(e as Map<String, dynamic>))
          .toList(),
      checklistProgress: cp is Map
          ? {
              'done': cp['done'] as int? ?? 0,
              'total': cp['total'] as int? ?? 0,
            }
          : const {'done': 0, 'total': 0},
    );
  }
}

class TaskTag {
  const TaskTag({required this.id, required this.name, required this.color});

  final int id;
  final String name;
  final String color;

  factory TaskTag.fromJson(Map<String, dynamic> json) {
    return TaskTag(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      color: json['color'] as String? ?? '#64748b',
    );
  }
}

class TaskComment {
  const TaskComment({
    required this.id,
    required this.body,
    this.userName,
    this.createdAt,
  });

  final int id;
  final String body;
  final String? userName;
  final String? createdAt;

  factory TaskComment.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    return TaskComment(
      id: json['id'] as int,
      body: json['body'] as String? ?? '',
      userName: user is Map ? user['name'] as String? : null,
      createdAt: json['created_at'] as String?,
    );
  }
}

class TaskChecklist {
  const TaskChecklist({
    required this.id,
    required this.name,
    required this.items,
  });

  final int id;
  final String name;
  final List<TaskChecklistItem> items;

  factory TaskChecklist.fromJson(Map<String, dynamic> json) {
    return TaskChecklist(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      items: (json['items'] as List<dynamic>? ?? [])
          .map((e) => TaskChecklistItem.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

class TaskChecklistItem {
  const TaskChecklistItem({
    required this.id,
    required this.content,
    required this.isDone,
  });

  final int id;
  final String content;
  final bool isDone;

  factory TaskChecklistItem.fromJson(Map<String, dynamic> json) {
    return TaskChecklistItem(
      id: json['id'] as int,
      content: json['content'] as String? ?? '',
      isDone: json['is_done'] == true,
    );
  }
}

class ActivityLogEntry {
  const ActivityLogEntry({
    required this.id,
    required this.message,
    this.userName,
    this.createdAt,
  });

  final int id;
  final String message;
  final String? userName;
  final String? createdAt;

  factory ActivityLogEntry.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    return ActivityLogEntry(
      id: json['id'] as int,
      message: json['message'] as String? ?? '',
      userName: user is Map ? user['name'] as String? : null,
      createdAt: json['created_at'] as String?,
    );
  }
}

class BugMessage {
  const BugMessage({
    required this.id,
    required this.body,
    this.userName,
    this.createdAt,
  });

  final int id;
  final String body;
  final String? userName;
  final String? createdAt;

  factory BugMessage.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    return BugMessage(
      id: json['id'] as int,
      body: json['body'] as String? ?? '',
      userName: user is Map ? user['name'] as String? : null,
      createdAt: json['created_at'] as String?,
    );
  }
}

class WorkspaceNote {
  const WorkspaceNote({
    required this.id,
    required this.title,
    this.content,
    required this.color,
    required this.pinned,
  });

  final int id;
  final String title;
  final String? content;
  final String color;
  final bool pinned;

  factory WorkspaceNote.fromJson(Map<String, dynamic> json) {
    return WorkspaceNote(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      content: json['content'] as String?,
      color: json['color'] as String? ?? '#fef3c7',
      pinned: json['pinned'] as bool? ?? false,
    );
  }
}

class WorkspaceEvent {
  const WorkspaceEvent({
    required this.id,
    required this.title,
    this.description,
    this.startAt,
    this.endAt,
    required this.allDay,
    required this.color,
    this.recurrence,
    this.recurrenceWeekdays = const [],
    this.recurrenceUntil,
    this.seriesId,
  });

  final int id;
  final String title;
  final String? description;
  final String? startAt;
  final String? endAt;
  final bool allDay;
  final String color;
  final String? recurrence;
  final List<int> recurrenceWeekdays;
  final String? recurrenceUntil;
  final int? seriesId;

  DateTime? get startAtDate =>
      startAt == null ? null : DateTime.tryParse(startAt!)?.toLocal();

  DateTime? get recurrenceUntilDate => recurrenceUntil == null
      ? null
      : DateTime.tryParse('${recurrenceUntil!}T23:59:59')?.toLocal();

  DateTime? get endAtDate =>
      endAt == null ? null : DateTime.tryParse(endAt!)?.toLocal();

  factory WorkspaceEvent.fromJson(Map<String, dynamic> json) {
    return WorkspaceEvent(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      startAt: json['start_at'] as String?,
      endAt: json['end_at'] as String?,
      allDay: json['all_day'] as bool? ?? false,
      color: json['color'] as String? ?? '#7c5cff',
      recurrence: json['recurrence'] as String?,
      recurrenceWeekdays: (json['recurrence_weekdays'] as List<dynamic>? ?? [])
          .map((value) => value as int)
          .toList(),
      recurrenceUntil: json['recurrence_until'] as String?,
    );
  }
}

class WorkspaceBug {
  const WorkspaceBug({
    required this.id,
    required this.title,
    this.description,
    required this.priority,
    required this.status,
    required this.isSlaBreached,
    this.reporterName,
    this.assigneeName,
    required this.canManage,
  });

  final int id;
  final String title;
  final String? description;
  final String priority;
  final String status;
  final bool isSlaBreached;
  final String? reporterName;
  final String? assigneeName;
  final bool canManage;

  factory WorkspaceBug.fromJson(Map<String, dynamic> json) {
    final reporter = json['reporter'];
    final assignee = json['assignee'];
    return WorkspaceBug(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      priority: json['priority'] as String? ?? 'medium',
      status: json['status'] as String? ?? 'open',
      isSlaBreached: json['is_sla_breached'] as bool? ?? false,
      reporterName: reporter is Map ? reporter['name'] as String? : null,
      assigneeName: assignee is Map ? assignee['name'] as String? : null,
      canManage: json['can_manage'] as bool? ?? false,
    );
  }
}

class WorkspaceFileNode {
  const WorkspaceFileNode({
    required this.id,
    this.parentId,
    required this.type,
    required this.name,
    this.url,
    this.size,
  });

  final int id;
  final int? parentId;
  final String type;
  final String name;
  final String? url;
  final int? size;

  factory WorkspaceFileNode.fromJson(Map<String, dynamic> json) {
    return WorkspaceFileNode(
      id: json['id'] as int,
      parentId: json['parent_id'] as int?,
      type: json['type'] as String? ?? 'file',
      name: json['name'] as String? ?? '',
      url: json['url'] as String?,
      size: json['size'] as int?,
    );
  }
}

class WorkspaceChatMessage {
  const WorkspaceChatMessage({
    required this.id,
    required this.body,
    this.userName,
    this.userId,
    required this.createdAt,
    required this.pinned,
    this.replyPreview,
    this.reactions = const [],
    this.attachments = const [],
    this.canEdit = false,
    this.editedAt,
  });

  final int id;
  final String body;
  final String? userName;
  final int? userId;
  final String? createdAt;
  final bool pinned;
  final ReplyPreview? replyPreview;
  final List<MessageReaction> reactions;
  final List<PanelAttachment> attachments;
  final bool canEdit;
  final String? editedAt;

  factory WorkspaceChatMessage.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    return WorkspaceChatMessage(
      id: json['id'] as int,
      body: json['body'] as String? ?? '',
      userName: user is Map ? user['name'] as String? : null,
      userId: user is Map ? user['id'] as int? : null,
      createdAt: json['created_at'] as String?,
      pinned: json['is_pinned'] as bool? ?? json['pinned_at'] != null,
      replyPreview: json['reply_preview'] is Map<String, dynamic>
          ? ReplyPreview.fromJson(json['reply_preview'] as Map<String, dynamic>)
          : null,
      reactions: (json['reactions'] as List<dynamic>? ?? [])
          .map((e) => MessageReaction.fromJson(e as Map<String, dynamic>))
          .toList(),
      attachments: (json['attachments'] as List<dynamic>? ?? [])
          .map((e) => PanelAttachment.fromJson(e as Map<String, dynamic>))
          .toList(),
      canEdit: json['can_edit'] == true,
      editedAt: json['edited_at'] as String?,
    );
  }
}

class WorkspaceMember {
  const WorkspaceMember({
    required this.id,
    required this.name,
    required this.email,
  });

  final int id;
  final String name;
  final String email;

  factory WorkspaceMember.fromJson(Map<String, dynamic> json) {
    return WorkspaceMember(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
    );
  }
}
