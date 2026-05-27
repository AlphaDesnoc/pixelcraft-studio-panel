class PanelUser {
  const PanelUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.twoFactorEnabled,
    required this.isAdmin,
    this.notificationPreferences = const {},
  });

  final int id;
  final String name;
  final String email;
  final String role;
  final bool twoFactorEnabled;
  final bool isAdmin;
  final Map<String, bool> notificationPreferences;

  factory PanelUser.fromJson(Map<String, dynamic> json) {
    final prefsRaw = json['notification_preferences'];
    final prefs = <String, bool>{};
    if (prefsRaw is Map) {
      prefsRaw.forEach((key, value) {
        prefs[key.toString()] = value == true;
      });
    }

    return PanelUser(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: json['role'] as String? ?? 'member',
      twoFactorEnabled: json['two_factor_enabled'] == true,
      isAdmin: json['is_admin'] == true,
      notificationPreferences: prefs,
    );
  }
}
