import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:livekit_client/livekit_client.dart';
import 'package:permission_handler/permission_handler.dart';

import '../api/panel_api.dart';
import '../models/workspace.dart';

/// Vue d'un participant d'un salon vocal/réunion (dérivée de l'état LiveKit).
class VoiceMember {
  VoiceMember({
    required this.identity,
    required this.name,
    required this.avatarUrl,
    required this.isLocal,
    required this.speaking,
    required this.micOn,
    required this.camOn,
    required this.screenOn,
    required this.role,
    required this.canModerate,
    required this.handRaised,
    required this.cameraTrack,
    required this.screenTrack,
  });

  final String identity;
  final String name;
  final String? avatarUrl;
  final bool isLocal;
  final bool speaking;
  final bool micOn;
  final bool camOn;
  final bool screenOn;
  final String role; // speaker | audience
  final bool canModerate;
  final bool handRaised;
  final VideoTrack? cameraTrack;
  final VideoTrack? screenTrack;

  bool get isSpeaker => role == 'speaker';
  bool get hasVideo => cameraTrack != null || screenTrack != null;
}

/// Service global gérant la connexion à un salon vocal / réunion via LiveKit.
/// Un seul salon actif à la fois, à l'image du composable web `useVoiceRoom`.
class VoiceRoomService extends ChangeNotifier {
  Room? _room;
  EventsListener<RoomEvent>? _listener;
  PanelApi? _api;

  // Contexte du salon courant.
  String? projectSlug;
  int? channelId;
  String? channelName;
  bool isStage = false;

  String? _identity; // id utilisateur local (String)

  bool connecting = false;
  bool muted = false;
  bool cameraEnabled = false;
  bool deafened = false;
  CameraPosition _cameraPosition = CameraPosition.front;

  // Réunion (stage) : rôle et droits de l'utilisateur local.
  String myRole = 'speaker'; // speaker | audience
  bool amModerator = false;
  bool canPublishLocal = true;

  String? lastError;

  final Map<String, bool> _handStates = {};
  List<VoiceMember> participants = [];

  bool get inRoom => _room != null;
  int get participantCount => participants.length;
  bool get handRaisedLocal =>
      _identity != null && (_handStates[_identity] ?? false);

  /// Rejoint un salon vocal/réunion. Quitte automatiquement tout salon précédent.
  Future<void> join({
    required PanelApi api,
    required String projectSlug,
    required VoiceChannelModel channel,
    required int userId,
  }) async {
    if (connecting) return;
    if (inRoom) {
      // Déjà dans un autre salon : on le quitte d'abord.
      await leave();
    }

    connecting = true;
    lastError = null;
    notifyListeners();

    Room? room;
    try {
      final tk = await api.requestVoiceToken(
        projectSlug: projectSlug,
        channelId: channel.id,
      );

      // Permissions micro (et caméra pour une réunion). Sur Android, flutter_webrtc
      // ne demande pas les permissions runtime lui-même : on les exige donc en amont.
      // Sur iOS/web, getUserMedia déclenche la demande native (chaînes Info.plist).
      final micOk = await _ensurePermission(Permission.microphone);
      if (!micOk && defaultTargetPlatform == TargetPlatform.android) {
        throw Exception('Accès au micro refusé.');
      }
      if (channel.withVideo) {
        await _ensurePermission(Permission.camera);
      }

      room = Room(
        roomOptions: const RoomOptions(
          adaptiveStream: true,
          dynacast: true,
        ),
      );
      final listener = room.createListener();
      _bindEvents(listener, room);

      await room.connect(tk.url, tk.token);

      _room = room;
      _listener = listener;
      _api = api;
      this.projectSlug = projectSlug;
      channelId = channel.id;
      channelName = channel.name;
      isStage = tk.isStage;
      _identity = userId.toString();
      _handStates.clear();

      room.addListener(_onRoomUpdate);
      _updateLocalRole();

      // Auditeur de réunion : pas de publication tant qu'il n'est pas promu.
      if (canPublishLocal) {
        await room.localParticipant?.setMicrophoneEnabled(true);
        muted = false;
        if (channel.withVideo) {
          await room.localParticipant?.setCameraEnabled(true);
          cameraEnabled = true;
        }
      }
      _rebuild();
    } catch (error) {
      lastError = error.toString();
      // Nettoyage en cas d'échec.
      try {
        await room?.disconnect();
      } catch (_) {}
      _api = api;
      this.projectSlug = projectSlug;
      channelId = channel.id;
      await leave();
      rethrow;
    } finally {
      connecting = false;
      notifyListeners();
    }
  }

  Future<void> leave() async {
    final slug = projectSlug;
    final id = channelId;
    final api = _api;

    _listener?.dispose();
    _listener = null;
    _room?.removeListener(_onRoomUpdate);
    try {
      await _room?.disconnect();
    } catch (_) {}
    try {
      await _room?.dispose();
    } catch (_) {}

    if (api != null && slug != null && id != null) {
      // Best-effort : on informe le serveur du départ (présence du lobby).
      api.leaveVoiceChannel(projectSlug: slug, channelId: id).catchError((_) {});
    }

    _resetState();
    notifyListeners();
  }

  Future<void> toggleMute() async {
    final lp = _room?.localParticipant;
    if (lp == null || !canPublishLocal) return;
    muted = !muted;
    await lp.setMicrophoneEnabled(!muted);
    _rebuild();
  }

  Future<void> toggleCamera() async {
    final lp = _room?.localParticipant;
    if (lp == null || !canPublishLocal) return;
    cameraEnabled = !cameraEnabled;
    await lp.setCameraEnabled(cameraEnabled);
    _rebuild();
  }

  Future<void> switchCamera() async {
    final lp = _room?.localParticipant;
    if (lp == null) return;
    for (final pub in lp.videoTrackPublications) {
      if (pub.source != TrackSource.camera) continue;
      final track = pub.track;
      if (track is LocalVideoTrack) {
        try {
          _cameraPosition = _cameraPosition.switched();
          await track.setCameraPosition(_cameraPosition);
        } catch (_) {}
      }
      break;
    }
  }

  /// Sourdine : coupe la réception de toutes les pistes audio distantes.
  Future<void> toggleDeafen() async {
    deafened = !deafened;
    _applyDeafen();
    // Cohérence avec le web : se mettre en sourdine coupe aussi le micro.
    if (deafened && !muted) {
      await toggleMute();
    }
    notifyListeners();
  }

  void _applyDeafen() {
    final room = _room;
    if (room == null) return;
    for (final p in room.remoteParticipants.values) {
      for (final pub in p.audioTrackPublications) {
        if (deafened) {
          pub.disable();
        } else {
          pub.enable();
        }
      }
    }
  }

  // ---- Réunion (stage) : lever la main, promotion / rétrogradation ----

  Future<void> toggleHand() async {
    final raised = !handRaisedLocal;
    if (_identity != null) {
      _handStates[_identity!] = raised;
      _rebuild();
    }
    try {
      await _room?.localParticipant?.publishData(
        utf8.encode(jsonEncode({'t': 'hand', 'raised': raised})),
        reliable: true,
      );
    } catch (_) {}
  }

  Future<void> setParticipantRole(String identity, String role) async {
    final slug = projectSlug;
    final id = channelId;
    final api = _api;
    if (slug == null || id == null || api == null) return;
    await api.setVoiceParticipantRole(
      projectSlug: slug,
      channelId: id,
      identity: identity,
      role: role,
    );
    if (role == 'speaker') {
      _handStates[identity] = false;
      _rebuild();
    }
  }

  Future<void> joinStage() async {
    if (_identity != null) await setParticipantRole(_identity!, 'speaker');
  }

  Future<void> leaveStage() async {
    if (_identity != null) await setParticipantRole(_identity!, 'audience');
  }

  // ---- Internes ----

  Future<bool> _ensurePermission(Permission permission) async {
    if (kIsWeb) return true; // getUserMedia déclenche la demande nativement.
    try {
      final status = await permission.request();
      return status.isGranted || status.isLimited;
    } catch (_) {
      // permission_handler indisponible (ex. macros iOS non définies) :
      // on laisse WebRTC déclencher la demande native.
      return true;
    }
  }

  void _bindEvents(EventsListener<RoomEvent> listener, Room room) {
    listener
      ..on<RoomDisconnectedEvent>((_) {
        _resetState();
        notifyListeners();
      })
      ..on<ParticipantConnectedEvent>((_) => _rebuild())
      ..on<ParticipantDisconnectedEvent>((e) {
        _handStates.remove(e.participant.identity);
        _rebuild();
      })
      ..on<TrackSubscribedEvent>((_) {
        if (deafened) _applyDeafen();
        _rebuild();
      })
      ..on<TrackUnsubscribedEvent>((_) => _rebuild())
      ..on<TrackMutedEvent>((_) => _rebuild())
      ..on<TrackUnmutedEvent>((_) => _rebuild())
      ..on<LocalTrackPublishedEvent>((_) => _rebuild())
      ..on<LocalTrackUnpublishedEvent>((_) => _rebuild())
      ..on<ActiveSpeakersChangedEvent>((_) => _rebuild())
      ..on<ParticipantMetadataUpdatedEvent>((e) {
        if (e.participant.identity == room.localParticipant?.identity) {
          _updateLocalRole();
        }
        _rebuild();
      })
      ..on<ParticipantPermissionsUpdatedEvent>((e) {
        if (e.participant.identity == room.localParticipant?.identity) {
          final couldPublish = canPublishLocal;
          _updateLocalRole();
          // Promu intervenant : on réactive le micro automatiquement.
          if (!couldPublish && canPublishLocal) {
            muted = false;
            room.localParticipant?.setMicrophoneEnabled(true).catchError(
                  (_) => null,
                );
          }
          // Rétrogradé auditeur : LiveKit retire nos pistes.
          if (!canPublishLocal) {
            cameraEnabled = false;
          }
        }
        _rebuild();
      })
      ..on<DataReceivedEvent>((e) {
        try {
          final msg = jsonDecode(utf8.decode(e.data)) as Map<String, dynamic>;
          final from = e.participant?.identity;
          if (msg['t'] == 'hand' && from != null) {
            _handStates[from] = msg['raised'] == true;
            _rebuild();
          }
        } catch (_) {}
      });
  }

  void _onRoomUpdate() => _rebuild();

  void _updateLocalRole() {
    final lp = _room?.localParticipant;
    if (lp == null) return;
    final meta = _parseMeta(lp.metadata);
    myRole = (meta['role'] as String?) ?? 'speaker';
    amModerator = meta['can_moderate'] == true;
    canPublishLocal = lp.permissions.canPublish;
  }

  Map<String, dynamic> _parseMeta(String? raw) {
    if (raw == null || raw.isEmpty) return const {};
    try {
      final decoded = jsonDecode(raw);
      return decoded is Map<String, dynamic> ? decoded : const {};
    } catch (_) {
      return const {};
    }
  }

  VideoTrack? _videoTrack(Participant p, TrackSource source) {
    for (final pub in p.videoTrackPublications) {
      if (pub.source == source && !pub.muted && pub.subscribed) {
        final track = pub.track;
        if (track is VideoTrack) return track;
      }
    }
    return null;
  }

  VoiceMember _describe(Participant p, bool isLocal) {
    final meta = _parseMeta(p.metadata);
    return VoiceMember(
      identity: p.identity,
      name: p.name.isNotEmpty ? p.name : (meta['name'] as String? ?? p.identity),
      avatarUrl: meta['avatar_url'] as String?,
      isLocal: isLocal,
      speaking: p.isSpeaking,
      micOn: p.isMicrophoneEnabled(),
      camOn: p.isCameraEnabled(),
      screenOn: p.isScreenShareEnabled(),
      role: (meta['role'] as String?) ?? 'speaker',
      canModerate: meta['can_moderate'] == true,
      handRaised: _handStates[p.identity] ?? false,
      cameraTrack: _videoTrack(p, TrackSource.camera),
      screenTrack: _videoTrack(p, TrackSource.screenShareVideo),
    );
  }

  void _rebuild() {
    final room = _room;
    if (room == null) {
      participants = const [];
      notifyListeners();
      return;
    }
    final list = <VoiceMember>[];
    final lp = room.localParticipant;
    if (lp != null) list.add(_describe(lp, true));
    for (final p in room.remoteParticipants.values) {
      list.add(_describe(p, false));
    }
    participants = list;
    notifyListeners();
  }

  void _resetState() {
    _room = null;
    _api = null;
    projectSlug = null;
    channelId = null;
    channelName = null;
    isStage = false;
    _identity = null;
    muted = false;
    cameraEnabled = false;
    deafened = false;
    _cameraPosition = CameraPosition.front;
    myRole = 'speaker';
    amModerator = false;
    canPublishLocal = true;
    _handStates.clear();
    participants = const [];
  }

  @override
  void dispose() {
    _listener?.dispose();
    _room?.removeListener(_onRoomUpdate);
    _room?.dispose();
    super.dispose();
  }
}
