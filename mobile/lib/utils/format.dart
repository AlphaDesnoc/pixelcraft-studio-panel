import 'package:intl/intl.dart';

String formatMessageTime(String? iso) {
  if (iso == null || iso.isEmpty) return '';

  final date = DateTime.tryParse(iso);
  if (date == null) return '';

  final local = date.toLocal();
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final messageDay = DateTime(local.year, local.month, local.day);

  if (messageDay == today) {
    return DateFormat.Hm('fr_FR').format(local);
  }
  if (messageDay == today.subtract(const Duration(days: 1))) {
    return 'Hier ${DateFormat.Hm('fr_FR').format(local)}';
  }
  if (now.difference(local).inDays < 7) {
    return '${DateFormat.E('fr_FR').format(local)} ${DateFormat.Hm('fr_FR').format(local)}';
  }
  return DateFormat('dd/MM/yy HH:mm', 'fr_FR').format(local);
}

String formatRelativeTime(String? iso) {
  if (iso == null || iso.isEmpty) {
    return '';
  }

  final date = DateTime.tryParse(iso);
  if (date == null) {
    return '';
  }

  final local = date.toLocal();
  final now = DateTime.now();
  final diff = now.difference(local);

  if (diff.inMinutes < 1) return 'À l’instant';
  if (diff.inHours < 1) return 'Il y a ${diff.inMinutes} min';
  if (diff.inDays < 1) return DateFormat.Hm().format(local);
  if (diff.inDays < 7) return DateFormat.E('fr_FR').format(local);
  return DateFormat.yMMMd('fr_FR').format(local);
}

String initialsFromName(String name) {
  final parts = name.trim().split(RegExp(r'\s+'));
  if (parts.isEmpty || parts.first.isEmpty) return '?';
  if (parts.length == 1) return parts.first[0].toUpperCase();
  return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
}
