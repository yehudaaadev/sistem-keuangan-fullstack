import 'package:shared_preferences/shared_preferences.dart';

/// Penyimpanan sesi (token Sanctum) yang bekerja di Android, iOS, web, dan
/// desktop tanpa plugin native tambahan.
///
/// Catatan: `shared_preferences` menyimpan data sebagai berkas preferensi
/// aplikasi (bukan Keychain/Keystore). Untuk aplikasi produksi yang menangani
/// data keuangan sensitif, pertimbangkan `flutter_secure_storage`.
class TokenStorage {
  TokenStorage._();

  static const _tokenKey = 'token';
  static const _roleKey = 'role';

  static Future<String?> bacaToken() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(_tokenKey);
    } catch (_) {
      return null;
    }
  }

  static Future<String?> bacaRole() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(_roleKey);
    } catch (_) {
      return null;
    }
  }

  static Future<void> simpanSesi({
    required String token,
    required String role,
  }) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_tokenKey, token);
      await prefs.setString(_roleKey, role);
    } catch (_) {}
  }

  static Future<void> hapusSesi() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_tokenKey);
      await prefs.remove(_roleKey);
    } catch (_) {}
  }
}
