import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:sistemkeuanganflutter/core/auth_service.dart';
import 'package:sistemkeuanganflutter/main.dart';

/// Uji end-to-end ke backend Laravel sungguhan.
///
/// Jalankan backend lebih dulu, lalu:
///   flutter test integration_test/login_test.dart -d windows \
///     --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('login owner lalu akses endpoint terproteksi', (tester) async {
    await tester.pumpWidget(const MyApp());
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byType(TextField).at(0),
      'owner@example.com',
    );
    await tester.enterText(find.byType(TextField).at(1), 'password');
    await tester.tap(find.text('Masuk'));

    // Tunggu permintaan jaringan dan perpindahan halaman (maks. 30 detik).
    for (var i = 0; i < 60; i++) {
      await tester.pump(const Duration(milliseconds: 500));
      if (find.text('Login berhasil sebagai: owner').evaluate().isNotEmpty) {
        break;
      }
    }

    expect(find.text('Login berhasil sebagai: owner'), findsOneWidget);

    // Token dari shared_preferences harus otomatis dikirim sebagai Bearer.
    final user = await AuthService().me();
    expect(user['email'], 'owner@example.com');
    expect(user['role'], 'owner');

    await AuthService().logout();
  });
}
