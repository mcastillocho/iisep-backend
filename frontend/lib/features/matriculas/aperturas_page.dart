import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

import '../../core/permissions.dart';
import '../../core/theme.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../shared/widgets.dart';
import '../auth/auth_controller.dart';
import '../sedes/sedes_dialogs.dart' show confirmDialog, errorText, showBlocked;
import 'aperturas_dialogs.dart';
import 'matriculas_dialogs.dart';
import 'matriculas_page.dart' show refreshEnrollments, toast;

class AperturasPage extends ConsumerWidget {
  const AperturasPage({super.key});

  Future<void> _create(BuildContext context, WidgetRef ref) async {
    if (await showScheduleForm(context) && context.mounted) {
      refreshEnrollments(ref);
      toast(context, 'Curso aperturado.');
    }
  }

  Future<void> _edit(BuildContext context, WidgetRef ref, ScheduledCourse s) async {
    if (await showScheduleForm(context, apertura: s) && context.mounted) {
      refreshEnrollments(ref);
      toast(context, 'Apertura actualizada.');
    }
  }

  Future<void> _delete(BuildContext context, WidgetRef ref, ScheduledCourse s) async {
    final yes = await confirmDialog(
      context,
      title: 'Eliminar apertura',
      message:
          '¿Eliminar la apertura de «${s.courseName}» en ${s.branchName}? Solo es posible si no tiene alumnos matriculados.',
      confirmLabel: 'Eliminar',
      danger: true,
    );
    if (!yes || !context.mounted) return;
    try {
      await ref.read(scheduleRepositoryProvider).deleteScheduledCourse(s.id);
      refreshEnrollments(ref);
      if (context.mounted) toast(context, 'Apertura eliminada.');
    } catch (e) {
      if (context.mounted) {
        await showBlocked(context, 'No se puede eliminar', errorText(e));
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).user!;
    final fmt = DateFormat('dd/MM/yyyy');
    final now = DateTime.now();

    return PageFrame(
      title: 'Apertura de cursos',
      subtitle: 'Programación de cursos y talleres por sede',
      scroll: false,
      actions: [
        if (user.canCreateSchedule)
          FilledButton.icon(
            onPressed: () => _create(context, ref),
            style: FilledButton.styleFrom(minimumSize: const Size(0, 42)),
            icon: const Icon(Icons.add, size: 20),
            label: const Text('Aperturar curso'),
          ),
      ],
      child: AsyncBody<List<ScheduledCourse>>(
        value: ref.watch(scheduledCoursesProvider),
        onRetry: () => ref.invalidate(scheduledCoursesProvider),
        builder: (all) {
          final rows = all
              .where((s) => user.isGlobal || s.branchId == user.branchId)
              .toList();
          if (rows.isEmpty) return const EmptyState('No hay aperturas.');
          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(Tk.s4, 0, Tk.s4, Tk.s4),
            itemCount: rows.length,
            separatorBuilder: (_, _) => const SizedBox(height: Tk.s3),
            itemBuilder: (_, i) {
              final s = rows[i];
              final st = s.stateAt(now);
              final (label, fg, bg) = switch (st) {
                ScheduleState.enCurso => ('En curso', Tk.primary, Tk.primarySoft),
                ScheduleState.programado => ('Programado', Tk.info, Tk.infoSoft),
                ScheduleState.finalizado => ('Finalizado', Tk.inkMute, Tk.canvas),
              };
              final canManage = user.canManageSchedule(s.branchId);
              return Card(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(Tk.s4, Tk.s4, Tk.s3, Tk.s4),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(s.courseName,
                                style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w700,
                                    color: Tk.ink)),
                          ),
                          const SizedBox(width: Tk.s2),
                          Pill(label, fg: fg, bg: bg),
                          if (canManage)
                            PopupMenuButton<String>(
                              tooltip: 'Acciones',
                              icon: const Icon(Icons.more_vert, size: 20),
                              onSelected: (v) => v == 'edit'
                                  ? _edit(context, ref, s)
                                  : _delete(context, ref, s),
                              itemBuilder: (_) => const [
                                PopupMenuItem(value: 'edit', child: Text('Editar')),
                                PopupMenuItem(value: 'delete', child: Text('Eliminar')),
                              ],
                            )
                          else
                            const SizedBox(width: Tk.s3),
                        ],
                      ),
                      const SizedBox(height: Tk.s2),
                      Text('${s.branchName} · ${s.classroomName}',
                          style: const TextStyle(fontSize: 14, color: Tk.inkSoft)),
                      Text('${s.days.join(', ')} · ${s.startTime}–${s.endTime}',
                          style: const TextStyle(fontSize: 14, color: Tk.inkSoft)),
                      Text(
                          '${fmt.format(s.startDate)} al ${fmt.format(s.endDate)} · ${s.teacherName ?? 'Docente por asignar'}',
                          style: const TextStyle(fontSize: 13, color: Tk.inkMute)),
                      const SizedBox(height: Tk.s3),
                      Padding(
                        padding: const EdgeInsets.only(right: Tk.s3),
                        child: Row(
                          children: [
                            Expanded(
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(999),
                                child: LinearProgressIndicator(
                                  value: s.occupancy.clamp(0, 1).toDouble(),
                                  minHeight: 8,
                                  backgroundColor: Tk.line,
                                  color: s.occupancy >= 0.9 ? Tk.warn : Tk.primary,
                                ),
                              ),
                            ),
                            const SizedBox(width: Tk.s3),
                            Text('${s.enrolled}/${s.capacity} · ${s.vacancies} libres',
                                style: const TextStyle(fontSize: 13, color: Tk.inkSoft)),
                          ],
                        ),
                      ),
                      if (user.canEnroll && st != ScheduleState.finalizado) ...[
                        const SizedBox(height: Tk.s3),
                        Align(
                          alignment: Alignment.centerRight,
                          child: Padding(
                            padding: const EdgeInsets.only(right: Tk.s3),
                            child: OutlinedButton.icon(
                              onPressed: s.vacancies > 0
                                  ? () async {
                                      if (await showEnrollForm(context,
                                              scheduledCourseId: s.id) &&
                                          context.mounted) {
                                        refreshEnrollments(ref);
                                        toast(context, 'Matrícula registrada.');
                                      }
                                    }
                                  : null,
                              style: OutlinedButton.styleFrom(
                                  minimumSize: const Size(0, 40)),
                              icon: const Icon(Icons.person_add_alt, size: 18),
                              label: Text(
                                  s.vacancies > 0 ? 'Matricular' : 'Sin vacantes'),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
