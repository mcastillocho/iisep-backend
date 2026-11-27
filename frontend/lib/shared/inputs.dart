import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

/// Formato de fecha de toda la app (dd/mm/aaaa, como se escribe en Perú).
final dateFmt = DateFormat('dd/MM/yyyy');

/// dd/MM/yyyy → fecha, o null si no es válida.
DateTime? parseDate(String text) {
  try {
    return dateFmt.parseStrict(text.trim());
  } catch (_) {
    return null;
  }
}

/// HH:mm → minutos desde medianoche, o null si no es válida.
int? parseTime(String text) {
  final m = RegExp(r'^([01]\d|2[0-3]):([0-5]\d)$').firstMatch(text.trim());
  if (m == null) return null;
  return int.parse(m.group(1)!) * 60 + int.parse(m.group(2)!);
}

/// Inserta las barras mientras se escribe: 25112026 → 25/11/2026.
class DateSlashFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
      TextEditingValue oldValue, TextEditingValue newValue) {
    final d = newValue.text.replaceAll(RegExp(r'[^0-9]'), '');
    final cut = d.length > 8 ? d.substring(0, 8) : d;
    final b = StringBuffer();
    for (var i = 0; i < cut.length; i++) {
      if (i == 2 || i == 4) b.write('/');
      b.write(cut[i]);
    }
    final s = b.toString();
    return TextEditingValue(
      text: s,
      selection: TextSelection.collapsed(offset: s.length),
    );
  }
}

/// Inserta los dos puntos mientras se escribe: 1830 → 18:30.
class TimeColonFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
      TextEditingValue oldValue, TextEditingValue newValue) {
    final d = newValue.text.replaceAll(RegExp(r'[^0-9]'), '');
    final cut = d.length > 4 ? d.substring(0, 4) : d;
    final s = cut.length > 2 ? '${cut.substring(0, 2)}:${cut.substring(2)}' : cut;
    return TextEditingValue(
      text: s,
      selection: TextSelection.collapsed(offset: s.length),
    );
  }
}
