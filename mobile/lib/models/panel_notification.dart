class PanelNotification {
  const PanelNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.url,
    required this.readAt,
    required this.createdAt,
  });

  final int id;
  final String title;
  final String body;
  final String? url;
  final String? readAt;
  final String? createdAt;

  bool get isUnread => readAt == null;

  factory PanelNotification.fromJson(Map<String, dynamic> json) {
    return PanelNotification(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      url: json['url'] as String?,
      readAt: json['read_at'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}
