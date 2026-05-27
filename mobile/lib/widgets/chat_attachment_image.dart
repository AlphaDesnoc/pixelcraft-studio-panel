import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

class ChatAttachmentImage extends StatefulWidget {
  const ChatAttachmentImage({
    super.key,
    required this.url,
    required this.name,
    this.token,
    this.maxWidth = 260,
    this.maxHeight = 195,
    this.onOpen,
  });

  final String url;
  final String name;
  final String? token;
  final double maxWidth;
  final double maxHeight;
  final VoidCallback? onOpen;

  @override
  State<ChatAttachmentImage> createState() => _ChatAttachmentImageState();
}

class _ChatAttachmentImageState extends State<ChatAttachmentImage> {
  Uint8List? _bytes;
  bool _failed = false;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(covariant ChatAttachmentImage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.url != widget.url || oldWidget.token != widget.token) {
      _bytes = null;
      _failed = false;
      _loading = true;
      _load();
    }
  }

  Future<void> _load() async {
    try {
      final dio = Dio(
        BaseOptions(
          connectTimeout: const Duration(seconds: 12),
          receiveTimeout: const Duration(seconds: 20),
          followRedirects: true,
          validateStatus: (status) => status != null && status >= 200 && status < 400,
        ),
      );

      final headers = <String, String>{};
      if (widget.token != null && widget.token!.isNotEmpty) {
        headers['Authorization'] = 'Bearer ${widget.token}';
      }

      final response = await dio.get<List<int>>(
        widget.url,
        options: Options(
          responseType: ResponseType.bytes,
          headers: headers,
        ),
      );

      final data = response.data;
      if (!mounted) return;

      if (data == null || data.isEmpty) {
        setState(() {
          _failed = true;
          _loading = false;
        });
        return;
      }

      setState(() {
        _bytes = Uint8List.fromList(data);
        _failed = false;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _failed = true;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    if (_failed) {
      return _AttachmentFallback(
        name: widget.name,
        onTap: widget.onOpen,
      );
    }

    if (_loading || _bytes == null) {
      return SizedBox(
        width: widget.maxWidth,
        height: widget.maxHeight,
        child: DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: theme.colorScheme.outlineVariant.withValues(alpha: 0.35),
            ),
          ),
          child: const Center(
            child: SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          ),
        ),
      );
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(8),
      child: InkWell(
        onTap: widget.onOpen,
        child: Image.memory(
          _bytes!,
          width: widget.maxWidth,
          height: widget.maxHeight,
          fit: BoxFit.cover,
          gaplessPlayback: true,
          errorBuilder: (context, error, stackTrace) {
            return _AttachmentFallback(
              name: widget.name,
              onTap: widget.onOpen,
            );
          },
        ),
      ),
    );
  }
}

class _AttachmentFallback extends StatelessWidget {
  const _AttachmentFallback({
    required this.name,
    this.onTap,
  });

  final String name;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 4),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.broken_image_outlined,
                size: 18, color: theme.colorScheme.primary),
            const SizedBox(width: 6),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 220),
              child: Text(
                name,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.primary,
                  fontSize: 13,
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
