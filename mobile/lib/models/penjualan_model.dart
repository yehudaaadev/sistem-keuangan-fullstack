import 'pembeli_model.dart';

class PenjualanModel {
  final int id;
  final String noNota;
  final String? nomorAntrean;
  final String tanggal;
  final String tipe;
  final String? meja;
  final String? sumber;
  final String metode;
  final double subtotal;
  final double diskon;
  final String? voucherKode;
  final double service;
  final double pajak;
  final double total;
  final double bayar;
  final double kembalian;
  final int poinDidapat;
  final int poinDigunakan;
  final double diskonPoin;
  final String? catatan;
  final String? voidAt;
  final String? voidAlasan;
  final PembeliModel? pembeli;
  final String kasirNama;
  final List<PenjualanDetailModel> detail;

  PenjualanModel({
    required this.id,
    required this.noNota,
    this.nomorAntrean,
    required this.tanggal,
    required this.tipe,
    this.meja,
    this.sumber,
    required this.metode,
    required this.subtotal,
    this.diskon = 0.0,
    this.voucherKode,
    this.service = 0.0,
    this.pajak = 0.0,
    required this.total,
    required this.bayar,
    this.kembalian = 0.0,
    this.poinDidapat = 0,
    this.poinDigunakan = 0,
    this.diskonPoin = 0.0,
    this.catatan,
    this.voidAt,
    this.voidAlasan,
    this.pembeli,
    this.kasirNama = 'Kasir',
    this.detail = const [],
  });

  bool get isVoid => voidAt != null && voidAt!.isNotEmpty;

  factory PenjualanModel.fromJson(Map<String, dynamic> json) {
    return PenjualanModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      noNota: json['no_nota']?.toString() ?? '',
      nomorAntrean: json['nomor_antrean']?.toString(),
      tanggal: json['tanggal']?.toString() ?? '',
      tipe: json['tipe']?.toString() ?? 'dine_in',
      meja: json['meja']?.toString(),
      sumber: json['sumber']?.toString(),
      metode: json['metode']?.toString() ?? 'tunai',
      subtotal: double.tryParse(json['subtotal']?.toString() ?? '0') ?? 0.0,
      diskon: double.tryParse(json['diskon']?.toString() ?? '0') ?? 0.0,
      voucherKode: json['voucher_kode']?.toString(),
      service: double.tryParse(json['service']?.toString() ?? '0') ?? 0.0,
      pajak: double.tryParse(json['pajak']?.toString() ?? '0') ?? 0.0,
      total: double.tryParse(json['total']?.toString() ?? '0') ?? 0.0,
      bayar: double.tryParse(json['bayar']?.toString() ?? '0') ?? 0.0,
      kembalian: double.tryParse(json['kembalian']?.toString() ?? '0') ?? 0.0,
      poinDidapat: int.tryParse(json['poin_didapat']?.toString() ?? '0') ?? 0,
      poinDigunakan: int.tryParse(json['poin_digunakan']?.toString() ?? '0') ?? 0,
      diskonPoin: double.tryParse(json['diskon_poin']?.toString() ?? '0') ?? 0.0,
      catatan: json['catatan']?.toString(),
      voidAt: json['void_at']?.toString(),
      voidAlasan: json['void_alasan']?.toString(),
      pembeli: json['pembeli'] is Map
          ? PembeliModel.fromJson(Map<String, dynamic>.from(json['pembeli']))
          : null,
      kasirNama: json['user']?['name']?.toString() ?? 'Kasir',
      detail: (json['detail'] is List)
          ? (json['detail'] as List)
              .whereType<Map>()
              .map((d) => PenjualanDetailModel.fromJson(Map<String, dynamic>.from(d)))
              .toList()
          : [],
    );
  }
}

class PenjualanDetailModel {
  final int id;
  final int produkId;
  final String namaProduk;
  final String satuan;
  final double qty;
  final double hargaJual;
  final double subtotal;
  final List<Map<String, dynamic>> opsi;

  PenjualanDetailModel({
    required this.id,
    required this.produkId,
    required this.namaProduk,
    this.satuan = 'porsi',
    required this.qty,
    required this.hargaJual,
    required this.subtotal,
    this.opsi = const [],
  });

  factory PenjualanDetailModel.fromJson(Map<String, dynamic> json) {
    final produk = json['produk'] is Map ? json['produk'] : {};
    return PenjualanDetailModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      produkId: json['produk_id'] is int
          ? json['produk_id']
          : int.tryParse(json['produk_id']?.toString() ?? '0') ?? 0,
      namaProduk: produk['nama']?.toString() ?? 'Menu',
      satuan: produk['satuan']?.toString() ?? 'porsi',
      qty: double.tryParse(json['qty']?.toString() ?? '0') ?? 0.0,
      hargaJual: double.tryParse(json['harga_jual']?.toString() ?? '0') ?? 0.0,
      subtotal: double.tryParse(json['subtotal']?.toString() ?? '0') ?? 0.0,
      opsi: (json['opsi'] is List)
          ? (json['opsi'] as List)
              .whereType<Map>()
              .map((o) => Map<String, dynamic>.from(o))
              .toList()
          : [],
    );
  }
}

