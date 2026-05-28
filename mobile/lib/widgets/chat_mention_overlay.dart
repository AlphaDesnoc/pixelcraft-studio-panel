import 'package:flutter/material.dart';

import '../models/workspace.dart';

class ChatMentionCandidate {
  const ChatMentionCandidate({
    required this.label,
    required this.insertText,
    this.subtitle,
  });

  final String label;
  final String insertText;
  final String? subtitle;
}

List<ChatMentionCandidate> buildChatMentionCandidates({
  required String text,
  required int cursor,
  required List<WorkspaceMember> members,
  required List<WorkspaceRank> ranks,
  required bool includeRanks,
}) {
  final before = text.substring(0, cursor.clamp(0, text.length));
  final match = RegExp(r'@([\w.-]*)$').firstMatch(before);
  if (match == null) return [];

  final query = match.group(1)?.toLowerCase() ?? '';
  final results = <ChatMentionCandidate>[];

  if (includeRanks) {
    for (final rank in ranks) {
      final key = rank.key.toLowerCase();
      if (query.isEmpty || key.startsWith(query) || rank.label.toLowerCase().contains(query)) {
        results.add(
          ChatMentionCandidate(
            label: rank.label,
            insertText: '@${rank.key}',
            subtitle: 'Rank',
          ),
        );
      }
    }
  }

  for (final member in members) {
    final pseudo = member.email.split('@').first.toLowerCase();
    if (query.isEmpty || pseudo.startsWith(query) || member.name.toLowerCase().contains(query)) {
      results.add(
        ChatMentionCandidate(
          label: member.name,
          insertText: '@$pseudo',
          subtitle: 'Membre',
        ),
      );
    }
  }

  return results.take(8).toList();
}

String applyMentionInsert({
  required String text,
  required int cursor,
  required String insertText,
}) {
  final before = text.substring(0, cursor.clamp(0, text.length));
  final after = text.substring(cursor.clamp(0, text.length));
  final match = RegExp(r'@([\w.-]*)$').firstMatch(before);
  if (match == null) return text;

  final prefix = before.substring(0, match.start);
  return '$prefix$insertText $after';
}

class ChatMentionOverlay extends StatelessWidget {
  const ChatMentionOverlay({
    super.key,
    required this.candidates,
    required this.onSelect,
  });

  final List<ChatMentionCandidate> candidates;
  final ValueChanged<ChatMentionCandidate> onSelect;

  @override
  Widget build(BuildContext context) {
    if (candidates.isEmpty) return const SizedBox.shrink();

    return Material(
      elevation: 4,
      borderRadius: BorderRadius.circular(8),
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxHeight: 180),
        child: ListView.separated(
          shrinkWrap: true,
          padding: EdgeInsets.zero,
          itemCount: candidates.length,
          separatorBuilder: (context, index) => const Divider(height: 1),
          itemBuilder: (context, index) {
            final candidate = candidates[index];
            return ListTile(
              dense: true,
              title: Text(candidate.label),
              subtitle: candidate.subtitle != null ? Text(candidate.subtitle!) : null,
              onTap: () => onSelect(candidate),
            );
          },
        ),
      ),
    );
  }
}
