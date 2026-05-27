import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:pixelcraft_panel/widgets/chat_bubble.dart';

void main() {
  setUpAll(() async {
    await initializeDateFormatting('fr_FR');
  });

  testWidgets('ChatMessageRow stays compact inside ListView', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 800));

    await tester.pumpWidget(
      MaterialApp(
        theme: ThemeData(useMaterial3: true),
        home: Scaffold(
          body: Column(
            children: [
              Expanded(
                child: ListView.builder(
                  itemCount: 1,
                  itemBuilder: (context, index) {
                    return const ChatMessageRow(
                      isMine: false,
                      userName: 'Narkoo',
                      body: 'Salut, comment ça va ?',
                      createdAt: '2026-01-15T12:00:00.000000Z',
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );

    await tester.pumpAndSettle();

    final rowBox = tester.renderObject<RenderBox>(
      find.byType(ChatMessageRow),
    );
    expect(
      rowBox.size.height,
      lessThan(120),
      reason: 'Row height was ${rowBox.size.height}',
    );

    final decorated = tester.widgetList<DecoratedBox>(
      find.descendant(
        of: find.byType(ChatMessageRow),
        matching: find.byType(DecoratedBox),
      ),
    );
    for (final box in decorated) {
      final renderBox = tester.renderObject<RenderBox>(
        find.byWidget(box),
      );
      expect(
        renderBox.size.height,
        lessThan(120),
        reason: 'Bubble height was ${renderBox.size.height}',
      );
    }
  });
}
