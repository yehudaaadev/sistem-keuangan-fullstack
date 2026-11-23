import 'package:flutter_test/flutter_test.dart';

import 'package:sistemkeuanganflutter/main.dart';

void main() {
  testWidgets('halaman login tampil', (WidgetTester tester) async {
    await tester.pumpWidget(const MyApp());

    expect(find.text('Login'), findsOneWidget);
    expect(find.text('Email'), findsOneWidget);
    expect(find.text('Sandi'), findsOneWidget);
    expect(find.text('Masuk'), findsOneWidget);
  });
}
