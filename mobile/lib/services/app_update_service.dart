import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path_provider/path_provider.dart';

import '../config/app_config.dart';

class AppUpdateInfo {
  const AppUpdateInfo({
    required this.version,
    required this.build,
    required this.apkUrl,
  });

  final String version;
  final int build;
  final String apkUrl;
}

class AppUpdateService {
  static bool _checkedThisSession = false;

  static Future<void> checkOnStartup(BuildContext context) async {
    if (_checkedThisSession || kIsWeb || !Platform.isAndroid) return;
    _checkedThisSession = true;

    try {
      final remote = await _fetchRemoteManifest();
      if (remote == null || !context.mounted) return;

      final package = await PackageInfo.fromPlatform();
      final currentBuild = int.tryParse(package.buildNumber) ?? 0;

      if (remote.build <= currentBuild) return;
      if (!context.mounted) return;

      await _showUpdateDialog(
        context,
        remote: remote,
        currentVersion: package.version,
        currentBuild: currentBuild,
      );
    } catch (error, stackTrace) {
      assert(() {
        debugPrint('AppUpdateService: $error\n$stackTrace');
        return true;
      }());
    }
  }

  static Future<AppUpdateInfo?> _fetchRemoteManifest() async {
    final dio = Dio(
      BaseOptions(
        connectTimeout: const Duration(seconds: 12),
        receiveTimeout: const Duration(seconds: 12),
        followRedirects: true,
        maxRedirects: 5,
        validateStatus: (status) => status != null && status >= 200 && status < 400,
        headers: {
          'Accept': 'text/plain, */*',
          'User-Agent': AppConfig.mobileUserAgentSuffix,
        },
      ),
    );

    final response = await dio.get<String>(AppConfig.updateManifestUrl);
    final body = response.data;
    if (body == null || body.trim().isEmpty) return null;

    // GitHub peut renvoyer une page HTML si l'asset est absent.
    if (body.trimLeft().startsWith('<!DOCTYPE') ||
        body.trimLeft().startsWith('<html')) {
      return null;
    }

    return _parseManifest(body);
  }

  /// Exposé pour les tests unitaires.
  static AppUpdateInfo? parseManifest(String raw) => _parseManifest(raw);

  /// Exposé pour les tests unitaires.
  static bool isSignatureInstallConflict(String? message) {
    final lower = (message ?? '').toLowerCase();
    if (lower.isEmpty) return false;

    const markers = [
      'conflit',
      'conflict',
      'signatures do not match',
      'signature mismatch',
      'install_failed_update_incompatible',
      'update incompatible',
      'package conflicts',
      'existing package',
      'package déjà present',
      'package deja present',
    ];

    return markers.any(lower.contains);
  }

  /// Exposé pour les tests unitaires.
  static String userFacingInstallMessage(String? systemMessage) {
    if (isSignatureInstallConflict(systemMessage)) {
      return 'Installation impossible : l’APK n’a pas la même signature que '
          'l’app installée.\n\n'
          'Désinstallez PixelCraft Panel (Paramètres → Applications), '
          'puis réinstallez la mise à jour.';
    }

    if (systemMessage != null && systemMessage.trim().isNotEmpty) {
      return systemMessage.trim();
    }

    return 'Impossible de lancer l’installation. Autorisez les sources '
        'inconnues si Android le demande.';
  }

  static AppUpdateInfo? _parseManifest(String raw) {
    String? version;
    int? build;
    String? apk;

    for (final line in raw.split('\n')) {
      final trimmed = line.trim();
      if (trimmed.isEmpty || trimmed.startsWith('#')) continue;

      final separator = trimmed.contains('=') ? '=' : ':';
      final parts = trimmed.split(separator);
      if (parts.length < 2) continue;

      final key = parts.first.trim().toLowerCase();
      final value = parts.sublist(1).join(separator).trim();

      switch (key) {
        case 'version':
          version = value;
        case 'build':
          build = int.tryParse(value);
        case 'apk':
        case 'url':
        case 'apk_url':
          apk = value;
      }
    }

    if (version == null || build == null || apk == null || apk.isEmpty) {
      return null;
    }

    return AppUpdateInfo(version: version, build: build, apkUrl: apk);
  }

  static Future<void> _showUpdateDialog(
    BuildContext context, {
    required AppUpdateInfo remote,
    required String currentVersion,
    required int currentBuild,
  }) {
    return showDialog<void>(
      context: context,
      barrierDismissible: false,
      useRootNavigator: true,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Mise à jour disponible'),
        content: Text(
          'La version ${remote.version} (build ${remote.build}) est disponible.\n'
          'Vous utilisez la version $currentVersion (build $currentBuild).',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Plus tard'),
          ),
          FilledButton(
            onPressed: () async {
              Navigator.pop(dialogContext);
              if (!context.mounted) return;
              await _downloadAndInstall(context, remote);
            },
            child: const Text('Mettre à jour'),
          ),
        ],
      ),
    );
  }

  static Future<void> _downloadAndInstall(
    BuildContext context,
    AppUpdateInfo remote,
  ) async {
    if (!context.mounted) return;

    final progress = ValueNotifier<double?>(null);
    var cancelled = false;

    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => ValueListenableBuilder<double?>(
        valueListenable: progress,
        builder: (context, value, _) {
          return AlertDialog(
            title: const Text('Téléchargement…'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (value == null)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 12),
                    child: Center(child: CircularProgressIndicator()),
                  )
                else
                  LinearProgressIndicator(value: value.clamp(0, 1)),
                const SizedBox(height: 12),
                Text(
                  value == null
                      ? 'Préparation du téléchargement…'
                      : '${(value * 100).clamp(0, 100).toStringAsFixed(0)} %',
                  textAlign: TextAlign.center,
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () {
                  cancelled = true;
                  Navigator.pop(dialogContext);
                },
                child: const Text('Annuler'),
              ),
            ],
          );
        },
      ),
    );

    try {
      final dir = await getTemporaryDirectory();
      final safeVersion = remote.version.replaceAll(RegExp(r'[^\d.]'), '_');
      final filePath = '${dir.path}/pixelcraft-panel-$safeVersion.apk';

      final dio = Dio(
        BaseOptions(
          connectTimeout: const Duration(seconds: 30),
          receiveTimeout: const Duration(minutes: 10),
        ),
      );

      await dio.download(
        remote.apkUrl,
        filePath,
        onReceiveProgress: (received, total) {
          if (total > 0) {
            progress.value = received / total;
          }
        },
      );

      if (cancelled || !context.mounted) return;

      Navigator.of(context, rootNavigator: true).pop();

      if (!context.mounted) return;
      await _showInstallReadyDialog(context, filePath, remote);
    } catch (error) {
      if (context.mounted) {
        Navigator.of(context, rootNavigator: true).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Échec du téléchargement : $error')),
        );
      }
    } finally {
      progress.dispose();
    }
  }

  static Future<void> _showInstallReadyDialog(
    BuildContext context,
    String filePath,
    AppUpdateInfo remote,
  ) {
    return showDialog<void>(
      context: context,
      useRootNavigator: true,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Installer la mise à jour'),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'La version ${remote.version} (build ${remote.build}) est prête '
                'à être installée.',
              ),
              const SizedBox(height: 12),
              const Text(
                'Si Android affiche « conflit avec un package déjà present », '
                'l’app actuelle a été signée avec une autre clé (debug vs release, '
                'ou ancienne build).',
              ),
              const SizedBox(height: 8),
              const Text(
                'Dans ce cas :\n'
                '1. Désinstallez PixelCraft Panel\n'
                '2. Relancez l’installation\n\n'
                'Votre session locale sera perdue ; reconnectez-vous ensuite.',
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () async {
              Navigator.pop(dialogContext);
              if (!context.mounted) return;
              await _launchApkInstall(context, filePath);
            },
            child: const Text('Installer'),
          ),
        ],
      ),
    );
  }

  static Future<void> _launchApkInstall(
    BuildContext context,
    String filePath,
  ) async {
    final result = await OpenFilex.open(
      filePath,
      type: 'application/vnd.android.package-archive',
    );

    if (!context.mounted) return;

    if (result.type != ResultType.done) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(userFacingInstallMessage(result.message)),
          duration: const Duration(seconds: 8),
        ),
      );
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Installation lancée. En cas d’échec (conflit de package), '
          'désinstallez l’app puis réessayez.',
        ),
        duration: Duration(seconds: 6),
      ),
    );
  }
}
