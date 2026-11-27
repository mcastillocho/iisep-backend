import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

import '../../core/permissions.dart';
import '../../core/theme.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../data/repositories.dart';
import '../../shared/widgets.dart';
import '../auth/auth_controller.dart';

/// Texto legible para cualquier error de escritura.
String errorText(Object e) => switch (e) {
      ValidationException() => e.toString(),
      BusinessRuleException() => e.message,
      ForbiddenException() => e.message,
      _ => 'No se pudo completar la acción. Intenta nuevamente.',
    };

// ───────────────────────── Sede ─────────────────────────

/// Crear (branch == null) o editar una sede. Devuelve true si se guardó.
Future<bool> showBranchForm(BuildContext context, {Branch? branch}) async {
  final ok = await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (_) => _BranchForm(branch: branch),
  );
  return ok ?? false;
}

class _BranchForm extends ConsumerStatefulWidget {
  const _BranchForm({this.branch});
  final Branch? branch;

  @override
  ConsumerState<_BranchForm> createState() => _BranchFormState();
}

class _BranchFormState extends ConsumerState<_BranchForm> {
  final _form = GlobalKey<FormState>();
  late final _name = TextEditingController(text: widget.branch?.name);
  late final _code = TextEditingController(text: widget.branch?.code);
  late final _address = TextEditingController(text: widget.branch?.address);
  late final _phone = TextEditingController(text: widget.branch?.phone);

  bool _saving = false;
  String? _banner;
  Map<String, String> _server = {};

  bool get _editing => widget.branch != null;

  @override
  void dispose() {
    _name.dispose();
    _code.dispose();
    _address.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_form.currentState?.validate() ?? false)) return;
    setState(() {
      _saving = true;
      _banner = null;
      _server = {};
    });
    final repo = ref.read(infrastructureRepositoryProvider);
    final input = BranchInput(
      name: _name.text,
      code: _code.text,
      address: _address.text,
      phone: _phone.text,
    );
    try {
      if (_editing) {
        await repo.updateBranch(widget.branch!.id, input);
      } else {
        await repo.createBranch(input);
      }
      if (mounted) Navigator.of(context).pop(true);
    } on ValidationException catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _server = e.fields;
      });
      _form.currentState?.validate();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _banner = errorText(e);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).user!;
    final identityLocked = _editing && !user.canEditBranchIdentity;

    return AlertDialog(
      title: Text(_editing ? 'Editar sede' : 'Nueva sede'),
      content: SizedBox(
        width: 440,
        child: SingleChildScrollView(
          child: Form(
            key: _form,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (identityLocked)
                  const _Note(
                      'El nombre y el código solo los cambia la Dirección General.'),
                TextFormField(
                  controller: _name,
                  enabled: !identityLocked && !_saving,
                  autofocus: !identityLocked,
                  maxLength: 255,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                      labelText: 'Nombre de la sede *', counterText: ''),
                  onChanged: (_) => _server.remove('name'),
                  validator: (v) => (v ?? '').trim().isEmpty
                      ? 'El nombre es obligatorio.'
                      : _server['name'],
                ),
                const SizedBox(height: Tk.s3),
                TextFormField(
                  controller: _code,
                  enabled: !identityLocked && !_saving,
                  maxLength: 50,
                  textCapitalization: TextCapitalization.characters,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                    labelText: 'Código',
                    helperText: 'Si lo dejas vacío se genera de 3 letras.',
                    counterText: '',
                  ),
                  onChanged: (_) => _server.remove('code'),
                  validator: (v) => _server['code'],
                ),
                const SizedBox(height: Tk.s3),
                TextFormField(
                  controller: _address,
                  enabled: !_saving,
                  maxLength: 255,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                      labelText: 'Dirección', counterText: ''),
                  onChanged: (_) => _server.remove('address'),
                  validator: (v) => _server['address'],
                ),
                const SizedBox(height: Tk.s3),
                TextFormField(
                  controller: _phone,
                  enabled: !_saving,
                  maxLength: 50,
                  keyboardType: TextInputType.phone,
                  onFieldSubmitted: (_) => _submit(),
                  decoration: const InputDecoration(
                      labelText: 'Teléfono', counterText: ''),
                  onChanged: (_) => _server.remove('phone'),
                  validator: (v) => _server['phone'],
                ),
                if (_banner != null) _Banner(_banner!),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(false),
          child: const Text('Cancelar'),
        ),
        FilledButton(
          onPressed: _saving ? null : _submit,
          style: FilledButton.styleFrom(minimumSize: const Size(110, 44)),
          child: _saving
              ? const _Spinner()
              : Text(_editing ? 'Guardar' : 'Crear sede'),
        ),
      ],
    );
  }
}

// ───────────────────────── Aula ─────────────────────────

/// Crear (classroom == null) o editar un aula de [branch].
Future<bool> showClassroomForm(
  BuildContext context, {
  required Branch branch,
  Classroom? classroom,
  required List<Branch> allBranches,
}) async {
  final ok = await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (_) => _ClassroomForm(
        branch: branch, classroom: classroom, allBranches: allBranches),
  );
  return ok ?? false;
}

class _ClassroomForm extends ConsumerStatefulWidget {
  const _ClassroomForm({
    required this.branch,
    required this.allBranches,
    this.classroom,
  });
  final Branch branch;
  final Classroom? classroom;
  final List<Branch> allBranches;

  @override
  ConsumerState<_ClassroomForm> createState() => _ClassroomFormState();
}

class _ClassroomFormState extends ConsumerState<_ClassroomForm> {
  final _form = GlobalKey<FormState>();
  late final _name = TextEditingController(text: widget.classroom?.name);
  late final _capacity =
      TextEditingController(text: widget.classroom?.capacity.toString());
  late int _branchId = widget.branch.id;

  bool _saving = false;
  String? _banner;
  Map<String, String> _server = {};

  bool get _editing => widget.classroom != null;

  @override
  void dispose() {
    _name.dispose();
    _capacity.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_form.currentState?.validate() ?? false)) return;
    setState(() {
      _saving = true;
      _banner = null;
      _server = {};
    });
    final repo = ref.read(infrastructureRepositoryProvider);
    final input = ClassroomInput(
      name: _name.text,
      capacity: int.parse(_capacity.text.trim()),
      branchId: _branchId,
    );
    try {
      if (_editing) {
        await repo.updateClassroom(widget.classroom!.id, input);
      } else {
        await repo.createClassroom(input);
      }
      if (mounted) Navigator.of(context).pop(true);
    } on ValidationException catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _server = e.fields;
      });
      _form.currentState?.validate();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _banner = errorText(e);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).user!;
    final nameLocked = _editing && !user.canRenameClassroom(widget.branch.id);
    final canTransfer = _editing && user.canTransferClassroom;

    return AlertDialog(
      title: Text(_editing ? 'Editar aula' : 'Nueva aula'),
      content: SizedBox(
        width: 440,
        child: SingleChildScrollView(
          child: Form(
            key: _form,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _Note('Sede: ${widget.branch.name}'),
                if (nameLocked)
                  const _Note('Secretaría solo puede cambiar el aforo.'),
                TextFormField(
                  controller: _name,
                  enabled: !nameLocked && !_saving,
                  autofocus: !nameLocked,
                  maxLength: 255,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                      labelText: 'Nombre del aula *', counterText: ''),
                  onChanged: (_) => _server.remove('name'),
                  validator: (v) => (v ?? '').trim().isEmpty
                      ? 'El nombre es obligatorio.'
                      : _server['name'],
                ),
                const SizedBox(height: Tk.s3),
                TextFormField(
                  controller: _capacity,
                  enabled: !_saving,
                  autofocus: nameLocked,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  onFieldSubmitted: (_) => _submit(),
                  decoration: const InputDecoration(
                    labelText: 'Aforo (vacantes) *',
                    helperText: 'Entre 1 y 500.',
                  ),
                  onChanged: (_) => _server.remove('capacity'),
                  validator: (v) {
                    final n = int.tryParse((v ?? '').trim());
                    if (n == null) return 'Ingresa un número.';
                    if (n < 1 || n > 500) return 'Debe estar entre 1 y 500.';
                    return _server['capacity'];
                  },
                ),
                if (canTransfer) ...[
                  const SizedBox(height: Tk.s3),
                  DropdownButtonFormField<int>(
                    initialValue: _branchId,
                    decoration: const InputDecoration(
                      labelText: 'Sede',
                      helperText:
                          'Cambiarla transfiere el aula (solo si no tiene horarios).',
                    ),
                    items: [
                      for (final b in widget.allBranches)
                        DropdownMenuItem(value: b.id, child: Text(b.name)),
                    ],
                    onChanged:
                        _saving ? null : (v) => setState(() => _branchId = v!),
                  ),
                ],
                if (_banner != null) _Banner(_banner!),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(false),
          child: const Text('Cancelar'),
        ),
        FilledButton(
          onPressed: _saving ? null : _submit,
          style: FilledButton.styleFrom(minimumSize: const Size(110, 44)),
          child: _saving
              ? const _Spinner()
              : Text(_editing ? 'Guardar' : 'Crear aula'),
        ),
      ],
    );
  }
}

// ───────────────────── Horarios del aula ─────────────────────

Future<void> showClassroomSchedules(BuildContext context, Classroom c) =>
    showDialog<void>(
      context: context,
      builder: (_) => _SchedulesDialog(classroom: c),
    );

class _SchedulesDialog extends ConsumerWidget {
  const _SchedulesDialog({required this.classroom});
  final Classroom classroom;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final value = ref.watch(classroomSchedulesProvider(classroom.id));
    final fmt = DateFormat('dd/MM/yyyy');
    return AlertDialog(
      title: Text('Disponibilidad · ${classroom.name}'),
      content: SizedBox(
        width: 480,
        child: AsyncBody<List<ClassroomSchedule>>(
          value: value,
          onRetry: () => ref.invalidate(classroomSchedulesProvider(classroom.id)),
          builder: (list) {
            if (list.isEmpty) {
              return const EmptyState('Aula libre: no tiene horarios.',
                  icon: Icons.event_available_outlined);
            }
            return ListView.separated(
              shrinkWrap: true,
              itemCount: list.length,
              separatorBuilder: (_, _) => const Divider(height: Tk.s5),
              itemBuilder: (_, i) {
                final s = list[i];
                final (label, fg, bg) = switch (s.state) {
                  ScheduleState.enCurso => ('En curso', Tk.primary, Tk.primarySoft),
                  ScheduleState.programado => ('Programado', Tk.info, Tk.infoSoft),
                  ScheduleState.finalizado => ('Finalizado', Tk.inkMute, Tk.canvas),
                };
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(s.courseName,
                              style: const TextStyle(
                                  fontWeight: FontWeight.w600, color: Tk.ink)),
                        ),
                        Pill(label, fg: fg, bg: bg),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text('${s.day} · ${s.startTime}–${s.endTime}',
                        style: const TextStyle(fontSize: 14)),
                    Text(
                        '${fmt.format(s.startDate)} al ${fmt.format(s.endDate)} · ${s.teacherName ?? 'Docente por asignar'}',
                        style: const TextStyle(fontSize: 13, color: Tk.inkMute)),
                  ],
                );
              },
            );
          },
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cerrar'),
        ),
      ],
    );
  }
}

// ───────────────────── Confirmaciones ─────────────────────

Future<bool> confirmDialog(
  BuildContext context, {
  required String title,
  required String message,
  required String confirmLabel,
  bool danger = false,
}) async {
  final ok = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: Text(message),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(ctx).pop(false),
          child: const Text('Cancelar'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(ctx).pop(true),
          style: danger
              ? FilledButton.styleFrom(backgroundColor: Tk.dangerFill)
              : null,
          child: Text(confirmLabel),
        ),
      ],
    ),
  );
  return ok ?? false;
}

/// Aviso cuando una regla de negocio impide la acción.
Future<void> showBlocked(BuildContext context, String title, String message) =>
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        icon: const Icon(Icons.block, color: Tk.warn),
        title: Text(title),
        content: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Text(message),
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Entendido'),
          ),
        ],
      ),
    );

// ───────────────────── piezas pequeñas ─────────────────────

class _Note extends StatelessWidget {
  const _Note(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: Tk.s3),
        child: Text(text,
            style: const TextStyle(fontSize: 13, color: Tk.inkMute)),
      );
}

class _Banner extends StatelessWidget {
  const _Banner(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(top: Tk.s3),
        padding: const EdgeInsets.all(Tk.s3),
        decoration: BoxDecoration(
          color: Tk.dangerSoft,
          borderRadius: BorderRadius.circular(Tk.radiusSm),
        ),
        child: Text(text,
            style: const TextStyle(color: Tk.danger, fontSize: 13)),
      );
}

class _Spinner extends StatelessWidget {
  const _Spinner();

  @override
  Widget build(BuildContext context) => const SizedBox(
        width: 20,
        height: 20,
        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
      );
}
