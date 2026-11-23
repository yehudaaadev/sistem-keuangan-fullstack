import 'package:intl/intl.dart';

class Formatters {
  Formatters._();

  static final NumberFormat _rupiahFormat = NumberFormat.currency(
    locale: 'id_ID',
    symbol: 'Rp ',
    decimalDigits: 0,
  );

  static final NumberFormat _angkaFormat = NumberFormat.decimalPattern('id_ID');

  static String rupiah(dynamic value) {
    if (value == null) return 'Rp 0';
    final numVal =
        value is num ? value : double.tryParse(value.toString()) ?? 0.0;
    return _rupiahFormat.format(numVal);
  }

  static String angka(dynamic value) {
    if (value == null) return '0';
    final numVal =
        value is num ? value : double.tryParse(value.toString()) ?? 0;
    if (numVal is double && numVal == numVal.toInt()) {
      return _angkaFormat.format(numVal.toInt());
    }
    return _angkaFormat.format(numVal);
  }

  static String tanggalWaktu(dynamic value) {
    if (value == null) return '-';
    try {
      final dt = (value is DateTime ? value : DateTime.parse(value.toString()))
          .toLocal();
      final tgl = dt.day.toString().padLeft(2, '0');
      final bln = dt.month.toString().padLeft(2, '0');
      final thn = dt.year.toString();
      final jam = dt.hour.toString().padLeft(2, '0');
      final mnt = dt.minute.toString().padLeft(2, '0');
      return '$tgl/$bln/$thn $jam:$mnt WIB';
    } catch (_) {
      return value.toString();
    }
  }

  static String jam(dynamic value) {
    if (value == null) return '-';
    try {
      final dt = (value is DateTime ? value : DateTime.parse(value.toString()))
          .toLocal();
      final j = dt.hour.toString().padLeft(2, '0');
      final m = dt.minute.toString().padLeft(2, '0');
      return '$j:$m WIB';
    } catch (_) {
      return value.toString();
    }
  }
}
