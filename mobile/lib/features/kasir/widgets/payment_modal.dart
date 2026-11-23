import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_widgets.dart';
import '../../../models/penjualan_model.dart';
import '../../../utils/formatters.dart';

class PaymentModal extends StatefulWidget {
  final double total;
  final Future<PenjualanModel> Function({
    required String metode,
    required double bayar,
  })
  onBayar;

  const PaymentModal({super.key, required this.total, required this.onBayar});

  static Future<PenjualanModel?> show({
    required BuildContext context,
    required double total,
    required Future<PenjualanModel> Function({
      required String metode,
      required double bayar,
    })
    onBayar,
  }) {
    return showModalBottomSheet<PenjualanModel>(
      context: context,
      isScrollControlled: true,
      builder: (_) => PaymentModal(total: total, onBayar: onBayar),
    );
  }

  @override
  State<PaymentModal> createState() => _PaymentModalState();
}

class _PaymentModalState extends State<PaymentModal> {
  String _metode = 'tunai';
  final _bayarCtrl = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _bayarCtrl.text = widget.total.toInt().toString();
  }

  @override
  void dispose() {
    _bayarCtrl.dispose();
    super.dispose();
  }

  double get _bayarNominal {
    if (_metode != 'tunai') return widget.total;
    return double.tryParse(_bayarCtrl.text) ?? 0.0;
  }

  double get _kembalian => _bayarNominal - widget.total;
  bool get _bisaBayar => _bayarNominal >= widget.total;

  List<double> get _pilihanCepat {
    final t = widget.total;
    final Set<double> set = {t};

    // Pembulatan ke atas terdekat
    if (t % 10000 != 0) {
      set.add(((t / 10000).ceil() * 10000).toDouble());
    }
    if (t % 50000 != 0 && t < 50000) set.add(50000);
    if (t < 100000) set.add(100000);
    if (t > 50000 && t < 100000) set.add(100000);
    if (t > 100000 && t < 200000) set.add(200000);

    final list = set.where((x) => x >= t).toList()..sort();
    return list;
  }

  Future<void> _submit() async {
    if (!_bisaBayar) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await widget.onBayar(metode: _metode, bayar: _bayarNominal);
      if (mounted) Navigator.pop(context, res);
    } catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = e.toString();
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.92,
        ),
        child: SafeArea(
          top: false,
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SheetHandle(),
                Row(
                  children: [
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Pembayaran',
                            style: TextStyle(
                              fontSize: 19,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.4,
                              color: AppColors.ink,
                            ),
                          ),
                          SizedBox(height: 2),
                          Text(
                            'Pilih metode lalu konfirmasi transaksi.',
                            style: TextStyle(
                              fontSize: 12,
                              color: AppColors.muted,
                            ),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      tooltip: 'Tutup',
                      onPressed: () => Navigator.pop(context),
                      style: IconButton.styleFrom(
                        backgroundColor: AppColors.cream,
                        foregroundColor: AppColors.muted,
                        minimumSize: const Size(36, 36),
                      ),
                      icon: const Icon(Icons.close_rounded, size: 18),
                    ),
                  ],
                ),
                const SizedBox(height: 14),

                // Total tagihan
                Container(
                  padding: const EdgeInsets.fromLTRB(18, 16, 16, 16),
                  decoration: const BoxDecoration(
                    gradient: AppColors.brandGradient,
                    borderRadius: BorderRadius.all(
                      Radius.circular(AppRadius.large),
                    ),
                    boxShadow: AppColors.softShadow,
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'TOTAL TAGIHAN',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 1.1,
                                color: AppColors.white.withValues(alpha: 0.7),
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              Formatters.rupiah(widget.total),
                              style: const TextStyle(
                                fontSize: 27,
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.8,
                                color: AppColors.white,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(
                          color: AppColors.white.withValues(alpha: 0.14),
                          borderRadius: BorderRadius.circular(13),
                        ),
                        child: const Icon(
                          Icons.receipt_long_rounded,
                          color: AppColors.white,
                          size: 21,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),

                const Text(
                  'Metode pembayaran',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ink,
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: _metodeTile(
                        'tunai',
                        'Tunai',
                        Icons.payments_rounded,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _metodeTile('qris', 'QRIS', Icons.qr_code_rounded),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: _metodeTile(
                        'kartu',
                        'Kartu',
                        Icons.credit_card_rounded,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _metodeTile(
                        'transfer',
                        'Transfer',
                        Icons.account_balance_rounded,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                if (_metode == 'tunai')
                  ..._buildTunai()
                else
                  ..._buildNonTunai(),

                if (_error != null) ...[
                  const SizedBox(height: 12),
                  InfoBanner(message: _error!, tone: BannerTone.danger),
                ],

                const SizedBox(height: 18),
                SizedBox(
                  height: 52,
                  child: FilledButton(
                    onPressed: (_loading || !_bisaBayar) ? null : _submit,
                    child: _loading
                        ? const Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.2,
                                  color: AppColors.white,
                                ),
                              ),
                              SizedBox(width: 10),
                              Text('Memproses...'),
                            ],
                          )
                        : const Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.check_circle_rounded, size: 19),
                              SizedBox(width: 8),
                              Text('Bayar & Selesaikan'),
                            ],
                          ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _metodeTile(String value, String label, IconData icon) {
    final selected = _metode == value;

    return Material(
      color: selected ? AppColors.surfaceSubtle : AppColors.white,
      shape: RoundedRectangleBorder(
        borderRadius: AppRadius.allMedium,
        side: BorderSide(
          color: selected ? AppColors.espresso : AppColors.line,
          width: selected ? 1.6 : 1.0,
        ),
      ),
      child: InkWell(
        borderRadius: AppRadius.allMedium,
        onTap: () {
          setState(() {
            _metode = value;
            if (_metode == 'tunai') {
              _bayarCtrl.text = widget.total.toInt().toString();
            }
          });
        },
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 13),
          child: Row(
            children: [
              Icon(
                icon,
                size: 20,
                color: selected ? AppColors.espresso : AppColors.muted,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                    color: selected ? AppColors.espresso : AppColors.ink,
                  ),
                ),
              ),
              if (selected)
                const Icon(
                  Icons.check_circle_rounded,
                  size: 16,
                  color: AppColors.espresso,
                ),
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> _buildTunai() {
    return [
      TextField(
        controller: _bayarCtrl,
        keyboardType: TextInputType.number,
        autofocus: true,
        onChanged: (_) => setState(() {}),
        style: const TextStyle(
          fontSize: 20,
          fontWeight: FontWeight.w800,
          letterSpacing: -0.4,
          color: AppColors.ink,
        ),
        decoration: const InputDecoration(
          labelText: 'Uang diterima',
          prefixText: 'Rp  ',
          prefixStyle: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            color: AppColors.muted,
          ),
        ),
      ),
      const SizedBox(height: 10),
      Wrap(
        spacing: 8,
        runSpacing: 8,
        children: _pilihanCepat.map((nominal) {
          final isPas = nominal == widget.total;
          final selected = _bayarNominal == nominal;
          return ChoiceChip(
            label: Text(isPas ? 'Uang pas' : Formatters.rupiah(nominal)),
            selected: selected,
            onSelected: (_) {
              setState(() {
                _bayarCtrl.text = nominal.toInt().toString();
              });
            },
          );
        }).toList(),
      ),
      const SizedBox(height: 12),
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: _kembalian < 0 ? AppColors.dangerSoft : AppColors.successSoft,
          borderRadius: AppRadius.allMedium,
          border: Border.all(
            color: _kembalian < 0
                ? AppColors.dangerBorder
                : AppColors.successBorder,
            width: 1.2,
          ),
        ),
        child: Row(
          children: [
            Icon(
              _kembalian < 0
                  ? Icons.error_outline_rounded
                  : Icons.savings_rounded,
              size: 19,
              color: _kembalian < 0 ? AppColors.danger : AppColors.success,
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Text(
                _kembalian < 0 ? 'Uang masih kurang' : 'Kembalian',
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: _kembalian < 0 ? AppColors.danger : AppColors.success,
                ),
              ),
            ),
            Text(
              Formatters.rupiah(_kembalian.abs()),
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.3,
                color: _kembalian < 0 ? AppColors.danger : AppColors.success,
              ),
            ),
          ],
        ),
      ),
    ];
  }

  List<Widget> _buildNonTunai() {
    return [
      InfoBanner(
        message:
            'Pastikan pembayaran via ${_metode.toUpperCase()} sudah berhasil masuk sebelum menekan tombol konfirmasi.',
        tone: BannerTone.info,
      ),
    ];
  }
}
