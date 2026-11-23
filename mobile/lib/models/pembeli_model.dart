class PembeliModel {
  final int id;
  final String nama;
  final String? telepon;
  final String? alamat;
  final int poin;

  PembeliModel({
    required this.id,
    required this.nama,
    this.telepon,
    this.alamat,
    this.poin = 0,
  });

  factory PembeliModel.fromJson(Map<String, dynamic> json) {
    return PembeliModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      nama: json['nama']?.toString() ?? '',
      telepon: json['telepon']?.toString(),
      alamat: json['alamat']?.toString(),
      poin: int.tryParse(json['poin']?.toString() ?? '0') ?? 0,
    );
  }
}

