import 'dart:ui' show ColorSpace;

import 'package:google_fonts/google_fonts.dart';
import 'package:material_ui/material_ui.dart';

/// Color que vale distinto en modo claro y oscuro.
///
/// Es un `Color` real (se puede usar en cualquier `const`), pero sus canales se
/// leen de [Tk.dark] cada vez que se pintan. Por eso cambiar de tema exige
/// reconstruir todo el árbol de widgets (ver `IisepApp`, que cambia su `key`):
/// los widgets `const` guardan el mismo objeto y no se repintarían solos.
class DynColor extends Color {
  const DynColor(this.light, this.dark) : super(0);

  final Color light;
  final Color dark;

  Color get _now => Tk.dark ? dark : light;

  @override
  double get a => _now.a;
  @override
  double get r => _now.r;
  @override
  double get g => _now.g;
  @override
  double get b => _now.b;
  @override
  ColorSpace get colorSpace => _now.colorSpace;
}

/// Tokens visuales del IISEP (verde institucional + estilo sobrio tipo
/// Mercury/Vercel de styles.refero.design). Fuente de verdad de los colores
/// del backend: ARQUITECTURA_ADMIN.md, sección 4.
class Tk {
  Tk._();

  /// Modo activo. Lo fija `IisepApp` antes de construir la interfaz.
  static bool dark = false;

  // Marca. `primary` es para texto e íconos; `fill` para fondos con texto
  // blanco encima (botones, barra superior): su verde no cambia de modo.
  static const primary = DynColor(Color(0xFF2E8B57), Color(0xFF4CBF86));
  static const fill = Color(0xFF2E8B57);
  static const primarySoft = DynColor(Color(0xFFECFDF5), Color(0xFF173A2B));
  static const primaryLine = DynColor(Color(0xFFA7F3D0), Color(0xFF1F5A40));

  // Superficies
  static const canvas = DynColor(Color(0xFFF4F7F5), Color(0xFF0F1512));
  static const surface = DynColor(Color(0xFFFFFFFF), Color(0xFF18211D));
  static const line = DynColor(Color(0xFFE2E8F0), Color(0xFF2B3832));

  // Texto
  static const ink = DynColor(Color(0xFF0F172A), Color(0xFFE7EEE9));
  static const inkSoft = DynColor(Color(0xFF334155), Color(0xFFC2CCC6));
  static const inkMute = DynColor(Color(0xFF5B6B7F), Color(0xFF8D9D94));

  // Estados
  static const warn = DynColor(Color(0xFFB45309), Color(0xFFF2B35E));
  static const warnSoft = DynColor(Color(0xFFFEF3C7), Color(0xFF3A2B12));
  static const danger = DynColor(Color(0xFFB91C1C), Color(0xFFF28B82));
  static const dangerFill = Color(0xFFB91C1C); // fondo con texto blanco
  static const dangerSoft = DynColor(Color(0xFFFEE2E2), Color(0xFF3D1C1C));
  static const info = DynColor(Color(0xFF1D4ED8), Color(0xFF8AB4F8));
  static const infoSoft = DynColor(Color(0xFFDBEAFE), Color(0xFF15294A));

  // Espaciado (base 4)
  static const s1 = 4.0, s2 = 8.0, s3 = 12.0, s4 = 16.0, s5 = 24.0, s6 = 32.0;

  static const radius = 12.0;
  static const radiusSm = 8.0;
}

ThemeData buildTheme({required bool dark}) {
  Tk.dark = dark;

  final scheme = ColorScheme.fromSeed(
    seedColor: const Color(0xFF2E8B57),
    brightness: dark ? Brightness.dark : Brightness.light,
  ).copyWith(
    primary: Tk.fill,
    onPrimary: Colors.white,
    surface: Tk.surface,
    onSurface: Tk.ink,
    onSurfaceVariant: Tk.inkMute,
    outline: Tk.line,
    outlineVariant: Tk.line,
    surfaceTint: Colors.transparent,
    surfaceContainerLowest: Tk.canvas,
    surfaceContainerLow: Tk.surface,
    surfaceContainer: Tk.surface,
    surfaceContainerHigh: Tk.surface,
    surfaceContainerHighest: Tk.line,
    secondaryContainer: Tk.primarySoft,
    onSecondaryContainer: Tk.primary,
    error: Tk.danger,
  );
  final text = GoogleFonts.interTextTheme().apply(
    bodyColor: Tk.inkSoft,
    displayColor: Tk.ink,
  );

  OutlineInputBorder border(Color c, [double w = 1]) => OutlineInputBorder(
        borderRadius: BorderRadius.circular(Tk.radiusSm),
        borderSide: BorderSide(color: c, width: w),
      );

  return ThemeData(
    useMaterial3: true,
    brightness: dark ? Brightness.dark : Brightness.light,
    colorScheme: scheme,
    scaffoldBackgroundColor: Tk.canvas,
    canvasColor: Tk.surface,
    textTheme: text,
    dividerColor: Tk.line,
    dialogTheme: const DialogThemeData(
      backgroundColor: Tk.surface,
      surfaceTintColor: Colors.transparent,
    ),
    drawerTheme: const DrawerThemeData(
      backgroundColor: Tk.surface,
      surfaceTintColor: Colors.transparent,
    ),
    popupMenuTheme: const PopupMenuThemeData(
      color: Tk.surface,
      surfaceTintColor: Colors.transparent,
    ),
    cardTheme: CardThemeData(
      color: Tk.surface,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(Tk.radius),
        side: const BorderSide(color: Tk.line),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Tk.surface,
      isDense: true,
      contentPadding:
          const EdgeInsets.symmetric(horizontal: Tk.s3, vertical: 14),
      border: border(Tk.line),
      enabledBorder: border(Tk.line),
      focusedBorder: border(Tk.primary, 1.6),
      errorBorder: border(Tk.danger),
      focusedErrorBorder: border(Tk.danger, 1.6),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: Tk.fill,
        foregroundColor: Colors.white,
        minimumSize: const Size(0, 46),
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Tk.radiusSm)),
        textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: Tk.inkSoft,
        side: const BorderSide(color: Tk.line),
        minimumSize: const Size(0, 46),
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Tk.radiusSm)),
      ),
    ),
    textButtonTheme: const TextButtonThemeData(
      style: ButtonStyle(foregroundColor: WidgetStatePropertyAll(Tk.primary)),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: Tk.surface,
      selectedColor: Tk.primarySoft,
      side: const BorderSide(color: Tk.line),
      labelStyle: const TextStyle(fontSize: 13, color: Tk.inkSoft),
      shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Tk.radiusSm)),
    ),
    tabBarTheme: const TabBarThemeData(
      labelColor: Tk.primary,
      unselectedLabelColor: Tk.inkMute,
      indicatorColor: Tk.primary,
      dividerColor: Tk.line,
      labelStyle: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
    ),
  );
}
