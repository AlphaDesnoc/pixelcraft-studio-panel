import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/app_config.dart';
import '../models/attachment.dart';
import '../utils/format.dart';
import 'reaction_bar.dart';

bool chatShouldShowBody(String body, List<PanelAttachment> attachments) {
  final trimmed = body.trim();
  if (trimmed.isEmpty) return false;
  if (attachments.isNotEmpty && trimmed.startsWith('📎')) return false;
  return true;
}

bool chatIsEmojiOnly(String body) {
  final trimmed = body.trim();
  if (trimmed.isEmpty) return false;
  return !RegExp(r'[\p{L}\p{N}]', unicode: true).hasMatch(trimmed);
}

bool chatIsImageAttachment(PanelAttachment attachment) {
  if (attachment.mimeType.startsWith('image/')) return true;
  return RegExp(
    r'\.(png|jpe?g|gif|webp|bmp|svg)$',
    caseSensitive: false,
  ).hasMatch(attachment.originalName);
}

String chatAttachmentUrl(String? url) {
  if (url == null || url.isEmpty) return '';
  if (url.startsWith('http')) return url;
  return '${AppConfig.panelBaseUrl}$url';
}

BorderRadius _waBubbleRadius({
  required bool isMine,
  required bool clusterStart,
  required bool clusterEnd,
}) {
  const large = 10.0;
  const small = 3.0;

  if (isMine) {
    return BorderRadius.only(
      topLeft: const Radius.circular(large),
      topRight: Radius.circular(clusterStart ? large : small),
      bottomLeft: const Radius.circular(large),
      bottomRight: Radius.circular(clusterEnd ? small : large),
    );
  }

  return BorderRadius.only(
    topLeft: Radius.circular(clusterStart ? large : small),
    topRight: const Radius.circular(large),
    bottomLeft: Radius.circular(clusterEnd ? small : large),
    bottomRight: const Radius.circular(large),
  );
}

class ChatMessageRow extends StatelessWidget {
  const ChatMessageRow({
    super.key,
    required this.isMine,
    required this.userName,
    required this.body,
    this.createdAt,
    this.editedAt,
    this.replyPreview,
    this.reactions = const [],
    this.attachments = const [],
    this.pinned = false,
    this.isRead,
    this.groupChat = false,
    this.clusterStart = true,
    this.clusterEnd = true,
    this.onToggleReaction,
    this.onReply,
    this.onLongPress,
    this.trailing,
    this.editingChild,
  });

  final bool isMine;
  final String userName;
  final String body;
  final String? createdAt;
  final String? editedAt;
  final ReplyPreview? replyPreview;
  final List<MessageReaction> reactions;
  final List<PanelAttachment> attachments;
  final bool pinned;
  final bool? isRead;
  final bool groupChat;
  final bool clusterStart;
  final bool clusterEnd;
  final ValueChanged<String>? onToggleReaction;
  final VoidCallback? onReply;
  final VoidCallback? onLongPress;
  final Widget? trailing;
  final Widget? editingChild;

  bool get _showSenderName => groupChat && !isMine && clusterStart;
  bool get _showAvatar => groupChat && !isMine && clusterEnd;

  @override
  Widget build(BuildContext context) {
    final screenWidth = MediaQuery.sizeOf(context).width;
    final maxWidth = (screenWidth * 0.78).clamp(220.0, 320.0);
    final topPadding = clusterStart ? 6.0 : 2.0;

    return Padding(
      padding: EdgeInsets.fromLTRB(8, topPadding, 8, 0),
      child: Row(
        mainAxisAlignment:
            isMine ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (groupChat && !isMine)
            SizedBox(
              width: 32,
              child: _showAvatar
                  ? Padding(
                      padding: const EdgeInsets.only(bottom: 2, right: 6),
                      child: _Avatar(name: userName, radius: 14),
                    )
                  : null,
            ),
          ConstrainedBox(
            constraints: BoxConstraints(maxWidth: maxWidth),
            child: GestureDetector(
              onLongPress: onLongPress ?? onReply,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment:
                    isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
                children: [
                  if (_showSenderName)
                    Padding(
                      padding: const EdgeInsets.only(left: 4, bottom: 2),
                      child: Text(
                        userName,
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                              color: Theme.of(context).colorScheme.primary,
                              fontWeight: FontWeight.w600,
                              fontSize: 12.5,
                            ),
                      ),
                    ),
                  _ChatBubble(
                    isMine: isMine,
                    pinned: pinned,
                    body: body,
                    createdAt: createdAt,
                    editedAt: editedAt,
                    replyPreview: replyPreview,
                    reactions: reactions,
                    attachments: attachments,
                    isRead: isRead,
                    clusterStart: clusterStart,
                    clusterEnd: clusterEnd,
                    onToggleReaction: onToggleReaction,
                    trailing: trailing,
                    editingChild: editingChild,
                  ),
                  if (reactions.isNotEmpty && editingChild == null)
                    Padding(
                      padding: EdgeInsets.only(
                        top: 4,
                        left: isMine ? 0 : 8,
                        right: isMine ? 8 : 0,
                      ),
                      child: _ReactionPills(
                        reactions: reactions,
                        onToggle: onToggleReaction,
                      ),
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

class _Avatar extends StatelessWidget {
  const _Avatar({required this.name, this.radius = 16});

  final String name;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return CircleAvatar(
      radius: radius,
      backgroundColor: theme.colorScheme.surfaceContainerHighest,
      foregroundColor: theme.colorScheme.onSurfaceVariant,
      child: Text(
        initialsFromName(name),
        style: TextStyle(
          fontSize: radius * 0.72,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _ChatBubble extends StatelessWidget {
  const _ChatBubble({
    required this.isMine,
    required this.pinned,
    required this.body,
    this.createdAt,
    this.editedAt,
    this.replyPreview,
    this.reactions = const [],
    this.attachments = const [],
    this.isRead,
    required this.clusterStart,
    required this.clusterEnd,
    this.onToggleReaction,
    this.trailing,
    this.editingChild,
  });

  final bool isMine;
  final bool pinned;
  final String body;
  final String? createdAt;
  final String? editedAt;
  final ReplyPreview? replyPreview;
  final List<MessageReaction> reactions;
  final List<PanelAttachment> attachments;
  final bool? isRead;
  final bool clusterStart;
  final bool clusterEnd;
  final ValueChanged<String>? onToggleReaction;
  final Widget? trailing;
  final Widget? editingChild;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final bubbleColor = pinned
        ? scheme.secondaryContainer.withValues(alpha: 0.65)
        : isMine
            ? scheme.primary.withValues(alpha: 0.14)
            : scheme.surfaceContainerHigh;
    final textColor = scheme.onSurface;
    final metaColor = scheme.onSurfaceVariant.withValues(alpha: 0.85);
    final showBody = chatShouldShowBody(body, attachments);
    final emojiOnly = showBody && chatIsEmojiOnly(body);
    final hasMedia = attachments.isNotEmpty;

    return DecoratedBox(
      decoration: BoxDecoration(
        color: bubbleColor,
        borderRadius: _waBubbleRadius(
          isMine: isMine,
          clusterStart: clusterStart,
          clusterEnd: clusterEnd,
        ),
        border: pinned
            ? Border.all(color: scheme.secondary.withValues(alpha: 0.35))
            : null,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 1,
            offset: const Offset(0, 1),
          ),
        ],
      ),
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          hasMedia ? 4 : 9,
          hasMedia ? 4 : 6,
          hasMedia ? 4 : 8,
          hasMedia ? 4 : 5,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (replyPreview != null) ...[
              _ReplyPreview(preview: replyPreview!, isMine: isMine),
              const SizedBox(height: 4),
            ],
            if (editingChild != null)
              editingChild!
            else ...[
              if (showBody)
                _MessageText(
                  body: body,
                  emojiOnly: emojiOnly,
                  textColor: textColor,
                ),
              if (attachments.isNotEmpty)
                Padding(
                  padding: EdgeInsets.only(top: showBody ? 4 : 0),
                  child: _AttachmentList(attachments: attachments),
                ),
              Padding(
                padding: const EdgeInsets.only(top: 2, left: 8),
                child: Align(
                  alignment: Alignment.centerRight,
                  child: _MetaRow(
                    createdAt: createdAt,
                    editedAt: editedAt,
                    isRead: isMine ? isRead : null,
                    pinned: pinned,
                    metaColor: metaColor,
                    scheme: scheme,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _MessageText extends StatelessWidget {
  const _MessageText({
    required this.body,
    required this.emojiOnly,
    required this.textColor,
  });

  final String body;
  final bool emojiOnly;
  final Color textColor;

  @override
  Widget build(BuildContext context) {
    return Text(
      body,
      style: TextStyle(
        color: textColor,
        fontSize: emojiOnly ? 34 : 15.5,
        height: emojiOnly ? 1.1 : 1.35,
        letterSpacing: 0.1,
      ),
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({
    required this.createdAt,
    required this.editedAt,
    required this.isRead,
    required this.pinned,
    required this.metaColor,
    required this.scheme,
  });

  final String? createdAt;
  final String? editedAt;
  final bool? isRead;
  final bool pinned;
  final Color metaColor;
  final ColorScheme scheme;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (pinned) ...[
          Icon(Icons.push_pin, size: 12, color: metaColor),
          const SizedBox(width: 3),
        ],
        if (editedAt != null && editedAt!.isNotEmpty) ...[
          Text(
            'Modifié',
            style: TextStyle(color: metaColor, fontSize: 11),
          ),
          const SizedBox(width: 4),
        ],
        if (createdAt != null && createdAt!.isNotEmpty)
          Text(
            formatMessageTime(createdAt),
            style: TextStyle(color: metaColor, fontSize: 11),
          ),
        if (isRead != null) ...[
          const SizedBox(width: 3),
          Icon(
            isRead! ? Icons.done_all : Icons.done,
            size: 15,
            color: isRead! ? scheme.primary : metaColor,
          ),
        ],
      ],
    );
  }
}

class _ReactionPills extends StatelessWidget {
  const _ReactionPills({
    required this.reactions,
    this.onToggle,
  });

  final List<MessageReaction> reactions;
  final ValueChanged<String>? onToggle;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 4,
            offset: const Offset(0, 1),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: reactions
              .map(
                (r) => InkWell(
                  onTap: onToggle == null ? null : () => onToggle!(r.emoji),
                  borderRadius: BorderRadius.circular(8),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 3),
                    child: Text(
                      r.emoji,
                      style: const TextStyle(fontSize: 14),
                    ),
                  ),
                ),
              )
              .toList(),
        ),
      ),
    );
  }
}

class _ReplyPreview extends StatelessWidget {
  const _ReplyPreview({required this.preview, required this.isMine});

  final ReplyPreview preview;
  final bool isMine;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Container(
      constraints: const BoxConstraints(maxWidth: 280),
      padding: const EdgeInsets.fromLTRB(8, 6, 8, 6),
      decoration: BoxDecoration(
        color: scheme.surface.withValues(alpha: isMine ? 0.35 : 0.5),
        borderRadius: BorderRadius.circular(6),
        border: Border(
          left: BorderSide(color: scheme.primary, width: 3),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (preview.userName != null && preview.userName!.isNotEmpty)
            Text(
              preview.userName!,
              style: theme.textTheme.labelSmall?.copyWith(
                fontWeight: FontWeight.w600,
                color: scheme.primary,
                fontSize: 12,
              ),
            ),
          Text(
            preview.body,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodySmall?.copyWith(
              color: scheme.onSurfaceVariant,
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }
}

class _AttachmentList extends StatelessWidget {
  const _AttachmentList({required this.attachments});

  final List<PanelAttachment> attachments;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: attachments.map((attachment) {
        final url = chatAttachmentUrl(attachment.url);
        if (url.isEmpty) return const SizedBox.shrink();

        if (chatIsImageAttachment(attachment)) {
          return ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: InkWell(
              onTap: () => _openUrl(url),
              child: Image.network(
                url,
                width: 240,
                height: 180,
                fit: BoxFit.cover,
                loadingBuilder: (context, child, progress) {
                  if (progress == null) return child;
                  return const SizedBox(
                    width: 240,
                    height: 120,
                    child: Center(child: CircularProgressIndicator()),
                  );
                },
                errorBuilder: (context, error, stackTrace) {
                  return _FileLink(name: attachment.originalName, url: url);
                },
              ),
            ),
          );
        }

        return Padding(
          padding: const EdgeInsets.only(top: 4),
          child: _FileLink(name: attachment.originalName, url: url),
        );
      }).toList(),
    );
  }

  Future<void> _openUrl(String url) async {
    final uri = Uri.parse(url);
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}

class _FileLink extends StatelessWidget {
  const _FileLink({required this.name, required this.url});

  final String name;
  final String url;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      onTap: () async {
        final uri = Uri.parse(url);
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      },
      borderRadius: BorderRadius.circular(6),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 4),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.insert_drive_file_outlined,
                size: 18, color: theme.colorScheme.primary),
            const SizedBox(width: 6),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 220),
              child: Text(
                name,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.primary,
                  fontSize: 13,
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

Future<void> showReactionPicker(
  BuildContext context,
  ValueChanged<String> onSelected,
) async {
  final emoji = await showModalBottomSheet<String>(
    context: context,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        child: Wrap(
          spacing: 8,
          runSpacing: 8,
          alignment: WrapAlignment.center,
          children: commonEmojis
              .map(
                (e) => InkWell(
                  onTap: () => Navigator.pop(context, e),
                  borderRadius: BorderRadius.circular(12),
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: Text(e, style: const TextStyle(fontSize: 28)),
                  ),
                ),
              )
              .toList(),
        ),
      ),
    ),
  );
  if (emoji != null) onSelected(emoji);
}
