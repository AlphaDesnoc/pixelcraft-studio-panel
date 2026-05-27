class PanelAttachment {
  const PanelAttachment({
    required this.id,
    required this.originalName,
    required this.mimeType,
    required this.size,
    this.url,
  });

  final int id;
  final String originalName;
  final String mimeType;
  final int size;
  final String? url;

  factory PanelAttachment.fromJson(Map<String, dynamic> json) {
    return PanelAttachment(
      id: json['id'] as int,
      originalName: json['original_name'] as String? ?? '',
      mimeType: json['mime_type'] as String? ?? '',
      size: json['size'] as int? ?? 0,
      url: json['url'] as String?,
    );
  }
}

class MessageReaction {
  const MessageReaction({
    required this.emoji,
    required this.count,
    required this.users,
    required this.me,
  });

  final String emoji;
  final int count;
  final List<String> users;
  final bool me;

  factory MessageReaction.fromJson(Map<String, dynamic> json) {
    return MessageReaction(
      emoji: json['emoji'] as String? ?? '',
      count: json['count'] as int? ?? 0,
      users: (json['users'] as List<dynamic>? ?? [])
          .map((e) => e.toString())
          .toList(),
      me: json['me'] == true,
    );
  }
}

class ReplyPreview {
  const ReplyPreview({
    required this.id,
    required this.body,
    this.userName,
  });

  final int id;
  final String body;
  final String? userName;

  factory ReplyPreview.fromJson(Map<String, dynamic> json) {
    return ReplyPreview(
      id: json['id'] as int,
      body: json['body'] as String? ?? '',
      userName: json['user_name'] as String?,
    );
  }
}
