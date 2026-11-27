import 'package:material_ui/material_ui.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/router.dart';
import '../../core/theme.dart';
import '../../shared/widgets.dart';
import '../auth/auth_controller.dart';

const _icons = <String, IconData>{
  '/asistencias': Icons.fact_check_outlined,
  '/matriculas': Icons.how_to_reg_outlined,
  '/aperturas': Icons.event_available_outlined,
  '/oferta': Icons.menu_book_outlined,
  '/sedes': Icons.apartment_outlined,
  '/resumen': Icons.insights_outlined,
};

class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.location, required this.child});

  final String location;
  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).user;
    if (user == null) return const SizedBox.shrink();

    final items =
        AppRoute.all.where((r) => user.hasLevel(r.minLevel)).toList();
    final wide = MediaQuery.sizeOf(context).width >= kWideBreakpoint;
    final current = items.where((r) => r.path == location).firstOrNull;

    Widget menu({required bool inDrawer}) => _Menu(
          items: items,
          location: location,
          onSelect: (r) {
            if (inDrawer) Navigator.of(context).pop();
            context.go(r.path);
          },
          onLogout: () => ref.read(authProvider.notifier).logout(),
        );

    if (wide) {
      return Scaffold(
        body: Row(
          children: [
            SizedBox(width: 264, child: menu(inDrawer: false)),
            const VerticalDivider(width: 1),
            Expanded(child: child),
          ],
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        backgroundColor: Tk.fill,
        foregroundColor: Colors.white,
        title: Text(current?.label ?? 'IISEP',
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
      ),
      drawer: Drawer(child: menu(inDrawer: true)),
      body: child,
    );
  }
}

class _Menu extends ConsumerWidget {
  const _Menu({
    required this.items,
    required this.location,
    required this.onSelect,
    required this.onLogout,
  });

  final List<AppRoute> items;
  final String location;
  final ValueChanged<AppRoute> onSelect;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).user!;
    return Material(
      color: Tk.surface,
      child: SafeArea(
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(Tk.s4),
              decoration: const BoxDecoration(
                border: Border(bottom: BorderSide(color: Tk.line)),
              ),
              child: const Row(
                children: [
                  LogoMark(size: 56),
                  SizedBox(width: Tk.s3),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('CETPRO IISEP',
                            style: TextStyle(
                                color: Tk.ink,
                                fontSize: 16,
                                fontWeight: FontWeight.w700)),
                        Text('Panel administrativo',
                            style: TextStyle(fontSize: 12, color: Tk.inkMute)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(Tk.s3),
                children: [
                  for (final r in items)
                    _Item(
                      icon: _icons[r.path] ?? Icons.circle_outlined,
                      label: r.label,
                      selected: r.path == location,
                      onTap: () => onSelect(r),
                    ),
                ],
              ),
            ),
            const Divider(height: 1),
            const Padding(
              padding: EdgeInsets.fromLTRB(Tk.s3, Tk.s3, Tk.s3, 0),
              child: ThemeModeSwitch(),
            ),
            Padding(
              padding: const EdgeInsets.all(Tk.s3),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 18,
                    backgroundColor: Tk.primarySoft,
                    child: Text(user.initials,
                        style: const TextStyle(
                            color: Tk.primary,
                            fontSize: 13,
                            fontWeight: FontWeight.w700)),
                  ),
                  const SizedBox(width: Tk.s3),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(user.fullName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                                color: Tk.ink)),
                        Text(
                            '${user.position.label} · ${user.branchName ?? 'Todas las sedes'}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                                fontSize: 12, color: Tk.inkMute)),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Cerrar sesión',
                    onPressed: onLogout,
                    icon: const Icon(Icons.logout, size: 20),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Item extends StatelessWidget {
  const _Item({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: Tk.s1),
        child: Material(
          color: selected ? Tk.primarySoft : Colors.transparent,
          borderRadius: BorderRadius.circular(Tk.radiusSm),
          child: InkWell(
            borderRadius: BorderRadius.circular(Tk.radiusSm),
            onTap: onTap,
            child: Padding(
              padding:
                  const EdgeInsets.symmetric(horizontal: Tk.s3, vertical: 12),
              child: Row(
                children: [
                  Icon(icon,
                      size: 20, color: selected ? Tk.primary : Tk.inkMute),
                  const SizedBox(width: Tk.s3),
                  Text(label,
                      style: TextStyle(
                          fontSize: 14,
                          fontWeight:
                              selected ? FontWeight.w600 : FontWeight.w500,
                          color: selected ? Tk.primary : Tk.inkSoft)),
                ],
              ),
            ),
          ),
        ),
      );
}
