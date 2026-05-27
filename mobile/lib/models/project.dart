class DashboardStats {
  const DashboardStats({
    required this.projects,
    required this.tasks,
    required this.completed,
    required this.overdue,
  });

  final int projects;
  final int tasks;
  final int completed;
  final int overdue;

  factory DashboardStats.fromJson(Map<String, dynamic> json) {
    return DashboardStats(
      projects: json['projects'] as int? ?? 0,
      tasks: json['tasks'] as int? ?? 0,
      completed: json['completed'] as int? ?? 0,
      overdue: json['overdue'] as int? ?? 0,
    );
  }
}

class PanelProject {
  const PanelProject({
    required this.id,
    required this.name,
    required this.slug,
    required this.membersCount,
    required this.tasksTotal,
    required this.tasksDone,
  });

  final int id;
  final String name;
  final String slug;
  final int membersCount;
  final int tasksTotal;
  final int tasksDone;

  factory PanelProject.fromJson(Map<String, dynamic> json) {
    return PanelProject(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      membersCount: json['members_count'] as int? ?? 0,
      tasksTotal: json['tasks_total'] as int? ?? 0,
      tasksDone: json['tasks_done'] as int? ?? 0,
    );
  }
}
