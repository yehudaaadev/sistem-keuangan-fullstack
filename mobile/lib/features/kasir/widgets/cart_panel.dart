import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_widgets.dart';
import '../../../models/cart_item_model.dart';
import '../../../models/pembeli_model.dart';
import '../../../utils/formatters.dart';

class CartPanel extends StatefulWidget {
  final List<CartItemModel> items;
  final void Function(int index, double delta) onUpdateQty;
  final void Function(int index) onRemoveItem;
  final VoidCallback onClearCart;
  final String tipe;
  final void Function(String) onTipeChanged;
  final String meja;
  final void Function(String) onMejaChanged;
  final PembeliModel? pembeliTerpilih;
  final List<PembeliModel> pembeliList;
  final void Function(PembeliModel?) onPembeliChanged;
  final VoidCallback onTambahPembeli;
  final bool pakaiPoin;
  final void Function(bool) onTogglePakaiPoin;
  final int poinDigunakan;
  final double diskonPoin;
  final String? voucherKode;
  final double diskonVoucher;
  final Future<void> Function(String) onApplyVoucher;
  final VoidCallback onRemoveVoucher;
  final double servicePersen;
  final double pajakPersen;
  final VoidCallback onCheckout;
  final bool isModal;

  const CartPanel({
    super.key,
    required this.items,
    required this.onUpdateQty,
    required this.onRemoveItem,
    required this.onClearCart,
    required this.tipe,
    required this.onTipeChanged,
    required this.meja,
    required this.onMejaChanged,
    required this.pembeliTerpilih,
    required this.pembeliList,
    required this.onPembeliChanged,
    required this.onTambahPembeli,
    required this.pakaiPoin,
    required this.onTogglePakaiPoin,
    required this.poinDigunakan,
    required this.diskonPoin,
    required this.voucherKode,
    required this.diskonVoucher,
    required this.onApplyVoucher,
    required this.onRemoveVoucher,
    required this.servicePersen,
    required this.pajakPersen,
    required this.onCheckout,
    this.isModal = false,
  });

  @override
  State<CartPanel> createState() => _CartPanelState();
}

class _CartPanelState extends State<CartPanel> {
  final _voucherCtrl = TextEditingController();
  final _mejaCtrl = TextEditingController();
  bool _voucherLoading = false;
  String? _voucherError;

  @override
  void initState() {
    super.initState();
    _mejaCtrl.text = widget.meja;
  }

  @override
  void didUpdateWidget(covariant CartPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.meja != widget.meja) {
      _mejaCtrl.text = widget.meja;
    }
  }

  @override
  void dispose() {
    _voucherCtrl.dispose();
    _mejaCtrl.dispose();
    super.dispose();
  }

  double get _subtotal {
    return widget.items.fold(0.0, (sum, it) => sum + it.subtotal);
  }

  double get _dasar =>
      (_subtotal - widget.diskonVoucher).clamp(0.0, double.infinity);
  double get _service => (_dasar * widget.servicePersen / 100).roundToDouble();
  double get _pajak =>
      ((_dasar + _service) * widget.pajakPersen / 100).roundToDouble();
  double get _totalSebelumPoin => _dasar + _service + _pajak;
  double get _totalAkhir =>
      (_totalSebelumPoin - widget.diskonPoin).clamp(0.0, double.infinity);

  int get _jumlahItem =>
      widget.items.fold(0, (sum, it) => sum + it.qty.round());

  Future<void> _applyVoucher() async {
    final kode = _voucherCtrl.text.trim();
    if (kode.isEmpty) return;

    setState(() {
      _voucherLoading = true;
      _voucherError = null;
    });

    try {
      await widget.onApplyVoucher(kode);
      _voucherCtrl.clear();
    } catch (e) {
      setState(() => _voucherError = e.toString());
    } finally {
      if (mounted) setState(() => _voucherLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppColors.white,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (widget.isModal) const SheetHandle(),

          // Header Struk Pesanan
          _buildHeader(),
          const Divider(height: 1, color: AppColors.line),

          // Tipe Pesanan & Pelanggan
          _buildPengaturan(),
          const Divider(height: 1, color: AppColors.line),

          // Daftar Item Pesanan
          Expanded(
            child: widget.items.isEmpty
                ? _buildEmptyCart()
                : ListView.builder(
                    padding: const EdgeInsets.symmetric(
                      vertical: 6,
                      horizontal: 12,
                    ),
                    itemCount: widget.items.length,
                    itemBuilder: (context, i) => _itemRow(i),
                  ),
          ),
          const Divider(height: 1, color: AppColors.line),

          // Ringkasan Pembayaran & Tombol Bayar
          _buildRingkasan(),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 12, 10, 10),
      child: Row(
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: AppColors.sand,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: AppColors.line),
            ),
            child: const Icon(
              Icons.receipt_long_rounded,
              size: 17,
              color: AppColors.espresso,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    const Text(
                      'Daftar Pesanan',
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.2,
                        color: AppColors.ink,
                      ),
                    ),
                    if (widget.items.isNotEmpty) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.espresso,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          '$_jumlahItem item',
                          style: const TextStyle(
                            color: AppColors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
                Text(
                  widget.items.isEmpty
                      ? 'Belum ada menu yang dipilih'
                      : Formatters.rupiah(_subtotal),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: AppColors.muted,
                  ),
                ),
              ],
            ),
          ),
          if (widget.items.isNotEmpty)
            TextButton.icon(
              style: TextButton.styleFrom(
                foregroundColor: AppColors.danger,
                visualDensity: VisualDensity.compact,
                padding: const EdgeInsets.symmetric(horizontal: 8),
                textStyle: const TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
              onPressed: widget.onClearCart,
              icon: const Icon(Icons.delete_outline_rounded, size: 15),
              label: const Text('Reset'),
            ),
        ],
      ),
    );
  }

  Widget _buildPengaturan() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Segmented Tipe Pesanan: Di Tempat / Bawa Pulang
          SizedBox(
            height: 38,
            child: SegmentedButton<String>(
              segments: const [
                ButtonSegment(
                  value: 'dine_in',
                  label: Text('Makan di Tempat'),
                  icon: Icon(Icons.storefront_rounded, size: 16),
                ),
                ButtonSegment(
                  value: 'bawa_pulang',
                  label: Text('Bawa Pulang'),
                  icon: Icon(Icons.shopping_bag_outlined, size: 16),
                ),
              ],
              selected: {widget.tipe},
              showSelectedIcon: false,
              style: SegmentedButton.styleFrom(
                visualDensity: VisualDensity.compact,
                textStyle: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
              onSelectionChanged: (val) => widget.onTipeChanged(val.first),
            ),
          ),

          // Jika Di Tempat: Baris Meja
          if (widget.tipe == 'dine_in') ...[
            const SizedBox(height: 8),
            TextField(
              controller: _mejaCtrl,
              onChanged: widget.onMejaChanged,
              style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700),
              decoration: const InputDecoration(
                labelText: 'Nomor Meja Pelanggan',
                hintText: 'mis. 04',
                prefixIcon: Icon(Icons.table_restaurant_rounded, size: 18),
                isDense: true,
                contentPadding: EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 10,
                ),
              ),
            ),
          ],

          const SizedBox(height: 8),

          // Baris Pelanggan (Member / Umum)
          Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<int?>(
                  initialValue: widget.pembeliTerpilih?.id,
                  isExpanded: true,
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ink,
                  ),
                  icon: const Icon(Icons.expand_more_rounded, size: 18),
                  decoration: const InputDecoration(
                    labelText: 'Profil Pelanggan',
                    isDense: true,
                    contentPadding: EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 10,
                    ),
                    prefixIcon: Icon(Icons.person_outline_rounded, size: 18),
                  ),
                  items: [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('Pembeli Umum (Non-Member)'),
                    ),
                    ...widget.pembeliList.map((b) {
                      return DropdownMenuItem<int?>(
                        value: b.id,
                        child: Text(
                          '${b.nama} (${b.poin} Poin)',
                          overflow: TextOverflow.ellipsis,
                        ),
                      );
                    }),
                  ],
                  onChanged: (id) {
                    if (id == null) {
                      widget.onPembeliChanged(null);
                    } else {
                      final b = widget.pembeliList.firstWhere(
                        (x) => x.id == id,
                      );
                      widget.onPembeliChanged(b);
                    }
                  },
                ),
              ),
              const SizedBox(width: 8),
              SizedBox(
                width: 42,
                height: 42,
                child: Tooltip(
                  message: 'Daftar Pelanggan Baru',
                  child: OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      padding: EdgeInsets.zero,
                      backgroundColor: AppColors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                        side: const BorderSide(color: AppColors.line),
                      ),
                    ),
                    onPressed: widget.onTambahPembeli,
                    child: const Icon(Icons.person_add_alt_1_rounded, size: 18),
                  ),
                ),
              ),
            ],
          ),

          // Banner Penukaran Poin Member
          if (widget.pembeliTerpilih != null &&
              widget.pembeliTerpilih!.poin > 0) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.fromLTRB(10, 6, 6, 6),
              decoration: BoxDecoration(
                color: AppColors.warningSoft,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: AppColors.warningBorder,
                  width: 1.2,
                ),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.stars_rounded,
                    size: 16,
                    color: AppColors.warning,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      '${widget.pembeliTerpilih!.poin} poin · Potongan ${Formatters.rupiah(widget.pembeliTerpilih!.poin * 1000)}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: AppColors.warning,
                      ),
                    ),
                  ),
                  TextButton(
                    style: TextButton.styleFrom(
                      foregroundColor: widget.pakaiPoin
                          ? AppColors.danger
                          : AppColors.warning,
                      visualDensity: VisualDensity.compact,
                      minimumSize: const Size(0, 30),
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                      textStyle: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    onPressed: () =>
                        widget.onTogglePakaiPoin(!widget.pakaiPoin),
                    child: Text(widget.pakaiPoin ? 'Batalkan' : 'Pakai Poin'),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildEmptyCart() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: AppColors.sand,
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.line),
              ),
              child: const Icon(
                Icons.shopping_bag_outlined,
                size: 26,
                color: AppColors.espresso,
              ),
            ),
            const SizedBox(height: 14),
            const Text(
              'Meja Kasir Siap',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Ketuk menu kopi, makanan, atau minuman di sebelah kiri untuk memasukkan pesanan pembeli.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 11.5,
                height: 1.4,
                color: AppColors.muted,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _itemRow(int i) {
    final it = widget.items[i];

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Container(
        padding: const EdgeInsets.fromLTRB(10, 8, 8, 8),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: AppColors.line, width: 1.2),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    it.produk.nama,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12.5,
                      height: 1.25,
                      fontWeight: FontWeight.w800,
                      color: AppColors.ink,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  Formatters.rupiah(it.subtotal),
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.2,
                    color: AppColors.espresso,
                  ),
                ),
              ],
            ),
            if (it.labelOpsi.isNotEmpty) ...[
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: AppColors.sand,
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  it.labelOpsi,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    color: AppColors.latte,
                  ),
                ),
              ),
            ],
            const SizedBox(height: 6),
            Row(
              children: [
                Text(
                  '${Formatters.rupiah(it.hargaSatuan)} / ${it.produk.satuan}',
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                    color: AppColors.muted,
                  ),
                ),
                const Spacer(),
                QtyStepper(
                  qty: it.qty,
                  onMinus: () => widget.onUpdateQty(i, -1),
                  onPlus: () => widget.onUpdateQty(i, 1),
                ),
                const SizedBox(width: 6),
                SizedBox(
                  width: 28,
                  height: 28,
                  child: IconButton(
                    tooltip: 'Hapus item',
                    padding: EdgeInsets.zero,
                    visualDensity: VisualDensity.compact,
                    iconSize: 16,
                    color: AppColors.muted,
                    onPressed: () => widget.onRemoveItem(i),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRingkasan() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          // Kupon Voucher Diskon
          if (widget.voucherKode == null)
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: TextField(
                    controller: _voucherCtrl,
                    textCapitalization: TextCapitalization.characters,
                    onSubmitted: (_) => _applyVoucher(),
                    style: const TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                    ),
                    decoration: InputDecoration(
                      labelText: 'Kode Voucher',
                      hintText: 'mis. DISKON10',
                      prefixIcon: const Icon(
                        Icons.confirmation_number_outlined,
                        size: 18,
                      ),
                      isDense: true,
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 10,
                      ),
                      errorText: _voucherError,
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                SizedBox(
                  height: 38,
                  child: FilledButton.tonal(
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.sand,
                      foregroundColor: AppColors.espresso,
                      minimumSize: const Size(0, 38),
                      padding: const EdgeInsets.symmetric(horizontal: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                    onPressed: _voucherLoading ? null : _applyVoucher,
                    child: _voucherLoading
                        ? const SizedBox(
                            width: 14,
                            height: 14,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Pakai'),
                  ),
                ),
              ],
            )
          else
            Container(
              padding: const EdgeInsets.fromLTRB(10, 6, 6, 6),
              decoration: BoxDecoration(
                color: AppColors.successSoft,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: AppColors.successBorder,
                  width: 1.2,
                ),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.confirmation_number_rounded,
                    size: 16,
                    color: AppColors.success,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      'Voucher ${widget.voucherKode}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                        color: AppColors.success,
                      ),
                    ),
                  ),
                  TextButton(
                    style: TextButton.styleFrom(
                      foregroundColor: AppColors.danger,
                      visualDensity: VisualDensity.compact,
                      minimumSize: const Size(0, 30),
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                      textStyle: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    onPressed: widget.onRemoveVoucher,
                    child: const Text('Lepas'),
                  ),
                ],
              ),
            ),

          const SizedBox(height: 8),

          // Kotak Rekapitulasi Rincian Tagihan
          Container(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
            decoration: BoxDecoration(
              color: AppColors.cream,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: AppColors.line, width: 1.2),
            ),
            child: Column(
              children: [
                SummaryRow(
                  label: 'Subtotal Menu',
                  value: Formatters.rupiah(_subtotal),
                ),
                if (widget.diskonVoucher > 0)
                  SummaryRow(
                    label: 'Potongan Voucher',
                    value: '-${Formatters.rupiah(widget.diskonVoucher)}',
                    discount: true,
                  ),
                if (widget.diskonPoin > 0)
                  SummaryRow(
                    label: 'Tukar Poin (${widget.poinDigunakan} pt)',
                    value: '-${Formatters.rupiah(widget.diskonPoin)}',
                    discount: true,
                  ),
                if (_service > 0)
                  SummaryRow(
                    label:
                        'Biaya Layanan (${Formatters.angka(widget.servicePersen)}%)',
                    value: Formatters.rupiah(_service),
                  ),
                if (_pajak > 0)
                  SummaryRow(
                    label: 'Pajak Resto (${Formatters.angka(widget.pajakPersen)}%)',
                    value: Formatters.rupiah(_pajak),
                  ),
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 5),
                  child: Divider(height: 1, color: AppColors.line),
                ),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.baseline,
                  textBaseline: TextBaseline.alphabetic,
                  children: [
                    const Expanded(
                      child: Text(
                        'Total Tagihan',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: AppColors.ink,
                        ),
                      ),
                    ),
                    Text(
                      Formatters.rupiah(_totalAkhir),
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.4,
                        color: AppColors.espresso,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          const SizedBox(height: 8),

          // Tombol Utama: Bayar Transaksi
          _tombolBayar(),
        ],
      ),
    );
  }

  Widget _tombolBayar() {
    final aktif = widget.items.isNotEmpty;

    return Opacity(
      opacity: aktif ? 1 : 0.5,
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.espresso,
          borderRadius: BorderRadius.circular(8),
          boxShadow: AppColors.softShadow,
        ),
        child: Material(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(8),
          child: InkWell(
            borderRadius: BorderRadius.circular(8),
            onTap: aktif ? widget.onCheckout : null,
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
              child: Row(
                children: [
                  const Icon(
                    Icons.payments_rounded,
                    color: AppColors.white,
                    size: 19,
                  ),
                  const SizedBox(width: 8),
                  const Text(
                    'Bayar Pesanan',
                    style: TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w900,
                      color: AppColors.white,
                      letterSpacing: -0.1,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    Formatters.rupiah(_totalAkhir),
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w900,
                      color: AppColors.white,
                    ),
                  ),
                  const SizedBox(width: 6),
                  const Icon(
                    Icons.arrow_forward_rounded,
                    color: AppColors.white,
                    size: 16,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
