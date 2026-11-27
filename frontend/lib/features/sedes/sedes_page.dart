import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../core/permissions.dart';
import '../../core/theme.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../data/repositories.dart';
import '../../shared/widgets.dart';
import '../auth/auth_controller.dart';
import 'sedes_dialogs.dart';

class SedesPage extends ConsumerWidget {
  const SedesPage({super.key});

  void _refresh(WidgetRef ref) {
    ref.invalidate(branchesProvider);
    ref.invalidate(inactiveBranchesProvider);
  }

  void _toast(BuildContext context, String msg) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        content: Text(msg),
        behavior: SnackBarBehavior.floating,
        width: 420,
      ));
  }

  /// Ejecuta una escritura: las reglas de negocio se muestran en un aviso;
  /// el resto, como mensaje breve.
  Future<void> _run(
    BuildContext context,
    WidgetRef ref, {
    required Future<void> Function() action,
    required String ok,
    String blockedTitle = 'No se pudo completar',
  }) async {
    try {
      await action();
      _refresh(ref);
      if (context.mounted) _toast(context, ok);
    } on BusinessRuleException catch (e) {
      if (context.mounted) await showBlocked(context, blockedTitle, e.message);
    } catch (e) {
      if (context.mounted) _toast(context, errorText(e));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).user!;
    final value = ref.watch(branchesProvider);
    final repo = ref.read(infrastructureRepositoryProvider);

    return PageFrame(
      title: 'Sedes y aulas',
      subtitle: user.isGlobal
          ? 'Infraestructura física del instituto'
          : 'Tu sede: ${user.branchName}',
      actions: [
        if (user.canCreateBranch)
          FilledButton.icon(
            onPressed: () async {
              if (await showBranchForm(context) && context.mounted) {
                _refresh(ref);
                _toast(context, 'Sede creada.');
              }
            },
            style: FilledButton.styleFrom(minimumSize: const Size(0, 42)),
            icon: const Icon(Icons.add, size: 20),
            label: const Text('Nueva sede'),
          ),
      ],
      child: AsyncBody<List<Branch>>(
        value: value,
        onRetry: () => ref.invalidate(branchesProvider),
        builder: (all) {
          // Alcance por sede: el personal de sede solo ve la suya.
          final branches =
              all.where((b) => user.isGlobal || b.id == user.branchId).toList();
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (branches.isEmpty)
                const EmptyState('Sin sedes asignadas.')
              else
                LayoutBuilder(builder: (context, c) {
                  final cols = c.maxWidth >= 760 ? 2 : 1;
                  final w = (c.maxWidth - (cols - 1) * Tk.s4) / cols;
                  return Wrap(
                    spacing: Tk.s4,
                    runSpacing: Tk.s4,
                    children: [
                      for (final b in branches)
                        SizedBox(
                          width: w,
                          child: _BranchCard(
                            branch: b,
                            user: user,
                            onEdit: () async {
                              if (await showBranchForm(context, branch: b) &&
                                  context.mounted) {
                                _refresh(ref);
                                _toast(context, 'Sede actualizada.');
                              }
                            },
                            onDelete: () async {
                              final yes = await confirmDialog(
                                context,
                                title: 'Dar de baja la sede',
                                message:
                                    '«${b.name}» y sus aulas dejarán de estar activas. Podrás reactivarla después.',
                                confirmLabel: 'Dar de baja',
                                danger: true,
                              );
                              if (yes && context.mounted) {
                                await _run(context, ref,
                                    action: () => repo.deleteBranch(b.id),
                                    ok: 'Sede dada de baja.',
                                    blockedTitle: 'No se puede dar de baja');
                              }
                            },
                            onAddClassroom: () async {
                              if (await showClassroomForm(context,
                                      branch: b, allBranches: all) &&
                                  context.mounted) {
                                _refresh(ref);
                                _toast(context, 'Aula creada.');
                              }
                            },
                            onEditClassroom: (c) async {
                              if (await showClassroomForm(context,
                                      branch: b,
                                      classroom: c,
                                      allBranches: all) &&
                                  context.mounted) {
                                _refresh(ref);
                                _toast(context, 'Aula actualizada.');
                              }
                            },
                            onDeleteClassroom: (c) async {
                              final yes = await confirmDialog(
                                context,
                                title: 'Eliminar aula',
                                message:
                                    '¿Eliminar «${c.name}» de ${b.name}? Esta acción no se puede deshacer desde aquí.',
                                confirmLabel: 'Eliminar',
                                danger: true,
                              );
                              if (yes && context.mounted) {
                                await _run(context, ref,
                                    action: () => repo.deleteClassroom(c.id),
                                    ok: 'Aula eliminada.',
                                    blockedTitle: 'No se puede eliminar');
                              }
                            },
                            onSchedules: (c) =>
                                showClassroomSchedules(context, c),
                          ),
                        ),
                    ],
                  );
                }),
              if (user.canRestoreBranch) ...[
                const SizedBox(height: Tk.s5),
                _InactiveBranches(
                  onRestore: (b) => _run(context, ref,
                      action: () => repo.restoreBranch(b.id),
                      ok: 'Sede reactivada.',
                      blockedTitle: 'No se puede reactivar'),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _BranchCard extends StatelessWidget {
  const _BranchCard({
    required this.branch,
    required this.user,
    required this.onEdit,
    required this.onDelete,
    required this.onAddClassroom,
    required this.onEditClassroom,
    required this.onDeleteClassroom,
    required this.onSchedules,
  });

  final Branch branch;
  final AuthUser user;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  final VoidCallback onAddClassroom;
  final ValueChanged<Classroom> onEditClassroom;
  final ValueChanged<Classroom> onDeleteClassroom;
  final ValueChanged<Classroom> onSchedules;

  @override
  Widget build(BuildContext context) {
    final canEdit = user.canEditBranch(branch.id);
    final canDelete = user.canDeleteBranch;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Tk.s4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(branch.name,
                      style: const TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                          color: Tk.ink)),
                ),
                Pill(branch.code),
                if (canEdit || canDelete)
                  PopupMenuButton<String>(
                    tooltip: 'Acciones de la sede',
                    icon: const Icon(Icons.more_vert, size: 20),
                    onSelected: (v) => v == 'edit' ? onEdit() : onDelete(),
                    itemBuilder: (_) => [
                      if (canEdit)
                        const PopupMenuItem(
                            value: 'edit', child: Text('Editar sede')),
                      if (canDelete)
                        const PopupMenuItem(
                            value: 'delete', child: Text('Dar de baja')),
                    ],
                  ),
              ],
            ),
            const SizedBox(height: Tk.s3),
            _Line(Icons.place_outlined,
                branch.address.isEmpty ? 'Sin dirección' : branch.address),
            const SizedBox(height: Tk.s2),
            _Line(Icons.phone_outlined,
                branch.phone.isEmpty ? 'Sin teléfono' : branch.phone),
            const SizedBox(height: Tk.s4),
            Row(
              children: [
                _Stat('${branch.classrooms.length}', 'ambientes'),
                const SizedBox(width: Tk.s5),
                _Stat('${branch.totalCapacity}', 'vacantes totales'),
              ],
            ),
            const SizedBox(height: Tk.s4),
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.only(top: Tk.s2, bottom: Tk.s1),
              child: Row(
                children: [
                  const Expanded(
                    child: Text('Aulas',
                        style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: Tk.inkMute)),
                  ),
                  if (user.canCreateClassroom(branch.id))
                    TextButton.icon(
                      onPressed: onAddClassroom,
                      icon: const Icon(Icons.add, size: 18),
                      label: const Text('Agregar aula'),
                    ),
                ],
              ),
            ),
            if (branch.classrooms.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: Tk.s2),
                child: Text('Esta sede aún no tiene aulas.',
                    style: TextStyle(fontSize: 14, color: Tk.inkMute)),
              ),
            for (final c in branch.classrooms)
              _ClassroomRow(
                classroom: c,
                canEdit: user.canEditClassroom(branch.id),
                canDelete: user.canDeleteClassroom(branch.id),
                onEdit: () => onEditClassroom(c),
                onDelete: () => onDeleteClassroom(c),
                onSchedules: () => onSchedules(c),
              ),
          ],
        ),
      ),
    );
  }
}

class _ClassroomRow extends StatelessWidget {
  const _ClassroomRow({
    required this.classroom,
    required this.canEdit,
    required this.canDelete,
    required this.onEdit,
    required this.onDelete,
    required this.onSchedules,
  });

  final Classroom classroom;
  final bool canEdit;
  final bool canDelete;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  final VoidCallback onSchedules;

  Widget _icon(IconData i, String tip, VoidCallback f, {Color? color}) =>
      IconButton(
        tooltip: tip,
        onPressed: f,
        icon: Icon(i, size: 20, color: color ?? Tk.inkMute),
        visualDensity: VisualDensity.compact,
        constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
        padding: EdgeInsets.zero,
      );

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            const Icon(Icons.meeting_room_outlined, size: 18, color: Tk.inkMute),
            const SizedBox(width: Tk.s2),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(classroom.name,
                      style: const TextStyle(fontSize: 14, color: Tk.ink)),
                  Text('${classroom.capacity} vacantes',
                      style: const TextStyle(fontSize: 12, color: Tk.inkMute)),
                ],
              ),
            ),
            _icon(Icons.calendar_month_outlined, 'Ver disponibilidad',
                onSchedules),
            if (canEdit) _icon(Icons.edit_outlined, 'Editar aula', onEdit),
            if (canDelete)
              _icon(Icons.delete_outline, 'Eliminar aula', onDelete,
                  color: Tk.danger),
          ],
        ),
      );
}

class _InactiveBranches extends ConsumerWidget {
  const _InactiveBranches({required this.onRestore});
  final ValueChanged<Branch> onRestore;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final value = ref.watch(inactiveBranchesProvider);
    final list = value.asData?.value ?? const <Branch>[];
    if (list.isEmpty) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Sedes dadas de baja',
            style: TextStyle(
                fontSize: 15, fontWeight: FontWeight.w700, color: Tk.ink)),
        const SizedBox(height: Tk.s2),
        for (final b in list)
          Padding(
            padding: const EdgeInsets.only(bottom: Tk.s2),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(Tk.s3),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(b.name,
                              style: const TextStyle(
                                  fontWeight: FontWeight.w600, color: Tk.ink)),
                          Text(
                              '${b.code} · ${b.classrooms.length} aulas se reactivan con ella',
                              style: const TextStyle(
                                  fontSize: 12, color: Tk.inkMute)),
                        ],
                      ),
                    ),
                    OutlinedButton.icon(
                      onPressed: () => onRestore(b),
                      style: OutlinedButton.styleFrom(
                          minimumSize: const Size(0, 40)),
                      icon: const Icon(Icons.restore, size: 18),
                      label: const Text('Reactivar'),
                    ),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _Line extends StatelessWidget {
  const _Line(this.icon, this.text);
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: Tk.inkMute),
          const SizedBox(width: Tk.s2),
          Expanded(child: Text(text, style: const TextStyle(fontSize: 14))),
        ],
      );
}

class _Stat extends StatelessWidget {
  const _Stat(this.value, this.label);
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value,
              style: const TextStyle(
                  fontSize: 22, fontWeight: FontWeight.w700, color: Tk.primary)),
          Text(label, style: const TextStyle(fontSize: 12, color: Tk.inkMute)),
        ],
      );
}
