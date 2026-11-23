import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_widgets.dart';
import '../../../models/pembeli_model.dart';
import '../../../models/shift_model.dart';
import '../../../services/kasir_service.dart';
import '../../../utils/formatters.dart';

class ShiftDialogs {
  ShiftDialogs._();

  /// Dialog buka shift kasir dengan input modal awal.
  static Future<ShiftModel?> bukaShift(BuildContext context) async {
    final controller = TextEditingController(text: '0');
    bool loading = false;
    String? error;

    return showDialog<ShiftModel>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setState) {
            return AlertDialog(
              title: const Text('Buka Shift Kasir'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const InfoBanner(
                    message: 'Hitung uang di laci kasir saat ini, lalu masukkan sebagai modal awal untuk mulai melayani pelanggan.',
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: controller,
                    keyboardType: TextInputType.number,
                    autofocus: true,
                    decoration: const InputDecoration(
                      labelText: 'Modal Awal (Rp)',
                      prefixText: 'Rp ',
                    ),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 10),
                    InfoBanner(message: error!, tone: BannerTone.danger),
                  ],
                ],
              ),
              actions: [
                TextButton(
                  onPressed: loading ? null : () => Navigator.pop(ctx),
                  child: const Text('Batal'),
                ),
                FilledButton(
                  onPressed: loading
                      ? null
                      : () async {
                          final modal = double.tryParse(controller.text) ?? -1;
                          if (modal < 0) {
                            setState(() => error = 'Modal awal tidak valid.');
                            return;
                          }
                          setState(() {
                            loading = true;
                            error = null;
                          });
                          try {
                            final shift = await KasirService().bukaShift(modal);
                            if (ctx.mounted) Navigator.pop(ctx, shift);
                          } catch (e) {
                            if (ctx.mounted) {
                              setState(() {
                                loading = false;
                                error = e.toString();
                              });
                            }
                          }
                        },
                  child: Text(loading ? 'Membuka...' : 'Buka Shift'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  /// Dialog mencatat kas masuk / keluar laci.
  static Future<ShiftModel?> catatKas(BuildContext context, String tipe) async {
    final jumlahCtrl = TextEditingController();
    final ketCtrl = TextEditingController();
    bool loading = false;
    String? error;

    return showDialog<ShiftModel>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setState) {
            final isMasuk = tipe == 'masuk';
            return AlertDialog(
              title: Text(isMasuk ? 'Catat Kas Masuk' : 'Catat Kas Keluar'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    InfoBanner(
                      message: isMasuk
                          ? 'Uang yang ditambahkan ke laci di luar penjualan (mis. tambahan uang kembalian).'
                          : 'Uang yang diambil dari laci untuk operasional (mis. beli es batu atau galon).',
                      tone: isMasuk ? BannerTone.success : BannerTone.warning,
                      icon: isMasuk
                          ? Icons.south_west_rounded
                          : Icons.north_east_rounded,
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: jumlahCtrl,
                      keyboardType: TextInputType.number,
                      autofocus: true,
                      decoration: const InputDecoration(
                        labelText: 'Jumlah (Rp)',
                        prefixText: 'Rp ',
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: ketCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Keterangan / Alasan',
                      ),
                    ),
                    if (error != null) ...[
                      const SizedBox(height: 10),
                      InfoBanner(message: error!, tone: BannerTone.danger),
                    ],
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: loading ? null : () => Navigator.pop(ctx),
                  child: const Text('Batal'),
                ),
                FilledButton(
                  onPressed: loading
                      ? null
                      : () async {
                          final jumlah = double.tryParse(jumlahCtrl.text) ?? 0;
                          final ket = ketCtrl.text.trim();
                          if (jumlah <= 0) {
                            setState(
                              () =>
                                  error = 'Jumlah nominal harus lebih dari 0.',
                            );
                            return;
                          }
                          if (ket.isEmpty) {
                            setState(() => error = 'Keterangan wajib diisi.');
                            return;
                          }

                          setState(() {
                            loading = true;
                            error = null;
                          });
                          try {
                            final res = await KasirService().catatKas(
                              tipe: tipe,
                              jumlah: jumlah,
                              keterangan: ket,
                            );
                            if (ctx.mounted) Navigator.pop(ctx, res);
                          } catch (e) {
                            if (ctx.mounted) {
                              setState(() {
                                loading = false;
                                error = e.toString();
                              });
                            }
                          }
                        },
                  child: Text(loading ? 'Menyimpan...' : 'Simpan'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  /// Dialog tutup shift kasir dengan pencocokan kas fisik laci.
  static Future<ShiftModel?> tutupShift(
    BuildContext context,
    ShiftModel shift,
  ) async {
    final uangCtrl = TextEditingController();
    final catCtrl = TextEditingController();
    bool loading = false;
    String? error;
    double? selisih;

    final rekap = shift.rekap;
    final kasSeharusnya = rekap?.kasSeharusnya ?? shift.modalAwal;

    return showDialog<ShiftModel>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setState) {
            void updateSelisih(String val) {
              final d = double.tryParse(val);
              setState(() {
                if (d != null) {
                  selisih = d - kasSeharusnya;
                } else {
                  selisih = null;
                }
              });
            }

            return AlertDialog(
              title: const Text('Tutup Shift Kasir'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Container(
                      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                      decoration: BoxDecoration(
                        color: AppColors.cream,
                        borderRadius: AppRadius.allMedium,
                        border: Border.all(color: AppColors.line),
                      ),
                      child: Column(
                        children: [
                          _rowRekap(
                            'Modal Awal',
                            Formatters.rupiah(shift.modalAwal),
                          ),
                          if (rekap != null) ...[
                            _rowRekap(
                              'Penjualan Tunai',
                              '+${Formatters.rupiah(rekap.penjualanTunai)}',
                            ),
                            if (rekap.kasMasuk > 0)
                              _rowRekap(
                                'Kas Masuk',
                                '+${Formatters.rupiah(rekap.kasMasuk)}',
                              ),
                            if (rekap.kasKeluar > 0)
                              _rowRekap(
                                'Kas Keluar',
                                '-${Formatters.rupiah(rekap.kasKeluar)}',
                              ),
                            const Padding(
                              padding: EdgeInsets.symmetric(vertical: 7),
                              child: Divider(height: 1),
                            ),
                            _rowRekap(
                              'Kas Seharusnya',
                              Formatters.rupiah(kasSeharusnya),
                              bold: true,
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: uangCtrl,
                      keyboardType: TextInputType.number,
                      autofocus: true,
                      onChanged: updateSelisih,
                      decoration: const InputDecoration(
                        labelText: 'Uang Fisik Dihitung di Laci',
                        prefixText: 'Rp ',
                      ),
                    ),
                    if (selisih != null) ...[
                      const SizedBox(height: 10),
                      InfoBanner(
                        message:
                            '${selisih == 0 ? 'Kas pas' : (selisih! < 0 ? 'Uang kurang' : 'Uang lebih')} · ${Formatters.rupiah(selisih!.abs())}',
                        tone: selisih == 0
                            ? BannerTone.success
                            : (selisih! < 0
                                  ? BannerTone.danger
                                  : BannerTone.info),
                        icon: selisih == 0
                            ? Icons.check_circle_outline_rounded
                            : (selisih! < 0
                                  ? Icons.trending_down_rounded
                                  : Icons.trending_up_rounded),
                      ),
                    ],
                    const SizedBox(height: 12),
                    TextField(
                      controller: catCtrl,
                      maxLines: 2,
                      decoration: const InputDecoration(
                        labelText: 'Catatan (opsional)',
                      ),
                    ),
                    if (error != null) ...[
                      const SizedBox(height: 10),
                      InfoBanner(message: error!, tone: BannerTone.danger),
                    ],
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: loading ? null : () => Navigator.pop(ctx),
                  child: const Text('Batal'),
                ),
                FilledButton(
                  onPressed: loading
                      ? null
                      : () async {
                          final uang = double.tryParse(uangCtrl.text);
                          if (uang == null || uang < 0) {
                            setState(
                              () => error = 'Masukkan uang yang dihitung.',
                            );
                            return;
                          }
                          setState(() {
                            loading = true;
                            error = null;
                          });
                          try {
                            final res = await KasirService().tutupShift(
                              uangDihitung: uang,
                              catatan: catCtrl.text.trim(),
                            );
                            if (ctx.mounted) Navigator.pop(ctx, res);
                          } catch (e) {
                            if (ctx.mounted) {
                              setState(() {
                                loading = false;
                                error = e.toString();
                              });
                            }
                          }
                        },
                  child: Text(loading ? 'Menutup...' : 'Tutup Shift'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  /// Dialog tambah pembeli baru dari kasir.
  static Future<PembeliModel?> tambahPembeli(BuildContext context) async {
    final namaCtrl = TextEditingController();
    final telpCtrl = TextEditingController();
    final alamatCtrl = TextEditingController();
    bool loading = false;
    String? error;

    return showDialog<PembeliModel>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setState) {
            return AlertDialog(
              title: const Text('Tambah Pembeli'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextField(
                      controller: namaCtrl,
                      autofocus: true,
                      decoration: const InputDecoration(
                        labelText: 'Nama Lengkap *',
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: telpCtrl,
                      keyboardType: TextInputType.phone,
                      decoration: const InputDecoration(
                        labelText: 'Nomor WhatsApp / HP',
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: alamatCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Alamat (opsional)',
                      ),
                    ),
                    if (error != null) ...[
                      const SizedBox(height: 10),
                      InfoBanner(message: error!, tone: BannerTone.danger),
                    ],
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: loading ? null : () => Navigator.pop(ctx),
                  child: const Text('Batal'),
                ),
                FilledButton(
                  onPressed: loading
                      ? null
                      : () async {
                          final nama = namaCtrl.text.trim();
                          if (nama.isEmpty) {
                            setState(() => error = 'Nama wajib diisi.');
                            return;
                          }
                          setState(() {
                            loading = true;
                            error = null;
                          });
                          try {
                            final res = await KasirService().tambahPembeli(
                              nama: nama,
                              telepon: telpCtrl.text.trim(),
                              alamat: alamatCtrl.text.trim(),
                            );
                            if (ctx.mounted) Navigator.pop(ctx, res);
                          } catch (e) {
                            if (ctx.mounted) {
                              setState(() {
                                loading = false;
                                error = e.toString();
                              });
                            }
                          }
                        },
                  child: Text(loading ? 'Menyimpan...' : 'Simpan'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  static Widget _rowRekap(String left, String right, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            left,
            style: TextStyle(
              fontSize: 13,
              fontWeight: bold ? FontWeight.bold : FontWeight.normal,
            ),
          ),
          Text(
            right,
            style: TextStyle(
              fontSize: 13,
              fontWeight: bold ? FontWeight.bold : FontWeight.normal,
            ),
          ),
        ],
      ),
    );
  }
}
