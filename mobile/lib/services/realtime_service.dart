import 'dart:async';

import 'package:flutter/foundation.dart';

import '../api/panel_api.dart';
import '../models/direct_message.dart';
import '../models/panel_notification.dart';
import 'local_notification_service.dart';
import 'reverb_service.dart';

class RealtimeService extends ChangeNotifier {
  RealtimeService({PanelApi? api}) : _api = api ?? PanelApi();

  final PanelApi _api;
  final ReverbService _reverb = ReverbService();

  Timer? _timer;
  String? _since;
  int _lastUnreadNotifications = 0;
  int _lastUnreadMessages = 0;
  bool _running = false;
  bool _initialized = false;
  bool _live = false;
  int? _activeConversationId;
  LiveChannelSubscription? _inboxSubscription;

  int get unreadNotifications => _lastUnreadNotifications;
  int get unreadMessages => _lastUnreadMessages;
  bool get isLive => _live;

  final StreamController<Map<String, dynamic>> _directMessageEvents =
      StreamController<Map<String, dynamic>>.broadcast();

  final StreamController<PanelNotification> _notificationEvents =
      StreamController<PanelNotification>.broadcast();

  Stream<Map<String, dynamic>> get directMessageEvents =>
      _directMessageEvents.stream;

  Stream<PanelNotification> get notificationEvents =>
      _notificationEvents.stream;

  Future<void> init() async {
    if (_initialized) return;
    await LocalNotificationService.initialize();
    _initialized = true;
  }

  /// Demande la permission Android/iOS — à appeler après connexion (UI visible).
  Future<bool> ensureNotificationPermission() {
    return LocalNotificationService.requestPermission();
  }

  void setActiveConversationId(int? conversationId) {
    _activeConversationId = conversationId;
  }

  void start() {
    if (_running) return;
    _running = true;
    unawaited(_bootstrap());
  }

  Future<void> _bootstrap() async {
    await init();
    _live = await _reverb.connect(_api);
    notifyListeners();
    await _tick();
    _timer = Timer.periodic(const Duration(seconds: 3), (_) => _tick());
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
    _running = false;
    _inboxSubscription?.dispose();
    _inboxSubscription = null;
    unawaited(_reverb.disconnect());
    _live = false;
  }

  void subscribeInbox(int userId) {
    unawaited(_subscribeInbox(userId));
  }

  Future<void> _subscribeInbox(int userId) async {
    await _reverb.ensureConnected(_api);
    _live = _reverb.isConnected;
    notifyListeners();
    await _inboxSubscription?.dispose();
    _inboxSubscription = _reverb.subscribeUserChannel(
      userId: userId,
      onDirectMessage: _handleDirectMessageEvent,
      onNotification: _handleNotificationEvent,
    );
  }

  LiveChannelSubscription subscribeDirectConversation({
    required int conversationId,
    required void Function(DirectMessage message) onMessage,
    void Function(int conversationId, int readerId)? onRead,
    void Function(int userId, String userName)? onTyping,
  }) {
    unawaited(_reverb.ensureConnected(_api));
    return _reverb.subscribeDirectConversation(
      conversationId: conversationId,
      onMessageSent: (payload) {
        _handleDirectMessageEvent(payload);
        final raw = payload['message'];
        if (raw is Map<String, dynamic>) {
          onMessage(DirectMessage.fromJson(raw));
        }
      },
      onMessagesRead: onRead == null
          ? null
          : (payload) {
              final convId = payload['conversation_id'] as int?;
              final readerId = payload['reader_id'] as int?;
              if (convId != null && readerId != null) {
                onRead(convId, readerId);
              }
            },
      onTyping: onTyping,
    );
  }

  LiveChannelSubscription subscribeProjectChat({
    required int projectId,
    required String spaceKey,
    required void Function(Map<String, dynamic> payload) onMessageSent,
    void Function(Map<String, dynamic> payload)? onMessageUpdated,
    void Function(Map<String, dynamic> payload)? onMessageDeleted,
    void Function(Map<String, dynamic> payload)? onReactionUpdated,
    void Function(int userId, String userName)? onTyping,
  }) {
    unawaited(_reverb.ensureConnected(_api));
    return _reverb.subscribeProjectChat(
      projectId: projectId,
      spaceKey: spaceKey,
      onMessageSent: onMessageSent,
      onMessageUpdated: onMessageUpdated,
      onMessageDeleted: onMessageDeleted,
      onReactionUpdated: onReactionUpdated,
      onTyping: onTyping,
    );
  }

  LiveChannelSubscription subscribeProjectKanban({
    required int projectId,
    required int? currentUserId,
    required VoidCallback onUpdated,
  }) {
    unawaited(_reverb.ensureConnected(_api));
    return _reverb.subscribeProjectKanban(
      projectId: projectId,
      onUpdated: (payload) {
        final actorId = payload['actor_id'] as int?;
        if (actorId != null && actorId == currentUserId) return;
        onUpdated();
      },
    );
  }

  void _handleDirectMessageEvent(Map<String, dynamic> payload) {
    _directMessageEvents.add(payload);

    final messageRaw = payload['message'];
    if (messageRaw is! Map<String, dynamic>) return;

    final conversationId = messageRaw['direct_conversation_id'] as int?;
    final senderId = (messageRaw['user'] as Map?)?['id'] as int?;

    if (conversationId != null &&
        conversationId != _activeConversationId &&
        senderId != null) {
      _lastUnreadMessages += 1;
      notifyListeners();

      final body = messageRaw['body'] as String? ?? 'Nouveau message';
      final senderName =
          (messageRaw['user'] as Map?)?['name'] as String? ?? 'Quelqu\'un';
      unawaited(
        LocalNotificationService.showMessage(
          title: 'Message de $senderName',
          body: body,
          payload: 'dm:$conversationId',
        ),
      );
    }
  }

  void _handleNotificationEvent(Map<String, dynamic> payload) {
    final raw = payload['notification'];
    if (raw is Map<String, dynamic>) {
      final notification = PanelNotification.fromJson(raw);
      _notificationEvents.add(notification);
      unawaited(
        LocalNotificationService.showAlert(
          title: notification.title,
          body: notification.body,
          payload: notification.url,
        ),
      );
    }

    final unread = payload['unread_count'] as int?;
    if (unread != null) {
      _lastUnreadNotifications = unread;
    } else if (raw is Map && raw['read_at'] == null) {
      _lastUnreadNotifications += 1;
    }
    notifyListeners();
  }

  Future<void> _tick() async {
    try {
      await _api.heartbeat();
      final sync = await _api.syncRealtime(since: _since);
      _since = sync.serverTime;

      if (!_live) {
        if (sync.unreadNotifications > _lastUnreadNotifications) {
          await LocalNotificationService.showAlert(
            title: 'Nouvelle notification',
            body: 'Vous avez ${sync.unreadNotifications} notification(s)',
          );
        }

        if (sync.unreadCount > _lastUnreadMessages) {
          await LocalNotificationService.showMessage(
            title: 'Nouveau message',
            body: 'Vous avez ${sync.unreadCount} message(s) non lu(s)',
          );
        }

        for (final event in sync.events) {
          _handleDirectMessageEvent(Map<String, dynamic>.from(event));
        }
      }

      _lastUnreadNotifications = sync.unreadNotifications;
      _lastUnreadMessages = sync.unreadCount;

      notifyListeners();
    } catch (_) {}
  }

  void setUnreadMessages(int count) {
    _lastUnreadMessages = count;
    notifyListeners();
  }

  void setUnreadNotifications(int count) {
    _lastUnreadNotifications = count;
    notifyListeners();
  }

  @override
  void dispose() {
    stop();
    _directMessageEvents.close();
    _notificationEvents.close();
    super.dispose();
  }
}
