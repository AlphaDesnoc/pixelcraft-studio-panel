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
}
