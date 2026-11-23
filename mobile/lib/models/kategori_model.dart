class KategoriModel {
  final int id;
  final String nama;
  final String? keterangan;

  KategoriModel({
    required this.id,
    required this.nama,
    this.keterangan,
  });

  factory KategoriModel.fromJson(Map<String, dynamic> json) {
    return KategoriModel(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      nama: json['nama']?.toString() ?? '',
      keterangan: json['keterangan']?.toString(),
    );
  }
}

