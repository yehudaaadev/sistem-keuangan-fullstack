import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/app_widgets.dart';
import '../../models/penjualan_model.dart';
import '../../services/kasir_service.dart';
import '../../utils/formatters.dart';
import 'widgets/receipt_dialog.dart';

class RiwayatPenjualanScreen extends StatefulWidget {
  final Map<String, dynamic> toko;

  const RiwayatPenjualanScreen({super.key, required this.toko});

  @override
  State<RiwayatPenjualanScreen> createState() => _RiwayatPenjualanScreenState();
}

class _RiwayatPenjualanScreenState extends State<RiwayatPenjualanScreen> {
  final _searchCtrl = TextEditingController();
  List<PenjualanModel> _items = [];
  bool _loading = false;
  String? _error;
  int _page = 1;
  int _lastPage = 1;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadData({int page = 1}) async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await KasirService().getRiwayatPenjualan(
        page: page,
        search: _searchCtrl.text.trim(),
      );
      setState(() {
        _items = res['items'] as List<PenjualanModel>;
        _page = res['current_page'] as int;
        _lastPage = res['last_page'] as int;
      });
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _dialogVoid(PenjualanModel p) async {
    final alasanCtrl = TextEditingController();
    bool busy = false;
    String? err;

    final sukses = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setState) {
            return AlertDialog(
              title: Text('Void Nota ${p.noNota}'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const InfoBanner(
                    message: 'Stok menu akan dikembalikan dan nota dikeluarkan dari laporan. Tindakan ini tidak dapat dibatalkan.',
                    tone: BannerTone.danger,
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    controller: alasanCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Alasan void *',
                      prefixIcon: Icon(Icons.edit_note_rounded, size: 20),
                    ),
                  ),
                  if (err != null) ...[
                    const SizedBox(height: 10),
                    InfoBanner(message: err!, tone: BannerTone.danger),
                  ],
                ],
              ),
              actions: [
                TextButton(
                  onPressed: busy ? null : () => Navigator.pop(ctx, false),
                  child: const Text('Batal'),
                ),
                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.danger,
                    foregroundColor: AppColors.white,
                  ),
                  onPressed: busy
                      ? null
                      : () async {
                          final alasan = alasanCtrl.text.trim();
                          if (alasan.isEmpty) {
                            setState(() => err = 'Alasan void wajib diisi.');
                            return;
                          }
                          setState(() {
                            busy = true;
                            err = null;
                          });
                          try {
                            await KasirService().voidPenjualan(
                              penjualanId: p.id,
                              alasan: alasan,
                            );
                            if (ctx.mounted) Navigator.pop(ctx, true);
                          } catch (e) {
                            if (ctx.mounted) {
                              setState(() {
                                busy = false;
                                err = e.toString();
                              });
                            }
                          }
                        },
                  child: Text(busy ? 'Memproses...' : 'Void Nota'),
                ),
              ],
            );
          },
        );
      },
    );

    if (sukses == true) {
      _loadData(page: _page);
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Nota berhasil di-void.')));
      }
    }
  }

  void _lihatDetail(PenjualanModel p) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(ctx).size.height * 0.9,
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SheetHandle(),
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            p.noNota,
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.3,
                              color: AppColors.ink,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '${Formatters.tanggalWaktu(p.tanggal)} · ${p.kasirNama}',
                            style: const TextStyle(
                              fontSize: 11.5,
                              color: AppColors.muted,
                            ),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      tooltip: 'Tutup',
                      onPressed: () => Navigator.pop(ctx),
                      style: IconButton.styleFrom(
                        backgroundColor: AppColors.cream,
                        foregroundColor: AppColors.muted,
                        minimumSize: const Size(36, 36),
                      ),
                      icon: const Icon(Icons.close_rounded, size: 18),
                    ),
                  ],
                ),
                if (p.isVoid) ...[
                  const SizedBox(height: 8),
                  InfoBanner(
                    message:
                        'Nota DIBATALKAN (void)\nAlasan: ${p.voidAlasan ?? "-"}',
                    tone: BannerTone.danger,
                    icon: Icons.block_rounded,
                  ),
                ],
                const SizedBox(height: 12),
                Expanded(
                  child: ListView(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.white,
                          borderRadius: AppRadius.allMedium,
                          border: Border.all(color: AppColors.line),
                        ),
                        child: Column(
                          children: [
                            for (final d in p.detail) ...[
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Container(
                                    width: 30,
                                    height: 30,
                                    decoration: BoxDecoration(
                                      color: AppColors.cream,
                                      borderRadius: BorderRadius.circular(9),
                                    ),
                                    child: const Icon(
                                      Icons.coffee_rounded,
                                      size: 15,
                                      color: AppColors.latte,
                                    ),
                                  ),
                                  const SizedBox(width: 9),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          d.namaProduk,
                                          style: const TextStyle(
                                            fontSize: 12.5,
                                            fontWeight: FontWeight.w700,
                                            color: AppColors.ink,
                                          ),
                                        ),
                                        for (final o in d.opsi)
                                          Text(
                                            '+ ${o["nama"]}',
                                            style: const TextStyle(
                                              fontSize: 10.5,
                                              fontWeight: FontWeight.w600,
                                              color: AppColors.latte,
                                            ),
                                          ),
                                        Text(
                                          '${Formatters.angka(d.qty)} ${d.satuan} x ${Formatters.rupiah(d.hargaJual)}',
                                          style: const TextStyle(
                                            fontSize: 10.5,
                                            color: AppColors.muted,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  Text(
                                    Formatters.rupiah(d.subtotal),
                                    style: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.ink,
                                    ),
                                  ),
                                ],
                              ),
                              if (d != p.detail.last) const Divider(height: 18),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.cream,
                          borderRadius: AppRadius.allMedium,
                          border: Border.all(color: AppColors.line),
                        ),
                        child: Column(
                          children: [
                            SummaryRow(
                              label: 'Subtotal',
                              value: Formatters.rupiah(p.subtotal),
                            ),
                            if (p.diskon > 0)
                              SummaryRow(
                                label: 'Diskon ${p.voucherKode ?? ""}',
                                value: '-${Formatters.rupiah(p.diskon)}',
                                discount: true,
                              ),
                            if (p.diskonPoin > 0)
                              SummaryRow(
                                label: 'Diskon poin',
                                value: '-${Formatters.rupiah(p.diskonPoin)}',
                                discount: true,
                              ),
                            if (p.service > 0)
                              SummaryRow(
                                label: 'Service',
                                value: Formatters.rupiah(p.service),
                              ),
                            if (p.pajak > 0)
                              SummaryRow(
                                label: 'Pajak',
                                value: Formatters.rupiah(p.pajak),
                              ),
                            const Padding(
                              padding: EdgeInsets.symmetric(vertical: 7),
                              child: Divider(height: 1),
                            ),
                            SummaryRow(
                              label: 'Total tagihan',
                              value: Formatters.rupiah(p.total),
                              bold: true,
                            ),
                            SummaryRow(
                              label: 'Metode bayar',
                              value: p.metode.toUpperCase(),
                            ),
                            if (p.metode == 'tunai') ...[
                              SummaryRow(
                                label: 'Bayar',
                                value: Formatters.rupiah(p.bayar),
                              ),
                              SummaryRow(
                                label: 'Kembalian',
                                value: Formatters.rupiah(p.kembalian),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    if (!p.isVoid) ...[
                      Expanded(
                        child: OutlinedButton.icon(
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppColors.danger,
                            minimumSize: const Size(0, 46),
                            padding: const EdgeInsets.symmetric(horizontal: 10),
                          ),
                          onPressed: () {
                            Navigator.pop(ctx);
                            _dialogVoid(p);
                          },
                          icon: const Icon(Icons.block_rounded, size: 17),
                          label: const Text('Void'),
                        ),
                      ),
                      const SizedBox(width: 8),
                    ],
                    Expanded(
                      flex: 2,
                      child: FilledButton.icon(
                        style: FilledButton.styleFrom(
                          minimumSize: const Size(0, 46),
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                        ),
                        onPressed: () {
                          Navigator.pop(ctx);
                          ReceiptDialog.show(
                            context: context,
                            penjualan: p,
                            toko: widget.toko,
                          );
                        },
                        icon: const Icon(Icons.print_rounded, size: 17),
                        label: const Text('Cetak Nota'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Riwayat Penjualan'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: CircleActionButton(
              tooltip: 'Muat ulang',
              icon: Icons.refresh_rounded,
              onPressed: () => _loadData(page: 1),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 4, 14, 8),
            child: TextField(
              controller: _searchCtrl,
              style: const TextStyle(
                fontSize: 13.5,
                fontWeight: FontWeight.w600,
              ),
              decoration: InputDecoration(
                hintText: 'Cari no. nota atau pembeli...',
                prefixIcon: const Icon(Icons.search_rounded, size: 20),
                suffixIcon: _searchCtrl.text.isNotEmpty
                    ? IconButton(
                        tooltip: 'Bersihkan',
                        icon: const Icon(Icons.close_rounded, size: 18),
                        onPressed: () {
                          _searchCtrl.clear();
                          _loadData(page: 1);
                        },
                      )
                    : null,
                isDense: true,
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 13,
                ),
              ),
              onSubmitted: (_) => _loadData(page: 1),
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(
                    child: SizedBox(
                      width: 26,
                      height: 26,
                      child: CircularProgressIndicator(strokeWidth: 2.6),
                    ),
                  )
                : _error != null
                ? Center(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.all(20),
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 400),
                        child: SurfaceCard(
                          padding: const EdgeInsets.all(20),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const InfoBanner(
                                message: 'Gagal memuat riwayat penjualan.',
                                tone: BannerTone.danger,
                              ),
                              const SizedBox(height: 10),
                              Text(
                                _error!,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: AppColors.muted,
                                ),
                              ),
                              const SizedBox(height: 14),
                              OutlinedButton.icon(
                                onPressed: () => _loadData(page: _page),
                                icon: const Icon(
                                  Icons.refresh_rounded,
                                  size: 18,
                                ),
                                label: const Text('Coba Lagi'),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  )
                : _items.isEmpty
                ? const EmptyState(
                    icon: Icons.receipt_long_rounded,
                    title: 'Belum ada transaksi',
                    message: 'Nota penjualan yang selesai akan muncul di sini.',
                  )
                : RefreshIndicator(
                    onRefresh: () => _loadData(page: 1),
                    child: ListView.separated(
                      padding: const EdgeInsets.fromLTRB(14, 2, 14, 16),
                      itemCount: _items.length,
                      separatorBuilder: (ctx, idx) => const SizedBox(height: 8),
                      itemBuilder: (context, i) => _itemCard(_items[i]),
                    ),
                  ),
          ),
          if (_lastPage > 1) _buildPaging(),
        ],
      ),
    );
  }

  Widget _itemCard(PenjualanModel p) {
    final voided = p.isVoid;

    return SurfaceCard(
      padding: const EdgeInsets.all(11),
      onTap: () => _lihatDetail(p),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: voided ? AppColors.sand : AppColors.cream,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              voided ? Icons.block_rounded : Icons.receipt_long_rounded,
              size: 19,
              color: voided ? AppColors.danger : AppColors.coffee,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    Flexible(
                      child: Text(
                        p.noNota,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.2,
                          color: voided ? AppColors.muted : AppColors.ink,
                          decoration: voided
                              ? TextDecoration.lineThrough
                              : null,
                        ),
                      ),
                    ),
                    if (p.nomorAntrean != null &&
                        p.nomorAntrean!.isNotEmpty) ...[
                      const SizedBox(width: 6),
                      StatusPill(
                        label: '#${p.nomorAntrean}',
                        color: AppColors.info,
                        background: AppColors.infoSoft,
                      ),
                    ],
                    if (voided) ...[
                      const SizedBox(width: 6),
                      const StatusPill(
                        label: 'VOID',
                        color: AppColors.danger,
                        background: AppColors.dangerSoft,
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  '${Formatters.tanggalWaktu(p.tanggal)} · ${p.pembeli?.nama ?? "Umum"}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 11, color: AppColors.muted),
                ),
                const SizedBox(height: 1),
                Text(
                  '${p.metode.toUpperCase()} · ${p.detail.length} menu',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                    color: AppColors.muted,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                Formatters.rupiah(p.total),
                style: TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.3,
                  color: voided ? AppColors.muted : AppColors.espresso,
                ),
              ),
              const SizedBox(height: 2),
              const Icon(
                Icons.chevron_right_rounded,
                size: 17,
                color: AppColors.muted,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPaging() {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 8, 14, 10),
      decoration: const BoxDecoration(
        color: AppColors.white,
        border: Border(top: BorderSide(color: AppColors.line)),
      ),
      child: SafeArea(
        top: false,
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(0, 42),
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  textStyle: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                onPressed: _page > 1 ? () => _loadData(page: _page - 1) : null,
                icon: const Icon(Icons.chevron_left_rounded, size: 18),
                label: const Text('Sebelumnya'),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              child: Text(
                '$_page / $_lastPage',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  color: AppColors.ink,
                ),
              ),
            ),
            Expanded(
              child: OutlinedButton(
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(0, 42),
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  textStyle: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                onPressed: _page < _lastPage
                    ? () => _loadData(page: _page + 1)
                    : null,
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text('Berikutnya'),
                    SizedBox(width: 4),
                    Icon(Icons.chevron_right_rounded, size: 18),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
