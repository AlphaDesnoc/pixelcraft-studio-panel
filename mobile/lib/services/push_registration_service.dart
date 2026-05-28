import 'dart:io';

import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

import '../api/panel_api.dart';

/// Enregistre un token push auprès de l'API (FCM côté serveur).
/// Remplace le token stocké lorsque Firebase Messaging est configuré.
class PushRegistrationService {
  static const _tokenKey = 'pixelcraft_push_token';

  static Future<void> registerIfNeeded(PanelApi api) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      var token = prefs.getString(_tokenKey);
      token ??= const Uuid().v4();
      await prefs.setString(_tokenKey, token);

      await api.registerPushToken(
        platform: Platform.isIOS ? 'ios' : 'android',
        token: token,
      );
    } catch (_) {
      // Push optionnel — l'app fonctionne sans FCM configuré.
    }
  }

  static Future<void> updateToken(PanelApi api, String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
    await api.registerPushToken(
      platform: Platform.isIOS ? 'ios' : 'android',
      token: token,
    );
  }
}
