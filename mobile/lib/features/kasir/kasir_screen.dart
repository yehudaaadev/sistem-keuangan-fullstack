import 'package:flutter/material.dart';

import '../../core/auth_service.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/app_widgets.dart';
import '../../models/cart_item_model.dart';
import '../../models/kategori_model.dart';
import '../../models/pembeli_model.dart';
import '../../models/penjualan_model.dart';
import '../../models/produk_model.dart';
import '../../models/shift_model.dart';
import '../../services/kasir_service.dart';
import '../../utils/formatters.dart';
import '../auth/login_page.dart';
import 'riwayat_penjualan_screen.dart';
import 'widgets/cart_panel.dart';
import 'widgets/payment_modal.dart';
import 'widgets/product_option_dialog.dart';
import 'widgets/receipt_dialog.dart';
import 'widgets/shift_bar.dart';
import 'widgets/shift_dialogs.dart';

class _ProductTheme {
  final IconData icon;
  final Color primaryColor;
  final Color backgroundColor;
  final String categoryLabel;

  const _ProductTheme({
    required this.icon,
    required this.primaryColor,
    required this.backgroundColor,
    required this.categoryLabel,
  });
}

class KasirScreen extends StatefulWidget {
  const KasirScreen({super.key});

  @override
  State<KasirScreen> createState() => _KasirScreenState();
}

class _KasirScreenState extends State<KasirScreen> {
  final _service = KasirService();

  // State Data
  ShiftModel? _shift;
  List<KategoriModel> _kategoriList = [];
  int? _kategoriTerpilih;
  List<ProdukModel> _semuaProduk = [];
  List<PembeliModel> _pembeliList = [];
  Map<String, dynamic> _toko = {};

  // State Cart
  final List<CartItemModel> _cartItems = [];
  PembeliModel? _pembeliTerpilih;
  String _tipePesanan = 'dine_in';
  String _nomorMeja = '';
  bool _pakaiPoin = false;
  String? _voucherKode;
  double _diskonVoucher = 0.0;

  // Search & Loading
  final _searchCtrl = TextEditingController();
  bool _initialLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _initData();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _initData() async {
    setState(() {
      _initialLoading = true;
      _errorMessage = null;
    });

    try {
      final results = await Future.wait([
        _service.getShiftAktif(),
        _service.getKategori(),
        _service.getProduk(),
        _service.getPembeli(),
        _service.getPengaturan(),
      ]);

      if (!mounted) return;
      setState(() {
        _shift = results[0] as ShiftModel?;
        _kategoriList = results[1] as List<KategoriModel>;
        _semuaProduk = results[2] as List<ProdukModel>;
        _pembeliList = results[3] as List<PembeliModel>;
        _toko = results[4] as Map<String, dynamic>;
        _initialLoading = false;
      });

      // Jika kasir belum punya shift aktif, tawarkan langsung dialog buka shift
      if (_shift == null && mounted) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          _handleBukaShift();
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _initialLoading = false;
          _errorMessage = e.toString();
        });
      }
    }
  }

  // --- Filter Produk ---
  List<ProdukModel> get _produkFiltered {
    final query = _searchCtrl.text.trim().toLowerCase();
    return _semuaProduk.where((p) {
      final cocokKategori =
          _kategoriTerpilih == null || p.kategoriId == _kategoriTerpilih;
      final cocokCari = query.isEmpty || p.nama.toLowerCase().contains(query);
      return cocokKategori && cocokCari;
    }).toList();
  }

  // --- Operasi Shift ---
  Future<void> _handleBukaShift() async {
    final baru = await ShiftDialogs.bukaShift(context);
    if (baru != null && mounted) {
      setState(() => _shift = baru);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Shift berhasil dibuka! Selamat melayani.'),
        ),
      );
    }
  }

  Future<void> _handleKas(String tipe) async {
    final baru = await ShiftDialogs.catatKas(context, tipe);
    if (baru != null && mounted) {
      setState(() => _shift = baru);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Kas $tipe berhasil dicatat.')),
      );
    }
  }

  Future<void> _handleTutupShift() async {
    if (_shift == null) return;
    final baru = await ShiftDialogs.tutupShift(context, _shift!);
    if (baru != null && mounted) {
      setState(() => _shift = null);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Shift kasir berhasil ditutup.')),
      );
    }
  }

  // --- Operasi Keranjang ---
  void _tambahProduk(ProdukModel p) async {
    if (_shift == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Buka shift terlebih dahulu sebelum melayani transaksi!',
          ),
        ),
      );
      _handleBukaShift();
      return;
    }

    if (p.habis) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Stok menu ini sedang habis.')),
      );
      return;
    }

    if (p.adaOpsi) {
      final cartItem = await ProductOptionDialog.show(context, p);
      if (cartItem != null) {
        _masukkanKeKeranjang(cartItem);
      }
    } else {
      _masukkanKeKeranjang(CartItemModel(produk: p, qty: 1.0));
    }
  }

  void _masukkanKeKeranjang(CartItemModel item) {
    setState(() {
      final idx = _cartItems.indexWhere((it) => it.sig == item.sig);
      if (idx >= 0) {
        _cartItems[idx].qty += item.qty;
      } else {
        _cartItems.add(item);
      }
    });
  }

  void _updateQty(int index, double delta) {
    setState(() {
      final it = _cartItems[index];
      it.qty += delta;
      if (it.qty <= 0) {
        _cartItems.removeAt(index);
      }
    });
  }

  void _removeItem(int index) {
    setState(() {
      _cartItems.removeAt(index);
    });
  }

  void _clearCart() {
    setState(() {
      _cartItems.clear();
      _voucherKode = null;
      _diskonVoucher = 0.0;
      _pakaiPoin = false;
    });
  }

  // --- Voucher & Poin ---
  Future<void> _applyVoucher(String kode) async {
    final subtotal = _cartItems.fold(0.0, (sum, it) => sum + it.subtotal);
    final res = await _service.cekVoucher(kode: kode, subtotal: subtotal);
    setState(() {
      _voucherKode = res['voucher_kode']?.toString();
      _diskonVoucher = double.tryParse(res['diskon']?.toString() ?? '0') ?? 0.0;
    });
  }

  void _removeVoucher() {
    setState(() {
      _voucherKode = null;
      _diskonVoucher = 0.0;
    });
  }

  // --- Perhitungan Total Tagihan ---
  double get _subtotalPesanan =>
      _cartItems.fold(0.0, (sum, it) => sum + it.subtotal);

  double get _servicePersen =>
      double.tryParse(_toko['service_persen']?.toString() ?? '0') ?? 0.0;

  double get _pajakPersen =>
      double.tryParse(_toko['pajak_persen']?.toString() ?? '0') ?? 0.0;

  double get _dasarTagihan =>
      (_subtotalPesanan - _diskonVoucher).clamp(0.0, double.infinity);

  double get _nominalService =>
      (_dasarTagihan * _servicePersen / 100).roundToDouble();

  double get _nominalPajak =>
      ((_dasarTagihan + _nominalService) * _pajakPersen / 100).roundToDouble();

  double get _totalSebelumPoin =>
      _dasarTagihan + _nominalService + _nominalPajak;

  int get _poinTersedia => _pembeliTerpilih?.poin ?? 0;

  int get _poinDigunakan {
    if (!_pakaiPoin || _poinTersedia <= 0) return 0;
    final maxPotonganPoin = _poinTersedia * 1000.0;
    final diskon = maxPotonganPoin.clamp(0.0, _totalSebelumPoin);
    return (diskon / 1000).ceil();
  }

  double get _diskonPoin => (_poinDigunakan * 1000).toDouble();

  double get _totalAkhir =>
      (_totalSebelumPoin - _diskonPoin).clamp(0.0, double.infinity);

  // --- Pembayaran & Submit Transaksi ---
  Future<void> _bukaModalBayar() async {
    final penjualan = await PaymentModal.show(
      context: context,
      total: _totalAkhir,
      onBayar: _eksekusiBayar,
    );

    if (penjualan != null && mounted) {
      await ReceiptDialog.show(
        context: context,
        penjualan: penjualan,
        toko: _toko,
      );
    }
  }

  Future<PenjualanModel> _eksekusiBayar({
    required String metode,
    required double bayar,
  }) async {
    final payload = {
      'tipe': _tipePesanan,
      if (_tipePesanan == 'dine_in' && _nomorMeja.trim().isNotEmpty)
        'meja': _nomorMeja.trim(),
      if (_pembeliTerpilih != null) 'pembeli_id': _pembeliTerpilih!.id,
      if (_poinDigunakan > 0) 'tukar_poin': _poinDigunakan,
      if (_voucherKode != null) 'voucher': _voucherKode,
      'metode': metode,
      'bayar': bayar,
      'items': _cartItems.map((e) => e.toApiJson()).toList(),
    };

    final penjualan = await _service.simpanPenjualan(payload);

    // Reset keranjang
    _clearCart();

    // Refresh status shift & pembeli secara background
    _service.getShiftAktif().then((s) {
      if (mounted) setState(() => _shift = s);
    });
    _service.getPembeli().then((b) {
      if (mounted) setState(() => _pembeliList = b);
    });

    return penjualan;
  }

  // --- Dialog Pembeli Baru ---
  Future<void> _tambahPembeliBaru() async {
    final baru = await ShiftDialogs.tambahPembeli(context);
    if (baru != null && mounted) {
      setState(() {
        _pembeliList.insert(0, baru);
        _pembeliTerpilih = baru;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Pembeli ${baru.nama} berhasil dipilih.')),
      );
    }
  }

  // --- Logout ---
  Future<void> _logout() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Keluar dari Akun Kasir?'),
        content: const Text('Sesi meja kasir akan diakhiri.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );
    if (ok == true) {
      await AuthService().logout();
      if (!mounted) return;
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const LoginPage()),
        (r) => false,
      );
    }
  }

  // --- Bottom Sheet Keranjang untuk Mobile ---
  void _bukaKeranjangMobile() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return CartPanel(
              isModal: true,
              items: _cartItems,
              onUpdateQty: (i, d) {
                _updateQty(i, d);
                setModalState(() {});
              },
              onRemoveItem: (i) {
                _removeItem(i);
                setModalState(() {});
              },
              onClearCart: () {
                _clearCart();
                setModalState(() {});
              },
              tipe: _tipePesanan,
              onTipeChanged: (t) {
                setState(() => _tipePesanan = t);
                setModalState(() {});
              },
              meja: _nomorMeja,
              onMejaChanged: (m) {
                setState(() => _nomorMeja = m);
                setModalState(() {});
              },
              pembeliTerpilih: _pembeliTerpilih,
              pembeliList: _pembeliList,
              onPembeliChanged: (b) {
                setState(() => _pembeliTerpilih = b);
                setModalState(() {});
              },
              onTambahPembeli: _tambahPembeliBaru,
              pakaiPoin: _pakaiPoin,
              onTogglePakaiPoin: (val) {
                setState(() => _pakaiPoin = val);
                setModalState(() {});
              },
              poinDigunakan: _poinDigunakan,
              diskonPoin: _diskonPoin,
              voucherKode: _voucherKode,
              diskonVoucher: _diskonVoucher,
              onApplyVoucher: (k) async {
                await _applyVoucher(k);
                setModalState(() {});
              },
              onRemoveVoucher: () {
                _removeVoucher();
                setModalState(() {});
              },
              servicePersen: _servicePersen,
              pajakPersen: _pajakPersen,
              onCheckout: () {
                Navigator.pop(context);
                _bukaModalBayar();
              },
            );
          },
        );
      },
    );
  }

  // --- Deteksi Tema Visual Kategori & Produk ---
  _ProductTheme _getProductTheme(ProdukModel p) {
    final name = p.nama.toLowerCase();
    final cat = (p.kategori?.nama ?? '').toLowerCase();
    final combined = '$name $cat';

    if (combined.contains('hitam') ||
        combined.contains('manual brew') ||
        combined.contains('americano') ||
        combined.contains('espresso') ||
        combined.contains('cold brew') ||
        combined.contains('v60')) {
      return const _ProductTheme(
        icon: Icons.local_cafe_rounded,
        primaryColor: Color(0xFF3E2723),
        backgroundColor: Color(0xFFF5EBE6),
        categoryLabel: 'Kopi Hitam',
      );
    }
    if (combined.contains('susu') ||
        combined.contains('latte') ||
        combined.contains('macchiato') ||
        combined.contains('cappuccino') ||
        combined.contains('aren')) {
      return const _ProductTheme(
        icon: Icons.coffee_rounded,
        primaryColor: Color(0xFF8D5B2A),
        backgroundColor: Color(0xFFFAF3EC),
        categoryLabel: 'Kopi Susu',
      );
    }
    if (combined.contains('dessert') ||
        combined.contains('kue') ||
        combined.contains('cake') ||
        combined.contains('brownies') ||
        combined.contains('slice') ||
        combined.contains('croissant') ||
        combined.contains('pastry') ||
        combined.contains('roti')) {
      return const _ProductTheme(
        icon: Icons.cake_rounded,
        primaryColor: Color(0xFFB45309),
        backgroundColor: Color(0xFFFEF3C7),
        categoryLabel: 'Dessert & Kue',
      );
    }
    if (combined.contains('snack') ||
        combined.contains('makanan') ||
        combined.contains('goreng') ||
        combined.contains('kentang') ||
        combined.contains('fries') ||
        combined.contains('toast') ||
        combined.contains('platter')) {
      return const _ProductTheme(
        icon: Icons.lunch_dining_rounded,
        primaryColor: Color(0xFFC2410C),
        backgroundColor: Color(0xFFFFEDD5),
        categoryLabel: 'Makanan Ringan',
      );
    }
    if (combined.contains('non') ||
        combined.contains('chocolate') ||
        combined.contains('cokelat') ||
        combined.contains('tea') ||
        combined.contains('teh') ||
        combined.contains('matcha') ||
        combined.contains('taro') ||
        combined.contains('susu')) {
      return const _ProductTheme(
        icon: Icons.emoji_food_beverage_rounded,
        primaryColor: Color(0xFF0D9488),
        backgroundColor: Color(0xFFCCFBF1),
        categoryLabel: 'Non-Kopi',
      );
    }
    return const _ProductTheme(
      icon: Icons.restaurant_menu_rounded,
      primaryColor: AppColors.espresso,
      backgroundColor: AppColors.sand,
      categoryLabel: 'Menu',
    );
  }

  IconData _getCategoryIcon(String? nama) {
    final n = (nama ?? '').toLowerCase();
    if (n.contains('hitam') || n.contains('manual')) {
      return Icons.local_cafe_rounded;
    }
    if (n.contains('susu') || n.contains('latte')) {
      return Icons.coffee_rounded;
    }
    if (n.contains('non') || n.contains('cokelat') || n.contains('teh')) {
      return Icons.emoji_food_beverage_rounded;
    }
    if (n.contains('dessert') || n.contains('kue')) {
      return Icons.cake_rounded;
    }
    if (n.contains('snack') || n.contains('makanan')) {
      return Icons.lunch_dining_rounded;
    }
    return Icons.restaurant_menu_rounded;
  }

  @override
  Widget build(BuildContext context) {
    if (_initialLoading) return _buildLoading();

    if (_errorMessage != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Kasir')),
        body: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: SurfaceCard(
                padding: const EdgeInsets.all(24),
                elevated: true,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 60,
                      height: 60,
                      decoration: const BoxDecoration(
                        color: AppColors.dangerSoft,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.cloud_off_rounded,
                        color: AppColors.danger,
                        size: 28,
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      'Gagal memuat data kasir',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _errorMessage!,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 12.5,
                        height: 1.45,
                        color: AppColors.muted,
                      ),
                    ),
                    const SizedBox(height: 20),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: _initData,
                        icon: const Icon(Icons.refresh_rounded, size: 18),
                        label: const Text('Coba Lagi'),
                      ),
                    ),
                    const SizedBox(height: 10),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        onPressed: () async {
                          await AuthService().logout();
                          if (!context.mounted) return;
                          Navigator.pushAndRemoveUntil(
                            context,
                            MaterialPageRoute(builder: (_) => const LoginPage()),
                            (r) => false,
                          );
                        },
                        icon: const Icon(Icons.logout_rounded, size: 18),
                        label: const Text('Login Ulang'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        final isTablet = constraints.maxWidth >= 880;

        return Scaffold(
          appBar: _buildAppBar(isTablet),
          body: Column(
            children: [
              ShiftBar(
                shift: _shift,
                onBukaShift: _handleBukaShift,
                onKasMasuk: () => _handleKas('masuk'),
                onKasKeluar: () => _handleKas('keluar'),
                onTutupShift: _handleTutupShift,
              ),
              Expanded(
                child: isTablet
                    ? Row(
                        children: [
                          Expanded(flex: 6, child: _buildCatalogSection()),
                          Container(width: 1, color: AppColors.line),
                          Expanded(
                            flex: 4,
                            child: CartPanel(
                              items: _cartItems,
                              onUpdateQty: _updateQty,
                              onRemoveItem: _removeItem,
                              onClearCart: _clearCart,
                              tipe: _tipePesanan,
                              onTipeChanged: (t) =>
                                  setState(() => _tipePesanan = t),
                              meja: _nomorMeja,
                              onMejaChanged: (m) =>
                                  setState(() => _nomorMeja = m),
                              pembeliTerpilih: _pembeliTerpilih,
                              pembeliList: _pembeliList,
                              onPembeliChanged: (b) =>
                                  setState(() => _pembeliTerpilih = b),
                              onTambahPembeli: _tambahPembeliBaru,
                              pakaiPoin: _pakaiPoin,
                              onTogglePakaiPoin: (val) =>
                                  setState(() => _pakaiPoin = val),
                              poinDigunakan: _poinDigunakan,
                              diskonPoin: _diskonPoin,
                              voucherKode: _voucherKode,
                              diskonVoucher: _diskonVoucher,
                              onApplyVoucher: _applyVoucher,
                              onRemoveVoucher: _removeVoucher,
                              servicePersen: _servicePersen,
                              pajakPersen: _pajakPersen,
                              onCheckout: _bukaModalBayar,
                            ),
                          ),
                        ],
                      )
                    : _buildCatalogSection(),
              ),
            ],
          ),
          bottomNavigationBar: (!isTablet && _cartItems.isNotEmpty)
              ? _buildMobileCartBar()
              : null,
        );
      },
    );
  }

  AppBar _buildAppBar(bool isTablet) {
    final namaToko = _toko['nama_toko']?.toString();
    final judul = (namaToko != null && namaToko.isNotEmpty)
        ? namaToko
        : 'Kedai Kopi';
    final shiftAktif = _shift != null;

    return AppBar(
      titleSpacing: 14,
      title: Row(
        children: [
          const BrandMark(size: 36, shadow: false),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  judul,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.3,
                    color: AppColors.ink,
                  ),
                ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    Container(
                      width: 6,
                      height: 6,
                      decoration: BoxDecoration(
                        color: shiftAktif
                            ? AppColors.success
                            : AppColors.warning,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 5),
                    Text(
                      shiftAktif
                          ? 'Terminal Kasir Aktif'
                          : 'Terminal Kasir (Shift Belum Dibuka)',
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
              ],
            ),
          ),
        ],
      ),
      actions: [
        // Tombol Riwayat Penjualan Lengkap
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: OutlinedButton.icon(
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.espresso,
              backgroundColor: AppColors.white,
              side: const BorderSide(color: AppColors.line),
              minimumSize: const Size(0, 36),
              padding: const EdgeInsets.symmetric(horizontal: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
              textStyle: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
            icon: const Icon(Icons.receipt_long_rounded, size: 16),
            label: Text(isTablet ? 'Riwayat Nota' : 'Nota'),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => RiwayatPenjualanScreen(toko: _toko),
                ),
              );
            },
          ),
        ),
        const SizedBox(width: 8),
        CircleActionButton(
          tooltip: 'Muat Ulang Menu & Data',
          icon: Icons.refresh_rounded,
          onPressed: _initData,
        ),
        const SizedBox(width: 8),
        CircleActionButton(
          tooltip: 'Keluar Akun Kasir',
          icon: Icons.logout_rounded,
          onPressed: _logout,
          iconColor: AppColors.muted,
        ),
        const SizedBox(width: 14),
      ],
    );
  }

  Widget _buildLoading() {
    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            BrandMark(size: 56),
            SizedBox(height: 20),
            SizedBox(
              width: 26,
              height: 26,
              child: CircularProgressIndicator(strokeWidth: 2.6),
            ),
            SizedBox(height: 14),
            Text(
              'Menyiapkan meja kasir...',
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: AppColors.muted,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCatalogSection() {
    final produk = _produkFiltered;

    return Column(
      children: [
        // Kolom Pencarian Menu Modern
        Padding(
          padding: const EdgeInsets.fromLTRB(14, 8, 14, 0),
          child: TextField(
            controller: _searchCtrl,
            onChanged: (_) => setState(() {}),
            textInputAction: TextInputAction.search,
            style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600),
            decoration: InputDecoration(
              hintText: 'Cari kopi, pastry, makanan, atau minuman...',
              prefixIcon: const Icon(
                Icons.search_rounded,
                size: 20,
                color: AppColors.espresso,
              ),
              suffixIcon: _searchCtrl.text.isNotEmpty
                  ? IconButton(
                      tooltip: 'Bersihkan pencarian',
                      icon: const Icon(Icons.close_rounded, size: 18),
                      onPressed: () {
                        _searchCtrl.clear();
                        setState(() {});
                      },
                    )
                  : null,
              isDense: true,
              filled: true,
              fillColor: AppColors.white,
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 14,
                vertical: 12,
              ),
              border: const OutlineInputBorder(
                borderRadius: BorderRadius.all(
                  Radius.circular(AppRadius.medium),
                ),
                borderSide: BorderSide(color: AppColors.line),
              ),
              enabledBorder: const OutlineInputBorder(
                borderRadius: BorderRadius.all(
                  Radius.circular(AppRadius.medium),
                ),
                borderSide: BorderSide(color: AppColors.line),
              ),
              focusedBorder: const OutlineInputBorder(
                borderRadius: BorderRadius.all(
                  Radius.circular(AppRadius.medium),
                ),
                borderSide: BorderSide(color: AppColors.espresso, width: 1.5),
              ),
            ),
          ),
        ),

        // Filter Kategori dengan Ikon Tematik
        SizedBox(
          height: 52,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            children: [
              CategoryPill(
                label: 'Semua Menu',
                icon: Icons.dashboard_outlined,
                selected: _kategoriTerpilih == null,
                onTap: () => setState(() => _kategoriTerpilih = null),
              ),
              for (final kat in _kategoriList) ...[
                const SizedBox(width: 8),
                CategoryPill(
                  label: kat.nama,
                  icon: _getCategoryIcon(kat.nama),
                  selected: _kategoriTerpilih == kat.id,
                  onTap: () => setState(() => _kategoriTerpilih = kat.id),
                ),
              ],
            ],
          ),
        ),

        // Grid Menu Kasir
        Expanded(
          child: produk.isEmpty
              ? EmptyState(
                  icon: Icons.search_off_rounded,
                  title: 'Menu tidak ditemukan',
                  message: _searchCtrl.text.trim().isEmpty
                      ? 'Belum ada menu pada kategori ini.'
                      : 'Coba kata kunci lain atau pilih kategori Semua Menu.',
                  actionLabel:
                      _kategoriTerpilih != null || _searchCtrl.text.isNotEmpty
                      ? 'Reset filter'
                      : null,
                  onAction: () {
                    _searchCtrl.clear();
                    setState(() => _kategoriTerpilih = null);
                  },
                )
              : GridView.builder(
                  padding: const EdgeInsets.fromLTRB(14, 4, 14, 18),
                  gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                    maxCrossAxisExtent: 220,
                    childAspectRatio: 0.74,
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                  ),
                  itemCount: produk.length,
                  itemBuilder: (context, i) {
                    final p = produk[i];
                    final cartCount = _cartItems
                        .where((it) => it.produk.id == p.id)
                        .fold(0.0, (sum, it) => sum + it.qty);
                    return _productCard(p, cartCount);
                  },
                ),
        ),
      ],
    );
  }

  Widget _productCard(ProdukModel p, double cartCount) {
    final theme = _getProductTheme(p);
    final punyaGambar = p.imageUrl != null && p.imageUrl!.isNotEmpty;
    final terpilih = cartCount > 0;
    final isStokMenipis = !p.habis && p.stok > 0 && p.stok <= 5;

    return SurfaceCard(
      padding: EdgeInsets.zero,
      elevated: terpilih,
      borderColor: terpilih
          ? AppColors.espresso
          : (p.habis ? AppColors.line : AppColors.line),
      borderWidth: terpilih ? 2.0 : 1.2,
      color: p.habis ? AppColors.surfaceSubtle : AppColors.white,
      onTap: () => _tambahProduk(p),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Bagian Atas: Area Ilustrasi / Gambar Kategori Tematik
          Expanded(
            child: ClipRRect(
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(AppRadius.large),
              ),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  Container(
                    color: theme.backgroundColor,
                    child: punyaGambar
                        ? Image.network(
                            p.imageUrl!,
                            fit: BoxFit.cover,
                            errorBuilder: (ctx, err, stack) =>
                                _buildVectorCardIllustration(theme),
                          )
                        : _buildVectorCardIllustration(theme),
                  ),

                  // Lencana Kustom Opsi (Modifier)
                  if (p.adaOpsi)
                    Positioned(
                      top: 8,
                      left: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 3.5,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.espresso,
                          borderRadius: BorderRadius.circular(5),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.tune_rounded,
                              size: 10,
                              color: AppColors.white,
                            ),
                            SizedBox(width: 3.5),
                            Text(
                              'KUSTOM OPSI',
                              style: TextStyle(
                                color: AppColors.white,
                                fontSize: 8.5,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.4,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                  // Lencana Jumlah di Keranjang
                  if (terpilih)
                    Positioned(
                      top: 8,
                      right: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 3.5,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.espresso,
                          borderRadius: BorderRadius.circular(5),
                          boxShadow: AppColors.softShadow,
                        ),
                        child: Text(
                          '${Formatters.angka(cartCount)}× di pesanan',
                          style: const TextStyle(
                            color: AppColors.white,
                            fontSize: 9.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),

                  // Overlay Stok Habis
                  if (p.habis)
                    Container(
                      color: AppColors.white.withValues(alpha: 0.88),
                      alignment: Alignment.center,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.danger,
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(color: AppColors.dangerBorder),
                        ),
                        child: const Text(
                          'STOK HABIS',
                          style: TextStyle(
                            color: AppColors.white,
                            fontSize: 9.5,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),

          // Bagian Bawah: Metadata Produk, Harga & Tombol Tambah
          Padding(
            padding: const EdgeInsets.fromLTRB(11, 9, 11, 10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  p.nama,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13,
                    height: 1.25,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.2,
                    color: AppColors.ink,
                  ),
                ),
                const SizedBox(height: 3),
                Row(
                  children: [
                    if (isStokMenipis) ...[
                      Container(
                        width: 6,
                        height: 6,
                        decoration: const BoxDecoration(
                          color: AppColors.warning,
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 4),
                    ],
                    Expanded(
                      child: Text(
                        p.habis
                            ? 'Stok kosong'
                            : (isStokMenipis
                                ? 'Sisa ${Formatters.angka(p.stok)} ${p.satuan}'
                                : 'Tersedia ${Formatters.angka(p.stok)} ${p.satuan}'),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight:
                              isStokMenipis ? FontWeight.w700 : FontWeight.w500,
                          color: p.habis
                              ? AppColors.danger
                              : (isStokMenipis
                                  ? AppColors.warning
                                  : AppColors.muted),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        Formatters.rupiah(p.hargaJual),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w900,
                          letterSpacing: -0.3,
                          color: AppColors.espresso,
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: terpilih
                            ? AppColors.espresso
                            : (p.habis ? AppColors.line : AppColors.cream),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(
                          color: terpilih
                              ? AppColors.espresso
                              : (p.habis ? AppColors.line : AppColors.line),
                          width: 1.2,
                        ),
                      ),
                      child: Icon(
                        terpilih ? Icons.check_rounded : Icons.add_rounded,
                        size: 18,
                        color: p.habis
                            ? AppColors.muted
                            : (terpilih ? AppColors.white : AppColors.espresso),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildVectorCardIllustration(_ProductTheme theme) {
    return Stack(
      alignment: Alignment.center,
      children: [
        // Lingkaran aksen latar belakang
        Container(
          width: 64,
          height: 64,
          decoration: BoxDecoration(
            color: theme.primaryColor.withValues(alpha: 0.1),
            shape: BoxShape.circle,
            border: Border.all(
              color: theme.primaryColor.withValues(alpha: 0.18),
              width: 1.2,
            ),
          ),
          child: Icon(
            theme.icon,
            size: 32,
            color: theme.primaryColor,
          ),
        ),

        // Tag kategori mikro di kiri bawah
        Positioned(
          left: 8,
          bottom: 6,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            decoration: BoxDecoration(
              color: AppColors.white.withValues(alpha: 0.9),
              borderRadius: BorderRadius.circular(4),
              border: Border.all(
                color: theme.primaryColor.withValues(alpha: 0.2),
              ),
            ),
            child: Text(
              theme.categoryLabel.toUpperCase(),
              style: TextStyle(
                fontSize: 8,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.4,
                color: theme.primaryColor,
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildMobileCartBar() {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 10, 10, 10),
          decoration: const BoxDecoration(
            gradient: AppColors.brandGradient,
            borderRadius: BorderRadius.all(Radius.circular(20)),
            boxShadow: AppColors.liftShadow,
          ),
          child: Row(
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: AppColors.white.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: const Icon(
                      Icons.shopping_bag_rounded,
                      color: AppColors.white,
                      size: 20,
                    ),
                  ),
                  Positioned(
                    top: -5,
                    right: -5,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 5,
                        vertical: 1,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.caramel,
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(
                          color: AppColors.espressoDeep,
                          width: 1.5,
                        ),
                      ),
                      child: Text(
                        '${_cartItems.length}',
                        style: const TextStyle(
                          color: AppColors.white,
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Total pesanan',
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w500,
                        color: AppColors.white.withValues(alpha: 0.7),
                      ),
                    ),
                    const SizedBox(height: 1),
                    Text(
                      Formatters.rupiah(_totalAkhir),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: AppColors.white,
                        letterSpacing: -0.2,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.white,
                  foregroundColor: AppColors.espresso,
                  minimumSize: const Size(0, 42),
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  textStyle: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                onPressed: _bukaKeranjangMobile,
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text('Lihat Pesanan'),
                    SizedBox(width: 5),
                    Icon(Icons.arrow_forward_rounded, size: 16),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
