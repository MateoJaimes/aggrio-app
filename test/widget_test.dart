import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:agrofield/main.dart';

void main() {
  testWidgets('Smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const AgroFieldApp());
    expect(find.byType(MaterialApp), findsOneWidget);
  });
}
