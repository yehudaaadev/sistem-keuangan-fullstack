import 'kategori_model.dart';

class ProdukModel {
  final int id;
  final int? kategoriId;
  final String nama;
  final double hargaJual;
  final String satuan;
  final double stok;
  final String? imagePath;
  final String? imageUrl;
  final KategoriModel? kategori;
  final List<OpsiGrupModel> opsiGrup;

  ProdukModel({
    required this.id,
    this.kategoriId,
    required this.nama,
    required this.hargaJual,
    required this.satuan,
    required this.stok,
    this.imagePath,
    this.imageUrl,
    this.kategori,
    this.opsiGrup = const [],
  });

  bool get adaOpsi => opsiGrup.isNotEmpty;
  bool get habis => stok <= 0;

  factory ProdukModel.fromJson(Map<String, dynamic> json) {
    return ProdukModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      kategoriId: json['kategori_id'] != null
          ? int.tryParse(json['kategori_id'].toString())
          : null,
      nama: json['nama']?.toString() ?? '',
      hargaJual: double.tryParse(json['harga_jual']?.toString() ?? '0') ?? 0.0,
      satuan: json['satuan']?.toString() ?? 'porsi',
      stok: double.tryParse(json['stok']?.toString() ?? '0') ?? 0.0,
      imagePath: json['image_path']?.toString(),
      imageUrl: json['image_url']?.toString(),
      kategori: json['kategori'] is Map
          ? KategoriModel.fromJson(Map<String, dynamic>.from(json['kategori']))
          : null,
      opsiGrup: (json['opsi_grup'] is List)
          ? (json['opsi_grup'] as List)
              .whereType<Map>()
              .map((g) => OpsiGrupModel.fromJson(Map<String, dynamic>.from(g)))
              .toList()
          : [],
    );
  }
}

class OpsiGrupModel {
  final int id;
  final String nama;
  final String tipe; // 'radio' atau 'checkbox'
  final bool wajib;
  final List<PilihanOpsiModel> pilihan;

  OpsiGrupModel({
    required this.id,
    required this.nama,
    required this.tipe,
    required this.wajib,
    this.pilihan = const [],
  });

  factory OpsiGrupModel.fromJson(Map<String, dynamic> json) {
    return OpsiGrupModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      nama: json['nama']?.toString() ?? '',
      tipe: json['tipe']?.toString() ?? 'radio',
      wajib: json['wajib'] == true || json['wajib'] == 1 || json['wajib'] == '1',
      pilihan: (json['pilihan'] is List)
          ? (json['pilihan'] as List)
              .whereType<Map>()
              .map((p) => PilihanOpsiModel.fromJson(Map<String, dynamic>.from(p)))
              .toList()
          : [],
    );
  }
}

class PilihanOpsiModel {
  final int id;
  final int? opsiGrupId;
  final String nama;
  final double tambahanHarga;

  PilihanOpsiModel({
    required this.id,
    this.opsiGrupId,
    required this.nama,
    required this.tambahanHarga,
  });

  factory PilihanOpsiModel.fromJson(Map<String, dynamic> json) {
    return PilihanOpsiModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      opsiGrupId: json['opsi_grup_id'] != null
          ? int.tryParse(json['opsi_grup_id'].toString())
          : null,
      nama: json['nama']?.toString() ?? '',
      tambahanHarga:
          double.tryParse(json['tambahan_harga']?.toString() ?? '0') ?? 0.0,
    );
  }
}

