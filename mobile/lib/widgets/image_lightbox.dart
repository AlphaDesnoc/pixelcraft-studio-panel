import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

class LightboxImage {
  const LightboxImage({required this.url, required this.name, this.bytes});

  final String url;
  final String name;
  final Uint8List? bytes;
}

class ImageLightbox extends StatefulWidget {
  const ImageLightbox({
    super.key,
    required this.images,
    this.initialIndex = 0,
    this.token,
  });

  final List<LightboxImage> images;
  final int initialIndex;
  final String? token;

  static Future<void> show(
    BuildContext context, {
    required List<LightboxImage> images,
    int initialIndex = 0,
    String? token,
  }) {
    if (images.isEmpty) return Future.value();

    return showDialog<void>(
      context: context,
      barrierColor: Colors.black.withValues(alpha: 0.92),
      builder: (context) => ImageLightbox(
        images: images,
        initialIndex: initialIndex,
        token: token,
      ),
    );
  }

  @override
  State<ImageLightbox> createState() => _ImageLightboxState();
}

class _ImageLightboxState extends State<ImageLightbox> {
  late final PageController _pageController;
  late int _index;
  final _bytesCache = <String, Uint8List>{};

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex.clamp(0, widget.images.length - 1);
    _pageController = PageController(initialPage: _index);
    _preload(_index);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  Future<void> _preload(int index) async {
    final image = widget.images[index];
    if (image.bytes != null || _bytesCache.containsKey(image.url)) {
      return;
    }

    try {
      final dio = Dio(
        BaseOptions(
          headers: widget.token == null
              ? null
              : {'Authorization': 'Bearer ${widget.token}'},
          responseType: ResponseType.bytes,
        ),
      );
      final response = await dio.get<List<int>>(image.url);
      final bytes = Uint8List.fromList(response.data ?? []);
      if (!mounted) return;
      setState(() => _bytesCache[image.url] = bytes);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final current = widget.images[_index];

    return Material(
      color: Colors.transparent,
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      current.name,
                      style: const TextStyle(color: Colors.white, fontSize: 14),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (widget.images.length > 1)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: Text(
                        '${_index + 1}/${widget.images.length}',
                        style: TextStyle(color: Colors.white.withValues(alpha: 0.7)),
                      ),
                    ),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close, color: Colors.white),
                  ),
                ],
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _pageController,
                itemCount: widget.images.length,
                onPageChanged: (value) {
                  setState(() => _index = value);
                  _preload(value);
                },
                itemBuilder: (context, index) {
                  final image = widget.images[index];
                  final bytes = image.bytes ?? _bytesCache[image.url];

                  if (bytes != null) {
                    return InteractiveViewer(
                      minScale: 0.8,
                      maxScale: 4,
                      child: Center(
                        child: Image.memory(bytes, fit: BoxFit.contain),
                      ),
                    );
                  }

                  return const Center(
                    child: CircularProgressIndicator(color: Colors.white),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
