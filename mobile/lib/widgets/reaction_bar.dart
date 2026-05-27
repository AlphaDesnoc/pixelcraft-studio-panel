import 'package:flutter/material.dart';

import '../models/attachment.dart';

const commonEmojis = ['👍', '❤️', '😂', '🎉'];

class ReactionBar extends StatelessWidget {
  const ReactionBar({
    super.key,
    required this.reactions,
    required this.onToggle,
    this.compact = false,
  });

  final List<MessageReaction> reactions;
  final ValueChanged<String> onToggle;
  final bool compact;

  Future<void> _pickEmoji(BuildContext context) async {
    final emoji = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Wrap(
            spacing: 12,
            runSpacing: 12,
            children: commonEmojis
                .map(
                  (e) => ActionChip(
                    label: Text(e, style: const TextStyle(fontSize: 22)),
                    onPressed: () => Navigator.pop(context, e),
                  ),
                )
                .toList(),
          ),
        ),
      ),
    );
    if (emoji != null) onToggle(emoji);
  }

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 6,
      runSpacing: 4,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        ...reactions.map(
          (reaction) => ActionChip(
            visualDensity: compact ? VisualDensity.compact : VisualDensity.standard,
            label: Text('${reaction.emoji} ${reaction.count}'),
            backgroundColor: reaction.me
                ? Theme.of(context).colorScheme.primaryContainer
                : null,
            onPressed: () => onToggle(reaction.emoji),
          ),
        ),
        IconButton(
          visualDensity: compact ? VisualDensity.compact : VisualDensity.standard,
          iconSize: compact ? 18 : 22,
          padding: compact ? EdgeInsets.zero : null,
          constraints: compact ? const BoxConstraints(minWidth: 28, minHeight: 28) : null,
          tooltip: 'Réagir',
          icon: const Icon(Icons.add_reaction_outlined),
          onPressed: () => _pickEmoji(context),
        ),
      ],
    );
  }
}
