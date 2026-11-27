import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../core/permissions.dart';
import '../../core/theme.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../data/repositories.dart';
import '../auth/auth_controller.dart';
import '../../shared/inputs.dart';
import '../sedes/sedes_dialogs.dart' show errorText;

// ───────────────────── Nueva matrícula ─────────────────────

/// Abre el formulario de matrícula. [scheduledCourseId] la deja preseleccionada
/// (botón "Matricular" de cada apertura). Devuelve true si se guardó.
Future<bool> showEnrollForm(BuildContext context, {int? scheduledCourseId}) async {
  final ok = await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (_) => _EnrollForm(scheduledCourseId: scheduledCourseId),
  );
  return ok ?? false;
}

enum _Lookup { idle, searching, done }

class _EnrollForm extends ConsumerStatefulWidget {
  const _EnrollForm({this.scheduledCourseId});
  final int? scheduledCourseId;

  @override
  ConsumerState<_EnrollForm> createState() => _EnrollFormState();
}

class _EnrollFormState extends ConsumerState<_EnrollForm> {
  final _form = GlobalKey<FormState>();

  late int? _aperturaId = widget.scheduledCourseId;
  final _doc = TextEditingController();
  final _first = TextEditingController();
  final _pat = TextEditingController();
  final _mat = TextEditingController();
  final _birth = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();

  bool _withGuardian = false;
  final _gDoc = TextEditingController();
  final _gFirst = TextEditingController();
  final _gPat = TextEditingController();
  final _gMat = TextEditingController();
  final _gPhone = TextEditingController();
  final _gEmail = TextEditingController();

  final _date = TextEditingController(text: dateFmt.format(DateTime.now()));
  EnrollmentStatus _status = EnrollmentStatus.activo;

  _Lookup _lookup = _Lookup.idle;
  StudentLookup? _result;

  bool _saving = false;
  String? _banner;
  Map<String, String> _server = {};

  @override
  void dispose() {
    for (final c in [
      _doc, _first, _pat, _mat, _birth, _phone, _email,
      _gDoc, _gFirst, _gPat, _gMat, _gPhone, _gEmail, _date,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  bool get _found => _result?.found ?? false;
  bool get _alreadyEnrolled =>
      _found &&
      _aperturaId != null &&
      (_result!.enrolledScheduledIds.contains(_aperturaId));

  Future<void> _search() async {
    final doc = _doc.text.trim();
    if (doc.length != 8) return;
    setState(() => _lookup = _Lookup.searching);
    try {
      final r = await ref
          .read(enrollmentRepositoryProvider)
          .lookupStudent(doc, scheduledCourseId: _aperturaId);
      if (!mounted || _doc.text.trim() != doc) return;
      setState(() {
        _result = r;
        _lookup = _Lookup.done;
        _withGuardian = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _lookup = _Lookup.idle;
        _banner = errorText(e);
      });
    }
  }

  void _onDocChanged(String v) {
    _server.remove('student_document');
    if (v.length == 8) {
      _search();
    } else if (_lookup != _Lookup.idle) {
      setState(() {
        _lookup = _Lookup.idle;
        _result = null;
      });
    }
  }

  Future<void> _submit() async {
    if (!(_form.currentState?.validate() ?? false)) return;
    final found = _found;
    final guardianNeeded = _withGuardian && !(found && _result!.student!.guardian != null);
    final input = EnrollmentInput(
      scheduledCourseId: _aperturaId!,
      document: _doc.text,
      firstName: _first.text,
      paternalSurname: _pat.text,
      maternalSurname: _mat.text,
      birthDate: _birth.text.isEmpty ? null : parseDate(_birth.text),
      phone: _phone.text,
      email: _email.text,
      guardian: guardianNeeded
          ? GuardianInput(
              document: _gDoc.text,
              firstName: _gFirst.text,
              paternalSurname: _gPat.text,
              maternalSurname: _gMat.text,
              phone: _gPhone.text,
              email: _gEmail.text,
            )
          : null,
      enrollmentDate: parseDate(_date.text),
      status: _status,
    );
    setState(() {
      _saving = true;
      _banner = null;
      _server = {};
    });
    try {
      await ref.read(enrollmentRepositoryProvider).enroll(input);
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

  // ── piezas ──

  Widget _field(
    String key,
    TextEditingController c,
    String label, {
    TextInputType? type,
    List<TextInputFormatter>? formatters,
    int? maxLength,
    bool required = false,
    String? hint,
    String? Function(String?)? extra,
  }) =>
      TextFormField(
        controller: c,
        enabled: !_saving,
        keyboardType: type,
        inputFormatters: formatters,
        maxLength: maxLength,
        decoration: InputDecoration(
          labelText: required ? '$label *' : label,
          hintText: hint,
          counterText: '',
        ),
        onChanged: (_) => _server.remove(key),
        validator: (v) {
          if (required && (v ?? '').trim().isEmpty) return 'Campo obligatorio.';
          final x = extra?.call(v);
          if (x != null) return x;
          return _server[key];
        },
      );

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

  Widget _section(String t) => Padding(
        padding: const EdgeInsets.only(top: Tk.s4, bottom: Tk.s2),
        child: Text(t,
            style: const TextStyle(
                fontSize: 13, fontWeight: FontWeight.w700, color: Tk.inkMute)),
      );

  String? _emailCheck(String? v) {
    final x = (v ?? '').trim();
    if (x.isEmpty) return null;
    return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(x)
        ? null
        : 'Ingresa un correo válido.';
  }

  String? _dateCheck(String? v, {bool required = false}) {
    final x = (v ?? '').trim();
    if (x.isEmpty) return required ? 'Campo obligatorio.' : null;
    final d = parseDate(x);
    if (d == null || x.length != 10) return 'Fecha inválida (dd/mm/aaaa).';
    return null;
  }

  Widget _studentCard(Student s) {
    final minor = s.isMinorAt(DateTime.now());
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Tk.s3),
      decoration: BoxDecoration(
        color: Tk.primarySoft,
        borderRadius: BorderRadius.circular(Tk.radiusSm),
        border: Border.all(color: Tk.primaryLine),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            const Icon(Icons.check_circle_outline, size: 18, color: Tk.primary),
            const SizedBox(width: Tk.s2),
            const Text('Alumno registrado',
                style: TextStyle(
                    fontSize: 12, fontWeight: FontWeight.w600, color: Tk.primary)),
          ]),
          const SizedBox(height: Tk.s1),
          Text(s.fullName,
              style: const TextStyle(
                  fontSize: 15, fontWeight: FontWeight.w700, color: Tk.ink)),
          Text(
            [
              if (s.phone.isNotEmpty) s.phone,
              if (s.email.isNotEmpty) s.email,
              if (minor) 'Menor de edad',
            ].join(' · '),
            style: const TextStyle(fontSize: 13, color: Tk.inkMute),
          ),
          if (s.guardian != null)
            Text('Apoderado: ${s.guardian!.fullName}',
                style: const TextStyle(fontSize: 13, color: Tk.inkSoft)),
        ],
      ),
    );
  }

  Widget _warning(String text) => Container(
        width: double.infinity,
        margin: const EdgeInsets.only(top: Tk.s2),
        padding: const EdgeInsets.all(Tk.s3),
        decoration: BoxDecoration(
          color: Tk.warnSoft,
          borderRadius: BorderRadius.circular(Tk.radiusSm),
        ),
        child: Text(text, style: const TextStyle(fontSize: 13, color: Tk.warn)),
      );

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).user!;
    final aperturas = ref.watch(scheduledCoursesProvider);
    final now = DateTime.now();
    final statuses = [
      for (final s in const [
        EnrollmentStatus.activo,
        EnrollmentStatus.preinscrito,
        EnrollmentStatus.convalidado,
      ])
        if (user.canSetStatus(s)) s,
    ];
    final minorNote = !_found &&
        _lookup == _Lookup.done &&
        (parseDate(_birth.text)?.let((d) => Student(
                      document: '',
                      firstName: '',
                      paternalSurname: '',
                      birthDate: d,
                    ).isMinorAt(now)) ??
            false);
    final guardianInfoShown = _found && _result!.student!.guardian != null;

    return AlertDialog(
      title: const Text('Nueva matrícula'),
      content: SizedBox(
        width: 560,
        child: SingleChildScrollView(
          child: Form(
            key: _form,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _section('Curso'),
                aperturas.when(
                  loading: () => const LinearProgressIndicator(color: Tk.primary),
                  error: (_, _) => const Text('No se pudieron cargar los cursos.'),
                  data: (all) {
                    final list = [
                      for (final s in all)
                        if ((user.isGlobal || s.branchName == user.branchName) &&
                            s.stateAt(now) != ScheduleState.finalizado)
                          s,
                    ];
                    return DropdownButtonFormField<int>(
                      initialValue: list.any((s) => s.id == _aperturaId)
                          ? _aperturaId
                          : null,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Curso aperturado *'),
                      items: [
                        for (final s in list)
                          DropdownMenuItem(
                            value: s.id,
                            enabled: s.vacancies > 0,
                            child: Text(
                              '${s.courseName} · ${s.branchName} · ${s.days.join('/')} ${s.startTime} · ${s.vacancies > 0 ? '${s.vacancies} libres' : 'sin vacantes'}',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                      ],
                      onChanged: _saving ? null : (v) => setState(() => _aperturaId = v),
                      validator: (v) => v == null ? 'Selecciona un curso.' : null,
                    );
                  },
                ),
                _section('Alumno'),
                TextFormField(
                  controller: _doc,
                  enabled: !_saving,
                  autofocus: true,
                  maxLength: 8,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: InputDecoration(
                    labelText: 'DNI *',
                    helperText: 'Se busca al completar 8 dígitos.',
                    counterText: '',
                    suffixIcon: _lookup == _Lookup.searching
                        ? const Padding(
                            padding: EdgeInsets.all(12),
                            child: SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(strokeWidth: 2)),
                          )
                        : IconButton(
                            tooltip: 'Buscar',
                            icon: const Icon(Icons.search),
                            onPressed: _doc.text.length == 8 ? _search : null,
                          ),
                  ),
                  onChanged: _onDocChanged,
                  validator: (v) => (v ?? '').trim().length != 8
                      ? 'El DNI debe tener 8 dígitos.'
                      : _server['student_document'],
                ),
                if (_found) ...[
                  const SizedBox(height: Tk.s3),
                  _studentCard(_result!.student!),
                  if (_alreadyEnrolled)
                    _warning('Este alumno ya está matriculado en el curso elegido.'),
                ],
                if (_lookup == _Lookup.done && !_found) ...[
                  const SizedBox(height: Tk.s3),
                  const Text(
                      'No está registrado. Completa sus datos para darlo de alta.',
                      style: TextStyle(fontSize: 13, color: Tk.inkMute)),
                  const SizedBox(height: Tk.s3),
                  _pair(
                    _field('first_name', _first, 'Nombres', required: true, maxLength: 255),
                    _field('paternal_surname', _pat, 'Apellido paterno',
                        required: true, maxLength: 255),
                  ),
                  const SizedBox(height: Tk.s3),
                  _pair(
                    _field('maternal_surname', _mat, 'Apellido materno', maxLength: 255),
                    _field(
                      'birth_date',
                      _birth,
                      'Fecha de nacimiento',
                      hint: 'dd/mm/aaaa',
                      type: TextInputType.number,
                      formatters: [DateSlashFormatter()],
                      extra: _dateCheck,
                    ),
                  ),
                  const SizedBox(height: Tk.s3),
                  _pair(
                    _field('phone', _phone, 'Teléfono',
                        type: TextInputType.phone, maxLength: 30),
                    _field('email', _email, 'Correo',
                        type: TextInputType.emailAddress, extra: _emailCheck),
                  ),
                  if (minorNote) _warning('El alumno es menor de edad: conviene registrar apoderado.'),
                ],
                if (_lookup == _Lookup.done && !guardianInfoShown) ...[
                  _section('Apoderado'),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _withGuardian,
                    onChanged: _saving ? null : (v) => setState(() => _withGuardian = v),
                    title: const Text('Registrar apoderado'),
                  ),
                  if (_withGuardian) ...[
                    _pair(
                      _field('guardian_document', _gDoc, 'DNI del apoderado',
                          required: true,
                          maxLength: 8,
                          type: TextInputType.number,
                          formatters: [FilteringTextInputFormatter.digitsOnly],
                          extra: (v) => (v ?? '').trim().length != 8
                              ? 'El DNI debe tener 8 dígitos.'
                              : null),
                      _field('guardian_first_name', _gFirst, 'Nombres',
                          required: true, maxLength: 100),
                    ),
                    const SizedBox(height: Tk.s3),
                    _pair(
                      _field('guardian_paternal_surname', _gPat, 'Apellido paterno',
                          required: true, maxLength: 100),
                      _field('guardian_maternal_surname', _gMat, 'Apellido materno',
                          maxLength: 100),
                    ),
                    const SizedBox(height: Tk.s3),
                    _pair(
                      _field('guardian_phone', _gPhone, 'Teléfono',
                          type: TextInputType.phone, maxLength: 30),
                      _field('guardian_email', _gEmail, 'Correo',
                          type: TextInputType.emailAddress, extra: _emailCheck),
                    ),
                  ],
                ],
                _section('Matrícula'),
                _pair(
                  _field(
                    'enrollment_date',
                    _date,
                    'Fecha de matrícula',
                    hint: 'dd/mm/aaaa',
                    type: TextInputType.number,
                    formatters: [DateSlashFormatter()],
                    extra: (v) => _dateCheck(v, required: true),
                  ),
                  DropdownButtonFormField<EnrollmentStatus>(
                    initialValue: _status,
                    decoration: const InputDecoration(labelText: 'Estado'),
                    items: [
                      for (final s in statuses)
                        DropdownMenuItem(value: s, child: Text(s.label)),
                    ],
                    onChanged: _saving ? null : (v) => setState(() => _status = v!),
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
          onPressed: (_saving ||
                  _lookup != _Lookup.done ||
                  _alreadyEnrolled)
              ? null
              : _submit,
          style: FilledButton.styleFrom(minimumSize: const Size(120, 44)),
          child: _saving
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                )
              : const Text('Matricular'),
        ),
      ],
    );
  }
}

extension _Let<T> on T {
  R let<R>(R Function(T) f) => f(this);
}

// ───────────────────── Cambiar estado ─────────────────────

Future<bool> showEnrollmentStatusForm(BuildContext context, Enrollment e) async {
  final ok = await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (_) => _StatusForm(enrollment: e),
  );
  return ok ?? false;
}

class _StatusForm extends ConsumerStatefulWidget {
  const _StatusForm({required this.enrollment});
  final Enrollment enrollment;

  @override
  ConsumerState<_StatusForm> createState() => _StatusFormState();
}

class _StatusFormState extends ConsumerState<_StatusForm> {
  EnrollmentStatus? _picked;
  bool _saving = false;
  String? _banner;

  Future<void> _submit() async {
    setState(() {
      _saving = true;
      _banner = null;
    });
    try {
      await ref
          .read(enrollmentRepositoryProvider)
          .changeEnrollmentStatus(widget.enrollment.id, _picked!);
      if (mounted) Navigator.of(context).pop(true);
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
    final e = widget.enrollment;
    final options = [
      for (final s in user.settableStatuses)
        if (s != e.status) s,
    ];

    return AlertDialog(
      title: const Text('Cambiar estado'),
      content: SizedBox(
        width: 420,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(e.studentName,
                  style: const TextStyle(
                      fontWeight: FontWeight.w700, color: Tk.ink)),
              Text('${e.courseName} · estado actual: ${e.status.label}',
                  style: const TextStyle(fontSize: 13, color: Tk.inkMute)),
              const SizedBox(height: Tk.s3),
              if (user.position != StaffPosition.admin)
                const Padding(
                  padding: EdgeInsets.only(bottom: Tk.s2),
                  child: Text(
                    'Algunos estados los asigna solo Coordinación o la Dirección General.',
                    style: TextStyle(fontSize: 12, color: Tk.inkMute),
                  ),
                ),
              for (final s in options)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  enabled: !_saving,
                  onTap: () => setState(() => _picked = s),
                  leading: Icon(
                    _picked == s
                        ? Icons.radio_button_checked
                        : Icons.radio_button_unchecked,
                    color: _picked == s ? Tk.primary : Tk.inkMute,
                  ),
                  title: Text(s.label),
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
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(false),
          child: const Text('Cancelar'),
        ),
        FilledButton(
          onPressed: (_saving || _picked == null) ? null : _submit,
          style: FilledButton.styleFrom(minimumSize: const Size(110, 44)),
          child: _saving
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                )
              : const Text('Guardar'),
        ),
      ],
    );
  }
}
