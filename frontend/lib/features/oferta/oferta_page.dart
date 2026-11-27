import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../core/permissions.dart';
import '../../core/theme.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../data/repositories.dart';
import '../../shared/widgets.dart';
import '../auth/auth_controller.dart';
import '../matriculas/matriculas_page.dart' show toast;
import '../sedes/sedes_dialogs.dart' show confirmDialog, errorText, showBlocked;
import 'oferta_dialogs.dart';

enum _CourseFilter { todos, modulares, talleres }

class OfertaPage extends ConsumerStatefulWidget {
  const OfertaPage({super.key});

  @override
  ConsumerState<OfertaPage> createState() => _OfertaPageState();
}

class _OfertaPageState extends ConsumerState<OfertaPage>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 3, vsync: this)
    ..addListener(() => setState(() {}));
  String _q = '';
  _CourseFilter _filter = _CourseFilter.todos;

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  bool _match(String text) => fold(text).contains(fold(_q));

  void _refresh() {
    ref.invalidate(careersProvider);
    ref.invalidate(modulesProvider);
    ref.invalidate(coursesProvider);
    ref.invalidate(scheduledCoursesProvider); // muestran el nombre del curso
  }

  Future<void> _afterForm(Future<bool> form, String ok) async {
    if (await form && mounted) {
      _refresh();
      toast(context, ok);
    }
  }

  Future<void> _create() => switch (_tabs.index) {
    0 => _afterForm(showCareerForm(context), 'Carrera creada.'),
    1 => _afterForm(showModuleForm(context), 'Módulo creado.'),
    _ => _afterForm(showCourseForm(context), 'Curso o taller creado.'),
  };

  Future<void> _remove({
    required String title,
    required String message,
    required Future<void> Function() action,
    required String ok,
  }) async {
    final yes = await confirmDialog(
      context,
      title: title,
      message: message,
      confirmLabel: 'Eliminar',
      danger: true,
    );
    if (!yes || !mounted) return;
    try {
      await action();
      if (!mounted) return;
      _refresh();
      toast(context, ok);
    } catch (e) {
      if (mounted) {
        await showBlocked(context, 'No se puede eliminar', errorText(e));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authProvider).user!;
    final repo = ref.read(catalogRepositoryProvider);
    final canManage = user.canManageCatalog;

    return PageFrame(
      title: 'Oferta académica',
      subtitle: 'Carreras, módulos, cursos y talleres del catálogo',
      scroll: false,
      actions: [
        if (canManage)
          FilledButton.icon(
            onPressed: _create,
            style: FilledButton.styleFrom(minimumSize: const Size(0, 42)),
            icon: const Icon(Icons.add, size: 20),
            label: Text(switch (_tabs.index) {
              0 => 'Nueva carrera',
              1 => 'Nuevo módulo',
              _ => 'Nuevo curso',
            }),
          ),
      ],
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: Tk.s4),
            child: TabBar(
              controller: _tabs,
              isScrollable: MediaQuery.sizeOf(context).width < kWideBreakpoint,
              tabAlignment: MediaQuery.sizeOf(context).width < kWideBreakpoint
                  ? TabAlignment.start
                  : TabAlignment.fill,
              tabs: const [
                Tab(text: 'Carreras'),
                Tab(text: 'Módulos'),
                Tab(text: 'Cursos y talleres'),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(Tk.s4),
            child: SearchField(
              hint: 'Buscar en el catálogo',
              onChanged: (v) => setState(() => _q = v),
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: _tabs,
              children: [
                _careers(canManage, repo),
                _modules(canManage, repo),
                _courses(canManage, repo),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _careers(
    bool canManage,
    CatalogRepository repo,
  ) => AsyncBody<List<Career>>(
    value: ref.watch(careersProvider),
    onRetry: () => ref.invalidate(careersProvider),
    builder: (list) {
      final rows = list.where((c) => _match(c.name)).toList();
      if (rows.isEmpty) return const EmptyState('Sin resultados.');
      return _CardList(
        children: [
          for (final c in rows)
            _CatalogCard(
              title: c.name,
              description: c.description,
              badges: [
                Pill(
                  c.durationInWeeks > 0
                      ? '${c.durationInWeeks} semanas'
                      : 'Duración sin definir',
                ),
                for (final b in c.branchNames)
                  Pill(b, fg: Tk.info, bg: Tk.infoSoft),
                if (c.branchNames.isEmpty)
                  const Pill('Sin sedes', fg: Tk.warn, bg: Tk.warnSoft),
              ],
              detailTitle: 'Módulos en orden',
              detail: [
                for (var i = 0; i < c.moduleNames.length; i++)
                  '${i + 1}. ${c.moduleNames[i]}',
              ],
              onEdit: canManage
                  ? () => _afterForm(
                      showCareerForm(context, career: c),
                      'Carrera actualizada.',
                    )
                  : null,
              onDelete: canManage
                  ? () => _remove(
                      title: 'Eliminar carrera',
                      message:
                          '¿Eliminar «${c.name}»? Sus módulos se conservan: solo se desvinculan de esta carrera.',
                      action: () => repo.deleteCareer(c.id),
                      ok: 'Carrera eliminada.',
                    )
                  : null,
            ),
        ],
      );
    },
  );

  Widget _modules(
    bool canManage,
    CatalogRepository repo,
  ) => AsyncBody<List<Module>>(
    value: ref.watch(modulesProvider),
    onRetry: () => ref.invalidate(modulesProvider),
    builder: (list) {
      final rows = list.where((m) => _match(m.name)).toList();
      if (rows.isEmpty) return const EmptyState('Sin resultados.');
      return _CardList(
        children: [
          for (final m in rows)
            _CatalogCard(
              title: m.name,
              description: m.description,
              badges: [
                Pill('${m.durationInWeeks} semanas'),
                Pill(
                  '${m.courseNames.length} cursos',
                  fg: Tk.inkSoft,
                  bg: Tk.canvas,
                ),
              ],
              detailTitle: 'Cursos',
              detail: m.courseNames,
              onEdit: canManage
                  ? () => _afterForm(
                      showModuleForm(context, module: m),
                      'Módulo actualizado.',
                    )
                  : null,
              onDelete: canManage
                  ? () => _remove(
                      title: 'Eliminar módulo',
                      message:
                          '¿Eliminar «${m.name}»? Solo es posible si no tiene cursos, carreras ni matrículas asociadas.',
                      action: () => repo.deleteModule(m.id),
                      ok: 'Módulo eliminado.',
                    )
                  : null,
            ),
        ],
      );
    },
  );

  Widget _courses(bool canManage, CatalogRepository repo) => Column(
    children: [
      SizedBox(
        height: 40,
        child: ListView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: Tk.s4),
          children: [
            for (final (f, label) in const [
              (_CourseFilter.todos, 'Todos'),
              (_CourseFilter.modulares, 'Cursos modulares'),
              (_CourseFilter.talleres, 'Talleres libres'),
            ])
              Padding(
                padding: const EdgeInsets.only(right: Tk.s2),
                child: ChoiceChip(
                  label: Text(label),
                  selected: _filter == f,
                  showCheckmark: false,
                  onSelected: (_) => setState(() => _filter = f),
                ),
              ),
          ],
        ),
      ),
      const SizedBox(height: Tk.s3),
      Expanded(
        child: AsyncBody<List<Course>>(
          value: ref.watch(coursesProvider),
          onRetry: () => ref.invalidate(coursesProvider),
          builder: (list) {
            final rows = list.where((c) {
              if (_filter == _CourseFilter.modulares && c.isWorkshop) {
                return false;
              }
              if (_filter == _CourseFilter.talleres && !c.isWorkshop) {
                return false;
              }
              return _match(c.name);
            }).toList();
            if (rows.isEmpty) return const EmptyState('Sin resultados.');
            return _CardList(
              children: [
                for (final c in rows)
                  _CatalogCard(
                    title: c.name,
                    description: c.description,
                    badges: [
                      Pill('${c.durationInWeeks} semanas'),
                      c.isWorkshop
                          ? const Pill(
                              'Taller libre',
                              fg: Tk.warn,
                              bg: Tk.warnSoft,
                            )
                          : const Pill('Curso modular'),
                      if (!c.isWorkshop)
                        Pill(
                          c.moduleName ?? 'Sin módulo',
                          fg: Tk.inkSoft,
                          bg: Tk.canvas,
                        ),
                    ],
                    onEdit: canManage
                        ? () => _afterForm(
                            showCourseForm(context, course: c),
                            'Curso o taller actualizado.',
                          )
                        : null,
                    onDelete: canManage
                        ? () => _remove(
                            title:
                                'Eliminar ${c.isWorkshop ? 'taller' : 'curso'}',
                            message:
                                '¿Eliminar «${c.name}»? Solo es posible si no tiene aperturas.',
                            action: () => repo.deleteCourse(c.id),
                            ok: 'Eliminado.',
                          )
                        : null,
                  ),
              ],
            );
          },
        ),
      ),
    ],
  );
}

class _CardList extends StatelessWidget {
  const _CardList({required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => ListView.separated(
    padding: const EdgeInsets.fromLTRB(Tk.s4, 0, Tk.s4, Tk.s4),
    itemCount: children.length,
    separatorBuilder: (_, _) => const SizedBox(height: Tk.s3),
    itemBuilder: (_, i) => children[i],
  );
}

class _CatalogCard extends StatelessWidget {
  const _CatalogCard({
    required this.title,
    required this.description,
    required this.badges,
    this.detailTitle,
    this.detail = const [],
    this.onEdit,
    this.onDelete,
  });

  final String title;
  final String description;
  final List<Widget> badges;
  final String? detailTitle;
  final List<String> detail;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(Tk.s4, Tk.s4, Tk.s2, Tk.s4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    title,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: Tk.ink,
                    ),
                  ),
                ),
              ),
              if (onEdit != null || onDelete != null)
                PopupMenuButton<String>(
                  tooltip: 'Acciones',
                  icon: const Icon(Icons.more_vert, size: 20),
                  onSelected: (v) =>
                      v == 'edit' ? onEdit?.call() : onDelete?.call(),
                  itemBuilder: (_) => [
                    if (onEdit != null)
                      const PopupMenuItem(value: 'edit', child: Text('Editar')),
                    if (onDelete != null)
                      const PopupMenuItem(
                        value: 'delete',
                        child: Text('Eliminar'),
                      ),
                  ],
                ),
            ],
          ),
          if (description.isNotEmpty) ...[
            const SizedBox(height: Tk.s1),
            Padding(
              padding: const EdgeInsets.only(right: Tk.s3),
              child: Text(
                description,
                style: const TextStyle(fontSize: 14, color: Tk.inkMute),
              ),
            ),
          ],
          const SizedBox(height: Tk.s3),
          Wrap(spacing: Tk.s2, runSpacing: Tk.s2, children: badges),
          if (detail.isNotEmpty) ...[
            const SizedBox(height: Tk.s3),
            Text(
              detailTitle ?? '',
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: Tk.inkMute,
              ),
            ),
            const SizedBox(height: Tk.s1),
            for (final d in detail)
              Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Text(d, style: const TextStyle(fontSize: 14)),
              ),
          ],
        ],
      ),
    ),
  );
}
