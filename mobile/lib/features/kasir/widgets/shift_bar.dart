import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/shift_model.dart';
import '../../../utils/formatters.dart';

class ShiftBar extends StatelessWidget {
  final ShiftModel? shift;
  final VoidCallback onBukaShift;
  final VoidCallback onKasMasuk;
  final VoidCallback onKasKeluar;
  final VoidCallback onTutupShift;

  const ShiftBar({
    super.key,
    required this.shift,
    required this.onBukaShift,
    required this.onKasMasuk,
    required this.onKasKeluar,
    required this.onTutupShift,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 8, 14, 6),
      child: shift == null ? _buildBelumBuka(context) : _buildAktif(context),
    );
  }

  Widget _buildBelumBuka(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.warningSoft,
        borderRadius: AppRadius.allMedium,
        border: Border.all(color: AppColors.warningBorder, width: 1.2),
      ),
      child: Row(
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: AppColors.warningBorder),
            ),
            child: const Icon(
              Icons.lock_clock_rounded,
              size: 18,
              color: AppColors.warning,
            ),
          ),
          const SizedBox(width: 10),
          const Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Shift kasir belum dibuka',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w800,
                    color: AppColors.warning,
                  ),
                ),
                SizedBox(height: 1),
                Text(
                  'Buka shift kasir terlebih dahulu untuk mulai melayani transaksi.',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 11,
                    color: AppColors.warning,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          FilledButton.icon(
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.warning,
              foregroundColor: AppColors.white,
              minimumSize: const Size(0, 36),
              padding: const EdgeInsets.symmetric(horizontal: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
              textStyle: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
            onPressed: onBukaShift,
            icon: const Icon(Icons.lock_open_rounded, size: 15),
            label: const Text('Buka Shift'),
          ),
        ],
      ),
    );
  }

  Widget _buildAktif(BuildContext context) {
    final rekap = shift!.rekap;

    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 720;

        if (isWide) {
          // Tampilan Desktop / Tablet: Baris Horizontal Ramping & Terintegrasi
          return Container(
            height: 48,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: AppRadius.allMedium,
              border: Border.all(color: AppColors.line),
              boxShadow: AppColors.softShadow,
            ),
            child: Row(
              children: [
                // Status Shift & Jam
                Container(
                  width: 8,
                  height: 8,
                  decoration: const BoxDecoration(
                    color: AppColors.success,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  'Shift Aktif (${Formatters.jam(shift!.dibukaPada)})',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                  ),
                ),

                const SizedBox(width: 14),
                _divider(),
                const SizedBox(width: 14),

                // Ringkasan Statistik
                _compactStat('Modal Awal', Formatters.rupiah(shift!.modalAwal)),
                const SizedBox(width: 12),
                _divider(),
                const SizedBox(width: 12),
                _compactStat('Transaksi', '${rekap?.jumlahTransaksi ?? 0}'),
                const SizedBox(width: 12),
                _divider(),
                const SizedBox(width: 12),
                _compactStat(
                  'Penjualan',
                  Formatters.rupiah(rekap?.totalPenjualan ?? 0),
                  isHighlight: true,
                ),

                const Spacer(),

                // Tombol Aksi Operasional Kas
                _buttonKas(
                  icon: Icons.add_circle_outline_rounded,
                  label: 'Kas Masuk',
                  color: AppColors.success,
                  bgColor: AppColors.successSoft,
                  onPressed: onKasMasuk,
                ),
                const SizedBox(width: 6),
                _buttonKas(
                  icon: Icons.remove_circle_outline_rounded,
                  label: 'Kas Keluar',
                  color: AppColors.danger,
                  bgColor: AppColors.dangerSoft,
                  onPressed: onKasKeluar,
                ),
                const SizedBox(width: 8),
                _divider(),
                const SizedBox(width: 8),

                // Tombol Tutup Shift
                Tooltip(
                  message: 'Tutup sesi shift kasir',
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.espresso,
                      backgroundColor: AppColors.sand,
                      side: const BorderSide(color: AppColors.line),
                      minimumSize: const Size(0, 32),
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(6),
                      ),
                      textStyle: const TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    onPressed: onTutupShift,
                    icon: const Icon(Icons.lock_outline_rounded, size: 14),
                    label: const Text('Tutup Shift'),
                  ),
                ),
              ],
            ),
          );
        }

        // Tampilan Mobile: 2 Baris Kompak
        return Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: AppRadius.allMedium,
            border: Border.all(color: AppColors.line),
            boxShadow: AppColors.softShadow,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Container(
                    width: 7,
                    height: 7,
                    decoration: const BoxDecoration(
                      color: AppColors.success,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    'Shift Aktif · ${Formatters.jam(shift!.dibukaPada)}',
                    style: const TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w800,
                      color: AppColors.ink,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    'Omzet ${Formatters.rupiah(rekap?.totalPenjualan ?? 0)}',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                      color: AppColors.espresso,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: _buttonKas(
                      icon: Icons.add_circle_outline_rounded,
                      label: 'Kas Masuk',
                      color: AppColors.success,
                      bgColor: AppColors.successSoft,
                      onPressed: onKasMasuk,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: _buttonKas(
                      icon: Icons.remove_circle_outline_rounded,
                      label: 'Kas Keluar',
                      color: AppColors.danger,
                      bgColor: AppColors.dangerSoft,
                      onPressed: onKasKeluar,
                    ),
                  ),
                  const SizedBox(width: 6),
                  IconButton(
                    tooltip: 'Tutup Shift',
                    style: IconButton.styleFrom(
                      backgroundColor: AppColors.sand,
                      foregroundColor: AppColors.espresso,
                      minimumSize: const Size(32, 32),
                      padding: EdgeInsets.zero,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(6),
                        side: const BorderSide(color: AppColors.line),
                      ),
                    ),
                    onPressed: onTutupShift,
                    icon: const Icon(Icons.lock_outline_rounded, size: 15),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _compactStat(String label, String value, {bool isHighlight = false}) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          '$label: ',
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w600,
            color: AppColors.muted,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.2,
            color: isHighlight ? AppColors.espresso : AppColors.ink,
          ),
        ),
      ],
    );
  }

  Widget _divider() {
    return Container(width: 1, height: 18, color: AppColors.line);
  }

  Widget _buttonKas({
    required IconData icon,
    required String label,
    required Color color,
    required Color bgColor,
    required VoidCallback onPressed,
  }) {
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(6),
      child: Container(
        height: 32,
        padding: const EdgeInsets.symmetric(horizontal: 10),
        decoration: BoxDecoration(
          color: bgColor,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: color.withValues(alpha: 0.25)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 14, color: color),
            const SizedBox(width: 5),
            Text(
              label,
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
