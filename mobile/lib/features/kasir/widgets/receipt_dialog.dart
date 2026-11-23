import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pdf/pdf.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api_config.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_widgets.dart';
import '../../../models/penjualan_model.dart';
import '../../../services/kasir_service.dart';
import '../../../services/nota_print_service.dart';
import '../../../utils/formatters.dart';

class ReceiptDialog extends StatefulWidget {
  final PenjualanModel penjualan;
  final Map<String, dynamic> toko;

  const ReceiptDialog({super.key, required this.penjualan, required this.toko});

  static Future<void> show({
    required BuildContext context,
    required PenjualanModel penjualan,
    required Map<String, dynamic> toko,
  }) {
    return showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => ReceiptDialog(penjualan: penjualan, toko: toko),
    );
  }

  @override
  State<ReceiptDialog> createState() => _ReceiptDialogState();
}

class _ReceiptDialogState extends State<ReceiptDialog> {
  final _waCtrl = TextEditingController();
  bool _kirimWaLoading = false;
  String _paperFormat = '80mm';

  @override
  void initState() {
    super.initState();
    _waCtrl.text = widget.penjualan.pembeli?.telepon ?? '';
  }

  @override
  void dispose() {
    _waCtrl.dispose();
    super.dispose();
  }

  PdfPageFormat get _selectedFormat {
    switch (_paperFormat) {
      case '58mm':
        return PdfPageFormat.roll57;
      case 'a4':
        return PdfPageFormat.a4;
      default:
        return PdfPageFormat.roll80;
    }
  }

  Future<void> _cetakNota() async {
    try {
      await NotaPrintService.printReceipt(
        widget.penjualan,
        widget.toko,
        format: _selectedFormat,
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('Gagal mencetak: $e')));
      }
    }
  }

  Future<void> _unduhPdf() async {
    try {
      await NotaPrintService.shareReceipt(
        widget.penjualan,
        widget.toko,
        format: _selectedFormat,
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('Gagal menyimpan PDF: $e')));
      }
    }
  }

  Future<void> _bukaStrukWeb() async {
    try {
      final url = '${ApiConfig.baseUrl}/penjualan/${widget.penjualan.id}/nota';
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    } catch (_) {}
  }

  Future<void> _kirimWa() async {
    final telp = _waCtrl.text.trim();
    if (telp.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Masukkan nomor WhatsApp tujuan')),
      );
      return;
    }

    setState(() => _kirimWaLoading = true);
    try {
      // Coba kirim via server terlebih dahulu
      await KasirService().kirimStrukWa(widget.penjualan.id, telepon: telp);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Struk berhasil dikirim via WhatsApp!')),
        );
      }
    } catch (_) {
      // Fallback: buka aplikasi WhatsApp langsung dengan teks struk
      final cleanTelp = telp.startsWith('0') ? '62${telp.substring(1)}' : telp;
      final teks = Uri.encodeComponent(
        NotaPrintService.formatPlainText(widget.penjualan, widget.toko),
      );
      final waUri = Uri.parse('https://wa.me/$cleanTelp?text=$teks');
      if (await canLaunchUrl(waUri)) {
        await launchUrl(waUri, mode: LaunchMode.externalApplication);
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Tidak dapat membuka WhatsApp.')),
          );
        }
      }
    } finally {
      if (mounted) setState(() => _kirimWaLoading = false);
    }
  }

  void _salinTeks() {
    final teks = NotaPrintService.formatPlainText(
      widget.penjualan,
      widget.toko,
    );
    Clipboard.setData(ClipboardData(text: teks));
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Teks nota disalin ke clipboard!')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.penjualan;
    final toko = widget.toko;
    final namaToko = toko['nama_toko']?.toString().isNotEmpty == true
        ? toko['nama_toko'].toString()
        : 'Kedai Kopi';

    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 440),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(18, 20, 18, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Ikon sukses
              Center(
                child: Container(
                  width: 66,
                  height: 66,
                  decoration: BoxDecoration(
                    color: AppColors.successSoft,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: AppColors.success.withValues(alpha: 0.25),
                      width: 3,
                    ),
                  ),
                  child: const Icon(
                    Icons.check_rounded,
                    color: AppColors.success,
                    size: 34,
                  ),
                ),
              ),
              const SizedBox(height: 12),
              const Center(
                child: Text(
                  'Transaksi Berhasil',
                  style: TextStyle(
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.4,
                    color: AppColors.ink,
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Center(
                child: Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  alignment: WrapAlignment.center,
                  children: [
                    StatusPill(
                      label: p.noNota,
                      color: AppColors.espresso,
                      background: AppColors.sand,
                      icon: Icons.receipt_long_rounded,
                    ),
                    if (p.nomorAntrean != null && p.nomorAntrean!.isNotEmpty)
                      StatusPill(
                        label: 'Antrean #${p.nomorAntrean}',
                        color: AppColors.info,
                        background: AppColors.infoSoft,
                        icon: Icons.confirmation_number_outlined,
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // Ringkasan pembayaran
              Container(
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                decoration: BoxDecoration(
                  color: AppColors.cream,
                  borderRadius: AppRadius.allMedium,
                  border: Border.all(color: AppColors.line),
                ),
                child: Column(
                  children: [
                    _rowInfo(
                      'Total tagihan',
                      Formatters.rupiah(p.total),
                      bold: true,
                    ),
                    if (p.metode == 'tunai') ...[
                      _rowInfo('Bayar', Formatters.rupiah(p.bayar)),
                      _rowInfo(
                        'Kembalian',
                        Formatters.rupiah(p.kembalian),
                        color: AppColors.success,
                      ),
                    ],
                    if (p.poinDidapat > 0)
                      _rowInfo(
                        'Poin didapat',
                        '+${p.poinDidapat} poin',
                        color: AppColors.warning,
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 14),

              // Pratinjau kertas nota
              Container(
                padding: const EdgeInsets.fromLTRB(14, 16, 14, 16),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFFCF6),
                  borderRadius: AppRadius.allSmall,
                  border: Border.all(color: AppColors.line),
                  boxShadow: AppColors.softShadow,
                ),
                child: DefaultTextStyle(
                  style: const TextStyle(
                    fontFamily: 'monospace',
                    color: AppColors.ink,
                    fontSize: 10.5,
                    height: 1.45,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        namaToko.toUpperCase(),
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontFamily: 'monospace',
                          fontWeight: FontWeight.w800,
                          fontSize: 12.5,
                          letterSpacing: 0.6,
                          color: AppColors.ink,
                        ),
                      ),
                      if (toko['alamat']?.toString().isNotEmpty == true)
                        Text(
                          toko['alamat'].toString(),
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            fontFamily: 'monospace',
                            fontSize: 10,
                            color: AppColors.muted,
                          ),
                        ),
                      const _NotaLines(),
                      _notaRow('Nota', p.noNota),
                      _notaRow('Tanggal', Formatters.tanggalWaktu(p.tanggal)),
                      if (p.nomorAntrean != null && p.nomorAntrean!.isNotEmpty)
                        _notaRow('Antrean', '#${p.nomorAntrean}'),
                      _notaRow(
                        'Tipe',
                        p.tipe == 'dine_in'
                            ? 'Makan di tempat${p.meja != null ? " (Meja ${p.meja})" : ""}'
                            : 'Bawa pulang',
                      ),
                      _notaRow('Kasir', p.kasirNama),
                      _notaRow('Pembeli', p.pembeli?.nama ?? 'Umum'),
                      const _NotaLines(),
                      for (final d in p.detail) ...[
                        Text(
                          d.namaProduk,
                          style: const TextStyle(
                            fontFamily: 'monospace',
                            fontWeight: FontWeight.w700,
                            fontSize: 10.5,
                          ),
                        ),
                        for (final o in d.opsi)
                          Text(
                            '  + ${o["nama"] ?? ""}${_formatOpsiHarga(o["harga"])}',
                            style: const TextStyle(
                              fontFamily: 'monospace',
                              fontSize: 9.5,
                              color: AppColors.muted,
                            ),
                          ),
                        _notaRow(
                          '${Formatters.angka(d.qty)} ${d.satuan} x ${Formatters.rupiah(d.hargaJual)}',
                          Formatters.rupiah(d.subtotal),
                        ),
                        const SizedBox(height: 2),
                      ],
                      const _NotaLines(),
                      if (p.diskon > 0 ||
                          p.diskonPoin > 0 ||
                          p.service > 0 ||
                          p.pajak > 0) ...[
                        _notaRow('Subtotal', Formatters.rupiah(p.subtotal)),
                        if (p.diskon > 0)
                          _notaRow(
                            'Diskon ${p.voucherKode ?? ""}',
                            '-${Formatters.rupiah(p.diskon)}',
                          ),
                        if (p.diskonPoin > 0)
                          _notaRow(
                            'Diskon Poin',
                            '-${Formatters.rupiah(p.diskonPoin)}',
                          ),
                        if (p.service > 0)
                          _notaRow('Service', Formatters.rupiah(p.service)),
                        if (p.pajak > 0)
                          _notaRow('Pajak', Formatters.rupiah(p.pajak)),
                      ],
                      _notaRow('TOTAL', Formatters.rupiah(p.total), bold: true),
                      _notaRow('Metode', p.metode.toUpperCase()),
                      if (p.metode == 'tunai') ...[
                        _notaRow('Bayar', Formatters.rupiah(p.bayar)),
                        _notaRow('Kembalian', Formatters.rupiah(p.kembalian)),
                      ],
                      const _NotaLines(),
                      Text(
                        toko['footer_nota']?.toString().isNotEmpty == true
                            ? toko['footer_nota'].toString()
                            : 'Terima kasih, sampai jumpa!',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontFamily: 'monospace',
                          fontSize: 10,
                          color: AppColors.muted,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Kirim struk ke WhatsApp
              const Text(
                'Kirim struk ke WhatsApp',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink,
                ),
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _waCtrl,
                      keyboardType: TextInputType.phone,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                      decoration: const InputDecoration(
                        hintText: '08xxxxxxxxxx',
                        prefixIcon: Icon(Icons.phone_outlined, size: 19),
                        isDense: true,
                        contentPadding: EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 13,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  SizedBox(
                    width: 46,
                    height: 46,
                    child: FilledButton(
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFF25D366),
                        foregroundColor: AppColors.white,
                        minimumSize: const Size(46, 46),
                        padding: EdgeInsets.zero,
                      ),
                      onPressed: _kirimWaLoading ? null : _kirimWa,
                      child: _kirimWaLoading
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: AppColors.white,
                              ),
                            )
                          : const Icon(Icons.send_rounded, size: 19),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),

              // Pilihan Format Kertas / Perangkat Printer Universal
              const Text(
                'FORMAT KERTAS & PERANGKAT PRINTER',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  color: AppColors.muted,
                  letterSpacing: 0.5,
                ),
              ),
              const SizedBox(height: 6),
              SizedBox(
                width: double.infinity,
                child: SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(
                      value: '80mm',
                      label: Text('80 mm (USB/PC)'),
                      icon: Icon(Icons.print_rounded, size: 15),
                    ),
                    ButtonSegment(
                      value: '58mm',
                      label: Text('58 mm (Mini/BT)'),
                      icon: Icon(Icons.bluetooth_rounded, size: 15),
                    ),
                    ButtonSegment(
                      value: 'a4',
                      label: Text('A4 (Dokumen)'),
                      icon: Icon(Icons.description_rounded, size: 15),
                    ),
                  ],
                  selected: {_paperFormat},
                  showSelectedIcon: false,
                  onSelectionChanged: (val) =>
                      setState(() => _paperFormat = val.first),
                ),
              ),
              const SizedBox(height: 14),

              // Tombol tindakan utama
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size(0, 48),
                        padding: const EdgeInsets.symmetric(horizontal: 10),
                      ),
                      onPressed: _unduhPdf,
                      icon: const Icon(Icons.download_rounded, size: 18),
                      label: const Text('Simpan PDF'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    flex: 2,
                    child: FilledButton.icon(
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.espresso,
                        minimumSize: const Size(0, 48),
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                      ),
                      onPressed: _cetakNota,
                      icon: const Icon(Icons.print_rounded, size: 18),
                      label: const Text(
                        'Cetak ke Printer',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  TextButton.icon(
                    onPressed: _salinTeks,
                    icon: const Icon(Icons.copy_rounded, size: 15),
                    label: const Text('Salin Teks Nota'),
                  ),
                  TextButton.icon(
                    onPressed: _bukaStrukWeb,
                    icon: const Icon(Icons.open_in_browser_rounded, size: 15),
                    label: const Text('Buka di Browser'),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              SizedBox(
                width: double.infinity,
                child: TextButton(
                  onPressed: () => Navigator.pop(context),
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.muted,
                  ),
                  child: const Text('Tutup · Kembali ke Meja Kasir'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static Widget _rowInfo(
    String left,
    String right, {
    bool bold = false,
    Color? color,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2.5),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Text(
              left,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: bold ? FontWeight.w700 : FontWeight.w500,
                color: bold ? AppColors.ink : AppColors.muted,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Text(
            right,
            style: TextStyle(
              fontSize: bold ? 14 : 12.5,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
              color: color ?? AppColors.ink,
            ),
          ),
        ],
      ),
    );
  }

  static String _formatOpsiHarga(dynamic val) {
    if (val == null) return '';
    final numVal = val is num ? val : double.tryParse(val.toString()) ?? 0;
    return numVal > 0 ? ' (+${Formatters.rupiah(numVal)})' : '';
  }

  static Widget _notaRow(String left, String right, {bool bold = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Flexible(
          child: Text(
            left,
            style: TextStyle(
              fontFamily: 'monospace',
              fontSize: bold ? 11 : 10.5,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w400,
            ),
          ),
        ),
        const SizedBox(width: 8),
        Flexible(
          child: Text(
            right,
            textAlign: TextAlign.end,
            style: TextStyle(
              fontFamily: 'monospace',
              fontSize: bold ? 11 : 10.5,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w400,
            ),
          ),
        ),
      ],
    );
  }
}

/// Garis putus-putus pemisah pada pratinjau nota.
class _NotaLines extends StatelessWidget {
  const _NotaLines();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: List.generate(
          44,
          (i) => const Expanded(
            child: Padding(
              padding: EdgeInsets.only(right: 2.5),
              child: SizedBox(
                height: 1,
                child: ColoredBox(color: Color(0xFFD9CCBC)),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
