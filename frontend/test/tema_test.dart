import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:iisep_app/core/theme.dart';
import 'package:iisep_app/core/theme_mode.dart';
import 'package:material_ui/material_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Relación de contraste WCAG entre dos colores (4.5 = AA texto normal).
double contraste(Color a, Color b) {
  final la = a.computeLuminance();
  final lb = b.computeLuminance();
  final hi = la > lb ? la : lb;
  final lo = la > lb ? lb : la;
  return (hi + 0.05) / (lo + 0.05);
}

void main() {
  tearDown(() => Tk.dark = false);

  group('Colores dinámicos', () {
    test('un mismo Color cambia de valor según el modo', () {
      Tk.dark = false;
      expect(Tk.ink.toARGB32(), 0xFF0F172A);
      expect(Tk.surface.toARGB32(), 0xFFFFFFFF);
      Tk.dark = true;
      expect(Tk.ink.toARGB32(), 0xFFE7EEE9);
      expect(Tk.surface.toARGB32(), 0xFF18211D);
    });

    test('sirven dentro de const (por eso no hubo que tocar cada pantalla)', () {
      const estilo = TextStyle(color: Tk.ink);
      Tk.dark = true;
      expect(estilo.color!.toARGB32(), 0xFFE7EEE9);
    });

    test('los rellenos con texto blanco no cambian de modo', () {
      Tk.dark = false;
      final claro = Tk.fill.toARGB32();
      Tk.dark = true;
      expect(Tk.fill.toARGB32(), claro);
      expect(Tk.dangerFill.toARGB32(), 0xFFB91C1C);
    });
  });

  group('Legibilidad (contraste mínimo)', () {
    for (final dark in [false, true]) {
      final modo = dark ? 'oscuro' : 'claro';

      test('texto principal y secundario sobre tarjetas y fondo, modo $modo', () {
        Tk.dark = dark;
        for (final fondo in [Tk.surface, Tk.canvas]) {
          expect(contraste(Tk.ink, fondo), greaterThanOrEqualTo(7), reason: 'ink');
          expect(contraste(Tk.inkSoft, fondo), greaterThanOrEqualTo(7), reason: 'inkSoft');
          expect(contraste(Tk.inkMute, fondo), greaterThanOrEqualTo(4.5), reason: 'inkMute');
        }
      });

      test('verde de acento y estados sobre su fondo, modo $modo', () {
        Tk.dark = dark;
        expect(contraste(Tk.primary, Tk.surface), greaterThanOrEqualTo(3.5), reason: 'primary');
        expect(contraste(Tk.primary, Tk.primarySoft), greaterThanOrEqualTo(3.5), reason: 'pill verde');
        expect(contraste(Tk.warn, Tk.warnSoft), greaterThanOrEqualTo(4.5), reason: 'pill ámbar');
        expect(contraste(Tk.danger, Tk.dangerSoft), greaterThanOrEqualTo(4.5), reason: 'pill rojo');
        expect(contraste(Tk.info, Tk.infoSoft), greaterThanOrEqualTo(4.5), reason: 'pill azul');
        expect(contraste(Tk.danger, Tk.surface), greaterThanOrEqualTo(4.5), reason: 'error');
      });

      test('texto blanco sobre botones rellenos, modo $modo', () {
        Tk.dark = dark;
        expect(contraste(Colors.white, Tk.fill), greaterThanOrEqualTo(3.9));
        expect(contraste(Colors.white, Tk.dangerFill), greaterThanOrEqualTo(6));
      });
    }
  });

  group('Preferencia de tema', () {
    test('sin dato guardado arranca en Automático', () async {
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();
      final c = ProviderContainer(
          overrides: [sharedPrefsProvider.overrideWithValue(prefs)]);
      addTearDown(c.dispose);
      expect(c.read(themeModeProvider), AppThemeMode.auto);
    });

    test('recuerda lo elegido', () async {
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();
      final c1 = ProviderContainer(
          overrides: [sharedPrefsProvider.overrideWithValue(prefs)]);
      await c1.read(themeModeProvider.notifier).set(AppThemeMode.dark);
      expect(c1.read(themeModeProvider), AppThemeMode.dark);
      c1.dispose();

      // «Otra visita»: nuevo contenedor, mismo almacenamiento.
      final c2 = ProviderContainer(
          overrides: [sharedPrefsProvider.overrideWithValue(prefs)]);
      addTearDown(c2.dispose);
      expect(c2.read(themeModeProvider), AppThemeMode.dark);
    });

    test('un valor guardado inválido se ignora', () async {
      SharedPreferences.setMockInitialValues({'iisep_theme_mode': 'rosa'});
      final prefs = await SharedPreferences.getInstance();
      final c = ProviderContainer(
          overrides: [sharedPrefsProvider.overrideWithValue(prefs)]);
      addTearDown(c.dispose);
      expect(c.read(themeModeProvider), AppThemeMode.auto);
    });

    test('sin almacenamiento disponible funciona solo en la sesión', () async {
      final c = ProviderContainer();
      addTearDown(c.dispose);
      await c.read(themeModeProvider.notifier).set(AppThemeMode.light);
      expect(c.read(themeModeProvider), AppThemeMode.light);
    });
  });
}
