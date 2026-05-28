class PanelNotification {
  const PanelNotification({
    required this.id,
    required this.title,
    required this.body,
    this.type,
    this.url,
    this.data,
    this.readAt,
    this.createdAt,
  });

  final int id;
  final String title;
  final String body;
  final String? type;
  final String? url;
  final Map<String, dynamic>? data;
  final String? readAt;
  final String? createdAt;

  bool get isUnread => readAt == null;

  factory PanelNotification.fromJson(Map<String, dynamic> json) {
    final dataRaw = json['data'];
    return PanelNotification(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      type: json['type'] as String?,
      url: json['url'] as String?,
      data: dataRaw is Map<String, dynamic>
          ? dataRaw
          : (dataRaw is Map ? Map<String, dynamic>.from(dataRaw) : null),
      readAt: json['read_at'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}
