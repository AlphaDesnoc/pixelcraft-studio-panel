import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/panel_api_extensions.dart';
import '../models/conversation.dart';
import '../models/direct_message.dart';
import '../services/auth_session.dart';
import '../services/realtime_service.dart';
import '../services/reverb_service.dart';
import '../utils/format.dart';
import '../utils/typing_users.dart';
import '../widgets/chat_actions.dart';
import '../widgets/chat_bubble.dart';
import '../widgets/chat_composer.dart';

class ChatScreen extends StatefulWidget {
  const ChatScreen({
    super.key,
    required this.conversation,
    this.contacts = const [],
  });

  final Conversation conversation;
  final List<ConversationParticipant> contacts;

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();

  List<DirectMessage> _messages = [];
  bool _loading = true;
  bool _sending = false;
  String? _error;
  DirectMessage? _replyTo;
  late Conversation _conversation;
  LiveChannelSubscription? _liveSubscription;
  RealtimeService? _realtime;
  final _typingUsers = TypingUsersController();
  Timer? _whisperTimer;

  @override
  void initState() {
    super.initState();
    _conversation = widget.conversation;
    _typingUsers.onChanged = () {
      if (mounted) setState(() {});
    };
    _load();
    WidgetsBinding.instance.addPostFrameCallback((_) => _subscribeLive());
  }

  void _subscribeLive() {
    if (!mounted) return;
    final user = context.read<AuthSession>().user;
    _realtime = context.read<RealtimeService>();
    _realtime!.setActiveConversationId(_conversation.id);
    _liveSubscription = _realtime!.subscribeDirectConversation(
      conversationId: _conversation.id,
      onMessage: _appendIncomingMessage,
      onTyping: (userId, name) {
        _typingUsers.add(userId, name, excludeUserId: user?.id);
      },
    );
  }

  void _notifyTyping() {
    final user = context.read<AuthSession>().user;
    final send = _liveSubscription?.sendTyping;
    if (user == null || send == null) return;

    _whisperTimer?.cancel();
    _whisperTimer = Timer(const Duration(milliseconds: 400), () {
      send(userId: user.id, userName: user.name);
    });
  }

  void _appendIncomingMessage(DirectMessage message) {
    if (!mounted) return;
    if (_messages.any((m) => m.id == message.id)) return;
    setState(() => _messages = [..._messages, message]);
    _scrollToBottom();
    context.read<AuthSession>().api.markConversationRead(_conversation.id);
  }

  @override
  void dispose() {
    _whisperTimer?.cancel();
    _typingUsers.dispose();
    _realtime?.setActiveConversationId(null);
    _liveSubscription?.dispose();
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final api = context.read<AuthSession>().api;
      final messages = await api.fetchMessages(_conversation.id);
      await api.markConversationRead(_conversation.id);
      if (!mounted) return;
      setState(() => _messages = messages);
      _scrollToBottom();
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _send() async {
    final body = _controller.text.trim();
    if (body.isEmpty || _sending) return;

    setState(() => _sending = true);

    try {
      final message = await context.read<AuthSession>().api.sendDirectMessage(
            conversationId: _conversation.id,
            body: body,
            replyToId: _replyTo?.id,
          );
      if (!mounted) return;
      setState(() {
        _messages = [..._messages, message];
        _replyTo = null;
      });
      _controller.clear();
      _scrollToBottom();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _uploadAttachment() async {
    final result = await FilePicker.platform.pickFiles(withData: false);
    if (result == null || result.files.isEmpty) return;
    final file = result.files.first;
    if (file.path == null) return;

    setState(() => _sending = true);
    try {
      final message = await context.read<AuthSession>().api.sendConversationAttachment(
            conversationId: _conversation.id,
            filePath: file.path!,
            fileName: file.name,
            replyToId: _replyTo?.id,
          );
      if (!mounted) return;
      setState(() {
        _messages = [..._messages, message];
        _replyTo = null;
      });
      _scrollToBottom();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _toggleReaction(DirectMessage message, String emoji) async {
    final reactions = await context.read<AuthSession>().api.toggleDirectMessageReaction(
          messageId: message.id,
          emoji: emoji,
        );
    setState(() {
      _messages = _messages.map((m) {
        if (m.id != message.id) return m;
        return DirectMessage(
          id: m.id,
          conversationId: m.conversationId,
          body: m.body,
          createdAt: m.createdAt,
          user: m.user,
          isRead: m.isRead,
          replyPreview: m.replyPreview,
          reactions: reactions,
          attachments: m.attachments,
          replyToId: m.replyToId,
        );
      }).toList();
    });
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final title = _conversation.participant?.name ?? 'Messages';
    final currentUserId = context.watch<AuthSession>().user?.id;

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Row(
          children: [
            CircleAvatar(
              radius: 18,
              child: Text(initialsFromName(title)),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(child: Text(_error!))
                    : ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.fromLTRB(0, 8, 0, 8),
                        itemCount: _messages.length,
                        itemBuilder: (context, index) {
                          final message = _messages[index];
                          final isMine = message.user?.id == currentUserId;
                          final prev =
                              index > 0 ? _messages[index - 1] : null;
                          final next = index < _messages.length - 1
                              ? _messages[index + 1]
                              : null;
                          final clusterStart = prev == null ||
                              prev.user?.id != message.user?.id;
                          final clusterEnd = next == null ||
                              next.user?.id != message.user?.id;

                          return ChatMessageRow(
                            isMine: isMine,
                            userName: message.user?.name ?? 'Utilisateur',
                            body: message.body,
                            createdAt: message.createdAt,
                            replyPreview: message.replyPreview,
                            reactions: message.reactions,
                            attachments: message.attachments,
                            isRead: isMine ? message.isRead : null,
                            clusterStart: clusterStart,
                            clusterEnd: clusterEnd,
                            onToggleReaction: (emoji) =>
                                _toggleReaction(message, emoji),
                            onLongPress: () => showChatMessageActions(
                              context,
                              onReply: () =>
                                  setState(() => _replyTo = message),
                              onReact: () => showReactionPicker(
                                context,
                                (emoji) => _toggleReaction(message, emoji),
                              ),
                            ),
                          );
                        },
                      ),
          ),
          if (_replyTo != null)
            ChatReplyBar(
              authorName: _replyTo!.user?.name ?? '',
              body: _replyTo!.body,
              onClose: () => setState(() => _replyTo = null),
            ),
          if (_typingUsers.label != null)
            ChatTypingIndicator(label: _typingUsers.label!),
          ChatComposer(
            controller: _controller,
            sending: _sending,
            onSend: _send,
            onAttach: _uploadAttachment,
            onChanged: (_) => _notifyTyping(),
            hintText: 'Message',
          ),
        ],
      ),
    );
  }
}

class NewConversationScreen extends StatelessWidget {
  const NewConversationScreen({
    super.key,
    required this.contacts,
  });

  final List<ConversationParticipant> contacts;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Nouvelle conversation')),
      body: ListView.separated(
        itemCount: contacts.length,
        separatorBuilder: (context, index) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final contact = contacts[index];
          return ListTile(
            leading: CircleAvatar(child: Text(initialsFromName(contact.name))),
            title: Text(contact.name),
            subtitle: Text(contact.email),
            onTap: () async {
              try {
                final message = await context.read<AuthSession>().api.sendDirectMessage(
                      recipientId: contact.id,
                      body: 'Bonjour',
                    );
                if (!context.mounted) return;
                final conversation = Conversation(
                  id: message.conversationId,
                  unreadCount: 0,
                  lastMessageAt: message.createdAt,
                  participant: contact,
                  lastMessage: ConversationPreview(
                    id: message.id,
                    body: message.body,
                    createdAt: message.createdAt,
                    userId: message.user?.id,
                  ),
                );
                Navigator.of(context).pushReplacement(
                  MaterialPageRoute(
                    builder: (_) => ChatScreen(conversation: conversation, contacts: contacts),
                  ),
                );
              } catch (error) {
                if (!context.mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text(error.toString())),
                );
              }
            },
          );
        },
      ),
    );
  }
}
