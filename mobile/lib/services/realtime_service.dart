import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:uuid/uuid.dart';

import '../api/panel_api.dart';

class RealtimeService extends ChangeNotifier {
  RealtimeService({PanelApi? api}) : _api = api ?? PanelApi();

  final PanelApi _api;
  final _notifications = FlutterLocalNotificationsPlugin();
  Timer? _timer;
  String? _since;
  int _lastUnreadNotifications = 0;
  int _lastUnreadMessages = 0;
  bool _running = false;
  bool _initialized = false;

  int get unreadNotifications => _lastUnreadNotifications;
  int get unreadMessages => _lastUnreadMessages;

  Future<void> init() async {
    if (_initialized) return;

    try {
      const android = AndroidInitializationSettings('@mipmap/ic_launcher');
      const ios = DarwinInitializationSettings();
      await _notifications.initialize(
        const InitializationSettings(android: android, iOS: ios),
      );
      await _registerPushToken();
    } catch (_) {
      // Notifications indisponibles (tests, desktop, permissions refusées).
    }

    _initialized = true;
  }

  Future<void> _registerPushToken() async {
    final token = const Uuid().v4();
    try {
      await _api.registerPushToken(
        platform: Platform.isIOS ? 'ios' : 'android',
        token: token,
      );
    } catch (_) {}
  }

  void start() {
    if (_running) return;
    _running = true;
    unawaited(init().then((_) => _tick()));
    _timer = Timer.periodic(const Duration(seconds: 3), (_) => _tick());
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
    _running = false;
  }

  Future<void> _tick() async {
    try {
      await _api.heartbeat();
      final sync = await _api.syncRealtime(since: _since);
      _since = sync.serverTime;

      if (sync.unreadNotifications > _lastUnreadNotifications) {
        await _showNotification(
          'Nouvelle notification',
          'Vous avez ${sync.unreadNotifications} notification(s)',
        );
      }

      if (sync.unreadCount > _lastUnreadMessages) {
        await _showNotification(
          'Nouveau message',
          'Vous avez ${sync.unreadCount} message(s) non lu(s)',
        );
      }

      _lastUnreadNotifications = sync.unreadNotifications;
      _lastUnreadMessages = sync.unreadCount;
      notifyListeners();
    } catch (_) {}
  }

  Future<void> _showNotification(String title, String body) async {
    const details = NotificationDetails(
      android: AndroidNotificationDetails(
        'pixelcraft_panel',
        'PixelCraft Panel',
        importance: Importance.defaultImportance,
        priority: Priority.defaultPriority,
      ),
      iOS: DarwinNotificationDetails(),
    );
    await _notifications.show(
      DateTime.now().millisecondsSinceEpoch ~/ 1000,
      title,
      body,
      details,
    );
  }

  @override
  void dispose() {
    stop();
    super.dispose();
  }
}
