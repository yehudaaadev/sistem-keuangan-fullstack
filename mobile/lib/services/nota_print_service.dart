import 'dart:typed_data';

import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import '../models/penjualan_model.dart';
import '../utils/formatters.dart';

class NotaPrintService {
  NotaPrintService._();

  /// Menghasilkan berkas PDF nota thermal universal (80mm, 58mm, atau A4).
  static Future<Uint8List> generateReceiptPdf(
    PenjualanModel penjualan,
    Map<String, dynamic> toko, {
    PdfPageFormat format = PdfPageFormat.roll80,
  }) async {
    final pdf = pw.Document();

    final namaToko = toko['nama_toko']?.toString().isNotEmpty == true
        ? toko['nama_toko'].toString()
        : 'Kedai Kopi';
    final alamat = toko['alamat']?.toString() ?? '';
    final telepon = toko['telepon']?.toString().isNotEmpty == true
        ? toko['telepon'].toString()
        : (toko['telepon_toko']?.toString() ?? '');
    final footer = toko['footer_nota']?.toString().isNotEmpty == true
        ? toko['footer_nota'].toString()
        : 'Terima kasih, sampai jumpa!';
    final pajakPersen = toko['pajak_persen'] != null &&
            (double.tryParse(toko['pajak_persen'].toString()) ?? 0) > 0
        ? '${toko['pajak_persen']}%'
        : '';
    final servicePersen = toko['service_persen'] != null &&
            (double.tryParse(toko['service_persen'].toString()) ?? 0) > 0
        ? '${toko['service_persen']}%'
        : '';

    final is58 = format.width < 65 * PdfPageFormat.mm;
    final isA4 = format.width > 100 * PdfPageFormat.mm;

    final fontSizeTitle = is58 ? 11.0 : (isA4 ? 13.0 : 13.5);
    final fontSizeBody = is58 ? 7.0 : (isA4 ? 8.5 : 8.0);
    final fontSizeQueue = is58 ? 17.0 : (isA4 ? 22.0 : 21.0);
    final hMargin = is58 ? 4.0 : (isA4 ? 20.0 : 8.0);

    pdf.addPage(
      pw.Page(
        pageFormat: format,
        margin: pw.EdgeInsets.symmetric(horizontal: hMargin, vertical: 8),
        build: (pw.Context context) {
          final content = pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.stretch,
            children: [
              // Header
              pw.Text(
                namaToko,
                textAlign: pw.TextAlign.center,
                style: pw.TextStyle(
                  fontSize: fontSizeTitle,
                  fontWeight: pw.FontWeight.bold,
                ),
              ),
              if (alamat.isNotEmpty)
                pw.Padding(
                  padding: const pw.EdgeInsets.only(top: 2),
                  child: pw.Text(
                    alamat,
                    textAlign: pw.TextAlign.center,
                    style: pw.TextStyle(fontSize: fontSizeBody - 0.5),
                  ),
                ),
              if (telepon.isNotEmpty)
                pw.Padding(
                  padding: const pw.EdgeInsets.only(top: 1),
                  child: pw.Text(
                    'Telp/WA: $telepon',
                    textAlign: pw.TextAlign.center,
                    style: pw.TextStyle(fontSize: fontSizeBody - 0.5),
                  ),
                ),
              pw.SizedBox(height: 3),
              pw.Text(
                penjualan.noNota,
                textAlign: pw.TextAlign.center,
                style: pw.TextStyle(fontSize: fontSizeBody - 0.5),
              ),
              pw.Text(
                Formatters.tanggalWaktu(penjualan.tanggal),
                textAlign: pw.TextAlign.center,
                style: pw.TextStyle(fontSize: fontSizeBody - 0.5),
              ),

              if (penjualan.isVoid) ...[
                pw.SizedBox(height: 4),
                pw.Container(
                  padding: const pw.EdgeInsets.all(3),
                  decoration: pw.BoxDecoration(
                    border: pw.Border.all(width: 1),
                  ),
                  child: pw.Text(
                    'DIBATALKAN (VOID)',
                    textAlign: pw.TextAlign.center,
                    style: pw.TextStyle(
                      fontWeight: pw.FontWeight.bold,
                      fontSize: fontSizeBody + 1.5,
                    ),
                  ),
                ),
              ],

              if (penjualan.nomorAntrean != null &&
                  penjualan.nomorAntrean!.isNotEmpty) ...[
                pw.SizedBox(height: 5),
                pw.Text(
                  'NO. ANTREAN',
                  textAlign: pw.TextAlign.center,
                  style: pw.TextStyle(fontSize: fontSizeBody - 1),
                ),
                pw.Text(
                  penjualan.nomorAntrean!,
                  textAlign: pw.TextAlign.center,
                  style: pw.TextStyle(
                    fontSize: fontSizeQueue,
                    fontWeight: pw.FontWeight.bold,
                  ),
                ),
              ],

              pw.SizedBox(height: 3),
              pw.Text(
                penjualan.tipe == 'dine_in'
                    ? 'Makan di tempat${penjualan.meja != null ? " · Meja ${penjualan.meja}" : ""}'
                    : 'Bawa pulang',
                textAlign: pw.TextAlign.center,
                style: pw.TextStyle(fontSize: fontSizeBody - 0.5),
              ),

              _divider(),

              _rowText('Kasir', penjualan.kasirNama, fontSize: fontSizeBody),
              _rowText('Pembeli', penjualan.pembeli?.nama ?? 'Umum', fontSize: fontSizeBody),

              _divider(),

              // Items
              ...penjualan.detail.map((item) {
                return pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.stretch,
                  children: [
                    pw.Text(
                      item.namaProduk,
                      style: pw.TextStyle(
                        fontSize: fontSizeBody + 0.5,
                        fontWeight: pw.FontWeight.bold,
                      ),
                    ),
                    if (item.opsi.isNotEmpty)
                      ...item.opsi.map((o) {
                        final namaOpsi = o['nama']?.toString() ?? '';
                        final hargaNum = o['harga'] is num
                            ? o['harga'] as num
                            : (double.tryParse(o['harga']?.toString() ?? '0') ?? 0);
                        final hargaOpsi = hargaNum > 0
                            ? ' (+${Formatters.rupiah(hargaNum)})'
                            : '';
                        return pw.Padding(
                          padding: const pw.EdgeInsets.only(left: 4),
                          child: pw.Text(
                            '+ $namaOpsi$hargaOpsi',
                            style: pw.TextStyle(fontSize: fontSizeBody - 1.2),
                          ),
                        );
                      }),
                    pw.Row(
                      mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                      children: [
                        pw.Text(
                          '${Formatters.angka(item.qty)} ${item.satuan} x ${Formatters.rupiah(item.hargaJual)}',
                          style: pw.TextStyle(fontSize: fontSizeBody - 0.5),
                        ),
                        pw.Text(
                          Formatters.rupiah(item.subtotal),
                          style: pw.TextStyle(fontSize: fontSizeBody - 0.5),
                        ),
                      ],
                    ),
                    pw.SizedBox(height: 2),
                  ],
                );
              }),

              _divider(),

              _rowText(
                'Total Qty',
                '${Formatters.angka(penjualan.detail.fold<double>(0.0, (s, i) => s + i.qty))} item (${penjualan.detail.length} menu)',
                fontSize: fontSizeBody,
              ),

              // Rincian
              if (penjualan.diskon > 0 ||
                  penjualan.diskonPoin > 0 ||
                  penjualan.service > 0 ||
                  penjualan.pajak > 0) ...[
                _rowText('Subtotal', Formatters.rupiah(penjualan.subtotal), fontSize: fontSizeBody),
                if (penjualan.diskon > 0)
                  _rowText(
                    'Diskon ${penjualan.voucherKode ?? ""}',
                    '-${Formatters.rupiah(penjualan.diskon)}',
                    fontSize: fontSizeBody,
                  ),
                if (penjualan.diskonPoin > 0)
                  _rowText(
                    'Diskon Poin (${penjualan.poinDigunakan} pt)',
                    '-${Formatters.rupiah(penjualan.diskonPoin)}',
                    fontSize: fontSizeBody,
                  ),
                if (penjualan.service > 0)
                  _rowText(
                    servicePersen.isNotEmpty ? 'Service ($servicePersen)' : 'Service',
                    Formatters.rupiah(penjualan.service),
                    fontSize: fontSizeBody,
                  ),
                if (penjualan.pajak > 0)
                  _rowText(
                    pajakPersen.isNotEmpty ? 'Pajak PB1 ($pajakPersen)' : 'Pajak PB1',
                    Formatters.rupiah(penjualan.pajak),
                    fontSize: fontSizeBody,
                  ),
              ],

              pw.Row(
                mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                children: [
                  pw.Text(
                    'TOTAL',
                    style: pw.TextStyle(
                      fontSize: fontSizeBody + 1.5,
                      fontWeight: pw.FontWeight.bold,
                    ),
                  ),
                  pw.Text(
                    Formatters.rupiah(penjualan.total),
                    style: pw.TextStyle(
                      fontSize: fontSizeBody + 1.5,
                      fontWeight: pw.FontWeight.bold,
                    ),
                  ),
                ],
              ),
              pw.SizedBox(height: 2),
              _rowText('Metode', penjualan.metode.toUpperCase(), fontSize: fontSizeBody),
              if (penjualan.metode == 'tunai') ...[
                _rowText('Bayar', Formatters.rupiah(penjualan.bayar), fontSize: fontSizeBody),
                _rowText('Kembalian', Formatters.rupiah(penjualan.kembalian), fontSize: fontSizeBody),
              ],
              if (penjualan.catatan != null && penjualan.catatan!.trim().isNotEmpty) ...[
                pw.SizedBox(height: 2),
                _rowText('Catatan', penjualan.catatan!.trim(), fontSize: fontSizeBody),
              ],

              if (penjualan.isVoid) ...[
                _divider(),
                pw.Text(
                  'Alasan Void: ${penjualan.voidAlasan ?? "-"}',
                  style: pw.TextStyle(fontSize: fontSizeBody - 0.5),
                ),
              ],

              _divider(),

              pw.Text(
                footer,
                textAlign: pw.TextAlign.center,
                style: pw.TextStyle(fontSize: fontSizeBody - 0.5),
              ),

              pw.SizedBox(height: 6),
              pw.Center(
                child: pw.BarcodeWidget(
                  barcode: pw.Barcode.code128(),
                  data: penjualan.noNota,
                  width: is58 ? 120 : 155,
                  height: 24,
                  drawText: false,
                ),
              ),
              pw.SizedBox(height: 2),
              pw.Text(
                penjualan.noNota,
                textAlign: pw.TextAlign.center,
                style: pw.TextStyle(fontSize: fontSizeBody - 1.5, letterSpacing: 1),
              ),
            ],
          );

          if (isA4) {
            return pw.Center(
              child: pw.Container(
                width: 75 * PdfPageFormat.mm,
                padding: const pw.EdgeInsets.all(12),
                decoration: pw.BoxDecoration(
                  border: pw.Border.all(color: PdfColors.grey400, width: 0.8),
                ),
                child: content,
              ),
            );
          }

          return content;
        },
      ),
    );

    return pdf.save();
  }

  /// Langsung memicu cetak ke printer sistem / Bluetooth thermal / USB / Dokumen.
  static Future<void> printReceipt(
    PenjualanModel penjualan,
    Map<String, dynamic> toko, {
    PdfPageFormat format = PdfPageFormat.roll80,
  }) async {
    final pdfBytes = await generateReceiptPdf(penjualan, toko, format: format);
    await Printing.layoutPdf(
      onLayout: (chosenFormat) async => pdfBytes,
      name: 'Nota-${penjualan.noNota}',
      format: format,
    );
  }

  /// Membagikan berkas PDF nota (WhatsApp / Telegram / Email / Unduh File).
  static Future<void> shareReceipt(
    PenjualanModel penjualan,
    Map<String, dynamic> toko, {
    PdfPageFormat format = PdfPageFormat.roll80,
  }) async {
    final pdfBytes = await generateReceiptPdf(penjualan, toko, format: format);
    await Printing.sharePdf(
      bytes: pdfBytes,
      filename: 'Nota-${penjualan.noNota}.pdf',
    );
  }

  /// Format teks polos untuk disalin atau dikirim lewat WhatsApp.
  static String formatPlainText(
    PenjualanModel penjualan,
    Map<String, dynamic> toko,
  ) {
    final namaToko = toko['nama_toko']?.toString().isNotEmpty == true
        ? toko['nama_toko'].toString()
        : 'Kedai Kopi';
    final alamat = toko['alamat']?.toString() ?? '';
    final telepon = toko['telepon']?.toString().isNotEmpty == true
        ? toko['telepon'].toString()
        : (toko['telepon_toko']?.toString() ?? '');
    final footer = toko['footer_nota']?.toString().isNotEmpty == true
        ? toko['footer_nota'].toString()
        : 'Terima kasih, sampai jumpa!';
    final pajakPersen = toko['pajak_persen'] != null &&
            (double.tryParse(toko['pajak_persen'].toString()) ?? 0) > 0
        ? '${toko['pajak_persen']}%'
        : '';
    final servicePersen = toko['service_persen'] != null &&
            (double.tryParse(toko['service_persen'].toString()) ?? 0) > 0
        ? '${toko['service_persen']}%'
        : '';

    final buffer = StringBuffer();
    buffer.writeln(namaToko.toUpperCase());
    if (alamat.isNotEmpty) buffer.writeln(alamat);
    if (telepon.isNotEmpty) buffer.writeln('Telp/WA: $telepon');
    buffer.writeln('--------------------------------');
    buffer.writeln('Nota     : ${penjualan.noNota}');
    buffer.writeln('Tanggal  : ${Formatters.tanggalWaktu(penjualan.tanggal)}');
    if (penjualan.nomorAntrean != null && penjualan.nomorAntrean!.isNotEmpty) {
      buffer.writeln('Antrean  : #${penjualan.nomorAntrean}');
    }
    final tipeDesc = penjualan.tipe == 'dine_in'
        ? 'Makan di tempat${penjualan.meja != null ? " (Meja ${penjualan.meja})" : ""}'
        : 'Bawa pulang';
    buffer.writeln('Tipe     : $tipeDesc');
    buffer.writeln('Kasir    : ${penjualan.kasirNama}');
    buffer.writeln('Pembeli  : ${penjualan.pembeli?.nama ?? "Umum"}');
    buffer.writeln('--------------------------------');

    for (final item in penjualan.detail) {
      buffer.writeln(item.namaProduk);
      for (final o in item.opsi) {
        final hargaNum = o['harga'] is num
            ? o['harga'] as num
            : (double.tryParse(o['harga']?.toString() ?? '0') ?? 0);
        final harga = hargaNum > 0 ? " (+${Formatters.rupiah(hargaNum)})" : "";
        buffer.writeln('  + ${o['nama']}$harga');
      }
      buffer.writeln(
        '  ${Formatters.angka(item.qty)} ${item.satuan} x ${Formatters.rupiah(item.hargaJual)} = ${Formatters.rupiah(item.subtotal)}',
      );
    }

    buffer.writeln('--------------------------------');
    final totalQty = penjualan.detail.fold<double>(0.0, (s, i) => s + i.qty);
    buffer.writeln('Total Qty: ${Formatters.angka(totalQty)} item (${penjualan.detail.length} menu)');
    if (penjualan.diskon > 0 ||
        penjualan.diskonPoin > 0 ||
        penjualan.service > 0 ||
        penjualan.pajak > 0) {
      buffer.writeln('Subtotal : ${Formatters.rupiah(penjualan.subtotal)}');
      if (penjualan.diskon > 0) {
        buffer.writeln('Diskon   : -${Formatters.rupiah(penjualan.diskon)}');
      }
      if (penjualan.diskonPoin > 0) {
        buffer.writeln('Poin     : -${Formatters.rupiah(penjualan.diskonPoin)}');
      }
      if (penjualan.service > 0) {
        final sLabel = servicePersen.isNotEmpty ? 'Service ($servicePersen)' : 'Service';
        buffer.writeln('$sLabel: ${Formatters.rupiah(penjualan.service)}');
      }
      if (penjualan.pajak > 0) {
        final pLabel = pajakPersen.isNotEmpty ? 'Pajak ($pajakPersen)' : 'Pajak PB1';
        buffer.writeln('$pLabel  : ${Formatters.rupiah(penjualan.pajak)}');
      }
    }

    buffer.writeln('TOTAL    : ${Formatters.rupiah(penjualan.total)}');
    buffer.writeln('Metode   : ${penjualan.metode.toUpperCase()}');
    if (penjualan.metode == 'tunai') {
      buffer.writeln('Bayar    : ${Formatters.rupiah(penjualan.bayar)}');
      buffer.writeln('Kembali  : ${Formatters.rupiah(penjualan.kembalian)}');
    }
    if (penjualan.catatan != null && penjualan.catatan!.trim().isNotEmpty) {
      buffer.writeln('Catatan  : ${penjualan.catatan!.trim()}');
    }
    if (penjualan.isVoid) {
      buffer.writeln('Status   : DIBATALKAN (VOID)');
      buffer.writeln('Alasan   : ${penjualan.voidAlasan ?? "-"}');
    }

    buffer.writeln('--------------------------------');
    buffer.writeln(footer);

    return buffer.toString();
  }

  static pw.Widget _divider() {
    return pw.Padding(
      padding: const pw.EdgeInsets.symmetric(vertical: 4),
      child: pw.Text(
        '--------------------------------',
        textAlign: pw.TextAlign.center,
        style: const pw.TextStyle(fontSize: 8),
      ),
    );
  }

  static pw.Widget _rowText(String left, String right, {double fontSize = 8.0}) {
    return pw.Padding(
      padding: const pw.EdgeInsets.symmetric(vertical: 1),
      child: pw.Row(
        mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
        children: [
          pw.Text(left, style: pw.TextStyle(fontSize: fontSize)),
          pw.Text(right, style: pw.TextStyle(fontSize: fontSize)),
        ],
      ),
    );
  }
}
