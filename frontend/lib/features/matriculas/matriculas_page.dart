import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

import '../../core/permissions.dart';
import '../../core/theme.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../shared/widgets.dart';
import '../auth/auth_controller.dart';
import '../sedes/sedes_dialogs.dart'
    show confirmDialog, errorText, showBlocked;
import 'matriculas_dialogs.dart';

(Color fg, Color bg) statusColors(EnrollmentStatus s) => switch (s) {
      EnrollmentStatus.activo => (Tk.primary, Tk.primarySoft),
      EnrollmentStatus.preinscrito => (Tk.info, Tk.infoSoft),
      EnrollmentStatus.completado => (Tk.inkSoft, Tk.canvas),
      EnrollmentStatus.convalidado => (Tk.warn, Tk.warnSoft),
      EnrollmentStatus.retirado => (Tk.danger, Tk.dangerSoft),
      EnrollmentStatus.anulado => (Tk.inkMute, Tk.line),
    };

/// Refresca lo que cambia al tocar matrículas (listas y ocupación de aperturas).
void refreshEnrollments(WidgetRef ref) {
  ref.invalidate(enrollmentsProvider);
  ref.invalidate(scheduledCoursesProvider);
}

void toast(BuildContext context, String msg) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Text(msg),
      behavior: SnackBarBehavior.floating,
      width: 420,
    ));
}

class MatriculasPage extends ConsumerStatefulWidget {
  const MatriculasPage({super.key});

  @override
  ConsumerState<MatriculasPage> createState() => _MatriculasPageState();
}

class _MatriculasPageState extends ConsumerState<MatriculasPage> {
  String _q = '';
  EnrollmentStatus? _status;

  Future<void> _enroll() async {
    if (await showEnrollForm(context) && mounted) {
      refreshEnrollments(ref);
      toast(context, 'Matrícula registrada.');
    }
  }

  Future<void> _changeStatus(Enrollment e) async {
    if (await showEnrollmentStatusForm(context, e) && mounted) {
      refreshEnrollments(ref);
      toast(context, 'Estado actualizado.');
    }
  }

  Future<void> _delete(Enrollment e) async {
    final yes = await confirmDialog(
      context,
      title: 'Eliminar matrícula',
      message:
          '¿Eliminar la matrícula de ${e.studentName} en «${e.courseName}»? Si solo dejó de asistir, es mejor cambiarla a «Retirado».',
      confirmLabel: 'Eliminar',
      danger: true,
    );
    if (!yes || !mounted) return;
    try {
      await ref.read(enrollmentRepositoryProvider).deleteEnrollment(e.id);
      if (!mounted) return;
      refreshEnrollments(ref);
      toast(context, 'Matrícula eliminada.');
    } catch (err) {
      if (!mounted) return;
      await showBlocked(context, 'No se pudo eliminar', errorText(err));
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).user!;
    final value = ref.watch(enrollmentsProvider);
    final fmt = DateFormat('dd/MM/yyyy');

    return PageFrame(
      title: 'Matrículas',
      subtitle: 'Inscripciones y estado de cada alumno',
      scroll: false,
      actions: [
        if (user.canEnroll)
          FilledButton.icon(
            onPressed: _enroll,
            style: FilledButton.styleFrom(minimumSize: const Size(0, 42)),
            icon: const Icon(Icons.add, size: 20),
            label: const Text('Nueva matrícula'),
          ),
      ],
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(Tk.s4, 0, Tk.s4, Tk.s3),
            child: SearchField(
              hint: 'Buscar por DNI, alumno o curso',
              onChanged: (v) => setState(() => _q = v),
            ),
          ),
          SizedBox(
            height: 40,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Tk.s4),
              children: [
                _chip('Todos', _status == null,
                    () => setState(() => _status = null)),
                for (final s in EnrollmentStatus.values)
                  _chip(s.label, _status == s,
                      () => setState(() => _status = s)),
              ],
            ),
          ),
          const SizedBox(height: Tk.s3),
          Expanded(
            child: AsyncBody<List<Enrollment>>(
              value: value,
              onRetry: () => ref.invalidate(enrollmentsProvider),
              builder: (all) {
                final rows = all.where((e) {
                  if (!user.isGlobal && e.branchName != user.branchName) {
                    return false; // alcance por sede
                  }
                  if (_status != null && e.status != _status) return false;
                  final q = fold(_q);
                  return q.isEmpty ||
                      e.document.contains(q) ||
                      fold(e.studentName).contains(q) ||
                      fold(e.courseName).contains(q);
                }).toList();
                if (rows.isEmpty) {
                  return const EmptyState('No hay matrículas con ese filtro.');
                }
                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(Tk.s4, 0, Tk.s4, Tk.s4),
                  itemCount: rows.length,
                  separatorBuilder: (_, _) => const SizedBox(height: Tk.s2),
                  itemBuilder: (_, i) {
                    final e = rows[i];
                    final (fg, bg) = statusColors(e.status);
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(Tk.s4, Tk.s3, Tk.s2, Tk.s3),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(e.studentName,
                                      style: const TextStyle(
                                          fontSize: 15,
                                          fontWeight: FontWeight.w600,
                                          color: Tk.ink)),
                                  const SizedBox(height: 2),
                                  Text('DNI ${e.document} · ${e.courseName}',
                                      style: const TextStyle(
                                          fontSize: 13, color: Tk.inkMute)),
                                  Text(
                                      '${e.branchName} · ${fmt.format(e.date)}',
                                      style: const TextStyle(
                                          fontSize: 12, color: Tk.inkMute)),
                                ],
                              ),
                            ),
                            const SizedBox(width: Tk.s3),
                            Pill(e.status.label, fg: fg, bg: bg),
                            if (user.canEnroll)
                              PopupMenuButton<String>(
                                tooltip: 'Acciones',
                                icon: const Icon(Icons.more_vert, size: 20),
                                onSelected: (v) =>
                                    v == 'status' ? _changeStatus(e) : _delete(e),
                                itemBuilder: (_) => [
                                  const PopupMenuItem(
                                      value: 'status',
                                      child: Text('Cambiar estado')),
                                  if (user.canDeleteEnrollment)
                                    const PopupMenuItem(
                                        value: 'delete',
                                        child: Text('Eliminar matrícula')),
                                ],
                              )
                            else
                              const SizedBox(width: Tk.s2),
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _chip(String label, bool selected, VoidCallback onTap) => Padding(
        padding: const EdgeInsets.only(right: Tk.s2),
        child: ChoiceChip(
          label: Text(label),
          selected: selected,
          onSelected: (_) => onTap(),
          showCheckmark: false,
        ),
      );
}
