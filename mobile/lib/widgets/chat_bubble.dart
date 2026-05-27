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
    this.onToggleReaction,
    this.onReply,
    this.trailing,
    this.footer,
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
  final ValueChanged<String>? onToggleReaction;
  final VoidCallback? onReply;
  final Widget? trailing;
  final Widget? footer;
  final Widget? editingChild;

  @override
  Widget build(BuildContext context) {
    final maxWidth = MediaQuery.sizeOf(context).width * 0.78;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        mainAxisAlignment: isMine ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (!isMine) ...[
            _Avatar(name: userName),
            const SizedBox(width: 8),
          ],
          Flexible(
            child: ConstrainedBox(
              constraints: BoxConstraints(maxWidth: maxWidth),
              child: _ChatBubble(
                isMine: isMine,
                pinned: pinned,
                userName: userName,
                body: body,
                createdAt: createdAt,
                editedAt: editedAt,
                replyPreview: replyPreview,
                reactions: reactions,
                attachments: attachments,
                isRead: isRead,
                onToggleReaction: onToggleReaction,
                onReply: onReply,
                trailing: trailing,
                footer: footer,
                editingChild: editingChild,
              ),
            ),
          ),
          if (isMine) ...[
            const SizedBox(width: 8),
            _Avatar(name: userName),
          ],
        ],
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return CircleAvatar(
      radius: 16,
      backgroundColor: theme.colorScheme.surfaceContainerHighest,
      foregroundColor: theme.colorScheme.onSurfaceVariant,
      child: Text(
        initialsFromName(name),
        style: theme.textTheme.labelSmall?.copyWith(fontWeight: FontWeight.w600),
      ),
    );
  }
}

class _ChatBubble extends StatelessWidget {
  const _ChatBubble({
    required this.isMine,
    required this.pinned,
    required this.userName,
    required this.body,
    this.createdAt,
    this.editedAt,
    this.replyPreview,
    this.reactions = const [],
    this.attachments = const [],
    this.isRead,
    this.onToggleReaction,
    this.onReply,
    this.trailing,
    this.footer,
    this.editingChild,
  });

  final bool isMine;
  final bool pinned;
  final String userName;
  final String body;
  final String? createdAt;
  final String? editedAt;
  final ReplyPreview? replyPreview;
  final List<MessageReaction> reactions;
  final List<PanelAttachment> attachments;
  final bool? isRead;
  final ValueChanged<String>? onToggleReaction;
  final VoidCallback? onReply;
  final Widget? trailing;
  final Widget? footer;
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
    final metaColor = scheme.onSurfaceVariant;
    final showBody = chatShouldShowBody(body, attachments);
    final emojiOnly = showBody && chatIsEmojiOnly(body);

    return DecoratedBox(
      decoration: BoxDecoration(
        color: bubbleColor,
        borderRadius: BorderRadius.circular(14),
        border: pinned
            ? Border.all(color: scheme.secondary.withValues(alpha: 0.35))
            : null,
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    [
                      userName,
                      if (createdAt != null && createdAt!.isNotEmpty)
                        formatRelativeTime(createdAt),
                      if (editedAt != null && editedAt!.isNotEmpty) '(modifié)',
                    ].join(' · '),
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: metaColor,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
                if (pinned)
                  Padding(
                    padding: const EdgeInsets.only(left: 4),
                    child: Icon(Icons.push_pin, size: 14, color: metaColor),
                  ),
                if (trailing != null) trailing!,
              ],
            ),
            if (replyPreview != null) ...[
              const SizedBox(height: 6),
              _ReplyPreview(preview: replyPreview!),
            ],
            if (editingChild != null) ...[
              const SizedBox(height: 6),
              editingChild!,
            ] else if (showBody) ...[
              const SizedBox(height: 4),
              Text(
                body,
                style: emojiOnly
                    ? theme.textTheme.headlineMedium?.copyWith(color: textColor)
                    : theme.textTheme.bodyMedium?.copyWith(color: textColor),
              ),
            ],
            if (attachments.isNotEmpty) ...[
              const SizedBox(height: 6),
              _AttachmentList(attachments: attachments),
            ],
            if (onToggleReaction != null) ...[
              const SizedBox(height: 4),
              ReactionBar(
                reactions: reactions,
                onToggle: onToggleReaction!,
                compact: true,
              ),
            ],
            if (footer != null) footer!,
            if (isMine && isRead != null)
              Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Align(
                  alignment: Alignment.centerRight,
                  child: Icon(
                    isRead! ? Icons.done_all : Icons.done,
                    size: 14,
                    color: isRead!
                        ? scheme.primary
                        : metaColor.withValues(alpha: 0.7),
                  ),
                ),
              ),
            if (onReply != null && editingChild == null)
              Align(
                alignment: Alignment.centerRight,
                child: IconButton(
                  visualDensity: VisualDensity.compact,
                  iconSize: 16,
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(minWidth: 28, minHeight: 28),
                  icon: Icon(Icons.reply, color: metaColor),
                  onPressed: onReply,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _ReplyPreview extends StatelessWidget {
  const _ReplyPreview({required this.preview});

  final ReplyPreview preview;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface.withValues(alpha: 0.45),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: theme.colorScheme.outline.withValues(alpha: 0.25),
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
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          Text(
            preview.body,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.labelSmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
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
      crossAxisAlignment: CrossAxisAlignment.start,
      children: attachments.map((attachment) {
        final url = chatAttachmentUrl(attachment.url);
        if (url.isEmpty) return const SizedBox.shrink();

        if (chatIsImageAttachment(attachment)) {
          return Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: InkWell(
                onTap: () => _openUrl(url),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxHeight: 192),
                  child: Image.network(
                    url,
                    fit: BoxFit.cover,
                    width: double.infinity,
                    loadingBuilder: (context, child, progress) {
                      if (progress == null) return child;
                      return SizedBox(
                        height: 120,
                        width: double.infinity,
                        child: Center(
                          child: CircularProgressIndicator(
                            value: progress.expectedTotalBytes != null
                                ? progress.cumulativeBytesLoaded /
                                    progress.expectedTotalBytes!
                                : null,
                          ),
                        ),
                      );
                    },
                    errorBuilder: (context, error, stackTrace) {
                      return _FileLink(name: attachment.originalName, url: url);
                    },
                  ),
                ),
              ),
            ),
          );
        }

        return Padding(
          padding: const EdgeInsets.only(bottom: 4),
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
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.attach_file, size: 16, color: theme.colorScheme.primary),
            const SizedBox(width: 4),
            Flexible(
              child: Text(
                name,
                style: theme.textTheme.labelMedium?.copyWith(
                  color: theme.colorScheme.primary,
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
