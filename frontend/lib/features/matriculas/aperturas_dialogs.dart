import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../core/theme.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../data/repositories.dart';
import '../../shared/inputs.dart';
import '../auth/auth_controller.dart';
import '../sedes/sedes_dialogs.dart' show errorText;

const kWeekDays = [
  'Lunes',
  'Martes',
  'Miércoles',
  'Jueves',
  'Viernes',
  'Sábado',
  'Domingo',
];

/// Crear (apertura == null) o editar una apertura. Devuelve true si se guardó.
Future<bool> showScheduleForm(BuildContext context,
    {ScheduledCourse? apertura}) async {
  final ok = await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (_) => _ScheduleForm(apertura: apertura),
  );
  return ok ?? false;
}

class _ScheduleForm extends ConsumerStatefulWidget {
  const _ScheduleForm({this.apertura});
  final ScheduledCourse? apertura;

  @override
  ConsumerState<_ScheduleForm> createState() => _ScheduleFormState();
}

class _ScheduleFormState extends ConsumerState<_ScheduleForm> {
  final _form = GlobalKey<FormState>();

  late int? _courseId = widget.apertura?.courseId;
  late int? _branchId = widget.apertura?.branchId;
  late int? _classroomId = widget.apertura?.classroomId;
  late int? _teacherId = widget.apertura?.teacherId;
  late final Set<String> _days = {...?widget.apertura?.days};
  late final _start = TextEditingController(
      text: widget.apertura == null ? '' : dateFmt.format(widget.apertura!.startDate));
  late final _from = TextEditingController(text: widget.apertura?.startTime);
  late final _to = TextEditingController(text: widget.apertura?.endTime);

  bool _saving = false;
  String? _banner;
  Map<String, String> _server = {};

  bool get _editing => widget.apertura != null;

  /// Con alumnos matriculados no se cambia el curso ni la sede.
  bool get _locked => _editing && widget.apertura!.enrolled > 0;

  @override
  void dispose() {
    _start.dispose();
    _from.dispose();
    _to.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_form.currentState?.validate() ?? false)) return;
    setState(() {
      _saving = true;
      _banner = null;
      _server = {};
    });
    final repo = ref.read(scheduleRepositoryProvider);
    final input = ScheduledCourseInput(
      courseId: _courseId!,
      branchId: _branchId!,
      classroomId: _classroomId!,
      teacherId: _teacherId,
      startDate: parseDate(_start.text)!,
      days: [for (final d in kWeekDays) if (_days.contains(d)) d],
      startTime: _from.text.trim(),
      endTime: _to.text.trim(),
    );
    try {
      if (_editing) {
        await repo.updateScheduledCourse(widget.apertura!.id, input);
      } else {
        await repo.createScheduledCourse(input);
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

  Widget _pair(Widget a, Widget b) {
    final wide = MediaQuery.sizeOf(context).width >= 640;
    if (!wide) {
      return Column(children: [a, const SizedBox(height: Tk.s3), b]);
    }
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: a),
        const SizedBox(width: Tk.s3),
        Expanded(child: b),
      ],
    );
  }

  Widget _text(
    String key,
    TextEditingController c,
    String label, {
    required String hint,
    required List<TextInputFormatter> formatters,
    required String? Function(String?) check,
  }) =>
      TextFormField(
        controller: c,
        enabled: !_saving,
        keyboardType: TextInputType.number,
        inputFormatters: formatters,
        onChanged: (_) {
          _server.remove(key);
          setState(() {}); // refresca la fecha de fin calculada
        },
        decoration: InputDecoration(labelText: '$label *', hintText: hint),
        validator: (v) => check(v) ?? _server[key],
      );

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).user!;
    final branches = ref.watch(branchesProvider);
    final courses = ref.watch(coursesProvider);
    final teachers = ref.watch(teachersProvider);

    final branchList = [
      for (final b in branches.asData?.value ?? const <Branch>[])
        if (user.isGlobal || b.id == user.branchId) b,
    ];
    final rooms = branchList
            .where((b) => b.id == _branchId)
            .firstOrNull
            ?.classrooms ??
        const <Classroom>[];
    final courseList = courses.asData?.value ?? const <Course>[];
    final course = courseList.where((c) => c.id == _courseId).firstOrNull;
    final start = parseDate(_start.text);
    final end = (course != null && start != null && _start.text.length == 10)
        ? start.add(Duration(days: course.durationInWeeks * 7))
        : null;

    final loading = branches.isLoading || courses.isLoading || teachers.isLoading;

    return AlertDialog(
      title: Text(_editing ? 'Editar apertura' : 'Aperturar curso'),
      content: SizedBox(
        width: 560,
        child: loading
            ? const Padding(
                padding: EdgeInsets.all(Tk.s5),
                child: Center(child: CircularProgressIndicator(color: Tk.primary)),
              )
            : SingleChildScrollView(
                child: Form(
                  key: _form,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (_locked)
                        const Padding(
                          padding: EdgeInsets.only(bottom: Tk.s3),
                          child: Text(
                            'Ya tiene alumnos matriculados: no se puede cambiar el curso ni la sede.',
                            style: TextStyle(fontSize: 13, color: Tk.warn),
                          ),
                        ),
                      DropdownButtonFormField<int>(
                        initialValue: _courseId,
                        isExpanded: true,
                        decoration:
                            const InputDecoration(labelText: 'Curso o taller *'),
                        items: [
                          for (final c in courseList)
                            DropdownMenuItem(
                              value: c.id,
                              child: Text(
                                '${c.name} · ${c.isWorkshop ? 'Taller' : 'Módulo'} · ${c.durationInWeeks} sem.',
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                        ],
                        onChanged: (_saving || _locked)
                            ? null
                            : (v) => setState(() {
                                  _courseId = v;
                                  _server.remove('course_id');
                                }),
                        validator: (v) =>
                            v == null ? 'Selecciona un curso.' : _server['course_id'],
                      ),
                      const SizedBox(height: Tk.s3),
                      _pair(
                        DropdownButtonFormField<int>(
                          initialValue: _branchId,
                          isExpanded: true,
                          decoration: const InputDecoration(labelText: 'Sede *'),
                          items: [
                            for (final b in branchList)
                              DropdownMenuItem(value: b.id, child: Text(b.name)),
                          ],
                          onChanged: (_saving || _locked)
                              ? null
                              : (v) => setState(() {
                                    _branchId = v;
                                    _classroomId = null; // el aula es de otra sede
                                    _server.remove('branch_id');
                                  }),
                          validator: (v) =>
                              v == null ? 'Selecciona una sede.' : _server['branch_id'],
                        ),
                        DropdownButtonFormField<int>(
                          key: ValueKey('aula-$_branchId'),
                          initialValue: rooms.any((r) => r.id == _classroomId)
                              ? _classroomId
                              : null,
                          isExpanded: true,
                          decoration: InputDecoration(
                            labelText: 'Aula *',
                            helperText: _branchId == null ? 'Primero elige la sede.' : null,
                          ),
                          items: [
                            for (final r in rooms)
                              DropdownMenuItem(
                                value: r.id,
                                child: Text('${r.name} · ${r.capacity}',
                                    overflow: TextOverflow.ellipsis),
                              ),
                          ],
                          onChanged: (_saving || _branchId == null)
                              ? null
                              : (v) => setState(() {
                                    _classroomId = v;
                                    _server.remove('classroom_id');
                                  }),
                          validator: (v) =>
                              v == null ? 'Selecciona un aula.' : _server['classroom_id'],
                        ),
                      ),
                      const SizedBox(height: Tk.s3),
                      DropdownButtonFormField<int?>(
                        initialValue: _teacherId,
                        isExpanded: true,
                        decoration: const InputDecoration(labelText: 'Docente'),
                        items: [
                          const DropdownMenuItem<int?>(
                              value: null, child: Text('Sin asignar')),
                          for (final t in teachers.asData?.value ?? const <Teacher>[])
                            DropdownMenuItem<int?>(
                              value: t.id,
                              child: Text('${t.fullName} · ${t.specialty}',
                                  overflow: TextOverflow.ellipsis),
                            ),
                        ],
                        onChanged: _saving
                            ? null
                            : (v) => setState(() {
                                  _teacherId = v;
                                  _server.remove('teacher_id');
                                }),
                        validator: (_) => _server['teacher_id'],
                      ),
                      const SizedBox(height: Tk.s3),
                      _text('start_date', _start, 'Fecha de inicio',
                          hint: 'dd/mm/aaaa',
                          formatters: [DateSlashFormatter()],
                          check: (v) {
                            final x = (v ?? '').trim();
                            if (x.isEmpty) return 'Campo obligatorio.';
                            return (x.length != 10 || parseDate(x) == null)
                                ? 'Fecha inválida (dd/mm/aaaa).'
                                : null;
                          }),
                      const SizedBox(height: Tk.s3),
                      FormField<Set<String>>(
                        validator: (_) => _days.isEmpty
                            ? 'Elige al menos un día.'
                            : _server['days'],
                        builder: (field) => Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Días de clase *',
                                style: TextStyle(fontSize: 13, color: Tk.inkMute)),
                            const SizedBox(height: Tk.s2),
                            Wrap(
                              spacing: Tk.s2,
                              runSpacing: Tk.s2,
                              children: [
                                for (final d in kWeekDays)
                                  FilterChip(
                                    label: Text(d),
                                    selected: _days.contains(d),
                                    onSelected: _saving
                                        ? null
                                        : (on) {
                                            setState(() {
                                              on ? _days.add(d) : _days.remove(d);
                                              _server.remove('days');
                                            });
                                            field.didChange(_days);
                                          },
                                  ),
                              ],
                            ),
                            if (field.hasError)
                              Padding(
                                padding: const EdgeInsets.only(top: Tk.s2),
                                child: Text(field.errorText!,
                                    style: const TextStyle(
                                        fontSize: 12, color: Tk.danger)),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: Tk.s3),
                      _pair(
                        _text('start_time', _from, 'Hora de inicio',
                            hint: 'HH:mm',
                            formatters: [TimeColonFormatter()],
                            check: (v) => parseTime(v ?? '') == null
                                ? 'Hora inválida (HH:mm).'
                                : null),
                        _text('end_time', _to, 'Hora de fin',
                            hint: 'HH:mm',
                            formatters: [TimeColonFormatter()],
                            check: (v) {
                              final b = parseTime(v ?? '');
                              if (b == null) return 'Hora inválida (HH:mm).';
                              final a = parseTime(_from.text);
                              if (a != null && a >= b) {
                                return 'Debe ser posterior a la hora de inicio.';
                              }
                              return null;
                            }),
                      ),
                      Padding(
                        padding: const EdgeInsets.only(top: Tk.s3),
                        child: Text(
                          end == null
                              ? 'Puedes elegir varios días por semana. La fecha de fin se calcula sola: inicio + semanas del curso.'
                              : 'Termina el ${dateFmt.format(end)} (${course!.durationInWeeks} semanas desde el inicio).',
                          style: const TextStyle(fontSize: 13, color: Tk.inkMute),
                        ),
                      ),
                      if (_banner != null)
                        Container(
                          margin: const EdgeInsets.only(top: Tk.s3),
                          padding: const EdgeInsets.all(Tk.s3),
                          decoration: BoxDecoration(
                            color: Tk.dangerSoft,
                            borderRadius: BorderRadius.circular(Tk.radiusSm),
                          ),
                          child: Text(_banner!,
                              style: const TextStyle(color: Tk.danger, fontSize: 13)),
                        ),
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
          onPressed: (_saving || loading) ? null : _submit,
          style: FilledButton.styleFrom(minimumSize: const Size(120, 44)),
          child: _saving
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                )
              : Text(_editing ? 'Guardar' : 'Aperturar'),
        ),
      ],
    );
  }
}
