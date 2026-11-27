import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../core/theme.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../data/repositories.dart';
import '../sedes/sedes_dialogs.dart' show errorText;

/// Lógica común de los tres formularios: validar, guardar, mostrar los errores
/// del servidor bajo cada campo y cerrar con su propio contexto.
mixin _FormSubmit<T extends ConsumerStatefulWidget> on ConsumerState<T> {
  final form = GlobalKey<FormState>();
  bool saving = false;
  String? banner;
  Map<String, String> server = {};

  String? serverErr(String key) => server[key];

  void clearErr(String key) {
    if (server.remove(key) != null) setState(() {});
  }

  Future<void> submit(Future<void> Function() action) async {
    if (!(form.currentState?.validate() ?? false)) return;
    setState(() {
      saving = true;
      banner = null;
      server = {};
    });
    try {
      await action();
      if (mounted) Navigator.of(context).pop(true);
    } on ValidationException catch (e) {
      if (!mounted) return;
      setState(() {
        saving = false;
        server = e.fields;
      });
      form.currentState?.validate();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        saving = false;
        banner = errorText(e);
      });
    }
  }

  Widget shell({
    required String title,
    required String submitLabel,
    required List<Widget> children,
    bool loading = false,
  }) =>
      AlertDialog(
        title: Text(title),
        content: SizedBox(
          width: 560,
          child: loading
              ? const Padding(
                  padding: EdgeInsets.all(Tk.s5),
                  child: Center(child: CircularProgressIndicator(color: Tk.primary)),
                )
              : SingleChildScrollView(
                  child: Form(
                    key: form,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        ...children,
                        if (banner != null)
                          Container(
                            margin: const EdgeInsets.only(top: Tk.s3),
                            padding: const EdgeInsets.all(Tk.s3),
                            decoration: BoxDecoration(
                              color: Tk.dangerSoft,
                              borderRadius: BorderRadius.circular(Tk.radiusSm),
                            ),
                            child: Text(banner!,
                                style: const TextStyle(color: Tk.danger, fontSize: 13)),
                          ),
                      ],
                    ),
                  ),
                ),
        ),
        actions: [
          TextButton(
            onPressed: saving ? null : () => Navigator.of(context).pop(false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: (saving || loading) ? null : _trigger,
            style: FilledButton.styleFrom(minimumSize: const Size(120, 44)),
            child: saving
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  )
                : Text(submitLabel),
          ),
        ],
      );

  /// Cada formulario define qué se guarda.
  void _trigger() => onSave();
  void onSave();

  // ── campos ──

  Widget textField(
    String key,
    TextEditingController c,
    String label, {
    bool required = false,
    int maxLength = 255,
    int maxLines = 1,
    String? helper,
    TextInputType? type,
    List<TextInputFormatter>? formatters,
    String? Function(String?)? extra,
    VoidCallback? onChanged,
  }) =>
      TextFormField(
        controller: c,
        enabled: !saving,
        maxLength: maxLength,
        maxLines: maxLines,
        keyboardType: type,
        inputFormatters: formatters,
        decoration: InputDecoration(
          labelText: required ? '$label *' : label,
          helperText: helper,
          counterText: '',
        ),
        onChanged: (_) {
          clearErr(key);
          onChanged?.call();
        },
        validator: (v) {
          if (required && (v ?? '').trim().isEmpty) return 'Campo obligatorio.';
          return extra?.call(v) ?? serverErr(key);
        },
      );

  Widget groupError(String key) => serverErr(key) == null
      ? const SizedBox.shrink()
      : Padding(
          padding: const EdgeInsets.only(top: Tk.s2),
          child: Text(serverErr(key)!,
              style: const TextStyle(color: Tk.danger, fontSize: 12)),
        );

  Widget sectionLabel(String t) => Padding(
        padding: const EdgeInsets.only(top: Tk.s4, bottom: Tk.s2),
        child: Text(t,
            style: const TextStyle(
                fontSize: 13, fontWeight: FontWeight.w700, color: Tk.inkMute)),
      );
}

String? _weeksCheck(String? v, int max, {bool required = true}) {
  final x = (v ?? '').trim();
  if (x.isEmpty) return required ? 'Campo obligatorio.' : null;
  final n = int.tryParse(x);
  if (n == null) return 'Ingresa un número.';
  if (n < 1 || n > max) return 'Debe estar entre 1 y $max.';
  return null;
}

// ───────────────────────── Carrera ─────────────────────────

Future<bool> showCareerForm(BuildContext context, {Career? career}) async =>
    await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _CareerForm(career: career),
    ) ??
    false;

class _CareerForm extends ConsumerStatefulWidget {
  const _CareerForm({this.career});
  final Career? career;

  @override
  ConsumerState<_CareerForm> createState() => _CareerFormState();
}

class _CareerFormState extends ConsumerState<_CareerForm> with _FormSubmit {
  late final _name = TextEditingController(text: widget.career?.name);
  late final _desc = TextEditingController(text: widget.career?.description);
  late final _weeks = TextEditingController(
      text: widget.career == null ? '' : widget.career!.durationInWeeks.toString());
  late final List<int> _moduleIds = List.of(widget.career?.moduleIds ?? const []);
  late final Set<int> _branchIds = {...?widget.career?.branchIds};

  @override
  void dispose() {
    _name.dispose();
    _desc.dispose();
    _weeks.dispose();
    super.dispose();
  }

  @override
  void onSave() => submit(() => ref.read(catalogRepositoryProvider).run(
        widget.career == null,
        create: (r) => r.createCareer(_input()),
        update: (r) => r.updateCareer(widget.career!.id, _input()),
      ));

  CareerInput _input() => CareerInput(
        name: _name.text,
        description: _desc.text,
        durationInWeeks: _weeks.text.trim().isEmpty ? null : int.parse(_weeks.text.trim()),
        moduleIds: _moduleIds,
        branchIds: _branchIds.toList(),
      );

  void _move(int i, int delta) {
    final j = i + delta;
    if (j < 0 || j >= _moduleIds.length) return;
    setState(() {
      final x = _moduleIds.removeAt(i);
      _moduleIds.insert(j, x);
      clearErr('modules');
    });
  }

  @override
  Widget build(BuildContext context) {
    final modules = ref.watch(modulesProvider);
    final branches = ref.watch(branchesProvider);
    final all = modules.asData?.value ?? const <Module>[];
    final byId = {for (final m in all) m.id: m};
    final sum = _moduleIds.fold(0, (s, id) => s + (byId[id]?.durationInWeeks ?? 0));
    final free = [for (final m in all) if (!_moduleIds.contains(m.id)) m];

    return shell(
      title: widget.career == null ? 'Nueva carrera' : 'Editar carrera',
      submitLabel: widget.career == null ? 'Crear carrera' : 'Guardar',
      loading: modules.isLoading || branches.isLoading,
      children: [
        textField('name', _name, 'Nombre de la carrera', required: true),
        const SizedBox(height: Tk.s3),
        textField('description', _desc, 'Descripción', maxLength: 1000, maxLines: 3),
        const SizedBox(height: Tk.s3),
        textField(
          'duration_in_weeks',
          _weeks,
          'Duración (semanas)',
          maxLength: 3,
          type: TextInputType.number,
          formatters: [FilteringTextInputFormatter.digitsOnly],
          helper: _moduleIds.isEmpty
              ? 'Entre 1 y 200. Vacío = suma de los módulos.'
              : 'Entre 1 y 200. Vacío = $sum semanas (suma de los módulos).',
          extra: (v) => _weeksCheck(v, 200, required: false),
          onChanged: () => setState(() {}),
        ),
        sectionLabel('Módulos, en orden cronológico'),
        if (_moduleIds.isEmpty)
          const Text('Aún no hay módulos.',
              style: TextStyle(fontSize: 13, color: Tk.inkMute)),
        for (var i = 0; i < _moduleIds.length; i++)
          Container(
            margin: const EdgeInsets.only(bottom: Tk.s1),
            padding: const EdgeInsets.only(left: Tk.s3),
            decoration: BoxDecoration(
              border: Border.all(color: Tk.line),
              borderRadius: BorderRadius.circular(Tk.radiusSm),
            ),
            child: Row(
              children: [
                Text('${i + 1}.', style: const TextStyle(color: Tk.inkMute)),
                const SizedBox(width: Tk.s2),
                Expanded(
                  child: Text(
                    '${byId[_moduleIds[i]]?.name ?? '—'} · ${byId[_moduleIds[i]]?.durationInWeeks ?? 0} sem.',
                    style: const TextStyle(fontSize: 14, color: Tk.ink),
                  ),
                ),
                IconButton(
                  tooltip: 'Subir',
                  onPressed: saving || i == 0 ? null : () => _move(i, -1),
                  icon: const Icon(Icons.arrow_upward, size: 18),
                  visualDensity: VisualDensity.compact,
                ),
                IconButton(
                  tooltip: 'Bajar',
                  onPressed: saving || i == _moduleIds.length - 1
                      ? null
                      : () => _move(i, 1),
                  icon: const Icon(Icons.arrow_downward, size: 18),
                  visualDensity: VisualDensity.compact,
                ),
                IconButton(
                  tooltip: 'Quitar',
                  onPressed: saving
                      ? null
                      : () => setState(() {
                            _moduleIds.removeAt(i);
                            clearErr('modules');
                          }),
                  icon: const Icon(Icons.close, size: 18, color: Tk.danger),
                  visualDensity: VisualDensity.compact,
                ),
              ],
            ),
          ),
        if (free.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: Tk.s2),
            child: DropdownButtonFormField<int>(
              key: ValueKey('add-${_moduleIds.length}'),
              initialValue: null,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'Agregar módulo'),
              items: [
                for (final m in free)
                  DropdownMenuItem(
                    value: m.id,
                    child: Text('${m.name} · ${m.durationInWeeks} sem.',
                        overflow: TextOverflow.ellipsis),
                  ),
              ],
              onChanged: saving
                  ? null
                  : (v) => setState(() {
                        if (v != null) _moduleIds.add(v);
                        clearErr('modules');
                      }),
            ),
          ),
        groupError('modules'),
        sectionLabel('Sedes donde se dicta'),
        Wrap(
          spacing: Tk.s2,
          runSpacing: Tk.s2,
          children: [
            for (final b in branches.asData?.value ?? const <Branch>[])
              FilterChip(
                label: Text(b.name),
                selected: _branchIds.contains(b.id),
                showCheckmark: false,
                onSelected: saving
                    ? null
                    : (v) => setState(() {
                          v ? _branchIds.add(b.id) : _branchIds.remove(b.id);
                          clearErr('branch_ids');
                        }),
              ),
          ],
        ),
        groupError('branch_ids'),
      ],
    );
  }
}

// ───────────────────────── Módulo ─────────────────────────

Future<bool> showModuleForm(BuildContext context, {Module? module}) async =>
    await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _ModuleForm(module: module),
    ) ??
    false;

class _ModuleForm extends ConsumerStatefulWidget {
  const _ModuleForm({this.module});
  final Module? module;

  @override
  ConsumerState<_ModuleForm> createState() => _ModuleFormState();
}

class _ModuleFormState extends ConsumerState<_ModuleForm> with _FormSubmit {
  late final _name = TextEditingController(text: widget.module?.name);
  late final _desc = TextEditingController(text: widget.module?.description);
  late final _weeks = TextEditingController(
      text: widget.module == null ? '' : widget.module!.durationInWeeks.toString());
  late final Set<int> _courseIds = {...?widget.module?.courseIds};

  @override
  void dispose() {
    _name.dispose();
    _desc.dispose();
    _weeks.dispose();
    super.dispose();
  }

  @override
  void onSave() => submit(() => ref.read(catalogRepositoryProvider).run(
        widget.module == null,
        create: (r) => r.createModule(_input()),
        update: (r) => r.updateModule(widget.module!.id, _input()),
      ));

  ModuleInput _input() => ModuleInput(
        name: _name.text,
        description: _desc.text,
        durationInWeeks: int.parse(_weeks.text.trim()),
        courseIds: _courseIds.toList(),
      );

  @override
  Widget build(BuildContext context) {
    final courses = ref.watch(coursesProvider);
    // Solo cursos modulares sin módulo, o ya de este módulo (como el backend).
    final assignable = [
      for (final c in courses.asData?.value ?? const <Course>[])
        if (!c.isWorkshop && (c.moduleId == null || c.moduleId == widget.module?.id)) c,
    ];

    return shell(
      title: widget.module == null ? 'Nuevo módulo' : 'Editar módulo',
      submitLabel: widget.module == null ? 'Crear módulo' : 'Guardar',
      loading: courses.isLoading,
      children: [
        textField('name', _name, 'Nombre del módulo', required: true),
        const SizedBox(height: Tk.s3),
        textField('description', _desc, 'Descripción o competencias',
            maxLength: 1000, maxLines: 3),
        const SizedBox(height: Tk.s3),
        textField(
          'duration_in_weeks',
          _weeks,
          'Duración (semanas)',
          required: true,
          maxLength: 2,
          type: TextInputType.number,
          formatters: [FilteringTextInputFormatter.digitsOnly],
          helper: 'Entre 1 y 52.',
          extra: (v) => _weeksCheck(v, 52),
        ),
        sectionLabel('Cursos del módulo'),
        if (assignable.isEmpty)
          const Text(
            'No hay cursos modulares libres. Crea uno en «Cursos y talleres».',
            style: TextStyle(fontSize: 13, color: Tk.inkMute),
          ),
        for (final c in assignable)
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            dense: true,
            controlAffinity: ListTileControlAffinity.leading,
            value: _courseIds.contains(c.id),
            onChanged: saving
                ? null
                : (v) => setState(() {
                      v == true ? _courseIds.add(c.id) : _courseIds.remove(c.id);
                      clearErr('course_ids');
                    }),
            title: Text(c.name, style: const TextStyle(fontSize: 14)),
            subtitle: Text('${c.durationInWeeks} semanas',
                style: const TextStyle(fontSize: 12)),
          ),
        groupError('course_ids'),
      ],
    );
  }
}

// ───────────────────── Curso o taller ─────────────────────

Future<bool> showCourseForm(BuildContext context, {Course? course}) async =>
    await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _CourseForm(course: course),
    ) ??
    false;

class _CourseForm extends ConsumerStatefulWidget {
  const _CourseForm({this.course});
  final Course? course;

  @override
  ConsumerState<_CourseForm> createState() => _CourseFormState();
}

class _CourseFormState extends ConsumerState<_CourseForm> with _FormSubmit {
  late final _name = TextEditingController(text: widget.course?.name);
  late final _desc = TextEditingController(text: widget.course?.description);
  late final _weeks = TextEditingController(
      text: widget.course == null ? '' : widget.course!.durationInWeeks.toString());
  late bool _isWorkshop = widget.course?.isWorkshop ?? false;
  late int? _moduleId = widget.course?.moduleId;

  @override
  void dispose() {
    _name.dispose();
    _desc.dispose();
    _weeks.dispose();
    super.dispose();
  }

  @override
  void onSave() => submit(() => ref.read(catalogRepositoryProvider).run(
        widget.course == null,
        create: (r) => r.createCourse(_input()),
        update: (r) => r.updateCourse(widget.course!.id, _input()),
      ));

  CourseInput _input() => CourseInput(
        name: _name.text,
        description: _desc.text,
        durationInWeeks: int.parse(_weeks.text.trim()),
        isWorkshop: _isWorkshop,
        moduleId: _isWorkshop ? null : _moduleId,
      );

  @override
  Widget build(BuildContext context) {
    final modules = ref.watch(modulesProvider);
    return shell(
      title: widget.course == null ? 'Nuevo curso o taller' : 'Editar curso o taller',
      submitLabel: widget.course == null ? 'Crear' : 'Guardar',
      loading: modules.isLoading,
      children: [
        SegmentedButton<bool>(
          segments: const [
            ButtonSegment(value: false, label: Text('Curso modular')),
            ButtonSegment(value: true, label: Text('Taller libre')),
          ],
          selected: {_isWorkshop},
          showSelectedIcon: false,
          onSelectionChanged: saving
              ? null
              : (s) => setState(() {
                    _isWorkshop = s.first;
                    clearErr('module_id');
                  }),
        ),
        const SizedBox(height: Tk.s3),
        textField('name', _name, 'Nombre', required: true),
        const SizedBox(height: Tk.s3),
        textField('description', _desc, 'Temario o descripción',
            maxLength: 1000, maxLines: 3),
        const SizedBox(height: Tk.s3),
        textField(
          'duration_in_weeks',
          _weeks,
          'Duración (semanas)',
          required: true,
          maxLength: 2,
          type: TextInputType.number,
          formatters: [FilteringTextInputFormatter.digitsOnly],
          helper: 'Entre 1 y 52.',
          extra: (v) => _weeksCheck(v, 52),
        ),
        if (!_isWorkshop) ...[
          const SizedBox(height: Tk.s3),
          DropdownButtonFormField<int?>(
            initialValue: _moduleId,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Módulo'),
            items: [
              const DropdownMenuItem<int?>(value: null, child: Text('Sin módulo')),
              for (final m in modules.asData?.value ?? const <Module>[])
                DropdownMenuItem<int?>(
                  value: m.id,
                  child: Text(m.name, overflow: TextOverflow.ellipsis),
                ),
            ],
            onChanged: saving
                ? null
                : (v) => setState(() {
                      _moduleId = v;
                      clearErr('module_id');
                    }),
            validator: (_) => serverErr('module_id'),
          ),
        ] else
          const Padding(
            padding: EdgeInsets.only(top: Tk.s3),
            child: Text('Un taller libre no pertenece a ningún módulo.',
                style: TextStyle(fontSize: 13, color: Tk.inkMute)),
          ),
        if (_isWorkshop) groupError('module_id'),
      ],
    );
  }
}

/// Elige crear o actualizar sin repetir el `if` en cada formulario.
extension _Run on CatalogRepository {
  Future<void> run(
    bool creating, {
    required Future<void> Function(CatalogRepository r) create,
    required Future<void> Function(CatalogRepository r) update,
  }) =>
      creating ? create(this) : update(this);
}
