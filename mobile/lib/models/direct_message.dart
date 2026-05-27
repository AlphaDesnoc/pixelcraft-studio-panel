class MessageAuthor {
  const MessageAuthor({required this.id, required this.name});

  final int id;
  final String name;

  factory MessageAuthor.fromJson(Map<String, dynamic> json) {
    return MessageAuthor(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
    );
  }
}

class DirectMessage {
  const DirectMessage({
    required this.id,
    required this.conversationId,
    required this.body,
    required this.createdAt,
    required this.user,
    required this.isRead,
  });

  final int id;
  final int conversationId;
  final String body;
  final String? createdAt;
  final MessageAuthor? user;
  final bool isRead;

  factory DirectMessage.fromJson(Map<String, dynamic> json) {
    return DirectMessage(
      id: json['id'] as int,
      conversationId: json['direct_conversation_id'] as int,
      body: json['body'] as String? ?? '',
      createdAt: json['created_at'] as String?,
      user: json['user'] is Map<String, dynamic>
          ? MessageAuthor.fromJson(json['user'] as Map<String, dynamic>)
          : null,
      isRead: json['is_read'] == true,
    );
  }
}
