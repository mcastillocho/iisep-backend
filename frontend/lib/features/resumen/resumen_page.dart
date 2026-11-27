import 'package:material_ui/material_ui.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../shared/widgets.dart';
import '../auth/auth_controller.dart';

/// KPIs calculados en el cliente a partir de los mismos datos de las demás
/// pantallas. Cuando exista /admin/resumen en el backend se reemplaza.
class ResumenPage extends ConsumerWidget {
  const ResumenPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).user!;
    final branches = ref.watch(branchesProvider);
    final enrollments = ref.watch(enrollmentsProvider);
    final scheduled = ref.watch(scheduledCoursesProvider);

    final loading = branches.isLoading || enrollments.isLoading || scheduled.isLoading;
    final failed = branches.hasError || enrollments.hasError || scheduled.hasError;

    return PageFrame(
      title: 'Resumen y KPIs',
      subtitle: user.isGlobal ? 'Todas las sedes' : user.branchName,
      child: loading
          ? const Center(
              child: Padding(
                padding: EdgeInsets.all(Tk.s6),
                child: CircularProgressIndicator(color: Tk.primary),
              ),
            )
          : failed
              ? Center(
                  child: OutlinedButton(
                    onPressed: () {
                      ref.invalidate(branchesProvider);
                      ref.invalidate(enrollmentsProvider);
                      ref.invalidate(scheduledCoursesProvider);
                    },
                    child: const Text('Reintentar'),
                  ),
                )
              : _Kpis(
                  branches: branches.requireValue
                      .where((b) => user.isGlobal || b.id == user.branchId)
                      .toList(),
                  enrollments: enrollments.requireValue
                      .where((e) =>
                          user.isGlobal || e.branchName == user.branchName)
                      .toList(),
                  scheduled: scheduled.requireValue
                      .where((s) =>
                          user.isGlobal || s.branchName == user.branchName)
                      .toList(),
                ),
    );
  }
}

class _Kpis extends StatelessWidget {
  const _Kpis({
    required this.branches,
    required this.enrollments,
    required this.scheduled,
  });

  final List<Branch> branches;
  final List<Enrollment> enrollments;
  final List<ScheduledCourse> scheduled;

  @override
  Widget build(BuildContext context) {
    final now = DateTime.now();
    final aulas = branches.fold(0, (s, b) => s + b.classrooms.length);
    final activas =
        enrollments.where((e) => e.status == EnrollmentStatus.activo).length;
    final enCurso =
        scheduled.where((s) => s.stateAt(now) == ScheduleState.enCurso).length;
    final cap = scheduled.fold(0, (s, x) => s + x.capacity);
    final ocup = scheduled.fold(0, (s, x) => s + x.enrolled);
    final pct = cap == 0 ? 0 : (ocup * 100 / cap).round();

    final tiles = [
      _Kpi('Matrículas activas', '$activas', Icons.how_to_reg_outlined),
      _Kpi('Cursos en dictado', '$enCurso', Icons.event_available_outlined),
      _Kpi('Ocupación de aperturas', '$pct %', Icons.groups_outlined),
      _Kpi('Sedes · aulas', '${branches.length} · $aulas', Icons.apartment_outlined),
    ];

    return LayoutBuilder(builder: (context, c) {
      final cols = c.maxWidth >= 900 ? 4 : (c.maxWidth >= 520 ? 2 : 1);
      final w = (c.maxWidth - (cols - 1) * Tk.s4) / cols;
      return Wrap(
        spacing: Tk.s4,
        runSpacing: Tk.s4,
        children: [for (final t in tiles) SizedBox(width: w, child: t)],
      );
    });
  }
}

class _Kpi extends StatelessWidget {
  const _Kpi(this.label, this.value, this.icon);
  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(Tk.s4),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: Tk.primarySoft,
                  borderRadius: BorderRadius.circular(Tk.radiusSm),
                ),
                child: Icon(icon, color: Tk.primary),
              ),
              const SizedBox(width: Tk.s3),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(value,
                        style: const TextStyle(
                            fontSize: 24,
                            fontWeight: FontWeight.w700,
                            color: Tk.ink)),
                    Text(label,
                        style:
                            const TextStyle(fontSize: 13, color: Tk.inkMute)),
                  ],
                ),
              ),
            ],
          ),
        ),
      );
}
