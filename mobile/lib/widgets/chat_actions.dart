import 'package:flutter/material.dart';

Future<void> showChatMessageActions(
  BuildContext context, {
  VoidCallback? onReply,
  VoidCallback? onReact,
  List<({String label, IconData icon, VoidCallback onTap})> extraActions = const [],
}) {
  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (onReply != null)
            ListTile(
              leading: const Icon(Icons.reply),
              title: const Text('Répondre'),
              onTap: () {
                Navigator.pop(context);
                onReply();
              },
            ),
          if (onReact != null)
            ListTile(
              leading: const Icon(Icons.add_reaction_outlined),
              title: const Text('Réagir'),
              onTap: () {
                Navigator.pop(context);
                onReact();
              },
            ),
          for (final action in extraActions)
            ListTile(
              leading: Icon(action.icon),
              title: Text(action.label),
              onTap: () {
                Navigator.pop(context);
                action.onTap();
              },
            ),
        ],
      ),
    ),
  );
}
