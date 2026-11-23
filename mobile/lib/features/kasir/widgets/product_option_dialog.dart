import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_widgets.dart';
import '../../../models/cart_item_model.dart';
import '../../../models/produk_model.dart';
import '../../../utils/formatters.dart';

class ProductOptionDialog extends StatefulWidget {
  final ProdukModel produk;

  const ProductOptionDialog({super.key, required this.produk});

  static Future<CartItemModel?> show(BuildContext context, ProdukModel produk) {
    return showModalBottomSheet<CartItemModel>(
      context: context,
      isScrollControlled: true,
      builder: (_) => ProductOptionDialog(produk: produk),
    );
  }

  @override
  State<ProductOptionDialog> createState() => _ProductOptionDialogState();
}

class _ProductOptionDialogState extends State<ProductOptionDialog> {
  final Map<int, PilihanOpsiModel?> _selectedRadio = {};
  final Map<int, Set<PilihanOpsiModel>> _selectedCheckbox = {};
  double _qty = 1.0;

  @override
  void initState() {
    super.initState();
    // Inisialisasi pilihan awal
    for (final grup in widget.produk.opsiGrup) {
      if (grup.tipe == 'radio') {
        if (grup.pilihan.isNotEmpty) {
          _selectedRadio[grup.id] = grup.pilihan.first;
        }
      } else {
        _selectedCheckbox[grup.id] = {};
      }
    }
  }

  List<PilihanOpsiModel> get _allSelected {
    final list = <PilihanOpsiModel>[];
    for (final opt in _selectedRadio.values) {
      if (opt != null) list.add(opt);
    }
    for (final set in _selectedCheckbox.values) {
      list.addAll(set);
    }
    return list;
  }

  double get _hargaSatuan {
    final extra = _allSelected.fold(0.0, (sum, o) => sum + o.tambahanHarga);
    return widget.produk.hargaJual + extra;
  }

  double get _totalHarga => _hargaSatuan * _qty;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.9,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SheetHandle(),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 6, 12, 14),
              child: Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: AppColors.sand,
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: const Icon(
                      Icons.local_cafe_rounded,
                      size: 22,
                      color: AppColors.coffee,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          widget.produk.nama,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 17,
                            height: 1.25,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.3,
                            color: AppColors.ink,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Harga dasar ${Formatters.rupiah(widget.produk.hargaJual)} / ${widget.produk.satuan}',
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
            ),
            const Divider(height: 1),

            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
                children: [
                  for (final grup in widget.produk.opsiGrup) ...[
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            grup.nama,
                            style: const TextStyle(
                              fontSize: 13.5,
                              fontWeight: FontWeight.w800,
                              color: AppColors.ink,
                            ),
                          ),
                        ),
                        if (grup.wajib)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.dangerSoft,
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: const Text(
                              'Wajib',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                color: AppColors.danger,
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Material(
                      color: AppColors.white,
                      shape: const RoundedRectangleBorder(
                        borderRadius: BorderRadius.all(
                          Radius.circular(AppRadius.medium),
                        ),
                        side: BorderSide(color: AppColors.line),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: grup.tipe == 'radio'
                          ? RadioGroup<int>(
                              groupValue: _selectedRadio[grup.id]?.id,
                              onChanged: (val) {
                                final found = grup.pilihan
                                    .where((x) => x.id == val)
                                    .firstOrNull;
                                if (found != null) {
                                  setState(
                                    () => _selectedRadio[grup.id] = found,
                                  );
                                }
                              },
                              child: Column(
                                children: [
                                  for (
                                    var i = 0;
                                    i < grup.pilihan.length;
                                    i++
                                  ) ...[
                                    if (i > 0) const Divider(height: 1),
                                    _radioTile(grup, grup.pilihan[i]),
                                  ],
                                ],
                              ),
                            )
                          : Column(
                              children: [
                                for (
                                  var i = 0;
                                  i < grup.pilihan.length;
                                  i++
                                ) ...[
                                  if (i > 0) const Divider(height: 1),
                                  _checkboxTile(grup, grup.pilihan[i]),
                                ],
                              ],
                            ),
                    ),
                    const SizedBox(height: 16),
                  ],
                ],
              ),
            ),

            const Divider(height: 1),
            _buildBottomBar(),
          ],
        ),
      ),
    );
  }

  Widget _radioTile(OpsiGrupModel grup, PilihanOpsiModel p) {
    final isSelected = _selectedRadio[grup.id]?.id == p.id;

    return RadioListTile<int>(
      value: p.id,
      dense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      visualDensity: VisualDensity.compact,
      activeColor: AppColors.espresso,
      selected: isSelected,
      selectedTileColor: AppColors.surfaceSubtle,
      title: Text(
        p.nama,
        style: TextStyle(
          fontSize: 13,
          fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
          color: AppColors.ink,
        ),
      ),
      secondary: p.tambahanHarga > 0
          ? Text(
              '+${Formatters.rupiah(p.tambahanHarga)}',
              style: const TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
                color: AppColors.latte,
              ),
            )
          : null,
    );
  }

  Widget _checkboxTile(OpsiGrupModel grup, PilihanOpsiModel p) {
    final isChecked =
        _selectedCheckbox[grup.id]?.any((x) => x.id == p.id) ?? false;

    return CheckboxListTile(
      value: isChecked,
      onChanged: (checked) {
        setState(() {
          final set = _selectedCheckbox[grup.id] ?? {};
          if (checked == true) {
            set.add(p);
          } else {
            set.removeWhere((x) => x.id == p.id);
          }
          _selectedCheckbox[grup.id] = set;
        });
      },
      dense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      visualDensity: VisualDensity.compact,
      activeColor: AppColors.espresso,
      controlAffinity: ListTileControlAffinity.leading,
      selected: isChecked,
      selectedTileColor: AppColors.surfaceSubtle,
      title: Text(
        p.nama,
        style: TextStyle(
          fontSize: 13,
          fontWeight: isChecked ? FontWeight.w700 : FontWeight.w500,
          color: AppColors.ink,
        ),
      ),
      secondary: p.tambahanHarga > 0
          ? Text(
              '+${Formatters.rupiah(p.tambahanHarga)}',
              style: const TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
                color: AppColors.latte,
              ),
            )
          : null,
    );
  }

  Widget _buildBottomBar() {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
        child: Row(
          children: [
            Container(
              decoration: BoxDecoration(
                color: AppColors.white,
                borderRadius: AppRadius.allMedium,
                border: Border.all(color: AppColors.line),
              ),
              child: Row(
                children: [
                  IconButton(
                    tooltip: 'Kurangi',
                    onPressed: _qty > 1 ? () => setState(() => _qty--) : null,
                    style: IconButton.styleFrom(
                      minimumSize: const Size(36, 40),
                    ),
                    icon: const Icon(Icons.remove_rounded, size: 17),
                  ),
                  SizedBox(
                    width: 26,
                    child: Text(
                      Formatters.angka(_qty),
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Tambah',
                    onPressed: () => setState(() => _qty++),
                    style: IconButton.styleFrom(
                      minimumSize: const Size(36, 40),
                    ),
                    icon: const Icon(Icons.add_rounded, size: 17),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: FilledButton(
                style: FilledButton.styleFrom(
                  minimumSize: const Size(0, 50),
                  textStyle: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                onPressed: _tambahKePesanan,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.add_shopping_cart_rounded, size: 18),
                    const SizedBox(width: 7),
                    Flexible(
                      child: Text(
                        'Tambah · ${Formatters.rupiah(_totalHarga)}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _tambahKePesanan() {
    // Verifikasi grup wajib
    for (final grup in widget.produk.opsiGrup) {
      if (grup.wajib) {
        if (grup.tipe == 'radio' && _selectedRadio[grup.id] == null) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Pilih salah satu ${grup.nama}')),
          );
          return;
        }
        if (grup.tipe == 'checkbox' &&
            (_selectedCheckbox[grup.id]?.isEmpty ?? true)) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Pilih minimal satu ${grup.nama}')),
          );
          return;
        }
      }
    }

    final cartItem = CartItemModel(
      produk: widget.produk,
      qty: _qty,
      opsiTerpilih: _allSelected,
    );
    Navigator.pop(context, cartItem);
  }
}
