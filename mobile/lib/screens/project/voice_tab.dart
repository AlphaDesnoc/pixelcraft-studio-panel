import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/workspace.dart';
import '../../services/auth_session.dart';
import '../../services/voice_room_service.dart';
import 'voice_meeting_screen.dart';

/// Onglet « Vocal » : liste des salons vocaux et de réunion du projet, avec
/// connexion/déconnexion LiveKit et barre de contrôle du salon actif.
class VoiceTab extends StatelessWidget {
  const VoiceTab({super.key, required this.workspace});

  final ProjectWorkspace workspace;

  Color _parseColor(String hex) {
    final value = hex.replaceFirst('#', '');
    try {
      return Color(int.parse('FF$value', radix: 16));
    } catch (_) {
      return const Color(0xFF7C5CFF);
    }
  }

  Future<void> _join(BuildContext context, VoiceChannelModel channel) async {
    final session = context.read<AuthSession>();
    final voice = context.read<VoiceRoomService>();
    final userId = session.user?.id;
    if (userId == null) return;

    try {
      await voice.join(
        api: session.api,
        projectSlug: workspace.project.slug,
        channel: channel,
        userId: userId,
      );
      if (channel.withVideo && context.mounted) {
        _openMeeting(context);
      }
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Connexion impossible : $error')),
        );
      }
    }
  }

  void _openMeeting(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const VoiceMeetingScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final voice = context.watch<VoiceRoomService>();
    final channels = [...workspace.voiceChannels]
      ..sort((a, b) => a.position.compareTo(b.position));

    final vocal = channels.where((c) => !c.withVideo).toList();
    final meetings = channels.where((c) => c.withVideo).toList();

    return Scaffold(
      body: channels.isEmpty
          ? const _EmptyVoice()
          : ListView(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 96),
              children: [
                if (vocal.isNotEmpty) ...[
                  const _SectionHeader(
                    icon: Icons.volume_up_outlined,
                    label: 'Salons vocaux',
                  ),
                  ...vocal.map((c) => _ChannelCard(
                        channel: c,
                        voice: voice,
                        parseColor: _parseColor,
                        onJoin: () => _join(context, c),
                        onOpen: () => _openMeeting(context),
                      )),
                ],
                if (meetings.isNotEmpty) ...[
                  const _SectionHeader(
                    icon: Icons.groups_outlined,
                    label: 'Salons de réunion',
                  ),
                  ...meetings.map((c) => _ChannelCard(
                        channel: c,
                        voice: voice,
                        parseColor: _parseColor,
                        onJoin: () => _join(context, c),
                        onOpen: () => _openMeeting(context),
                      )),
                ],
              ],
            ),
      bottomSheet: voice.inRoom
          ? _ActiveRoomBar(
              voice: voice,
              onOpenMeeting: () => _openMeeting(context),
            )
          : null,
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 12, 4, 6),
      child: Row(
        children: [
          Icon(icon, size: 18, color: Theme.of(context).colorScheme.primary),
          const SizedBox(width: 8),
          Text(
            label.toUpperCase(),
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  letterSpacing: 0.8,
                  fontWeight: FontWeight.w700,
                ),
          ),
        ],
      ),
    );
  }
}

class _ChannelCard extends StatelessWidget {
  const _ChannelCard({
    required this.channel,
    required this.voice,
    required this.parseColor,
    required this.onJoin,
    required this.onOpen,
  });

  final VoiceChannelModel channel;
  final VoiceRoomService voice;
  final Color Function(String) parseColor;
  final VoidCallback onJoin;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isCurrent = voice.inRoom && voice.channelId == channel.id;

    // Salon actif : participants live LiveKit ; sinon : présence du lobby.
    final liveNames = isCurrent
        ? voice.participants.map((p) => p.name).toList()
        : channel.participants.map((p) => p.name).toList();

    return Card(
      margin: const EdgeInsets.symmetric(vertical: 4),
      color: isCurrent
          ? theme.colorScheme.primaryContainer.withValues(alpha: 0.5)
          : null,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: isCurrent
            ? BorderSide(color: theme.colorScheme.primary, width: 1.5)
            : BorderSide.none,
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: voice.connecting
            ? null
            : isCurrent
                ? (channel.withVideo ? onOpen : null)
                : onJoin,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(
                    channel.withVideo
                        ? Icons.video_camera_front_outlined
                        : Icons.volume_up_rounded,
                    color: theme.colorScheme.primary,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      channel.name,
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.w600),
                    ),
                  ),
                  if (channel.rank != null)
                    Container(
                      padding:
                          const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: parseColor(channel.rank!.color)
                            .withValues(alpha: 0.18),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        channel.rank!.name,
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: parseColor(channel.rank!.color),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  if (isCurrent) ...[
                    const SizedBox(width: 8),
                    Icon(Icons.graphic_eq, size: 18, color: theme.colorScheme.primary),
                  ],
                ],
              ),
              if (liveNames.isEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    voice.connecting && voice.channelId == channel.id
                        ? 'Connexion…'
                        : 'Personne pour le moment',
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: theme.hintColor),
                  ),
                )
              else
                Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: liveNames
                        .map((name) => _ParticipantChip(name: name))
                        .toList(),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ParticipantChip extends StatelessWidget {
  const _ParticipantChip({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          CircleAvatar(
            radius: 9,
            child: Text(
              name.isNotEmpty ? name[0].toUpperCase() : '?',
              style: const TextStyle(fontSize: 10),
            ),
          ),
          const SizedBox(width: 6),
          Text(name, style: theme.textTheme.labelSmall),
        ],
      ),
    );
  }
}

/// Barre persistante en bas de l'onglet quand on est connecté à un salon.
class _ActiveRoomBar extends StatelessWidget {
  const _ActiveRoomBar({required this.voice, required this.onOpenMeeting});

  final VoiceRoomService voice;
  final VoidCallback onOpenMeeting;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      elevation: 8,
      color: theme.colorScheme.surfaceContainerHigh,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            children: [
              Icon(Icons.graphic_eq, color: theme.colorScheme.primary),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      voice.channelName ?? 'Salon',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleSmall
                          ?.copyWith(fontWeight: FontWeight.w600),
                    ),
                    Text(
                      '${voice.participantCount} connecté(s)'
                      '${voice.canPublishLocal ? '' : ' · auditeur'}',
                      style: theme.textTheme.labelSmall
                          ?.copyWith(color: theme.hintColor),
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: voice.muted ? 'Activer le micro' : 'Couper le micro',
                onPressed: voice.canPublishLocal ? voice.toggleMute : null,
                icon: Icon(voice.muted ? Icons.mic_off : Icons.mic),
                color: voice.muted ? theme.colorScheme.error : null,
              ),
              IconButton(
                tooltip: voice.deafened ? 'Réactiver le son' : 'Sourdine',
                onPressed: voice.toggleDeafen,
                icon: Icon(
                    voice.deafened ? Icons.headset_off : Icons.headset),
                color: voice.deafened ? theme.colorScheme.error : null,
              ),
              if (voice.isStage)
                IconButton(
                  tooltip: 'Ouvrir la réunion',
                  onPressed: onOpenMeeting,
                  icon: const Icon(Icons.open_in_full),
                ),
              IconButton(
                tooltip: 'Quitter',
                onPressed: voice.leave,
                icon: const Icon(Icons.call_end),
                color: theme.colorScheme.error,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmptyVoice extends StatelessWidget {
  const _EmptyVoice();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.headset_mic_outlined,
              size: 48, color: Theme.of(context).hintColor),
          const SizedBox(height: 12),
          Text(
            'Aucun salon vocal',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 4),
          Text(
            'Les salons vocaux et de réunion apparaîtront ici.',
            style: Theme.of(context).textTheme.bodySmall,
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}
