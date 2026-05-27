class MyTask {
  const MyTask({
    required this.id,
    required this.title,
    required this.priority,
    required this.status,
    this.dueDate,
    required this.isOverdue,
    this.listName,
    this.project,
  });

  final int id;
  final String title;
  final String priority;
  final String status;
  final String? dueDate;
  final bool isOverdue;
  final String? listName;
  final MyTaskProject? project;

  factory MyTask.fromJson(Map<String, dynamic> json) {
    final projectRaw = json['project'];
    return MyTask(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      priority: json['priority'] as String? ?? 'medium',
      status: json['status'] as String? ?? 'todo',
      dueDate: json['due_date'] as String?,
      isOverdue: json['is_overdue'] as bool? ?? false,
      listName: json['list_name'] as String?,
      project: projectRaw is Map<String, dynamic>
          ? MyTaskProject.fromJson(projectRaw)
          : null,
    );
  }
}

class MyTaskProject {
  const MyTaskProject({
    required this.id,
    required this.name,
    required this.slug,
  });

  final int id;
  final String name;
  final String slug;

  factory MyTaskProject.fromJson(Map<String, dynamic> json) {
    return MyTaskProject(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
    );
  }
}

class SearchResult {
  const SearchResult({
    required this.type,
    required this.label,
    this.meta,
    this.url,
  });

  final String type;
  final String label;
  final String? meta;
  final String? url;

  factory SearchResult.fromJson(Map<String, dynamic> json) {
    return SearchResult(
      type: json['type'] as String? ?? '',
      label: json['label'] as String? ?? '',
      meta: json['meta'] as String?,
      url: json['url'] as String?,
    );
  }
}
