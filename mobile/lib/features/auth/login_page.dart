import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api_config.dart';
import '../../core/auth_service.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/app_widgets.dart';
import '../kasir/kasir_screen.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage>
    with SingleTickerProviderStateMixin {
  final _email = TextEditingController(text: 'kasir@example.com');
  final _sandi = TextEditingController(text: 'password');

  bool _loading = false;
  bool _isSuccess = false;
  bool _sandiTerlihat = false;
  String? _error;
  String _selectedRole = 'kasir';

  late final AnimationController _shakeCtrl;
  late final Animation<double> _shakeAnim;

  @override
  void initState() {
    super.initState();
    _shakeCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 420),
    );

    _shakeAnim = TweenSequence<double>([
      TweenSequenceItem(
        tween: Tween(begin: 0.0, end: -12.0)
            .chain(CurveTween(curve: Curves.easeOut)),
        weight: 1,
      ),
      TweenSequenceItem(
        tween: Tween(begin: -12.0, end: 12.0)
            .chain(CurveTween(curve: Curves.easeInOut)),
        weight: 2,
      ),
      TweenSequenceItem(
        tween: Tween(begin: 12.0, end: -8.0)
            .chain(CurveTween(curve: Curves.easeInOut)),
        weight: 2,
      ),
      TweenSequenceItem(
        tween: Tween(begin: -8.0, end: 6.0)
            .chain(CurveTween(curve: Curves.easeInOut)),
        weight: 2,
      ),
      TweenSequenceItem(
        tween: Tween(begin: 6.0, end: 0.0)
            .chain(CurveTween(curve: Curves.easeIn)),
        weight: 1,
      ),
    ]).animate(_shakeCtrl);
  }

  @override
  void dispose() {
    _shakeCtrl.dispose();
    _email.dispose();
    _sandi.dispose();
    super.dispose();
  }

  void _pilihOperator(String role, String email) {
    HapticFeedback.selectionClick();
    setState(() {
      _selectedRole = role;
      _email.text = email;
      _sandi.text = 'password';
      _error = null;
    });
  }

  Future<void> _login() async {
    final emailText = _email.text.trim();
    final sandiText = _sandi.text;

    if (emailText.isEmpty || sandiText.isEmpty) {
      _shakeCtrl.forward(from: 0.0);
      HapticFeedback.heavyImpact();
      setState(() => _error = 'Email dan kata sandi wajib diisi.');
      return;
    }

    setState(() {
      _loading = true;
      _isSuccess = false;
      _error = null;
    });

    try {
      final role = await AuthService().login(emailText, sandiText);
      if (!mounted) return;

      HapticFeedback.mediumImpact();
      setState(() {
        _loading = false;
        _isSuccess = true;
      });

      await Future.delayed(const Duration(milliseconds: 380));
      if (!mounted) return;

      final Widget targetPage = (role == 'kasir')
          ? const KasirScreen()
          : HomeSementara(role: role);

      Navigator.pushAndRemoveUntil(
        context,
        PageRouteBuilder(
          transitionDuration: const Duration(milliseconds: 260),
          pageBuilder: (_, animation, secondaryAnimation) => targetPage,
          transitionsBuilder: (_, animation, secondaryAnimation, child) {
            return FadeTransition(
              opacity: CurvedAnimation(
                parent: animation,
                curve: Curves.easeOutCubic,
              ),
              child: child,
            );
          },
        ),
        (r) => false,
      );
    } on DioException catch (e) {
      if (mounted) {
        _shakeCtrl.forward(from: 0.0);
        HapticFeedback.heavyImpact();
        setState(() {
          _loading = false;
          _isSuccess = false;
          _error = _pesanError(e);
        });
      }
    } catch (e) {
      if (mounted) {
        _shakeCtrl.forward(from: 0.0);
        HapticFeedback.heavyImpact();
        setState(() {
          _loading = false;
          _isSuccess = false;
          _error = e.toString();
        });
      }
    }
  }

  String _pesanError(DioException e) {
    const gagalKoneksi = {
      DioExceptionType.connectionTimeout,
      DioExceptionType.sendTimeout,
      DioExceptionType.receiveTimeout,
      DioExceptionType.connectionError,
    };

    if (gagalKoneksi.contains(e.type)) {
      return 'Koneksi ke backend Laravel terputus.\n'
          'Pastikan backend berjalan di komputer:\n'
          'php artisan serve --host=0.0.0.0 --port=8000\n\n'
          'Host aktif: ${ApiConfig.baseUrl}\n'
          'Klik tombol Host API di bawah untuk ganti IP.';
    }

    final data = e.response?.data;
    if (data is Map) {
      final message = data['message'];
      if (message is String && message.isNotEmpty) return message;

      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final pertama = errors.values.first;
        if (pertama is List && pertama.isNotEmpty) {
          return pertama.first.toString();
        }
      }
    }

    final status = e.response?.statusCode;
    if (status == 401) {
      return 'Email atau sandi tidak cocok. Silakan periksa kredensial.';
    }
    return status == null
        ? 'Gagal menghubungi server (${e.type.name}).'
        : 'Terjadi kesalahan pada backend (HTTP $status).';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.espressoDeep,
      body: LayoutBuilder(
        builder: (context, constraints) {
          final isWide = constraints.maxWidth >= 900;
          if (isWide) {
            return _buildWideLayout();
          } else {
            return _buildMobileLayout();
          }
        },
      ),
    );
  }

  // --- Layout Tablet / Desktop (Split-Screen) ---
  Widget _buildWideLayout() {
    return Row(
      children: [
        // Sisi Kiri: Brand & Info
        Expanded(
          flex: 5,
          child: Container(
            color: AppColors.espressoDeep,
            padding: const EdgeInsets.symmetric(horizontal: 48, vertical: 42),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildBrandHeader(),
                const Spacer(),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: AppColors.espresso,
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(
                      color: AppColors.caramel.withValues(alpha: 0.35),
                    ),
                  ),
                  child: const Text(
                    'SISTEM KASIR KEDAI KOPI',
                    style: TextStyle(
                      color: AppColors.caramel,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.1,
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Aplikasi Kasir &\nPembukuan Toko',
                  style: TextStyle(
                    color: AppColors.white,
                    fontSize: 30,
                    height: 1.18,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.6,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Mudah digunakan untuk mencatat pesanan pembeli, memotong stok bahan kopi otomatis, dan menghitung uang di laci kasir secara rapi.',
                  style: TextStyle(
                    color: AppColors.sand.withValues(alpha: 0.8),
                    fontSize: 13.5,
                    height: 1.5,
                  ),
                ),
                const SizedBox(height: 28),
                _featureItem(
                  icon: Icons.lock_clock_rounded,
                  title: 'Shift & Uang Laci Kasir',
                  desc: 'Catat modal awal kasir dan cek uang fisik saat tutup toko.',
                ),
                const SizedBox(height: 14),
                _featureItem(
                  icon: Icons.inventory_2_outlined,
                  title: 'Stok Bahan Berkurang Otomatis',
                  desc: 'Biji kopi, susu, dan bahan langsung berkurang tiap ada pesanan.',
                ),
                const SizedBox(height: 14),
                _featureItem(
                  icon: Icons.receipt_long_rounded,
                  title: 'Cetak Nota & WhatsApp',
                  desc: 'Cetak nota ke printer kasir atau kirim bukti bayar ke WhatsApp pembeli.',
                ),
                const Spacer(),
                _buildSystemBadge(),
              ],
            ),
          ),
        ),

        // Sisi Kanan: Panel Form Kasir
        Expanded(
          flex: 6,
          child: Container(
            color: AppColors.cream,
            alignment: Alignment.center,
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 48, vertical: 36),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 480),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Masuk Meja Kasir',
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.5,
                        color: AppColors.ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    const Text(
                      'Pilih profil operator kasir atau gunakan kredensial manual:',
                      style: TextStyle(
                        fontSize: 12.5,
                        color: AppColors.muted,
                      ),
                    ),
                    const SizedBox(height: 20),
                    _operatorTiles(),
                    const SizedBox(height: 18),
                    AnimatedBuilder(
                      animation: _shakeAnim,
                      builder: (context, child) {
                        return Transform.translate(
                          offset: Offset(_shakeAnim.value, 0),
                          child: child,
                        );
                      },
                      child: _inputCredentialBox(),
                    ),
                    const SizedBox(height: 18),
                    _buildHostPill(),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  // --- Layout Mobile / Sempit (Premium Hero Card + Console) ---
  Widget _buildMobileLayout() {
    return Column(
      children: [
        // Top Hero Card (Obsidian Espresso Slate)
        Container(
          color: AppColors.espressoDeep,
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 22),
          child: SafeArea(
            bottom: false,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 36,
                          height: 36,
                          decoration: BoxDecoration(
                            color: AppColors.espresso,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(
                              color: AppColors.caramel.withValues(alpha: 0.4),
                            ),
                          ),
                          child: const Icon(
                            Icons.coffee_rounded,
                            color: AppColors.caramel,
                            size: 19,
                          ),
                        ),
                        const SizedBox(width: 10),
                        const Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'KEDAI KOPI POS',
                              style: TextStyle(
                                color: AppColors.white,
                                fontSize: 13,
                                fontWeight: FontWeight.w900,
                                letterSpacing: 0.8,
                              ),
                            ),
                            Text(
                              'Terminal Kasir & Keuangan',
                              style: TextStyle(
                                color: AppColors.sand,
                                fontSize: 10.5,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppColors.espresso,
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(
                          color: AppColors.caramel.withValues(alpha: 0.3),
                        ),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.circle, color: AppColors.success, size: 7),
                          SizedBox(width: 5),
                          Text(
                            'ONLINE',
                            style: TextStyle(
                              color: AppColors.caramel,
                              fontSize: 9.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                const Text(
                  'Otentikasi Operator Kasir',
                  style: TextStyle(
                    color: AppColors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.3,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  'Pilih akun tugas Anda untuk memulai shift transaksi:',
                  style: TextStyle(
                    color: AppColors.sand.withValues(alpha: 0.75),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ),

        // Bottom Form Canvas (Porcelain Cream)
        Expanded(
          child: Container(
            color: AppColors.cream,
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(18, 16, 18, 24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 480),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _operatorTiles(),
                    const SizedBox(height: 16),
                    AnimatedBuilder(
                      animation: _shakeAnim,
                      builder: (context, child) {
                        return Transform.translate(
                          offset: Offset(_shakeAnim.value, 0),
                          child: child,
                        );
                      },
                      child: _inputCredentialBox(),
                    ),
                    const SizedBox(height: 16),
                    _buildHostPill(),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildBrandHeader() {
    return Row(
      children: [
        Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: AppColors.espresso,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: AppColors.caramel.withValues(alpha: 0.4),
              width: 1.2,
            ),
          ),
          child: const Icon(
            Icons.coffee_rounded,
            color: AppColors.caramel,
            size: 22,
          ),
        ),
        const SizedBox(width: 12),
        const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'KEDAI KOPI',
              style: TextStyle(
                color: AppColors.white,
                fontSize: 14,
                fontWeight: FontWeight.w900,
                letterSpacing: 1.2,
              ),
            ),
            Text(
              'Sistem Keuangan & Kasir Terpadu',
              style: TextStyle(
                color: AppColors.sand,
                fontSize: 11,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _featureItem({
    required IconData icon,
    required String title,
    required String desc,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: AppColors.espresso,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: AppColors.caramel.withValues(alpha: 0.25),
            ),
          ),
          child: Icon(icon, color: AppColors.caramel, size: 18),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: AppColors.white,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                desc,
                style: TextStyle(
                  color: AppColors.sand.withValues(alpha: 0.75),
                  fontSize: 11.5,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildSystemBadge() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.espresso,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: AppColors.lineStrong.withValues(alpha: 0.2)),
      ),
      child: const Row(
        children: [
          Icon(Icons.check_circle_rounded, color: AppColors.success, size: 16),
          SizedBox(width: 10),
          Expanded(
            child: Text(
              'Laravel Sanctum API · MySQL · Flutter POS Ready',
              style: TextStyle(
                color: AppColors.white,
                fontSize: 11.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // --- Ubin Pemilih Operator Kasir ---
  Widget _operatorTiles() {
    return Column(
      children: [
        _operatorCard(
          role: 'kasir',
          name: 'Kasir Front-Line (POS)',
          email: 'kasir@example.com',
          roleBadge: 'Akses Meja Kasir',
          icon: Icons.point_of_sale_rounded,
        ),
        const SizedBox(height: 8),
        _operatorCard(
          role: 'admin',
          name: 'Administrator Toko',
          email: 'admin@example.com',
          roleBadge: 'Stok & Belanja',
          icon: Icons.inventory_2_outlined,
        ),
        const SizedBox(height: 8),
        _operatorCard(
          role: 'owner',
          name: 'Pemilik Kedai (Owner)',
          email: 'owner@example.com',
          roleBadge: 'Laba/Rugi & Audit',
          icon: Icons.analytics_outlined,
        ),
      ],
    );
  }

  Widget _operatorCard({
    required String role,
    required String name,
    required String email,
    required String roleBadge,
    required IconData icon,
  }) {
    final isSelected = _selectedRole == role;

    return Material(
      color: isSelected ? AppColors.white : AppColors.surfaceSubtle,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: isSelected ? AppColors.espresso : AppColors.line,
          width: isSelected ? 2.0 : 1.0,
        ),
      ),
      child: InkWell(
        onTap: () => _pilihOperator(role, email),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: isSelected ? AppColors.espresso : AppColors.sand,
                  borderRadius: BorderRadius.circular(9),
                ),
                child: Icon(
                  icon,
                  size: 19,
                  color: isSelected ? AppColors.white : AppColors.espresso,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight:
                            isSelected ? FontWeight.w900 : FontWeight.w700,
                        color: AppColors.ink,
                      ),
                    ),
                    const SizedBox(height: 1),
                    Text(
                      email,
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppColors.muted,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: isSelected
                      ? AppColors.surfaceSubtle
                      : AppColors.sand.withValues(alpha: 0.5),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(
                    color: isSelected ? AppColors.lineStrong : AppColors.line,
                  ),
                ),
                child: Text(
                  roleBadge,
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    color: isSelected ? AppColors.espresso : AppColors.muted,
                  ),
                ),
              ),
              if (isSelected) ...[
                const SizedBox(width: 8),
                const Icon(
                  Icons.check_circle_rounded,
                  size: 18,
                  color: AppColors.espresso,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  // --- Kotak Form Kredensial & CTA ---
  Widget _inputCredentialBox() {
    final hasError = _error != null;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: hasError ? AppColors.dangerBorder : AppColors.line,
          width: hasError ? 1.6 : 1.2,
        ),
        boxShadow: AppColors.softShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          TextField(
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.next,
            autofillHints: const [AutofillHints.email],
            style: const TextStyle(
              fontSize: 13.5,
              fontWeight: FontWeight.w600,
              color: AppColors.ink,
            ),
            decoration: const InputDecoration(
              labelText: 'Email Akun Operator',
              prefixIcon: Icon(Icons.alternate_email_rounded, size: 19),
              isDense: true,
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _sandi,
            obscureText: !_sandiTerlihat,
            textInputAction: TextInputAction.done,
            autofillHints: const [AutofillHints.password],
            onSubmitted: (_) => _loading ? null : _login(),
            style: const TextStyle(
              fontSize: 13.5,
              fontWeight: FontWeight.w600,
              color: AppColors.ink,
            ),
            decoration: InputDecoration(
              labelText: 'Kata Sandi',
              prefixIcon: const Icon(Icons.lock_outline_rounded, size: 19),
              isDense: true,
              suffixIcon: IconButton(
                tooltip: _sandiTerlihat ? 'Sembunyikan sandi' : 'Lihat sandi',
                icon: Icon(
                  _sandiTerlihat
                      ? Icons.visibility_off_outlined
                      : Icons.visibility_outlined,
                  size: 19,
                  color: AppColors.muted,
                ),
                onPressed: () =>
                    setState(() => _sandiTerlihat = !_sandiTerlihat),
              ),
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 14),
            InfoBanner(
              message: _error!,
              tone: BannerTone.danger,
              icon: Icons.error_rounded,
            ),
          ],
          const SizedBox(height: 18),
          SizedBox(
            height: 52,
            child: _buildSubmitButton(),
          ),
        ],
      ),
    );
  }

  Widget _buildSubmitButton() {
    if (_isSuccess) {
      return Container(
        decoration: BoxDecoration(
          color: AppColors.success,
          borderRadius: BorderRadius.circular(AppRadius.medium),
          border: Border.all(color: AppColors.successBorder, width: 1.5),
        ),
        child: const Center(
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.check_circle_rounded, color: AppColors.white, size: 21),
              SizedBox(width: 8),
              Text(
                'Otentikasi Berhasil · Membuka POS...',
                style: TextStyle(
                  color: AppColors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.2,
                ),
              ),
            ],
          ),
        ),
      );
    }

    return FilledButton(
      onPressed: _loading ? null : _login,
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.espresso,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.medium),
        ),
      ),
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
                Text(
                  'Memverifikasi kredensial...',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
              ],
            )
          : const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  'Buka Meja Kasir',
                  style: TextStyle(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.3,
                  ),
                ),
                SizedBox(width: 8),
                Icon(Icons.arrow_forward_rounded, size: 18),
              ],
            ),
    );
  }

  Widget _buildHostPill() {
    return Center(
      child: Tooltip(
        message: 'Klik untuk ubah alamat host API backend',
        child: InkWell(
          onTap: _tampilkanDialogServer,
          borderRadius: BorderRadius.circular(999),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(999),
              border: Border.all(color: AppColors.lineStrong),
              boxShadow: AppColors.softShadow,
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 7,
                  height: 7,
                  decoration: const BoxDecoration(
                    color: AppColors.success,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 8),
                const Text(
                  'Host API:',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: AppColors.muted,
                  ),
                ),
                const SizedBox(width: 6),
                ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 220),
                  child: Text(
                    ApiConfig.baseUrl,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w800,
                      color: AppColors.ink,
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                const Icon(
                  Icons.settings_outlined,
                  size: 13,
                  color: AppColors.muted,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _tampilkanDialogServer() async {
    final controller = TextEditingController(text: ApiConfig.baseUrl);
    String? pingStatus;
    bool isPinging = false;

    final hasil = await showDialog<String>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setDialogState) {
          Future<void> jalankanPing() async {
            setDialogState(() {
              isPinging = true;
              pingStatus = null;
            });
            try {
              final testUrl = controller.text.trim();
              final pingClient = Dio(
                BaseOptions(
                  baseUrl: testUrl.endsWith('/api/v1')
                      ? testUrl
                      : '$testUrl/api/v1',
                  connectTimeout: const Duration(seconds: 4),
                  receiveTimeout: const Duration(seconds: 4),
                ),
              );
              final stopwatch = Stopwatch()..start();
              final res = await pingClient.get('/ping');
              stopwatch.stop();

              if (res.statusCode == 200) {
                setDialogState(() {
                  pingStatus =
                      'Koneksi Berhasil (${stopwatch.elapsedMilliseconds} ms) · Database OK';
                  isPinging = false;
                });
              } else {
                setDialogState(() {
                  pingStatus = 'Respon server HTTP ${res.statusCode}';
                  isPinging = false;
                });
              }
            } catch (e) {
              setDialogState(() {
                pingStatus = 'Gagal terhubung ke host ini';
                isPinging = false;
              });
            }
          }

          return AlertDialog(
            title: const Row(
              children: [
                Icon(Icons.router_rounded, size: 20, color: AppColors.espresso),
                SizedBox(width: 8),
                Text(
                  'Konfigurasi Jaringan Server',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                ),
              ],
            ),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Alamat server backend Laravel tempat basis data dan API berjalan:',
                  style: TextStyle(fontSize: 12, color: AppColors.muted),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: controller,
                  style: const TextStyle(
                      fontSize: 13, fontWeight: FontWeight.w600),
                  decoration: const InputDecoration(
                    labelText: 'URL API Backend',
                    hintText: 'http://192.168.x.x:8000/api/v1',
                    prefixIcon: Icon(Icons.link_rounded, size: 18),
                    isDense: true,
                  ),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Preset Alamat:',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: AppColors.muted,
                  ),
                ),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    _chipPreset(
                      controller,
                      'IP LAN PC',
                      'http://192.168.50.8:8000/api/v1',
                    ),
                    _chipPreset(
                      controller,
                      'Emulator Android',
                      'http://10.0.2.2:8000/api/v1',
                    ),
                    _chipPreset(
                      controller,
                      'Localhost',
                      'http://127.0.0.1:8000/api/v1',
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                OutlinedButton.icon(
                  onPressed: isPinging ? null : jalankanPing,
                  icon: isPinging
                      ? const SizedBox(
                          width: 14,
                          height: 14,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.network_check_rounded, size: 16),
                  label: Text(isPinging ? 'Menguji...' : 'Uji Koneksi Host'),
                ),
                if (pingStatus != null) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                    decoration: BoxDecoration(
                      color: pingStatus!.startsWith('Koneksi Berhasil')
                          ? AppColors.successSoft
                          : AppColors.dangerSoft,
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(
                        color: pingStatus!.startsWith('Koneksi Berhasil')
                            ? AppColors.successBorder
                            : AppColors.dangerBorder,
                      ),
                    ),
                    child: Text(
                      pingStatus!,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: pingStatus!.startsWith('Koneksi Berhasil')
                            ? AppColors.success
                            : AppColors.danger,
                      ),
                    ),
                  ),
                ],
              ],
            ),
            actions: [
              TextButton(
                onPressed: () async {
                  await ApiConfig.resetBaseUrl();
                  if (ctx.mounted) Navigator.pop(ctx, ApiConfig.baseUrl);
                },
                child: const Text('Reset Default'),
              ),
              FilledButton(
                onPressed: () {
                  Navigator.pop(ctx, controller.text.trim());
                },
                child: const Text('Simpan'),
              ),
            ],
          );
        },
      ),
    );

    if (hasil != null && hasil.isNotEmpty) {
      await ApiConfig.setBaseUrl(hasil);
      if (mounted) {
        setState(() {});
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Alamat server dialihkan ke: ${ApiConfig.baseUrl}'),
            duration: const Duration(seconds: 2),
          ),
        );
      }
    }
  }

  Widget _chipPreset(
    TextEditingController controller,
    String label,
    String url,
  ) {
    return ActionChip(
      label: Text(
        label,
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
      ),
      padding: EdgeInsets.zero,
      onPressed: () {
        controller.text = url;
      },
    );
  }
}

class HomeSementara extends StatelessWidget {
  final String role;

  const HomeSementara({super.key, required this.role});

  Future<void> _logout(BuildContext context) async {
    try {
      await AuthService().logout();
    } catch (_) {}
    if (!context.mounted) return;
    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (_) => const LoginPage()),
      (r) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.cream,
      appBar: AppBar(
        title: const Row(
          children: [
            BrandMark(size: 34, shadow: false),
            SizedBox(width: 10),
            Text(
              'Sistem Manajemen Toko',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
            ),
          ],
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: CircleActionButton(
              tooltip: 'Keluar',
              icon: Icons.logout_rounded,
              onPressed: () => _logout(context),
            ),
          ),
        ],
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 440),
          child: SurfaceCard(
            margin: const EdgeInsets.all(24),
            padding: const EdgeInsets.all(28),
            elevated: true,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 54,
                  height: 54,
                  decoration: BoxDecoration(
                    color: AppColors.surfaceSubtle,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: AppColors.line),
                  ),
                  child: const Icon(
                    Icons.verified_user_rounded,
                    color: AppColors.espresso,
                    size: 28,
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Autentikasi Terverifikasi',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 10),
                StatusPill(
                  label: 'Hak Akses: $role',
                  color: AppColors.espresso,
                  background: AppColors.sand,
                  icon: Icons.badge_outlined,
                ),
                const SizedBox(height: 16),
                const Text(
                  'Aplikasi mobile/tablet ini dioptimasi khusus untuk terminal kasir (POS).\nUntuk modul administratif, stok bahan, dan laporan laba-rugi, silakan buka Web Browser.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12.5,
                    height: 1.5,
                    color: AppColors.muted,
                  ),
                ),
                const SizedBox(height: 22),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: () => _logout(context),
                    icon: const Icon(Icons.logout_rounded, size: 18),
                    label: const Text('Keluar dari Sesi'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
