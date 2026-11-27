import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:material_ui/material_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'core/router.dart';
import 'core/theme.dart';
import 'core/theme_mode.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('es');
  SharedPreferences? prefs;
  try {
    prefs = await SharedPreferences.getInstance();
  } catch (_) {
    prefs = null; // almacenamiento bloqueado: el tema no se recuerda
  }
  runApp(ProviderScope(
    overrides: [sharedPrefsProvider.overrideWithValue(prefs)],
    child: const IisepApp(),
  ));
}

class IisepApp extends ConsumerStatefulWidget {
  const IisepApp({super.key});

  @override
  ConsumerState<IisepApp> createState() => _IisepAppState();
}

class _IisepAppState extends ConsumerState<IisepApp>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// El dispositivo cambió entre claro y oscuro (modo Automático).
  @override
  void didChangePlatformBrightness() {
    ref.invalidate(routerProvider); // reconstrucción completa (ver abajo)
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    // Los colores de `Tk` se leen al pintar y los textos los guardan ya
    // resueltos: al cambiar de tema hay que reconstruir TODO desde cero. El
    // GoRouter conserva su navegador (y con él todas las pantallas) mientras
    // exista, así que se crea uno nuevo; la sesión y los datos viven en
    // providers y no se pierden, y `_lastLocation` mantiene la página.
    ref.listen(themeModeProvider, (_, _) => ref.invalidate(routerProvider));
    final mode = ref.watch(themeModeProvider);
    final system = WidgetsBinding.instance.platformDispatcher.platformBrightness;
    final dark = mode == AppThemeMode.dark ||
        (mode == AppThemeMode.auto && system == Brightness.dark);

    final theme = buildTheme(dark: dark); // también fija Tk.dark

    // La key reconstruye todo el árbol al cambiar de tema: los colores de
    // `Tk` se leen al pintar y los widgets `const` no se repintarían solos.
    return KeyedSubtree(
      key: ValueKey(dark),
      child: MaterialApp.router(
        title: 'CETPRO IISEP',
        debugShowCheckedModeBanner: false,
        theme: theme,
        routerConfig: ref.watch(routerProvider),
      ),
    );
  }
}
