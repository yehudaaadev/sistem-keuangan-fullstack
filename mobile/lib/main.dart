import 'package:flutter/material.dart';

import 'core/api_config.dart';
import 'core/theme/app_theme.dart';
import 'core/token_storage.dart';
import 'core/update_gate.dart';
import 'features/auth/login_page.dart';
import 'features/kasir/kasir_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  String? token;
  String? role;
  try {
    await ApiConfig.inisialisasi();
    token = await TokenStorage.bacaToken();
    role = await TokenStorage.bacaRole();
  } catch (e, stack) {
    debugPrint('Startup init error: $e\n$stack');
  }

  runApp(MyApp(initialToken: token, initialRole: role));
}

class MyApp extends StatelessWidget {
  final String? initialToken;
  final String? initialRole;

  const MyApp({super.key, this.initialToken, this.initialRole});

  @override
  Widget build(BuildContext context) {
    Widget home = const LoginPage();
    if (initialToken != null && initialToken!.isNotEmpty) {
      if (initialRole == 'kasir') {
        home = const KasirScreen();
      } else if (initialRole != null) {
        home = HomeSementara(role: initialRole!);
      }
    }

    return MaterialApp(
      title: 'Sistem Keuangan POS',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      home: UpdateGate(child: home),
    );
  }
}
