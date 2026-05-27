import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/panel_api_extensions.dart';
import '../config/app_config.dart';
import '../models/conversation.dart';
import '../models/direct_message.dart';
import '../services/auth_session.dart';
import '../services/realtime_service.dart';
import '../services/reverb_service.dart';
import '../utils/format.dart';
import '../utils/typing_users.dart';
import '../widgets/reaction_bar.dart';

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

  Future<void> _openAttachment(String? url) async {
    if (url == null) return;
    final uri = Uri.parse('${AppConfig.panelBaseUrl}$url');
    await launchUrl(uri, mode: LaunchMode.externalApplication);
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
      appBar: AppBar(title: Text(title)),
      body: Column(
        children: [
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(child: Text(_error!))
                    : ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.all(16),
                        itemCount: _messages.length,
                        itemBuilder: (context, index) {
                          final message = _messages[index];
                          final isMine = message.user?.id == currentUserId;

                          return Align(
                            alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
                            child: Container(
                              margin: const EdgeInsets.only(bottom: 8),
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                              constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.78),
                              decoration: BoxDecoration(
                                color: isMine
                                    ? Theme.of(context).colorScheme.primaryContainer
                                    : const Color(0xFF27272A),
                                borderRadius: BorderRadius.circular(16),
                              ),
                              child: Column(
                                crossAxisAlignment:
                                    isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
                                children: [
                                  if (message.replyPreview != null)
                                    Container(
                                      margin: const EdgeInsets.only(bottom: 6),
                                      padding: const EdgeInsets.all(8),
                                      decoration: BoxDecoration(
                                        color: Colors.black26,
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: Text(
                                        '${message.replyPreview!.userName ?? ''}: ${message.replyPreview!.body}',
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: Theme.of(context).textTheme.labelSmall,
                                      ),
                                    ),
                                  if (message.body.isNotEmpty) Text(message.body),
                                  ...message.attachments.map(
                                    (a) => ListTile(
                                      contentPadding: EdgeInsets.zero,
                                      dense: true,
                                      leading: const Icon(Icons.attach_file, size: 18),
                                      title: Text(a.originalName),
                                      onTap: () => _openAttachment(a.url),
                                    ),
                                  ),
                                  ReactionBar(
                                    reactions: message.reactions,
                                    onToggle: (emoji) => _toggleReaction(message, emoji),
                                    compact: true,
                                  ),
                                  Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Text(
                                        formatRelativeTime(message.createdAt),
                                        style: Theme.of(context).textTheme.labelSmall,
                                      ),
                                      IconButton(
                                        visualDensity: VisualDensity.compact,
                                        iconSize: 16,
                                        icon: const Icon(Icons.reply),
                                        onPressed: () => setState(() => _replyTo = message),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
          ),
          if (_replyTo != null)
            Material(
              color: Theme.of(context).colorScheme.surfaceContainerHighest,
              child: ListTile(
                dense: true,
                title: Text('Réponse à ${ _replyTo!.user?.name ?? ''}'),
                subtitle: Text(_replyTo!.body, maxLines: 1, overflow: TextOverflow.ellipsis),
                trailing: IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => setState(() => _replyTo = null),
                ),
              ),
            ),
          if (_typingUsers.label != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  _typingUsers.label!,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: Theme.of(context).colorScheme.primary,
                      ),
                ),
              ),
            ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
              child: Row(
                children: [
                  IconButton(
                    onPressed: _sending ? null : _uploadAttachment,
                    icon: const Icon(Icons.attach_file),
                  ),
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      minLines: 1,
                      maxLines: 4,
                      textInputAction: TextInputAction.send,
                      decoration: const InputDecoration(hintText: 'Écrire un message…'),
                      onChanged: (_) => _notifyTyping(),
                      onSubmitted: (_) => _send(),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: _sending ? null : _send,
                    icon: _sending
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.send),
                  ),
                ],
              ),
            ),
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
