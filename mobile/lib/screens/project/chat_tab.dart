import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/panel_api_extensions.dart';
import '../../models/attachment.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';
import '../../services/realtime_service.dart';
import '../../services/reverb_service.dart';
import '../../utils/typing_users.dart';
import '../../widgets/chat_actions.dart';
import '../../widgets/chat_bubble.dart';
import '../../widgets/chat_composer.dart';

class ChatTab extends StatefulWidget {
  const ChatTab({
    super.key,
    required this.workspace,
    required this.onChanged,
  });

  final ProjectWorkspace workspace;
  final Future<void> Function() onChanged;

  @override
  State<ChatTab> createState() => _ChatTabState();
}

///
/// Test de mise a jour
///

class _ChatTabState extends State<ChatTab> {
  late List<WorkspaceChatMessage> _messages;
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  bool _sending = false;
  WorkspaceChatMessage? _replyTo;
  int? _editingId;
  final _editController = TextEditingController();
  LiveChannelSubscription? _liveSubscription;
  RealtimeService? _realtime;
  final _typingUsers = TypingUsersController();
  Timer? _whisperTimer;

  bool get _canWrite => widget.workspace.canWrite('chat');
  String get _slug => widget.workspace.project.slug;
  int? get _currentUserId => context.watch<AuthSession>().user?.id;

  @override
  void initState() {
    super.initState();
    _messages = List.of(widget.workspace.chatMessages);
    _typingUsers.onChanged = () {
      if (mounted) setState(() {});
    };
    _refresh();
    WidgetsBinding.instance.addPostFrameCallback((_) => _subscribeLive());
  }

  void _subscribeLive() {
    if (!mounted) return;
    final ws = widget.workspace;
    final userId = _currentUserId;
    _realtime = context.read<RealtimeService>();
    _liveSubscription = _realtime!.subscribeProjectChat(
          projectId: ws.project.id,
          spaceKey: ws.activeSpace,
          onMessageSent: (payload) {
            final raw = payload['message'];
            if (raw is! Map<String, dynamic> || !mounted) return;
            final message = WorkspaceChatMessage.fromJson(raw);
            if (_messages.any((m) => m.id == message.id)) return;
            setState(() => _messages = [..._messages, message]);
            _scrollToBottom();
          },
          onMessageUpdated: (payload) {
            final raw = payload['message'];
            if (raw is! Map<String, dynamic> || !mounted) return;
            final message = WorkspaceChatMessage.fromJson(raw);
            setState(() {
              _messages = _messages
                  .map((m) => m.id == message.id ? message : m)
                  .toList();
            });
          },
          onMessageDeleted: (payload) {
            final id = payload['message_id'] as int?;
            if (id == null || !mounted) return;
            setState(() => _messages = _messages.where((m) => m.id != id).toList());
          },
          onReactionUpdated: (payload) {
            final messageId = payload['message_id'] as int?;
            final reactionsRaw = payload['reactions'];
            if (messageId == null || reactionsRaw is! List || !mounted) return;
            setState(() {
              _messages = _messages.map((m) {
                if (m.id != messageId) return m;
                return WorkspaceChatMessage(
                  id: m.id,
                  body: m.body,
                  userName: m.userName,
                  userId: m.userId,
                  createdAt: m.createdAt,
                  pinned: m.pinned,
                  replyPreview: m.replyPreview,
                  reactions: reactionsRaw
                      .map((e) => MessageReaction.fromJson(
                            e as Map<String, dynamic>,
                          ))
                      .toList(),
                  attachments: m.attachments,
                  canEdit: m.canEdit,
                  editedAt: m.editedAt,
                );
              }).toList();
            });
          },
          onTyping: (typingUserId, name) {
            _typingUsers.add(typingUserId, name, excludeUserId: userId);
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

  @override
  void dispose() {
    _whisperTimer?.cancel();
    _typingUsers.dispose();
    _liveSubscription?.dispose();
    _controller.dispose();
    _editController.dispose();
    _scrollController.dispose();
    super.dispose();
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

  Future<void> _refresh() async {
    try {
      final messages = await context.read<AuthSession>().api.fetchChatMessages(_slug);
      if (!mounted) return;
      setState(() => _messages = messages);
      _scrollToBottom();
    } catch (_) {}
  }

  void _setReply(WorkspaceChatMessage message) {
    setState(() => _replyTo = message);
  }

  Future<void> _send() async {
    final body = _controller.text.trim();
    if (body.isEmpty || _sending) return;

    setState(() => _sending = true);
    _controller.clear();
    final replyId = _replyTo?.id;
    setState(() => _replyTo = null);

    try {
      final api = context.read<AuthSession>().api;
      final message = await api.sendProjectChatMessage(
        projectSlug: _slug,
        body: body,
        replyToId: replyId,
      );
      if (!mounted) return;
      setState(() => _messages = [..._messages, message]);
      _scrollToBottom();
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
      final message = await context.read<AuthSession>().api.uploadChatAttachment(
            projectSlug: _slug,
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
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _toggleReaction(WorkspaceChatMessage message, String emoji) async {
    final reactions = await context.read<AuthSession>().api.toggleChatReaction(
          projectSlug: _slug,
          messageId: message.id,
          emoji: emoji,
        );
    setState(() {
      _messages = _messages.map((m) {
        if (m.id != message.id) return m;
        return WorkspaceChatMessage(
          id: m.id,
          body: m.body,
          userName: m.userName,
          userId: m.userId,
          createdAt: m.createdAt,
          pinned: m.pinned,
          replyPreview: m.replyPreview,
          reactions: reactions,
          attachments: m.attachments,
          canEdit: m.canEdit,
          editedAt: m.editedAt,
        );
      }).toList();
    });
  }

  Future<void> _saveEdit(WorkspaceChatMessage message) async {
    final body = _editController.text.trim();
    if (body.isEmpty) return;
    final updated = await context.read<AuthSession>().api.updateChatMessage(
          projectSlug: _slug,
          messageId: message.id,
          body: body,
        );
    setState(() {
      _editingId = null;
      _messages = _messages.map((m) => m.id == message.id ? updated : m).toList();
    });
  }

  Future<void> _deleteMessage(WorkspaceChatMessage message) async {
    await context.read<AuthSession>().api.deleteChatMessage(
          projectSlug: _slug,
          messageId: message.id,
        );
    setState(() => _messages = _messages.where((m) => m.id != message.id).toList());
  }

  Future<void> _pinMessage(WorkspaceChatMessage message) async {
    await context.read<AuthSession>().api.pinChatMessage(
          projectSlug: _slug,
          messageId: message.id,
        );
    await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Expanded(
          child: RefreshIndicator(
            onRefresh: _refresh,
            child: _messages.isEmpty
                ? ListView(
                    children: const [
                      SizedBox(height: 120),
                      Center(child: Text('Aucun message')),
                    ],
                  )
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.fromLTRB(0, 8, 0, 8),
                    itemCount: _messages.length,
                    itemBuilder: (context, index) {
                      final message = _messages[index];
                      final isMine = message.userId == _currentUserId;
                      final editing = _editingId == message.id;
                      final prev = index > 0 ? _messages[index - 1] : null;
                      final next = index < _messages.length - 1
                          ? _messages[index + 1]
                          : null;
                      final clusterStart =
                          prev == null || prev.userId != message.userId;
                      final clusterEnd =
                          next == null || next.userId != message.userId;

                      return ChatMessageRow(
                        isMine: isMine,
                        groupChat: true,
                        clusterStart: clusterStart,
                        clusterEnd: clusterEnd,
                        userName: message.userName ?? 'Membre',
                        body: message.body,
                        createdAt: message.createdAt,
                        editedAt: message.editedAt,
                        replyPreview: message.replyPreview,
                        reactions: message.reactions,
                        attachments: message.attachments,
                        pinned: message.pinned,
                        onToggleReaction: (emoji) =>
                            _toggleReaction(message, emoji),
                        onLongPress: () {
                          final extras =
                              <({String label, IconData icon, VoidCallback onTap})>[];
                          if (message.canEdit) {
                            extras.add((
                              label: 'Modifier',
                              icon: Icons.edit_outlined,
                              onTap: () {
                                setState(() {
                                  _editingId = message.id;
                                  _editController.text = message.body;
                                });
                              },
                            ));
                            extras.add((
                              label: 'Supprimer',
                              icon: Icons.delete_outline,
                              onTap: () => _deleteMessage(message),
                            ));
                          }
                          if (_canWrite) {
                            extras.add((
                              label: message.pinned ? 'Désépingler' : 'Épingler',
                              icon: message.pinned
                                  ? Icons.push_pin_outlined
                                  : Icons.push_pin,
                              onTap: () => _pinMessage(message),
                            ));
                          }
                          showChatMessageActions(
                            context,
                            onReply: () => _setReply(message),
                            onReact: () => showReactionPicker(
                              context,
                              (emoji) => _toggleReaction(message, emoji),
                            ),
                            extraActions: extras,
                          );
                        },
                        editingChild: editing
                            ? Row(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Expanded(
                                    child: TextField(
                                      controller: _editController,
                                      minLines: 1,
                                      maxLines: 4,
                                      style: const TextStyle(fontSize: 15.5),
                                    ),
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.check_circle),
                                    onPressed: () => _saveEdit(message),
                                  ),
                                ],
                              )
                            : null,
                      );
                    },
                  ),
          ),
        ),
        if (_replyTo != null)
          ChatReplyBar(
            authorName: _replyTo!.userName ?? '',
            body: _replyTo!.body,
            onClose: () => setState(() => _replyTo = null),
          ),
        if (_typingUsers.label != null)
          ChatTypingIndicator(label: _typingUsers.label!),
        if (_canWrite)
          ChatComposer(
            controller: _controller,
            sending: _sending,
            onSend: _send,
            onAttach: _uploadAttachment,
            onChanged: (_) => _notifyTyping(),
            hintText: 'Message',
          ),          
      ],
    );
  }
}
