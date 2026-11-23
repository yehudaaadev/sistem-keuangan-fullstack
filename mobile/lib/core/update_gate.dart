import 'package:flutter/material.dart';

import 'app_updater.dart';

/// Membungkus layar awal. Saat dibuka, mengecek update dan
/// menampilkan dialog jika ada versi baru.
class UpdateGate extends StatefulWidget {
  final Widget child;

  const UpdateGate({super.key, required this.child});

  @override
  State<UpdateGate> createState() => _UpdateGateState();
}

class _UpdateGateState extends State<UpdateGate> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _cek());
  }

  Future<void> _cek() async {
    final update = await AppUpdater.cekUpdate();
    if (update == null || !mounted) return;
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _DialogUpdate(update: update),
    );
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

class _DialogUpdate extends StatefulWidget {
  final InfoUpdate update;

  const _DialogUpdate({required this.update});

  @override
  State<_DialogUpdate> createState() => _DialogUpdateState();
}

class _DialogUpdateState extends State<_DialogUpdate> {
  bool _sedangUnduh = false;
  double _progres = 0;
  String? _pesanError;

  Future<void> _update() async {
    setState(() {
      _sedangUnduh = true;
      _pesanError = null;
    });
    try {
      await AppUpdater.unduhDanPasang(
        widget.update,
        onProgres: (p) {
          if (mounted) setState(() => _progres = p);
        },
      );
      if (mounted) Navigator.of(context).pop();
    } catch (e) {
      if (mounted) {
        setState(() {
          _sedangUnduh = false;
          _pesanError = 'Gagal mengunduh update. Coba lagi nanti.';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final u = widget.update;
    return AlertDialog(
      title: Text('Update tersedia ${u.versionName}'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (u.catatan.isNotEmpty) Text(u.catatan),
          if (_sedangUnduh) ...[
            const SizedBox(height: 16),
            LinearProgressIndicator(value: _progres > 0 ? _progres : null),
            const SizedBox(height: 8),
            Text('Mengunduh ${(_progres * 100).toStringAsFixed(0)}%'),
          ],
          if (_pesanError != null) ...[
            const SizedBox(height: 12),
            Text(_pesanError!, style: const TextStyle(color: Colors.red)),
          ],
        ],
      ),
      actions: [
        if (!_sedangUnduh)
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Nanti'),
          ),
        if (!_sedangUnduh)
          FilledButton(
            onPressed: _update,
            child: const Text('Update'),
          ),
      ],
    );
  }
}
