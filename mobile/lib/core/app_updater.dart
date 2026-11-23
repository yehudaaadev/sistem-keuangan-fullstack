import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:open_filex/open_filex.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path_provider/path_provider.dart';

/// Informasi versi terbaru yang dibaca dari file update.json.
class InfoUpdate {
  final int versionCode;
  final String versionName;
  final String apkUrl;
  final String catatan;

  const InfoUpdate({
    required this.versionCode,
    required this.versionName,
    required this.apkUrl,
    required this.catatan,
  });
}

/// Mengecek versi terbaru dan mengunduh APK untuk dipasang ulang (Android saja).
class AppUpdater {
  AppUpdater._();

  /// Alamat file update.json. Bisa diganti saat build:
  /// flutter build apk --dart-define=UPDATE_URL=https://...
  static const String urlManifest = String.fromEnvironment(
    'UPDATE_URL',
    defaultValue:
        'https://gitlab.com/davidyehudaaa/sistem-keuangan-flutter/-/raw/main/update.json',
  );

  /// Hanya aktif di Android (di web / desktop dilewati).
  static bool get didukung =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  /// Mengembalikan info update jika ada versi yang lebih baru, selain itu null.
  /// Gagal jaringan dianggap "tidak ada update" supaya kasir tetap bisa jalan offline.
  static Future<InfoUpdate?> cekUpdate() async {
    if (!didukung || urlManifest.isEmpty) return null;
    try {
      final dio = Dio(BaseOptions(
        connectTimeout: const Duration(seconds: 5),
        receiveTimeout: const Duration(seconds: 5),
      ));
      final respon = await dio.get<Map<String, dynamic>>(urlManifest);
      final data = respon.data;
      if (data == null) return null;

      final versiBaru = (data['version_code'] as num?)?.toInt() ?? 0;
      final apkUrl = (data['apk_url'] as String?) ?? '';
      if (apkUrl.isEmpty) return null;

      final info = await PackageInfo.fromPlatform();
      final versiSekarang = int.tryParse(info.buildNumber) ?? 0;
      if (versiBaru <= versiSekarang) return null;

      return InfoUpdate(
        versionCode: versiBaru,
        versionName: (data['version_name'] as String?) ?? '',
        apkUrl: apkUrl,
        catatan: (data['catatan'] as String?) ?? '',
      );
    } catch (e) {
      debugPrint('Cek update gagal: $e');
      return null;
    }
  }

  /// Mengunduh APK ke folder sementara lalu membuka penginstal Android.
  /// [onProgres] menerima nilai 0.0 sampai 1.0.
  static Future<void> unduhDanPasang(
    InfoUpdate update, {
    void Function(double progres)? onProgres,
  }) async {
    final dir = await getTemporaryDirectory();
    final path = '${dir.path}/update-${update.versionCode}.apk';

    final dio = Dio();
    await dio.download(
      update.apkUrl,
      path,
      onReceiveProgress: (diterima, total) {
        if (total > 0 && onProgres != null) {
          onProgres(diterima / total);
        }
      },
    );

    final hasil = await OpenFilex.open(
      path,
      type: 'application/vnd.android.package-archive',
    );
    if (hasil.type != ResultType.done) {
      throw Exception(hasil.message);
    }
  }
}
