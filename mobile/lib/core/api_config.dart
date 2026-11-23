import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Alamat dasar API Laravel untuk setiap platform.
/// Mendukung konfigurasi dinamis via SharedPreferences maupun override build-time.
class ApiConfig {
  ApiConfig._();

  static const String _override = String.fromEnvironment('API_BASE_URL');
  static const String _prefKey = 'custom_api_base_url';
  static String? _cachedUrl;

  /// Memuat konfigurasi URL server yang tersimpan di perangkat saat aplikasi start.
  static Future<void> inisialisasi() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      _cachedUrl = prefs.getString(_prefKey);
    } catch (_) {}
  }

  /// Alamat default bawaan sistem berdasarkan platform.
  static String get defaultBaseUrl {
    if (_override.isNotEmpty) {
      return _override.endsWith('/')
          ? _override.substring(0, _override.length - 1)
          : _override;
    }

    if (kIsWeb) {
      return 'http://127.0.0.1:8000/api/v1';
    }

    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        // Default IP LAN komputer saat ini (dapat diubah dinamis lewat antarmuka).
        return 'http://192.168.50.8:8000/api/v1';
      default:
        return 'http://127.0.0.1:8000/api/v1';
    }
  }

  /// Base URL aktif yang digunakan untuk seluruh request HTTP.
  static String get baseUrl {
    if (_cachedUrl != null && _cachedUrl!.trim().isNotEmpty) {
      var url = _cachedUrl!.trim();
      return url.endsWith('/') ? url.substring(0, url.length - 1) : url;
    }
    return defaultBaseUrl;
  }

  /// Menyimpan URL kustom baru ke memori dan penyimpanan lokal perangkat.
  static Future<void> setBaseUrl(String newUrl) async {
    var formatted = newUrl.trim();
    if (formatted.endsWith('/')) {
      formatted = formatted.substring(0, formatted.length - 1);
    }
    if (!formatted.startsWith('http://') && !formatted.startsWith('https://')) {
      formatted = 'http://$formatted';
    }
    if (!formatted.endsWith('/api/v1')) {
      formatted = '$formatted/api/v1';
    }
    _cachedUrl = formatted;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefKey, formatted);
  }

  /// Mengembalikan URL ke setelan default sistem.
  static Future<void> resetBaseUrl() async {
    _cachedUrl = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_prefKey);
  }
}
