import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../api/panel_api_extensions.dart';
import '../../config/app_config.dart';
import '../../models/attachment.dart';
import '../../models/workspace.dart';
import '../../services/auth_session.dart';
import '../../services/realtime_service.dart';
import '../../services/reverb_service.dart';
import '../../utils/format.dart';
import '../../utils/typing_users.dart';
import '../../widgets/reaction_bar.dart';

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

class _ChatTabState extends State<ChatTab> {
  late List<WorkspaceChatMessage> _messages;
  final _controller = TextEditingController();
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
    super.dispose();
  }

  Future<void> _refresh() async {
    try {
      final messages = await context.read<AuthSession>().api.fetchChatMessages(_slug);
      if (!mounted) return;
      setState(() => _messages = messages);
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

  Future<void> _openAttachment(String? url) async {
    if (url == null) return;
    final uri = Uri.parse('${AppConfig.panelBaseUrl}$url');
    await launchUrl(uri, mode: LaunchMode.externalApplication);
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
                    padding: const EdgeInsets.all(12),
                    itemCount: _messages.length,
                    itemBuilder: (context, index) {
                      final message = _messages[index];
                      final isMine = message.userId == _currentUserId;
                      final editing = _editingId == message.id;

                      return Align(
                        alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
                        child: Card(
                          margin: const EdgeInsets.only(bottom: 8),
                          color: message.pinned
                              ? Theme.of(context).colorScheme.secondaryContainer
                              : null,
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        message.userName ?? 'Membre',
                                        style: Theme.of(context).textTheme.labelLarge,
                                      ),
                                    ),
                                    if (message.pinned)
                                      const Icon(Icons.push_pin, size: 16),
                                    PopupMenuButton<String>(
                                      itemBuilder: (context) => [
                                        const PopupMenuItem(value: 'reply', child: Text('Répondre')),
                                        if (message.canEdit)
                                          const PopupMenuItem(value: 'edit', child: Text('Modifier')),
                                        if (message.canEdit)
                                          const PopupMenuItem(value: 'delete', child: Text('Supprimer')),
                                        if (_canWrite)
                                          PopupMenuItem(
                                            value: 'pin',
                                            child: Text(message.pinned ? 'Désépingler' : 'Épingler'),
                                          ),
                                      ],
                                      onSelected: (value) {
                                        switch (value) {
                                          case 'reply':
                                            _setReply(message);
                                          case 'edit':
                                            setState(() {
                                              _editingId = message.id;
                                              _editController.text = message.body;
                                            });
                                          case 'delete':
                                            _deleteMessage(message);
                                          case 'pin':
                                            _pinMessage(message);
                                        }
                                      },
                                    ),
                                  ],
                                ),
                                if (message.replyPreview != null) ...[
                                  const SizedBox(height: 6),
                                  Container(
                                    padding: const EdgeInsets.all(8),
                                    decoration: BoxDecoration(
                                      color: Theme.of(context).colorScheme.surfaceContainerHighest,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      '${message.replyPreview!.userName ?? ''}: ${message.replyPreview!.body}',
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(context).textTheme.labelSmall,
                                    ),
                                  ),
                                ],
                                const SizedBox(height: 4),
                                if (editing)
                                  Row(
                                    children: [
                                      Expanded(
                                        child: TextField(
                                          controller: _editController,
                                          minLines: 1,
                                          maxLines: 4,
                                        ),
                                      ),
                                      IconButton(
                                        icon: const Icon(Icons.check),
                                        onPressed: () => _saveEdit(message),
                                      ),
                                    ],
                                  )
                                else if (message.body.isNotEmpty)
                                  Text(message.body),
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
                                if (message.createdAt != null)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 4),
                                    child: Text(
                                      [
                                        formatRelativeTime(message.createdAt),
                                        if (message.editedAt != null) '(modifié)',
                                      ].join(' '),
                                      style: Theme.of(context).textTheme.labelSmall,
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ),
        if (_replyTo != null)
          Material(
            color: Theme.of(context).colorScheme.surfaceContainerHighest,
            child: ListTile(
              dense: true,
              title: Text('Réponse à ${ _replyTo!.userName ?? ''}'),
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
        if (_canWrite)
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(12),
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
                      decoration: const InputDecoration(
                        hintText: 'Message…',
                        border: OutlineInputBorder(),
                      ),
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
    );
  }
}
