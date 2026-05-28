class WorkspaceSheet {
  const WorkspaceSheet({
    required this.id,
    required this.name,
    required this.position,
    required this.rows,
    required this.cols,
    required this.data,
  });

  final int id;
  final String name;
  final int position;
  final int rows;
  final int cols;
  final Map<String, dynamic> data;

  factory WorkspaceSheet.fromJson(Map<String, dynamic> json) {
    final dataRaw = json['data'];
    return WorkspaceSheet(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      position: json['position'] as int? ?? 0,
      rows: json['rows'] as int? ?? 50,
      cols: json['cols'] as int? ?? 26,
      data: dataRaw is Map<String, dynamic>
          ? dataRaw
          : (dataRaw is Map ? Map<String, dynamic>.from(dataRaw) : {}),
    );
  }

  String cellValue(String key) {
    final cell = data[key];
    if (cell is Map && cell['v'] != null) {
      return cell['v'].toString();
    }
    if (cell != null && cell is! Map) {
      return cell.toString();
    }
    return '';
  }

  Map<String, dynamic> withCellValue(String key, String value) {
    final next = Map<String, dynamic>.from(data);
    if (value.isEmpty) {
      next.remove(key);
    } else {
      next[key] = {'v': value};
    }
    return next;
  }
}

class ProjectRank {
  const ProjectRank({
    required this.id,
    required this.name,
    required this.slug,
    required this.color,
    required this.managesBugs,
    this.responsibleName,
    required this.membersCount,
    required this.openTasks,
    required this.canManageMembers,
  });

  final int id;
  final String name;
  final String slug;
  final String color;
  final bool managesBugs;
  final String? responsibleName;
  final int membersCount;
  final int openTasks;
  final bool canManageMembers;

  factory ProjectRank.fromJson(Map<String, dynamic> json) {
    final counts = json['counts'];
    final responsible = json['responsible'];
    return ProjectRank(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      color: json['color'] as String? ?? '#7c5cff',
      managesBugs: json['manages_bugs'] as bool? ?? false,
      responsibleName: responsible is Map ? responsible['name'] as String? : null,
      membersCount: counts is Map ? counts['members'] as int? ?? 0 : 0,
      openTasks: counts is Map ? counts['tasks'] as int? ?? 0 : 0,
      canManageMembers: json['can_manage_members'] as bool? ?? false,
    );
  }
}

class RankDashboardEntry {
  const RankDashboardEntry({
    required this.id,
    required this.name,
    required this.color,
    required this.stats,
    this.capacityAlerts = const [],
  });

  final int id;
  final String name;
  final String color;
  final Map<String, dynamic> stats;
  final List<Map<String, dynamic>> capacityAlerts;

  factory RankDashboardEntry.fromJson(Map<String, dynamic> json) {
    return RankDashboardEntry(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      color: json['color'] as String? ?? '#7c5cff',
      stats: json['stats'] as Map<String, dynamic>? ?? {},
      capacityAlerts: (json['capacity_alerts'] as List<dynamic>? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList(),
    );
  }
}

class TeamMember {
  const TeamMember({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.isOwner,
    this.permissions = const {},
  });

  final int id;
  final String name;
  final String email;
  final String role;
  final bool isOwner;
  final Map<String, bool> permissions;

  factory TeamMember.fromJson(Map<String, dynamic> json) {
    final permsRaw = json['permissions'];
    return TeamMember(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: json['role'] as String? ?? 'member',
      isOwner: json['is_owner'] as bool? ?? false,
      permissions: permsRaw is Map
          ? permsRaw.map((k, v) => MapEntry(k.toString(), v == true))
          : const {},
    );
  }
}

class AdminUser {
  const AdminUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.isAdmin,
    required this.isActive,
    required this.projectsCount,
  });

  final int id;
  final String name;
  final String email;
  final String role;
  final bool isAdmin;
  final bool isActive;
  final int projectsCount;

  factory AdminUser.fromJson(Map<String, dynamic> json) {
    return AdminUser(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: json['role'] as String? ?? 'member',
      isAdmin: json['is_admin'] as bool? ?? false,
      isActive: json['is_active'] as bool? ?? true,
      projectsCount: json['projects_count'] as int? ?? 0,
    );
  }
}

class AdminProject {
  const AdminProject({
    required this.id,
    required this.name,
    required this.slug,
    required this.status,
    required this.membersCount,
    required this.tasksCount,
  });

  final int id;
  final String name;
  final String slug;
  final String status;
  final int membersCount;
  final int tasksCount;

  factory AdminProject.fromJson(Map<String, dynamic> json) {
    return AdminProject(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      status: json['status'] as String? ?? 'active',
      membersCount: json['members_count'] as int? ?? 0,
      tasksCount: json['tasks_count'] as int? ?? 0,
    );
  }
}

class KanbanSavedView {
  const KanbanSavedView({
    required this.id,
    required this.name,
    required this.filters,
    required this.isShared,
    this.userId,
  });

  final int id;
  final String name;
  final Map<String, dynamic> filters;
  final bool isShared;
  final int? userId;

  factory KanbanSavedView.fromJson(Map<String, dynamic> json) {
    final filtersRaw = json['filters'];
    return KanbanSavedView(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      filters: filtersRaw is Map<String, dynamic>
          ? filtersRaw
          : (filtersRaw is Map ? Map<String, dynamic>.from(filtersRaw) : {}),
      isShared: json['is_shared'] as bool? ?? true,
      userId: json['user_id'] as int?,
    );
  }
}

class AdminPortfolioData {
  const AdminPortfolioData({
    required this.projects,
    required this.capacityAlerts,
    required this.summary,
    this.capacityThreshold = 15,
  });

  final List<Map<String, dynamic>> projects;
  final List<Map<String, dynamic>> capacityAlerts;
  final Map<String, dynamic> summary;
  final int capacityThreshold;

  factory AdminPortfolioData.fromJson(Map<String, dynamic> json) {
    return AdminPortfolioData(
      projects: (json['projects'] as List<dynamic>? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList(),
      capacityAlerts: (json['capacityAlerts'] as List<dynamic>? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList(),
      summary: json['summary'] as Map<String, dynamic>? ?? {},
      capacityThreshold: json['capacityThreshold'] as int? ?? 15,
    );
  }
}

class RealtimeSyncResult {
  const RealtimeSyncResult({
    required this.serverTime,
    required this.unreadCount,
    required this.unreadNotifications,
    required this.events,
  });

  final String serverTime;
  final int unreadCount;
  final int unreadNotifications;
  final List<Map<String, dynamic>> events;

  factory RealtimeSyncResult.fromJson(Map<String, dynamic> json) {
    return RealtimeSyncResult(
      serverTime: json['server_time'] as String? ?? '',
      unreadCount: json['unread_count'] as int? ?? 0,
      unreadNotifications: json['unread_notifications'] as int? ?? 0,
      events: (json['events'] as List<dynamic>? ?? [])
          .map((e) => e as Map<String, dynamic>)
          .toList(),
    );
  }
}
