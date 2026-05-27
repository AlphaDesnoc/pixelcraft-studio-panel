import 'package:flutter_test/flutter_test.dart';
import 'package:pixelcraft_panel/main.dart';

void main() {
  testWidgets('App boots to login or home', (tester) async {
    await tester.pumpWidget(const PixelCraftPanelApp());
    await tester.pump();

    expect(find.byType(PixelCraftPanelApp), findsOneWidget);
  });
}
