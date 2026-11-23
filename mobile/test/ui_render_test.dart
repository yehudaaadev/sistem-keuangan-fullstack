import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:sistemkeuanganflutter/core/theme/app_theme.dart';
import 'package:sistemkeuanganflutter/features/kasir/widgets/cart_panel.dart';
import 'package:sistemkeuanganflutter/features/kasir/widgets/payment_modal.dart';
import 'package:sistemkeuanganflutter/features/kasir/widgets/product_option_dialog.dart';
import 'package:sistemkeuanganflutter/features/kasir/widgets/receipt_dialog.dart';
import 'package:sistemkeuanganflutter/features/kasir/widgets/shift_bar.dart';
import 'package:sistemkeuanganflutter/models/cart_item_model.dart';
import 'package:sistemkeuanganflutter/models/pembeli_model.dart';
import 'package:sistemkeuanganflutter/models/penjualan_model.dart';
import 'package:sistemkeuanganflutter/models/produk_model.dart';
import 'package:sistemkeuanganflutter/models/shift_model.dart';

ProdukModel _produk({bool opsi = false}) => ProdukModel(
  id: 1,
  nama: 'Es Kopi Susu Gula Aren Spesial',
  hargaJual: 22000,
  satuan: 'cup',
  stok: 12,
  opsiGrup: opsi
      ? [
          OpsiGrupModel(
            id: 1,
            nama: 'Level Gula',
            tipe: 'radio',
            wajib: true,
            pilihan: [
              PilihanOpsiModel(id: 1, nama: 'Normal', tambahanHarga: 0),
              PilihanOpsiModel(id: 2, nama: 'Less Sugar', tambahanHarga: 2000),
            ],
          ),
          OpsiGrupModel(
            id: 2,
            nama: 'Topping',
            tipe: 'checkbox',
            wajib: false,
            pilihan: [
              PilihanOpsiModel(id: 3, nama: 'Boba', tambahanHarga: 5000),
              PilihanOpsiModel(id: 4, nama: 'Cincau', tambahanHarga: 3000),
            ],
          ),
        ]
      : const [],
);

ShiftModel _shift() => ShiftModel(
  id: 1,
  userId: 2,
  modalAwal: 250000,
  dibukaPada: '2026-10-30T08:15:00',
  rekap: RekapShift(
    totalPenjualan: 1250000,
    jumlahTransaksi: 24,
    penjualanTunai: 750000,
    penjualanNonTunai: 500000,
    kasMasuk: 100000,
    kasKeluar: 50000,
    kasSeharusnya: 1050000,
  ),
);

PenjualanModel _penjualan() => PenjualanModel(
  id: 9,
  noNota: 'INV-20261030-0009',
  nomorAntrean: 'A12',
  tanggal: '2026-10-30T10:24:00',
  tipe: 'dine_in',
  meja: '04',
  metode: 'tunai',
  subtotal: 66000,
  service: 3300,
  pajak: 3597,
  total: 72897,
  bayar: 100000,
  kembalian: 27103,
  poinDidapat: 72,
  pembeli: PembeliModel(id: 1, nama: 'Andi', telepon: '08123', poin: 12),
  kasirNama: 'Siti',
  detail: [
    PenjualanDetailModel(
      id: 1,
      produkId: 1,
      namaProduk: 'Es Kopi Susu Gula Aren Spesial',
      satuan: 'cup',
      qty: 2,
      hargaJual: 22000,
      subtotal: 44000,
      opsi: [
        {'nama': 'Less Sugar', 'harga': 2000},
      ],
    ),
    PenjualanDetailModel(
      id: 2,
      produkId: 2,
      namaProduk: 'Croissant Butter',
      satuan: 'pcs',
      qty: 1,
      hargaJual: 22000,
      subtotal: 22000,
    ),
  ],
);

Map<String, dynamic> _toko() => {
  'nama_toko': 'Kopi Senja',
  'alamat': 'Jl. Merdeka No. 12, Bandung',
  'footer_nota': 'Terima kasih sudah mampir!',
};

Widget _wrap(Widget child, {double width = 360, double height = 740}) {
  return MediaQuery(
    data: const MediaQueryData(size: Size(360, 740)),
    child: MaterialApp(
      theme: AppTheme.light,
      home: Scaffold(
        body: SizedBox(width: width, height: height, child: child),
      ),
    ),
  );
}

void main() {
  Future<void> atSize(WidgetTester tester, Size size) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
  }

  testWidgets('shift bar aktif & kosong tidak overflow', (tester) async {
    await atSize(tester, const Size(360, 740));
    await tester.pumpWidget(
      _wrap(
        Column(
          children: [
            ShiftBar(
              shift: _shift(),
              onBukaShift: () {},
              onKasMasuk: () {},
              onKasKeluar: () {},
              onTutupShift: () {},
            ),
            ShiftBar(
              shift: null,
              onBukaShift: () {},
              onKasMasuk: () {},
              onKasKeluar: () {},
              onTutupShift: () {},
            ),
          ],
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Shift aktif'), findsOneWidget);
    expect(find.text('Belum ada shift aktif'), findsOneWidget);

    await atSize(tester, const Size(320, 700));
    await tester.pumpWidget(
      _wrap(
        ShiftBar(
          shift: _shift(),
          onBukaShift: () {},
          onKasMasuk: () {},
          onKasKeluar: () {},
          onTutupShift: () {},
        ),
        width: 320,
        height: 300,
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('panel pesanan tampil di layar kecil', (tester) async {
    await atSize(tester, const Size(360, 740));
    final items = [
      CartItemModel(
        produk: _produk(),
        qty: 2,
        opsiTerpilih: [PilihanOpsiModel(id: 2, nama: 'Less Sugar', tambahanHarga: 2000)],
      ),
      CartItemModel(produk: _produk(), qty: 1),
    ];

    await tester.pumpWidget(
      _wrap(
        CartPanel(
          items: items,
          onUpdateQty: (i, d) {},
          onRemoveItem: (i) {},
          onClearCart: () {},
          tipe: 'dine_in',
          onTipeChanged: (t) {},
          meja: '',
          onMejaChanged: (m) {},
          pembeliTerpilih: PembeliModel(id: 1, nama: 'Andi', poin: 120),
          pembeliList: [PembeliModel(id: 1, nama: 'Andi', poin: 120)],
          onPembeliChanged: (b) {},
          onTambahPembeli: () {},
          pakaiPoin: false,
          onTogglePakaiPoin: (v) {},
          poinDigunakan: 0,
          diskonPoin: 0,
          voucherKode: null,
          diskonVoucher: 0,
          onApplyVoucher: (k) async {},
          onRemoveVoucher: () {},
          servicePersen: 5,
          pajakPersen: 11,
          onCheckout: () {},
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Pesanan'), findsOneWidget);
    expect(find.text('Makan di Tempat'), findsNothing);
    expect(find.text('Di Tempat'), findsOneWidget);
  });

  testWidgets('modal pembayaran tunai menghitung kembalian', (tester) async {
    await atSize(tester, const Size(360, 740));
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: ElevatedButton(
                onPressed: () => PaymentModal.show(
                  context: context,
                  total: 72897,
                  onBayar: ({required metode, required bayar}) async =>
                      _penjualan(),
                ),
                child: const Text('buka'),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('buka'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('TOTAL TAGIHAN'), findsOneWidget);
    expect(find.text('Kembalian'), findsOneWidget);

    await tester.enterText(find.byType(TextField), '50000');
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Uang masih kurang'), findsOneWidget);
  });

  testWidgets('dialog opsi produk tampil rapi', (tester) async {
    await atSize(tester, const Size(360, 740));
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: ElevatedButton(
                onPressed: () =>
                    ProductOptionDialog.show(context, _produk(opsi: true)),
                child: const Text('buka'),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('buka'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Level Gula'), findsOneWidget);
    expect(find.textContaining('Tambah ·'), findsOneWidget);
  });

  testWidgets('dialog struk tampil dengan pratinjau nota', (tester) async {
    await atSize(tester, const Size(360, 740));
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: ReceiptDialog(penjualan: _penjualan(), toko: _toko()),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Transaksi Berhasil'), findsOneWidget);
    expect(find.text('Cetak Nota'), findsOneWidget);
  });
}
