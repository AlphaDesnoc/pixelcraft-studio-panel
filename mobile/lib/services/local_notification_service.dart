import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

/// Notifications affichées directement dans la barre système Android (sans FCM).
class LocalNotificationService {
  LocalNotificationService._();

  static final FlutterLocalNotificationsPlugin _plugin =
      FlutterLocalNotificationsPlugin();

  static bool _initialized = false;
  static int _id = 0;

  static const _channelMessages = AndroidNotificationChannel(
    'pixelcraft_messages',
    'Messages',
    description: 'Messages privés et chat d\'espace',
    importance: Importance.high,
    playSound: true,
    enableVibration: true,
  );

  static const _channelAlerts = AndroidNotificationChannel(
    'pixelcraft_alerts',
    'Alertes',
    description: 'Tâches, bugs, calendrier et autres alertes',
    importance: Importance.high,
    playSound: true,
    enableVibration: true,
  );

  static Future<bool> initialize() async {
    if (_initialized) return true;

    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const ios = DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );

    await _plugin.initialize(
      const InitializationSettings(android: android, iOS: ios),
    );

    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      final androidPlugin =
          _plugin.resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>();
      await androidPlugin?.createNotificationChannel(_channelMessages);
      await androidPlugin?.createNotificationChannel(_channelAlerts);
    }

    _initialized = true;
    return requestPermission();
  }

  /// Android 13+ : demande POST_NOTIFICATIONS à l'utilisateur.
  static Future<bool> requestPermission() async {
    if (kIsWeb) return false;

    if (defaultTargetPlatform == TargetPlatform.android) {
      final android = _plugin.resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin>();
      final granted = await android?.requestNotificationsPermission();
      return granted ?? false;
    }

    if (defaultTargetPlatform == TargetPlatform.iOS) {
      final ios = _plugin.resolvePlatformSpecificImplementation<
          IOSFlutterLocalNotificationsPlugin>();
      final granted = await ios?.requestPermissions(
        alert: true,
        badge: true,
        sound: true,
      );
      return granted ?? false;
    }

    return false;
  }

  static Future<void> showMessage({
    required String title,
    required String body,
    String? payload,
  }) {
    return _show(
      channel: _channelMessages,
      title: title,
      body: body,
      payload: payload,
    );
  }

  static Future<void> showAlert({
    required String title,
    required String body,
    String? payload,
  }) {
    return _show(
      channel: _channelAlerts,
      title: title,
      body: body,
      payload: payload,
    );
  }

  static Future<void> _show({
    required AndroidNotificationChannel channel,
    required String title,
    required String body,
    String? payload,
  }) async {
    if (!_initialized) {
      await initialize();
    }

    _id = (_id + 1) % 100000;

    final details = NotificationDetails(
      android: AndroidNotificationDetails(
        channel.id,
        channel.name,
        channelDescription: channel.description,
        importance: channel.importance,
        priority: Priority.high,
        icon: '@mipmap/ic_launcher',
        styleInformation: body.length > 40
            ? BigTextStyleInformation(body, contentTitle: title)
            : null,
      ),
      iOS: const DarwinNotificationDetails(
        presentAlert: true,
        presentBadge: true,
        presentSound: true,
      ),
    );

    await _plugin.show(_id, title, body, details, payload: payload);
  }
}
