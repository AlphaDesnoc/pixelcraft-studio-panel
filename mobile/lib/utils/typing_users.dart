import 'dart:async';

class TypingUsersController {
  TypingUsersController();

  static const ttl = Duration(seconds: 3);

  void Function()? onChanged;
  final Map<int, String> _users = {};
  final Map<int, Timer> _timeouts = {};

  List<String> get names => _users.values.toList();

  String? get label {
    final active = names;
    if (active.isEmpty) return null;
    if (active.length == 1) return '${active.first} écrit…';
    if (active.length == 2) return '${active[0]} et ${active[1]} écrivent…';
    return '${active.first} et ${active.length - 1} autres écrivent…';
  }

  void add(int userId, String name, {int? excludeUserId}) {
    if (excludeUserId != null && userId == excludeUserId) return;

    _timeouts[userId]?.cancel();
    _users[userId] = name;
    _timeouts[userId] = Timer(ttl, () {
      _users.remove(userId);
      _timeouts.remove(userId);
      onChanged?.call();
    });
    onChanged?.call();
  }

  void clear() {
    for (final timer in _timeouts.values) {
      timer.cancel();
    }
    _users.clear();
    _timeouts.clear();
  }

  void dispose() => clear();
}
