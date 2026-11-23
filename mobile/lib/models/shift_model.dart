class ShiftModel {
  final int id;
  final int userId;
  final double modalAwal;
  final String dibukaPada;
  final String? ditutupPada;
  final double? uangDihitung;
  final double? selisih;
  final String? catatan;
  final Map<String, dynamic>? user;
  final RekapShift? rekap;
  final List<KasItem> kas;

  ShiftModel({
    required this.id,
    required this.userId,
    required this.modalAwal,
    required this.dibukaPada,
    this.ditutupPada,
    this.uangDihitung,
    this.selisih,
    this.catatan,
    this.user,
    this.rekap,
    this.kas = const [],
  });

  factory ShiftModel.fromJson(Map<String, dynamic> json) {
    return ShiftModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      userId: json['user_id'] is int
          ? json['user_id']
          : int.tryParse(json['user_id']?.toString() ?? '0') ?? 0,
      modalAwal: double.tryParse(json['modal_awal']?.toString() ?? '0') ?? 0.0,
      dibukaPada: json['dibuka_pada']?.toString() ?? '',
      ditutupPada: json['ditutup_pada']?.toString(),
      uangDihitung: json['uang_dihitung'] != null
          ? double.tryParse(json['uang_dihitung'].toString())
          : null,
      selisih: json['selisih'] != null
          ? double.tryParse(json['selisih'].toString())
          : null,
      catatan: json['catatan']?.toString(),
      user: json['user'] is Map ? Map<String, dynamic>.from(json['user']) : null,
      rekap: json['rekap'] is Map
          ? RekapShift.fromJson(Map<String, dynamic>.from(json['rekap']))
          : null,
      kas: (json['kas'] is List)
          ? (json['kas'] as List)
              .whereType<Map>()
              .map((k) => KasItem.fromJson(Map<String, dynamic>.from(k)))
              .toList()
          : [],
    );
  }
}

class RekapShift {
  final double totalPenjualan;
  final int jumlahTransaksi;
  final double penjualanTunai;
  final double penjualanNonTunai;
  final double kasMasuk;
  final double kasKeluar;
  final double kasSeharusnya;
  final List<MetodeRekap> perMetode;

  RekapShift({
    required this.totalPenjualan,
    required this.jumlahTransaksi,
    required this.penjualanTunai,
    required this.penjualanNonTunai,
    required this.kasMasuk,
    required this.kasKeluar,
    required this.kasSeharusnya,
    this.perMetode = const [],
  });

  factory RekapShift.fromJson(Map<String, dynamic> json) {
    return RekapShift(
      totalPenjualan:
          double.tryParse(json['total_penjualan']?.toString() ?? '0') ?? 0.0,
      jumlahTransaksi:
          int.tryParse(json['jumlah_transaksi']?.toString() ?? '0') ?? 0,
      penjualanTunai:
          double.tryParse(json['penjualan_tunai']?.toString() ?? '0') ?? 0.0,
      penjualanNonTunai:
          double.tryParse(json['penjualan_non_tunai']?.toString() ?? '0') ?? 0.0,
      kasMasuk: double.tryParse(json['kas_masuk']?.toString() ?? '0') ?? 0.0,
      kasKeluar: double.tryParse(json['kas_keluar']?.toString() ?? '0') ?? 0.0,
      kasSeharusnya:
          double.tryParse(json['kas_seharusnya']?.toString() ?? '0') ?? 0.0,
      perMetode: (json['per_metode'] is List)
          ? (json['per_metode'] as List)
              .whereType<Map>()
              .map((m) => MetodeRekap.fromJson(Map<String, dynamic>.from(m)))
              .toList()
          : [],
    );
  }
}

class MetodeRekap {
  final String metode;
  final int jumlah;
  final double total;

  MetodeRekap({
    required this.metode,
    required this.jumlah,
    required this.total,
  });

  factory MetodeRekap.fromJson(Map<String, dynamic> json) {
    return MetodeRekap(
      metode: json['metode']?.toString() ?? '',
      jumlah: int.tryParse(json['jumlah']?.toString() ?? '0') ?? 0,
      total: double.tryParse(json['total']?.toString() ?? '0') ?? 0.0,
    );
  }
}

class KasItem {
  final int id;
  final String tipe; // 'masuk' atau 'keluar'
  final double jumlah;
  final String keterangan;
  final String? createdAt;

  KasItem({
    required this.id,
    required this.tipe,
    required this.jumlah,
    required this.keterangan,
    this.createdAt,
  });

  factory KasItem.fromJson(Map<String, dynamic> json) {
    return KasItem(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      tipe: json['tipe']?.toString() ?? 'masuk',
      jumlah: double.tryParse(json['jumlah']?.toString() ?? '0') ?? 0.0,
      keterangan: json['keterangan']?.toString() ?? '',
      createdAt: json['created_at']?.toString(),
    );
  }
}

