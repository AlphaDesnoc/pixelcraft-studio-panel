import 'package:flutter_test/flutter_test.dart';
import 'package:pixelcraft_panel/services/app_update_service.dart';

void main() {
  test('parseManifest reads version build and apk url', () {
    const raw = '''
# comment
version=1.0.7
build=7
apk=https://github.com/example/app/releases/download/v1.0.7/app.apk
''';

    final info = AppUpdateService.parseManifest(raw);

    expect(info, isNotNull);
    expect(info!.version, '1.0.7');
    expect(info.build, 7);
    expect(info.apkUrl, contains('app.apk'));
  });

  test('isSignatureInstallConflict detects package conflict messages', () {
    expect(
      AppUpdateService.isSignatureInstallConflict(
        'L\'application n\'a pas été installée car le package est en conflit',
      ),
      isTrue,
    );
    expect(
      AppUpdateService.isSignatureInstallConflict(
        'INSTALL_FAILED_UPDATE_INCOMPATIBLE: signatures do not match',
      ),
      isTrue,
    );
    expect(
      AppUpdateService.isSignatureInstallConflict('Permission denied'),
      isFalse,
    );
  });

  test('userFacingInstallMessage explains signature conflicts', () {
    final message = AppUpdateService.userFacingInstallMessage(
      'package conflicts with an existing package',
    );

    expect(message, contains('Désinstallez PixelCraft Panel'));
    expect(
      AppUpdateService.userFacingInstallMessage(null),
      contains('sources inconnues'),
    );
  });
}
