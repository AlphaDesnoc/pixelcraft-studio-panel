import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';

class MediaPreviewDialog extends StatefulWidget {
  const MediaPreviewDialog({
    super.key,
    required this.url,
    required this.name,
    required this.mimeType,
    this.token,
  });

  final String url;
  final String name;
  final String mimeType;
  final String? token;

  static bool isPreviewable(String mimeType, String name) {
    final lower = mimeType.toLowerCase();
    if (lower.startsWith('video/')) return true;
    if (lower == 'application/pdf') return true;
    return name.toLowerCase().endsWith('.pdf');
  }

  static Future<void> show(
    BuildContext context, {
    required String url,
    required String name,
    required String mimeType,
    String? token,
  }) {
    return showDialog<void>(
      context: context,
      builder: (context) => MediaPreviewDialog(
        url: url,
        name: name,
        mimeType: mimeType,
        token: token,
      ),
    );
  }

  @override
  State<MediaPreviewDialog> createState() => _MediaPreviewDialogState();
}

class _MediaPreviewDialogState extends State<MediaPreviewDialog> {
  VideoPlayerController? _controller;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    if (widget.mimeType.startsWith('video/')) {
      final headers = widget.token == null
          ? null
          : {'Authorization': 'Bearer ${widget.token}'};
      _controller = VideoPlayerController.networkUrl(
        Uri.parse(widget.url),
        httpHeaders: headers ?? const {},
      )..initialize().then((_) {
          if (!mounted) return;
          setState(() {});
          _controller?.play();
        }).catchError((_) {
          if (!mounted) return;
          setState(() => _failed = true);
        });
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  Future<void> _openExternal() async {
    await launchUrl(Uri.parse(widget.url), mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    final isVideo = widget.mimeType.startsWith('video/');
    final isPdf = widget.mimeType == 'application/pdf'
        || widget.name.toLowerCase().endsWith('.pdf');

    return Dialog.fullscreen(
      backgroundColor: Colors.black,
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      widget.name,
                      style: const TextStyle(color: Colors.white),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  IconButton(
                    onPressed: _openExternal,
                    icon: const Icon(Icons.open_in_new, color: Colors.white),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close, color: Colors.white),
                  ),
                ],
              ),
            ),
            Expanded(
              child: Center(
                child: isVideo
                    ? (_failed || _controller == null || !_controller!.value.isInitialized)
                        ? const Text('Impossible de lire la vidéo', style: TextStyle(color: Colors.white70))
                        : AspectRatio(
                            aspectRatio: _controller!.value.aspectRatio,
                            child: VideoPlayer(_controller!),
                          )
                    : isPdf
                        ? Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Icon(Icons.picture_as_pdf, color: Colors.white, size: 64),
                              const SizedBox(height: 12),
                              const Text(
                                'Aperçu PDF dans l’app bientôt disponible.',
                                style: TextStyle(color: Colors.white70),
                                textAlign: TextAlign.center,
                              ),
                              const SizedBox(height: 12),
                              FilledButton(
                                onPressed: _openExternal,
                                child: const Text('Ouvrir le PDF'),
                              ),
                            ],
                          )
                        : const SizedBox.shrink(),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
