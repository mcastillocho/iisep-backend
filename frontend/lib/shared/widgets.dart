import 'package:material_ui/material_ui.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/theme.dart';
import '../core/theme_mode.dart';

/// Ancho a partir del cual se usa barra lateral fija.
const kWideBreakpoint = 900.0;

/// Contenedor de página: título, subtítulo y contenido con ancho máximo.
class PageFrame extends StatelessWidget {
  const PageFrame({
    super.key,
    required this.title,
    this.subtitle,
    this.actions = const [],
    required this.child,
    this.scroll = true,
  });

  final String title;
  final String? subtitle;
  final List<Widget> actions;
  final Widget child;

  /// false cuando el hijo ya maneja su propio scroll (listas).
  final bool scroll;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    final wide = MediaQuery.sizeOf(context).width >= kWideBreakpoint;
    final pad = wide ? Tk.s5 : Tk.s4;

    // En pantalla angosta la barra verde ya muestra el título: aquí solo van
    // el subtítulo y las acciones, estas a todo el ancho (más fáciles de tocar).
    final header = !wide
        ? Padding(
            padding: const EdgeInsets.fromLTRB(Tk.s4, Tk.s3, Tk.s4, Tk.s3),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (subtitle != null)
                  Text(
                    subtitle!,
                    style: t.bodyMedium?.copyWith(color: Tk.inkMute),
                  ),
                for (final a in actions) ...[
                  const SizedBox(height: Tk.s3),
                  SizedBox(width: double.infinity, child: a),
                ],
              ],
            ),
          )
        : Padding(
            padding: EdgeInsets.fromLTRB(pad, pad, pad, Tk.s4),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: t.headlineSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                          color: Tk.ink,
                        ),
                      ),
                      if (subtitle != null) ...[
                        const SizedBox(height: Tk.s1),
                        Text(
                          subtitle!,
                          style: t.bodyMedium?.copyWith(color: Tk.inkMute),
                        ),
                      ],
                    ],
                  ),
                ),
                ...actions,
              ],
            ),
          );

    return Align(
      alignment: Alignment.topCenter,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 1200),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            header,
            Expanded(
              child: scroll
                  ? SingleChildScrollView(
                      padding: EdgeInsets.fromLTRB(pad, 0, pad, pad),
                      child: child,
                    )
                  : child,
            ),
          ],
        ),
      ),
    );
  }
}

/// Etiqueta pequeña de estado.
class Pill extends StatelessWidget {
  const Pill(
    this.label, {
    super.key,
    this.fg = Tk.primary,
    this.bg = Tk.primarySoft,
  });

  final String label;
  final Color fg;
  final Color bg;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
    decoration: BoxDecoration(
      color: bg,
      borderRadius: BorderRadius.circular(999),
    ),
    child: Text(
      label,
      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: fg),
    ),
  );
}

/// Muestra carga / error / datos de un FutureProvider.
class AsyncBody<T> extends StatelessWidget {
  const AsyncBody({
    super.key,
    required this.value,
    required this.onRetry,
    required this.builder,
  });

  final AsyncValue<T> value;
  final VoidCallback onRetry;
  final Widget Function(T data) builder;

  @override
  Widget build(BuildContext context) => value.when(
    loading: () => const Center(
      child: Padding(
        padding: EdgeInsets.all(Tk.s6),
        child: CircularProgressIndicator(color: Tk.primary),
      ),
    ),
    error: (e, _) => Center(
      child: Padding(
        padding: const EdgeInsets.all(Tk.s6),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off_outlined, size: 40, color: Tk.inkMute),
            const SizedBox(height: Tk.s3),
            const Text('No se pudieron cargar los datos.'),
            const SizedBox(height: Tk.s3),
            OutlinedButton(onPressed: onRetry, child: const Text('Reintentar')),
          ],
        ),
      ),
    ),
    data: builder,
  );
}

class EmptyState extends StatelessWidget {
  const EmptyState(this.message, {super.key, this.icon = Icons.inbox_outlined});
  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(Tk.s6),
    child: Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 40, color: Tk.inkMute),
          const SizedBox(height: Tk.s3),
          Text(message, style: const TextStyle(color: Tk.inkMute)),
        ],
      ),
    ),
  );
}

class SearchField extends StatelessWidget {
  const SearchField({super.key, required this.hint, required this.onChanged});
  final String hint;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => TextField(
    onChanged: onChanged,
    decoration: InputDecoration(
      hintText: hint,
      prefixIcon: const Icon(Icons.search, size: 20, color: Tk.inkMute),
    ),
  );
}

/// Quita tildes para búsquedas tolerantes ("matricula" encuentra "Matrícula").
String fold(String s) => s
    .toLowerCase()
    .replaceAll('á', 'a')
    .replaceAll('é', 'e')
    .replaceAll('í', 'i')
    .replaceAll('ó', 'o')
    .replaceAll('ú', 'u')
    .replaceAll('ñ', 'n');

/// Logo oficial del IISEP sobre fondo blanco (el archivo no es transparente).
class LogoMark extends StatelessWidget {
  const LogoMark({super.key, this.size = 64, this.bordered = false});

  final double size;
  final bool bordered;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(size * 0.18),
      border: bordered ? Border.all(color: Tk.line) : null,
    ),
    clipBehavior: Clip.antiAlias,
    child: Image.asset(
      'assets/images/logo_iisep.png',
      fit: BoxFit.contain,
      semanticLabel: 'Logo de CETPRO IISEP',
      filterQuality: FilterQuality.medium,
    ),
  );
}

/// Elige Automático / Claro / Oscuro. Vive en el menú.
class ThemeModeSwitch extends ConsumerWidget {
  const ThemeModeSwitch({super.key});

  static const _icons = {
    AppThemeMode.auto: Icons.brightness_auto_outlined,
    AppThemeMode.light: Icons.light_mode_outlined,
    AppThemeMode.dark: Icons.dark_mode_outlined,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(themeModeProvider);
    return Row(
      children: [
        const Text('Tema',
            style: TextStyle(
                fontSize: 13, fontWeight: FontWeight.w600, color: Tk.inkMute)),
        const SizedBox(width: Tk.s3),
        Expanded(
          child: SegmentedButton<AppThemeMode>(
            showSelectedIcon: false,
            style: const ButtonStyle(
              visualDensity: VisualDensity.compact,
              padding: WidgetStatePropertyAll(EdgeInsets.symmetric(horizontal: 4)),
            ),
            segments: [
              for (final m in AppThemeMode.values)
                ButtonSegment(
                  value: m,
                  tooltip: m.label,
                  icon: Icon(_icons[m], size: 18),
                ),
            ],
            selected: {mode},
            onSelectionChanged: (s) =>
                ref.read(themeModeProvider.notifier).set(s.first),
          ),
        ),
      ],
    );
  }
}

/// Un solo botón que recorre Automático → Claro → Oscuro (pantalla de ingreso).
class ThemeModeButton extends ConsumerWidget {
  const ThemeModeButton({super.key, this.onDark = false});

  /// true cuando está sobre un fondo oscuro fijo (el icono va en blanco).
  final bool onDark;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(themeModeProvider);
    final next = AppThemeMode.values[(mode.index + 1) % AppThemeMode.values.length];
    return IconButton(
      tooltip: 'Tema: ${mode.label} (tocar para cambiar a ${next.label})',
      onPressed: () => ref.read(themeModeProvider.notifier).set(next),
      color: onDark ? Colors.white : Tk.inkSoft,
      icon: Icon(switch (mode) {
        AppThemeMode.auto => Icons.brightness_auto_outlined,
        AppThemeMode.light => Icons.light_mode_outlined,
        AppThemeMode.dark => Icons.dark_mode_outlined,
      }),
    );
  }
}
