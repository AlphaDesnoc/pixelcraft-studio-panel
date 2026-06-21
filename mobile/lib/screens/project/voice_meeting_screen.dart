import 'package:flutter/material.dart';
import 'package:livekit_client/livekit_client.dart';
import 'package:provider/provider.dart';

import '../../services/voice_room_service.dart';

/// Vue plein écran d'une réunion (stage) : grille vidéo des participants,
/// contrôles média et modération de scène (promotion / rétrogradation).
class VoiceMeetingScreen extends StatelessWidget {
  const VoiceMeetingScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final voice = context.watch<VoiceRoomService>();

    // Salon quitté ailleurs : on ferme l'écran.
    if (!voice.inRoom) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (Navigator.of(context).canPop()) Navigator.of(context).pop();
      });
      return const Scaffold(body: SizedBox.shrink());
    }

    final members = voice.participants;
    final withVideo = members.where((m) => m.hasVideo).toList();

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(voice.channelName ?? 'Réunion'),
            Text(
              '${members.length} participant(s)'
              '${voice.canPublishLocal ? '' : ' · auditeur'}',
              style: const TextStyle(fontSize: 12, color: Colors.white70),
            ),
          ],
        ),
        leading: IconButton(
          icon: const Icon(Icons.keyboard_arrow_down),
          tooltip: 'Réduire',
          onPressed: () => Navigator.of(context).maybePop(),
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: withVideo.isEmpty
                ? _AudioGrid(members: members)
                : _VideoGrid(videoMembers: withVideo),
          ),
          if (voice.isStage) _StageBanner(voice: voice),
          _ParticipantRail(voice: voice),
          _MeetingControls(voice: voice),
        ],
      ),
    );
  }
}

/// Grille vidéo (caméra / partage d'écran).
class _VideoGrid extends StatelessWidget {
  const _VideoGrid({required this.videoMembers});

  final List<VoiceMember> videoMembers;

  @override
  Widget build(BuildContext context) {
    final count = videoMembers.length;
    final columns = count <= 1 ? 1 : (count <= 4 ? 2 : 3);
    return GridView.builder(
      padding: const EdgeInsets.all(8),
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: columns,
        mainAxisSpacing: 8,
        crossAxisSpacing: 8,
        childAspectRatio: 16 / 10,
      ),
      itemCount: count,
      itemBuilder: (context, index) {
        final m = videoMembers[index];
        final track = m.screenTrack ?? m.cameraTrack;
        return _VideoTile(member: m, track: track!);
      },
    );
  }
}

class _VideoTile extends StatelessWidget {
  const _VideoTile({required this.member, required this.track});

  final VoiceMember member;
  final VideoTrack track;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: Stack(
        fit: StackFit.expand,
        children: [
          Container(
            color: const Color(0xFF101014),
            child: VideoTrackRenderer(
              track,
              fit: VideoViewFit.cover,
            ),
          ),
          Positioned(
            left: 8,
            bottom: 8,
            child: _NamePlate(member: member),
          ),
          if (member.screenTrack != null)
            const Positioned(
              right: 8,
              top: 8,
              child: Icon(Icons.screen_share, color: Colors.white, size: 18),
            ),
        ],
      ),
    );
  }
}

/// Grille « audio uniquement » : avatars quand personne n'a de vidéo.
class _AudioGrid extends StatelessWidget {
  const _AudioGrid({required this.members});

  final List<VoiceMember> members;

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      padding: const EdgeInsets.all(12),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 1,
      ),
      itemCount: members.length,
      itemBuilder: (context, index) {
        final m = members[index];
        return Container(
          decoration: BoxDecoration(
            color: const Color(0xFF16161C),
            borderRadius: BorderRadius.circular(16),
            border: m.speaking
                ? Border.all(color: const Color(0xFF4ADE80), width: 2.5)
                : null,
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CircleAvatar(
                radius: 34,
                child: Text(
                  m.name.isNotEmpty ? m.name[0].toUpperCase() : '?',
                  style: const TextStyle(fontSize: 26),
                ),
              ),
              const SizedBox(height: 10),
              _NamePlate(member: m),
            ],
          ),
        );
      },
    );
  }
}

class _NamePlate extends StatelessWidget {
  const _NamePlate({required this.member});

  final VoiceMember member;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.black54,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            member.micOn ? Icons.mic : Icons.mic_off,
            size: 14,
            color: member.micOn
                ? (member.speaking ? const Color(0xFF4ADE80) : Colors.white)
                : Colors.redAccent,
          ),
          const SizedBox(width: 4),
          Flexible(
            child: Text(
              member.isLocal ? '${member.name} (vous)' : member.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white, fontSize: 12),
            ),
          ),
          if (member.handRaised) ...[
            const SizedBox(width: 4),
            const Text('✋', style: TextStyle(fontSize: 12)),
          ],
        ],
      ),
    );
  }
}

/// Bandeau d'état de scène (réunion) + actions de prise de parole.
class _StageBanner extends StatelessWidget {
  const _StageBanner({required this.voice});

  final VoiceRoomService voice;

  @override
  Widget build(BuildContext context) {
    final isAudience = !voice.canPublishLocal;
    return Container(
      width: double.infinity,
      color: const Color(0xFF1B1B22),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      child: Row(
        children: [
          Icon(
            isAudience ? Icons.hearing : Icons.record_voice_over,
            size: 18,
            color: Colors.white70,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              isAudience
                  ? 'Vous êtes auditeur'
                  : 'Vous êtes intervenant'
                      '${voice.amModerator ? ' · modérateur' : ''}',
              style: const TextStyle(color: Colors.white70, fontSize: 13),
            ),
          ),
          if (isAudience)
            FilledButton.tonalIcon(
              onPressed: voice.toggleHand,
              icon: Text(voice.handRaisedLocal ? '✋' : '🙋',
                  style: const TextStyle(fontSize: 14)),
              label: Text(voice.handRaisedLocal
                  ? 'Baisser la main'
                  : 'Demander la parole'),
            )
          else if (!voice.amModerator)
            TextButton(
              onPressed: voice.leaveStage,
              child: const Text('Quitter la scène'),
            ),
        ],
      ),
    );
  }
}

/// Rail horizontal listant tous les participants (avec modération).
class _ParticipantRail extends StatelessWidget {
  const _ParticipantRail({required this.voice});

  final VoiceRoomService voice;

  Future<void> _moderate(BuildContext context, VoiceMember m) async {
    if (!voice.amModerator || m.isLocal) return;
    final promote = m.role != 'speaker';
    final action = await showModalBottomSheet<String>(
      context: context,
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: CircleAvatar(
                child: Text(m.name.isNotEmpty ? m.name[0].toUpperCase() : '?'),
              ),
              title: Text(m.name),
              subtitle: Text(m.role == 'speaker' ? 'Intervenant' : 'Auditeur'),
            ),
            const Divider(height: 1),
            ListTile(
              leading: Icon(promote ? Icons.record_voice_over : Icons.hearing),
              title: Text(promote
                  ? 'Inviter à parler (intervenant)'
                  : 'Repasser auditeur'),
              onTap: () => Navigator.pop(context, promote ? 'speaker' : 'audience'),
            ),
          ],
        ),
      ),
    );
    if (action != null) {
      await voice.setParticipantRole(m.identity, action);
    }
  }

  @override
  Widget build(BuildContext context) {
    final members = voice.participants;
    return Container(
      height: 84,
      color: const Color(0xFF0E0E12),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        itemCount: members.length,
        separatorBuilder: (_, _) => const SizedBox(width: 12),
        itemBuilder: (context, index) {
          final m = members[index];
          return GestureDetector(
            onTap: voice.amModerator && !m.isLocal
                ? () => _moderate(context, m)
                : null,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Stack(
                  children: [
                    CircleAvatar(
                      radius: 22,
                      child: Text(
                        m.name.isNotEmpty ? m.name[0].toUpperCase() : '?',
                      ),
                    ),
                    if (m.speaking)
                      Positioned.fill(
                        child: Container(
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(
                                color: const Color(0xFF4ADE80), width: 2.5),
                          ),
                        ),
                      ),
                    Positioned(
                      right: -2,
                      bottom: -2,
                      child: CircleAvatar(
                        radius: 9,
                        backgroundColor: const Color(0xFF0E0E12),
                        child: Icon(
                          m.micOn ? Icons.mic : Icons.mic_off,
                          size: 11,
                          color: m.micOn ? Colors.white : Colors.redAccent,
                        ),
                      ),
                    ),
                    if (m.handRaised)
                      const Positioned(
                        left: -2,
                        top: -2,
                        child: Text('✋', style: TextStyle(fontSize: 14)),
                      ),
                  ],
                ),
                const SizedBox(height: 4),
                SizedBox(
                  width: 56,
                  child: Text(
                    m.isLocal ? 'Vous' : m.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Colors.white70, fontSize: 11),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

/// Barre de contrôle média en bas de la réunion.
class _MeetingControls extends StatelessWidget {
  const _MeetingControls({required this.voice});

  final VoiceRoomService voice;

  @override
  Widget build(BuildContext context) {
    final canPublish = voice.canPublishLocal;
    return Container(
      color: const Color(0xFF0A0A0D),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
              _CtrlButton(
                icon: voice.muted ? Icons.mic_off : Icons.mic,
                label: 'Micro',
                active: !voice.muted && canPublish,
                disabled: !canPublish,
                danger: voice.muted,
                onTap: canPublish ? voice.toggleMute : null,
              ),
              _CtrlButton(
                icon: voice.cameraEnabled
                    ? Icons.videocam
                    : Icons.videocam_off,
                label: 'Caméra',
                active: voice.cameraEnabled && canPublish,
                disabled: !canPublish,
                onTap: canPublish ? voice.toggleCamera : null,
              ),
              if (voice.cameraEnabled)
                _CtrlButton(
                  icon: Icons.cameraswitch,
                  label: 'Pivoter',
                  active: false,
                  onTap: voice.switchCamera,
                ),
              _CtrlButton(
                icon: voice.deafened ? Icons.headset_off : Icons.headset,
                label: 'Son',
                active: !voice.deafened,
                danger: voice.deafened,
                onTap: voice.toggleDeafen,
              ),
              _CtrlButton(
                icon: Icons.call_end,
                label: 'Quitter',
                active: false,
                danger: true,
                onTap: voice.leave,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CtrlButton extends StatelessWidget {
  const _CtrlButton({
    required this.icon,
    required this.label,
    required this.active,
    this.danger = false,
    this.disabled = false,
    this.onTap,
  });

  final IconData icon;
  final String label;
  final bool active;
  final bool danger;
  final bool disabled;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final Color bg = danger
        ? Colors.red.shade700
        : active
            ? Theme.of(context).colorScheme.primary
            : const Color(0xFF26262E);
    return Opacity(
      opacity: disabled ? 0.4 : 1,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Material(
            color: bg,
            shape: const CircleBorder(),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: onTap,
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Icon(icon, color: Colors.white),
              ),
            ),
          ),
          const SizedBox(height: 4),
          Text(label,
              style: const TextStyle(color: Colors.white70, fontSize: 11)),
        ],
      ),
    );
  }
}
