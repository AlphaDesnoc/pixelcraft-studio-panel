import 'dart:async';
import 'dart:convert';

import 'package:pusher_reverb_flutter/pusher_reverb_flutter.dart';

import '../api/panel_api.dart';

typedef ReverbEventHandler = void Function(Map<String, dynamic> payload);
typedef TypingEventHandler = void Function(int userId, String userName);

/// Subscription handle — call [dispose] when leaving a screen.
class LiveChannelSubscription {
  LiveChannelSubscription({
    required this.dispose,
    this.sendTyping,
  });

  final Future<void> Function() dispose;
  final void Function({required int userId, required String userName})? sendTyping;
}

class ReverbService {
  ReverbClient? _client;
  bool _connected = false;
  String? _token;
  RealtimeConfig? _config;

  bool get isConnected => _connected;
  bool get isAvailable =>
      _config?.echoAvailable == true && (_config?.key.isNotEmpty ?? false);

  Future<bool> connect(PanelApi api) async {
    if (_connected && _client != null) return true;

    try {
      _config = await api.fetchRealtimeConfig();
      if (!isAvailable) return false;

      _token = await api.client.readToken();
      if (_token == null || _token!.isEmpty) return false;

      final config = _config!;
      _client = ReverbClient.instance(
        host: config.host,
        port: config.port,
        appKey: config.key,
        useTLS: config.useTls,
        authEndpoint: config.authEndpoint,
        authorizer: (channelName, socketId) async {
          return {
            'Authorization': 'Bearer $_token',
            'Accept': 'application/json',
          };
        },
        onConnected: (_) => _connected = true,
        onDisconnected: () => _connected = false,
        onError: (_) => _connected = false,
      );

      await _client!.connect();
      _connected = _client!.socketId != null;
      return _connected;
    } catch (_) {
      _connected = false;
      return false;
    }
  }

  Future<void> ensureConnected(PanelApi api) async {
    if (!_connected) {
      await connect(api);
    }
  }

  Future<void> disconnect() async {
    _connected = false;
    try {
      _client?.disconnect();
    } catch (_) {}
    _client = null;
  }

  LiveChannelSubscription subscribeUserChannel({
    required int userId,
    ReverbEventHandler? onDirectMessage,
    ReverbEventHandler? onNotification,
  }) {
    final handlers = <String, ReverbEventHandler>{};
    if (onDirectMessage != null) {
      handlers['DirectMessageSent'] = onDirectMessage;
    }
    if (onNotification != null) {
      handlers['UserNotificationSent'] = onNotification;
    }
    return _subscribePrivate('private-App.Models.User.$userId', handlers);
  }

  LiveChannelSubscription subscribeDirectConversation({
    required int conversationId,
    required ReverbEventHandler onMessageSent,
    ReverbEventHandler? onMessagesRead,
    TypingEventHandler? onTyping,
  }) {
    final handlers = <String, ReverbEventHandler>{
      'DirectMessageSent': onMessageSent,
    };
    if (onMessagesRead != null) {
      handlers['DirectMessagesRead'] = onMessagesRead;
    }
    return _subscribePresence(
      'presence-direct.$conversationId',
      handlers,
      onTyping: onTyping,
    );
  }

  LiveChannelSubscription subscribeProjectChat({
    required int projectId,
    required String spaceKey,
    required ReverbEventHandler onMessageSent,
    ReverbEventHandler? onMessageUpdated,
    ReverbEventHandler? onMessageDeleted,
    ReverbEventHandler? onReactionUpdated,
    TypingEventHandler? onTyping,
  }) {
    final handlers = <String, ReverbEventHandler>{
      'ChatMessageSent': onMessageSent,
    };
    if (onMessageUpdated != null) {
      handlers['ChatMessageUpdated'] = onMessageUpdated;
    }
    if (onMessageDeleted != null) {
      handlers['ChatMessageDeleted'] = onMessageDeleted;
    }
    if (onReactionUpdated != null) {
      handlers['ChatReactionUpdated'] = onReactionUpdated;
    }
    return _subscribePresence(
      'presence-project-chat.$projectId.$spaceKey',
      handlers,
      onTyping: onTyping,
    );
  }

  LiveChannelSubscription subscribeProjectKanban({
    required int projectId,
    required ReverbEventHandler onUpdated,
  }) {
    return _subscribePrivate(
      'private-project-kanban.$projectId',
      {'TaskKanbanUpdated': onUpdated},
    );
  }

  LiveChannelSubscription _subscribePrivate(
    String channelName,
    Map<String, ReverbEventHandler> handlers,
  ) {
    final client = _client;
    if (client == null || !_connected) {
      return LiveChannelSubscription(dispose: () async {});
    }

    final channel = client.subscribeToPrivateChannel(channelName);
    final bindings = <String, ChannelEventListener>{};

    for (final entry in handlers.entries) {
      void callback(String eventName, dynamic data) =>
          entry.value(_parsePayload(data));
      bindings[entry.key] = callback;
      channel.bind(entry.key, callback);
    }

    return LiveChannelSubscription(
      dispose: () async {
        for (final entry in bindings.entries) {
          channel.unbind(entry.key, entry.value);
        }
        client.unsubscribeFromChannel(channelName);
      },
    );
  }

  LiveChannelSubscription _subscribePresence(
    String channelName,
    Map<String, ReverbEventHandler> handlers, {
    TypingEventHandler? onTyping,
  }) {
    final client = _client;
    if (client == null || !_connected) {
      return LiveChannelSubscription(dispose: () async {});
    }

    final channel = client.subscribeToPresenceChannel(channelName);
    final bindings = <String, ChannelEventListener>{};

    for (final entry in handlers.entries) {
      void callback(String eventName, dynamic data) =>
          entry.value(_parsePayload(data));
      bindings[entry.key] = callback;
      channel.bind(entry.key, callback);
    }

    ChannelEventListener? typingListener;
    if (onTyping != null) {
      void callback(String eventName, dynamic data) {
        if (eventName != 'client-typing') return;
        final parsed = _parsePayload(data);
        final user = parsed['user'];
        if (user is Map) {
          final rawId = user['id'];
          final id = rawId is int ? rawId : int.tryParse('$rawId');
          final name = user['name']?.toString() ?? 'Quelqu\'un';
          if (id != null) onTyping(id, name);
        }
      }
      typingListener = callback;
      channel.bind('client-typing', callback);
    }

    return LiveChannelSubscription(
      dispose: () async {
        for (final entry in bindings.entries) {
          channel.unbind(entry.key, entry.value);
        }
        if (typingListener != null) {
          channel.unbind('client-typing', typingListener);
        }
        client.unsubscribeFromChannel(channelName);
      },
      sendTyping: ({required int userId, required String userName}) {
        try {
          channel.whisper('typing', {
            'user': {'id': userId, 'name': userName},
          });
        } catch (_) {}
      },
    );
  }

  Map<String, dynamic> _parsePayload(dynamic data) {
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    if (data is String && data.isNotEmpty) {
      try {
        final decoded = jsonDecode(data);
        if (decoded is Map<String, dynamic>) return decoded;
        if (decoded is Map) return Map<String, dynamic>.from(decoded);
      } catch (_) {}
    }
    return {};
  }
}
