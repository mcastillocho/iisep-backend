import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/asistencias/asistencias_page.dart';
import '../features/auth/auth_controller.dart';
import '../features/auth/login_page.dart';
import '../features/matriculas/aperturas_page.dart';
import '../features/matriculas/matriculas_page.dart';
import '../features/oferta/oferta_page.dart';
import '../features/resumen/resumen_page.dart';
import '../features/sedes/sedes_page.dart';
import '../features/shell/app_shell.dart';

/// Rutas del panel. `minLevel` es el nivel jerárquico mínimo del cargo
/// (ROLES_Y_PERMISOS.md): 1 Recepción · 2 Secretaría · 3 Coordinación · 4 Dirección.
class AppRoute {
  const AppRoute(this.path, this.label, this.minLevel);
  final String path;
  final String label;
  final int minLevel;

  static const asistencias = AppRoute('/asistencias', 'Asistencias', 1);
  static const matriculas = AppRoute('/matriculas', 'Matrículas', 2);
  static const aperturas = AppRoute('/aperturas', 'Apertura de cursos', 3);
  static const oferta = AppRoute('/oferta', 'Oferta académica', 4);
  static const sedes = AppRoute('/sedes', 'Sedes y aulas', 1);
  static const resumen = AppRoute('/resumen', 'Resumen y KPIs', 3);

  static const all = [asistencias, matriculas, aperturas, oferta, sedes, resumen];
}

/// Última página visitada. Al cambiar de tema se crea un GoRouter nuevo (para
/// que Flutter reconstruya todo desde cero) y este dato lo deja en la misma página.
String _lastLocation = '/login';

final routerProvider = Provider<GoRouter>((ref) {
  // Avisa al router cuando cambia la sesión sin reconstruirlo.
  final refresh = ValueNotifier<int>(0);
  ref.listen(authProvider, (_, _) => refresh.value++);
  // Sin onDispose: el GoRouter anterior sigue montado un instante tras
  // reemplazarlo por uno nuevo al cambiar de tema.

  String? guard(String location) {
    _lastLocation = location;
    final user = ref.read(authProvider).user;
    if (user == null) return location == '/login' ? null : '/login';
    if (location == '/login') return AppRoute.matriculas.path;
    final route = AppRoute.all.where((r) => r.path == location).firstOrNull;
    if (route != null && !user.hasLevel(route.minLevel)) {
      return AppRoute.sedes.path;
    }
    return null;
  }

  return GoRouter(
    initialLocation: _lastLocation,
    refreshListenable: refresh,
    redirect: (context, state) => guard(state.matchedLocation),
    routes: [
      GoRoute(path: '/login', builder: (_, _) => const LoginPage()),
      ShellRoute(
        builder: (_, state, child) =>
            AppShell(location: state.matchedLocation, child: child),
        routes: [
          GoRoute(
              path: AppRoute.asistencias.path,
              builder: (_, _) => const AsistenciasPage()),
          GoRoute(
              path: AppRoute.matriculas.path,
              builder: (_, _) => const MatriculasPage()),
          GoRoute(
              path: AppRoute.aperturas.path,
              builder: (_, _) => const AperturasPage()),
          GoRoute(
              path: AppRoute.oferta.path,
              builder: (_, _) => const OfertaPage()),
          GoRoute(
              path: AppRoute.sedes.path,
              builder: (_, _) => const SedesPage()),
          GoRoute(
              path: AppRoute.resumen.path,
              builder: (_, _) => const ResumenPage()),
        ],
      ),
    ],
  );
});
