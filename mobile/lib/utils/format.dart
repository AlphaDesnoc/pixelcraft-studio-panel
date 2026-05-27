import 'package:intl/intl.dart';

String _formatClock(DateTime local) {
  final hours = local.hour.toString().padLeft(2, '0');
  final minutes = local.minute.toString().padLeft(2, '0');
  return '$hours:$minutes';
}

String formatMessageTime(String? iso) {
  if (iso == null || iso.isEmpty) return '';

  final date = DateTime.tryParse(iso);
  if (date == null) return '';

  final local = date.toLocal();
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final messageDay = DateTime(local.year, local.month, local.day);

  try {
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
  } catch (_) {
    if (messageDay == today) {
      return _formatClock(local);
    }
    if (messageDay == today.subtract(const Duration(days: 1))) {
      return 'Hier ${_formatClock(local)}';
    }
    final day = local.day.toString().padLeft(2, '0');
    final month = local.month.toString().padLeft(2, '0');
    final year = (local.year % 100).toString().padLeft(2, '0');
    return '$day/$month/$year ${_formatClock(local)}';
  }
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
  if (diff.inDays < 1) return _formatClock(local);
  try {
    if (diff.inDays < 7) return DateFormat.E('fr_FR').format(local);
    return DateFormat.yMMMd('fr_FR').format(local);
  } catch (_) {
    if (diff.inDays < 7) return DateFormat.E().format(local);
    return DateFormat.yMMMd().format(local);
  }
}

String initialsFromName(String name) {
  final parts = name.trim().split(RegExp(r'\s+'));
  if (parts.isEmpty || parts.first.isEmpty) return '?';
  if (parts.length == 1) return parts.first[0].toUpperCase();
  return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
}
