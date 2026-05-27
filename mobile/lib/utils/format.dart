import 'package:intl/intl.dart';

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
