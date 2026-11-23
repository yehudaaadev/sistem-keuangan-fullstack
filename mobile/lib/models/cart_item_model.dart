import 'produk_model.dart';

class CartItemModel {
  final ProdukModel produk;
  double qty;
  final List<PilihanOpsiModel> opsiTerpilih;

  CartItemModel({
    required this.produk,
    this.qty = 1.0,
    this.opsiTerpilih = const [],
  });

  String get sig {
    final ids = opsiTerpilih.map((o) => o.id).toList()..sort();
    return '${produk.id}_${ids.join("-")}';
  }

  double get hargaTambahanOpsi {
    return opsiTerpilih.fold(0.0, (sum, o) => sum + o.tambahanHarga);
  }

  double get hargaSatuan {
    return produk.hargaJual + hargaTambahanOpsi;
  }

  double get subtotal {
    return hargaSatuan * qty;
  }

  String get labelOpsi {
    if (opsiTerpilih.isEmpty) return '';
    return opsiTerpilih.map((o) {
      if (o.tambahanHarga > 0) {
        return '${o.nama} (+${o.tambahanHarga.toInt()})';
      }
      return o.nama;
    }).join(', ');
  }

  Map<String, dynamic> toApiJson() {
    return {
      'produk_id': produk.id,
      'qty': qty,
      if (opsiTerpilih.isNotEmpty)
        'opsi': opsiTerpilih.map((o) => o.id).toList(),
    };
  }
}

