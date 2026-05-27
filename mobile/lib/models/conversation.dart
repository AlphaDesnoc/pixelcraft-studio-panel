class ConversationParticipant {
  const ConversationParticipant({
    required this.id,
    required this.name,
    required this.email,
  });

  final int id;
  final String name;
  final String email;

  factory ConversationParticipant.fromJson(Map<String, dynamic> json) {
    return ConversationParticipant(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
    );
  }
}

class ConversationPreview {
  const ConversationPreview({
    required this.id,
    required this.body,
    required this.createdAt,
    required this.userId,
  });

  final int id;
  final String body;
  final String? createdAt;
  final int? userId;

  factory ConversationPreview.fromJson(Map<String, dynamic> json) {
    return ConversationPreview(
      id: json['id'] as int,
      body: json['body'] as String? ?? '',
      createdAt: json['created_at'] as String?,
      userId: json['user_id'] as int?,
    );
  }
}

class Conversation {
  const Conversation({
    required this.id,
    required this.unreadCount,
    required this.lastMessageAt,
    required this.participant,
    required this.lastMessage,
  });

  final int id;
  final int unreadCount;
  final String? lastMessageAt;
  final ConversationParticipant? participant;
  final ConversationPreview? lastMessage;

  factory Conversation.fromJson(Map<String, dynamic> json) {
    return Conversation(
      id: json['id'] as int,
      unreadCount: json['unread_count'] as int? ?? 0,
      lastMessageAt: json['last_message_at'] as String?,
      participant: json['participant'] is Map<String, dynamic>
          ? ConversationParticipant.fromJson(
              json['participant'] as Map<String, dynamic>,
            )
          : null,
      lastMessage: json['last_message'] is Map<String, dynamic>
          ? ConversationPreview.fromJson(
              json['last_message'] as Map<String, dynamic>,
            )
          : null,
    );
  }
}
