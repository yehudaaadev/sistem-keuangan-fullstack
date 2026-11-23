import 'package:dio/dio.dart';

import '../core/api_client.dart';
import '../models/kategori_model.dart';
import '../models/pembeli_model.dart';
import '../models/penjualan_model.dart';
import '../models/produk_model.dart';
import '../models/shift_model.dart';

class KasirService {
  /// Shift aktif milik kasir yang login, atau null bila belum ada shift buka.
  Future<ShiftModel?> getShiftAktif() async {
    final res = await dio.get('/shift/aktif');
    final shiftData = res.data['shift'];
    if (shiftData == null) return null;
    return ShiftModel.fromJson(Map<String, dynamic>.from(shiftData as Map));
  }

  /// Membuka shift baru kasir dengan modal awal.
  Future<ShiftModel> bukaShift(double modalAwal) async {
    final res = await dio.post('/shift/buka', data: {
      'modal_awal': modalAwal,
    });
    return ShiftModel.fromJson(Map<String, dynamic>.from(res.data as Map));
  }

  /// Mencatat kas masuk / kas keluar di tengah shift.
  Future<ShiftModel> catatKas({
    required String tipe, // 'masuk' atau 'keluar'
    required double jumlah,
    required String keterangan,
  }) async {
    final res = await dio.post('/shift/kas', data: {
      'tipe': tipe,
      'jumlah': jumlah,
      'keterangan': keterangan,
    });
    return ShiftModel.fromJson(Map<String, dynamic>.from(res.data as Map));
  }

  /// Menutup shift kasir dengan uang yang dihitung di laci.
  Future<ShiftModel> tutupShift({
    required double uangDihitung,
    String? catatan,
  }) async {
    final res = await dio.post('/shift/tutup', data: {
      'uang_dihitung': uangDihitung,
      if (catatan != null && catatan.isNotEmpty) 'catatan': catatan,
    });
    return ShiftModel.fromJson(Map<String, dynamic>.from(res.data as Map));
  }

  /// Mengambil daftar kategori menu produk.
  Future<List<KategoriModel>> getKategori() async {
    final res = await dio.get('/kategori-produk');
    final data = res.data;
    final List list = data is List
        ? data
        : (data is Map && data['data'] is List ? data['data'] : []);
    return list
        .whereType<Map>()
        .map((k) => KategoriModel.fromJson(Map<String, dynamic>.from(k)))
        .toList();
  }

  /// Mengambil daftar produk untuk kasir.
  Future<List<ProdukModel>> getProduk({
    int? kategoriId,
    String? search,
    bool stokTersedia = true,
  }) async {
    final params = <String, dynamic>{
      'per_page': 200,
      if (stokTersedia) 'stok_tersedia': 1,
      'kategori_id': ?kategoriId,
      if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
    };

    final res = await dio.get('/produk', queryParameters: params);
    final data = res.data;
    final List list = data is List
        ? data
        : (data is Map && data['data'] is List ? data['data'] : []);
    return list
        .whereType<Map>()
        .map((p) => ProdukModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();
  }

  /// Mengambil daftar pembeli / pelanggan.
  Future<List<PembeliModel>> getPembeli() async {
    final res = await dio.get('/pembeli', queryParameters: {'per_page': 100});
    final data = res.data;
    final List list = data is List
        ? data
        : (data is Map && data['data'] is List ? data['data'] : []);
    return list
        .whereType<Map>()
        .map((p) => PembeliModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();
  }

  /// Mendaftarkan pembeli baru secara cepat dari layar kasir.
  Future<PembeliModel> tambahPembeli({
    required String nama,
    String? telepon,
    String? alamat,
  }) async {
    final res = await dio.post('/pembeli', data: {
      'nama': nama,
      if (telepon != null && telepon.isNotEmpty) 'telepon': telepon,
      if (alamat != null && alamat.isNotEmpty) 'alamat': alamat,
    });
    return PembeliModel.fromJson(Map<String, dynamic>.from(res.data as Map));
  }

  /// Cek validitas voucher dan hitung potongan diskon.
  Future<Map<String, dynamic>> cekVoucher({
    required String kode,
    required double subtotal,
  }) async {
    final res = await dio.post('/voucher/cek', data: {
      'kode': kode.trim(),
      'subtotal': subtotal,
    });
    return Map<String, dynamic>.from(res.data as Map);
  }

  /// Mengirim transaksi penjualan baru dari kasir.
  Future<PenjualanModel> simpanPenjualan(Map<String, dynamic> payload) async {
    final res = await dio.post('/penjualan', data: payload);
    return PenjualanModel.fromJson(Map<String, dynamic>.from(res.data as Map));
  }

  /// Mengambil riwayat penjualan kasir.
  Future<Map<String, dynamic>> getRiwayatPenjualan({
    int page = 1,
    String? search,
  }) async {
    final params = <String, dynamic>{
      'page': page,
      'per_page': 20,
      if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
    };
    final res = await dio.get('/penjualan', queryParameters: params);
    final data = res.data as Map;
    final List list = (data['data'] is List) ? data['data'] : [];
    final items = list
        .whereType<Map>()
        .map((item) => PenjualanModel.fromJson(Map<String, dynamic>.from(item)))
        .toList();

    return {
      'items': items,
      'current_page': data['current_page'] ?? 1,
      'last_page': data['last_page'] ?? 1,
      'total': data['total'] ?? items.length,
    };
  }

  /// Membatalkan (void) penjualan kasir.
  Future<PenjualanModel> voidPenjualan({
    required int penjualanId,
    required String alasan,
  }) async {
    final res = await dio.post('/penjualan/$penjualanId/void', data: {
      'alasan': alasan,
    });
    return PenjualanModel.fromJson(Map<String, dynamic>.from(res.data as Map));
  }

  /// Mengambil pengaturan kedai (nama toko, alamat, pajak%, service%).
  Future<Map<String, dynamic>> getPengaturan() async {
    final res = await dio.get('/pengaturan');
    return Map<String, dynamic>.from(res.data as Map);
  }

  /// Mengirim struk digital lewat WhatsApp dari server backend.
  Future<void> kirimStrukWa(int penjualanId, {String? telepon}) async {
    await dio.post('/penjualan/$penjualanId/kirim-wa', data: {
      if (telepon != null && telepon.isNotEmpty) 'telepon': telepon,
    });
  }

  /// Mendapatkan teks format ESC/POS dari backend.
  Future<String> getEscposText(int penjualanId) async {
    final res = await dio.get<String>(
      '/penjualan/$penjualanId/escpos',
      queryParameters: {'format': 'text'},
      options: Options(responseType: ResponseType.plain),
    );
    return res.data ?? '';
  }
}
