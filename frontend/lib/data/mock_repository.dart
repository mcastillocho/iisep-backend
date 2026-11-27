import '../core/permissions.dart';
import '../shared/inputs.dart';
import 'models.dart';
import 'repositories.dart';

/// Datos de demostración basados en database/seeders/DatabaseSeeder.php del
/// backend (sedes Comas y Puente Piedra, carreras, módulos, talleres).
class MockRepository
    implements
        AuthRepository,
        AcademicRepository,
        InfrastructureRepository,
        EnrollmentRepository,
        ScheduleRepository,
        CatalogRepository {
  static const _latency = Duration(milliseconds: 350);

  // ── Auth ────────────────────────────────────────────────────────────────
  // Usuarios y cargos del seeder. Contraseña de demostración única.
  static const _password = 'admin123';
  static const _users = <String, AuthUser>{
    'admin': AuthUser(
      id: 1,
      fullName: 'Administrador General',
      username: 'admin',
      position: StaffPosition.admin,
    ),
    'storres': AuthUser(
      id: 2,
      fullName: 'Sofia Torres Castro',
      username: 'storres',
      position: StaffPosition.coord,
      branchId: 1,
      branchName: 'Sede Comas',
    ),
    'mcastro': AuthUser(
      id: 3,
      fullName: 'Milagros Castro Mendoza',
      username: 'mcastro',
      position: StaffPosition.secre,
      branchId: 1,
      branchName: 'Sede Comas',
    ),
    'lflores': AuthUser(
      id: 4,
      fullName: 'Luisa Flores Medina',
      username: 'lflores',
      position: StaffPosition.coord,
      branchId: 2,
      branchName: 'Sede Puente Piedra',
    ),
  };

  @override
  Future<AuthUser> login(String login, String password) async {
    await Future<void>.delayed(_latency);
    final key = login.trim().toLowerCase();
    final user = _users[key];
    if (user == null || password != _password) {
      throw const AuthException('Usuario o contraseña incorrectos.');
    }
    _actor = user;
    return user;
  }

  @override
  Future<void> logout() async {
    _actor = null;
  }

  // ── Académico ───────────────────────────────────────────────────────────
  @override
  Future<List<Branch>> branches() async {
    await Future<void>.delayed(_latency);
    return List.of(_branches);
  }

  // ── Sedes y aulas: escritura ────────────────────────────────────────────
  // Reflejan BranchController/ClassroomController. En el backend real el
  // actor sale del token; aquí se recuerda al iniciar sesión.
  static const _seedBranches = [
      Branch(
        id: 1,
        name: 'Sede Comas',
        code: 'COM',
        address: 'Av. Túpac Amaru 3850 - La Pascana (Costado Plaza Vea)',
        phone: '970518364',
        classrooms: [
          Classroom(id: 1, branchId: 1, name: 'Aula 101 - Teoría', capacity: 35),
          Classroom(
              id: 2, branchId: 1, name: 'Laboratorio de Cómputo 1', capacity: 24),
          Classroom(
              id: 3,
              branchId: 1,
              name: 'Taller de Gastronomía y Cocina',
              capacity: 20),
        ],
      ),
      Branch(
        id: 2,
        name: 'Sede Puente Piedra',
        code: 'PUP',
        address: 'Jr. Ricardo Palma 241 (2° piso de la RENIEC)',
        phone: '987710344',
        classrooms: [
          Classroom(
              id: 4, branchId: 2, name: 'Aula 201 - Multiusos', capacity: 30),
          Classroom(
              id: 5,
              branchId: 2,
              name: 'Taller de Estética y Cosmetología',
              capacity: 20),
          Classroom(
              id: 6, branchId: 2, name: 'Laboratorio de Cómputo 2', capacity: 25),
        ],
      ),
    ];

  late final List<Branch> _branches = List.of(_seedBranches);
  final List<Branch> _inactive = [];
  int _nextBranchId = 3;
  int _nextClassroomId = 7;
  AuthUser? _actor;

  bool get _isGlobalAdmin =>
      _actor != null &&
      _actor!.isGlobal &&
      _actor!.position == StaffPosition.admin;

  /// Nivel mínimo y alcance de sede (global o la misma sede).
  bool _can(int branchId, int minLevel) =>
      _actor != null &&
      _actor!.hasLevel(minLevel) &&
      (_actor!.isGlobal || _actor!.branchId == branchId);

  static String _norm(String s) => s.trim().toLowerCase();

  Branch _branch(int id) => _branches.firstWhere(
        (b) => b.id == id,
        orElse: () => throw const BusinessRuleException(
            'La sede no existe o está dada de baja.'),
      );

  void _put(Branch b) {
    final i = _branches.indexWhere((x) => x.id == b.id);
    _branches[i] = b;
  }

  Branch _withClassrooms(Branch b, List<Classroom> c) => Branch(
        id: b.id,
        name: b.name,
        code: b.code,
        address: b.address,
        phone: b.phone,
        classrooms: c,
      );

  Map<String, String> _branchErrors(BranchInput i,
      {int? exceptId, bool nameAndCode = true}) {
    final e = <String, String>{};
    if (nameAndCode) {
      final name = i.name.trim();
      if (name.isEmpty) {
        e['name'] = 'El nombre es obligatorio.';
      } else if (name.length > 255) {
        e['name'] = 'Máximo 255 caracteres.';
      } else if (_branches
          .any((b) => b.id != exceptId && _norm(b.name) == _norm(name))) {
        e['name'] = 'Ya existe una sede activa con ese nombre.';
      }
      final code = i.code.trim();
      if (code.length > 50) {
        e['code'] = 'Máximo 50 caracteres.';
      } else if (code.isNotEmpty &&
          _branches
              .any((b) => b.id != exceptId && _norm(b.code) == _norm(code))) {
        e['code'] = 'Ya existe una sede activa con ese código.';
      }
    }
    if (i.address.trim().length > 255) e['address'] = 'Máximo 255 caracteres.';
    if (i.phone.trim().length > 50) e['phone'] = 'Máximo 50 caracteres.';
    return e;
  }

  String _genCode(String name) {
    final letters = name.toUpperCase().replaceAll(RegExp(r'[^A-Z]'), '');
    final base = '${letters}XXX'.substring(0, 3);
    var code = base;
    var n = 1;
    while (_branches.any((b) => _norm(b.code) == _norm(code))) {
      code = base.substring(0, 2) + (n++).toString();
    }
    return code;
  }

  @override
  Future<List<Branch>> inactiveBranches() async {
    await Future<void>.delayed(_latency);
    if (!_isGlobalAdmin) throw const ForbiddenException();
    return List.of(_inactive);
  }

  @override
  Future<void> createBranch(BranchInput i) async {
    await Future<void>.delayed(_latency);
    if (!_isGlobalAdmin) {
      throw const ForbiddenException(
          'Solo la Dirección General puede crear sedes.');
    }
    final errors = _branchErrors(i);
    if (errors.isNotEmpty) throw ValidationException(errors);
    final name = i.name.trim();
    final code =
        i.code.trim().isEmpty ? _genCode(name) : i.code.trim().toUpperCase();
    _branches.add(Branch(
      id: _nextBranchId++,
      name: name,
      code: code,
      address: i.address.trim(),
      phone: i.phone.trim(),
      classrooms: const [],
    ));
  }

  @override
  Future<void> updateBranch(int id, BranchInput i) async {
    await Future<void>.delayed(_latency);
    if (!_can(id, 3)) throw const ForbiddenException();
    final cur = _branch(id);
    // Solo la Dirección puede cambiar nombre y código.
    final all = _isGlobalAdmin;
    final errors = _branchErrors(i, exceptId: id, nameAndCode: all);
    if (errors.isNotEmpty) throw ValidationException(errors);
    _put(Branch(
      id: id,
      name: all ? i.name.trim() : cur.name,
      code: all
          ? (i.code.trim().isEmpty ? cur.code : i.code.trim().toUpperCase())
          : cur.code,
      address: i.address.trim(),
      phone: i.phone.trim(),
      classrooms: cur.classrooms,
    ));
  }

  @override
  Future<void> deleteBranch(int id) async {
    await Future<void>.delayed(_latency);
    if (!_isGlobalAdmin) {
      throw const ForbiddenException(
          'Solo la Dirección General puede dar de baja sedes.');
    }
    final b = _branch(id);
    if (_branches.length <= 1) {
      throw const BusinessRuleException(
          'No se puede dar de baja la última sede activa.');
    }
    if (_users.values.any((u) => u.branchId == id)) {
      throw const BusinessRuleException(
          'La sede tiene personal asignado. Reasígnalo antes de darla de baja.');
    }
    final t = DateTime.now();
    final today = DateTime(t.year, t.month, t.day);
    final vigentes = (await scheduledCourses())
        .where((s) => s.branchId == b.id && !s.endDate.isBefore(today));
    if (vigentes.isNotEmpty) {
      throw const BusinessRuleException(
          'La sede tiene cursos vigentes o programados.');
    }
    final enCurso = (await enrollments()).where((e) =>
        e.branchName == b.name &&
        (e.status == EnrollmentStatus.activo ||
            e.status == EnrollmentStatus.preinscrito));
    if (enCurso.isNotEmpty) {
      throw const BusinessRuleException('La sede tiene matrículas en curso.');
    }
    _branches.removeWhere((x) => x.id == id);
    _inactive.add(b);
  }

  @override
  Future<void> restoreBranch(int id) async {
    await Future<void>.delayed(_latency);
    if (!_isGlobalAdmin) throw const ForbiddenException();
    final i = _inactive.indexWhere((b) => b.id == id);
    if (i < 0) throw const BusinessRuleException('La sede ya está activa.');
    final b = _inactive[i];
    final choca = _branches.any((x) =>
        _norm(x.name) == _norm(b.name) || _norm(x.code) == _norm(b.code));
    if (choca) {
      throw const BusinessRuleException(
          'Otra sede activa ya usa el mismo nombre o código.');
    }
    _inactive.removeAt(i);
    _branches.add(b);
  }

  Map<String, String> _classroomErrors(String name, int capacity, int branchId,
      {int? exceptId, bool checkName = true}) {
    final e = <String, String>{};
    if (checkName) {
      final n = name.trim();
      if (n.isEmpty) {
        e['name'] = 'El nombre es obligatorio.';
      } else if (n.length > 255) {
        e['name'] = 'Máximo 255 caracteres.';
      } else if (_branches.any((b) =>
          b.id == branchId &&
          b.classrooms
              .any((c) => c.id != exceptId && _norm(c.name) == _norm(n)))) {
        e['name'] = 'Ya existe un aula con ese nombre en la sede.';
      }
    }
    if (capacity < 1 || capacity > 500) {
      e['capacity'] = 'Debe estar entre 1 y 500.';
    }
    return e;
  }

  (Branch, Classroom) _findClassroom(int id) {
    for (final b in _branches) {
      for (final c in b.classrooms) {
        if (c.id == id) return (b, c);
      }
    }
    throw const BusinessRuleException('El aula no existe.');
  }

  Future<List<ScheduledCourse>> _schedulesOf(Branch b, Classroom c) async =>
      (await scheduledCourses())
          .where((s) => s.classroomId == c.id)
          .toList();

  @override
  Future<void> createClassroom(ClassroomInput i) async {
    await Future<void>.delayed(_latency);
    if (!_can(i.branchId, 3)) throw const ForbiddenException();
    final b = _branch(i.branchId);
    final errors = _classroomErrors(i.name, i.capacity, i.branchId);
    if (errors.isNotEmpty) throw ValidationException(errors);
    _put(_withClassrooms(b, [
      ...b.classrooms,
      Classroom(
        id: _nextClassroomId++,
        branchId: b.id,
        name: i.name.trim(),
        capacity: i.capacity,
      ),
    ]));
  }

  @override
  Future<void> updateClassroom(int id, ClassroomInput i) async {
    await Future<void>.delayed(_latency);
    final (b, c) = _findClassroom(id);
    if (!_can(b.id, 2)) throw const ForbiddenException();
    final canName = _actor!.hasLevel(3); // Secretaría solo cambia el aforo
    final moving = i.branchId != b.id;
    if (moving) {
      if (!_isGlobalAdmin) {
        throw const ForbiddenException(
            'Solo la Dirección General puede transferir aulas.');
      }
      if ((await _schedulesOf(b, c)).isNotEmpty) {
        throw const BusinessRuleException(
            'El aula tiene horarios asociados; no se puede transferir.');
      }
      _branch(i.branchId); // debe estar activa
    }
    final name = canName ? i.name.trim() : c.name;
    final errors = _classroomErrors(name, i.capacity, i.branchId,
        exceptId: id, checkName: canName);
    if (errors.isNotEmpty) throw ValidationException(errors);
    final updated =
        Classroom(id: id, branchId: i.branchId, name: name, capacity: i.capacity);
    if (!moving) {
      _put(_withClassrooms(
          b, [for (final x in b.classrooms) x.id == id ? updated : x]));
    } else {
      _put(_withClassrooms(b, [for (final x in b.classrooms) if (x.id != id) x]));
      final dest = _branch(i.branchId);
      _put(_withClassrooms(dest, [...dest.classrooms, updated]));
    }
  }

  @override
  Future<void> deleteClassroom(int id) async {
    await Future<void>.delayed(_latency);
    final (b, c) = _findClassroom(id);
    if (!_can(b.id, 3)) throw const ForbiddenException();
    if ((await _schedulesOf(b, c)).isNotEmpty) {
      throw const BusinessRuleException(
          'El aula tiene horarios asociados y no se puede eliminar.');
    }
    _put(_withClassrooms(b, [for (final x in b.classrooms) if (x.id != id) x]));
  }

  @override
  Future<List<ClassroomSchedule>> classroomSchedules(int id) async {
    final (b, c) = _findClassroom(id);
    final now = DateTime.now();
    return [
      for (final s in await _schedulesOf(b, c))
        for (final d in s.days)
          ClassroomSchedule(
            courseName: s.courseName,
            teacherName: s.teacherName,
            day: d,
            startTime: s.startTime,
            endTime: s.endTime,
            startDate: s.startDate,
            endDate: s.endDate,
            state: s.stateAt(now),
          ),
    ];
  }

  // ── Oferta académica (estado en memoria) ───────────────────────────────
  // Reflejan CareerController, ModuleController y WorkshopController. Todo es
  // exclusivo de la Dirección General. Los nombres se resuelven al leer.
  late final List<Career> _careers = List.of(_seedCareers());
  late final List<Module> _modules = _seedModules();
  late final List<Course> _courses = List.of(_seedCourses);
  int _nextCareerId = 4;
  int _nextModuleId = 8;
  int _nextCourseId = 16;

  List<Career> _seedCareers() => const [
        Career(
          id: 1,
          name: 'Computación e Informática',
          description:
              'Carrera técnica profesional de desarrollo de software, administración de redes y soporte.',
          durationInWeeks: 104,
          moduleNames: [],
          branchNames: [],
          moduleIds: [1, 2, 3],
          branchIds: [1, 2],
        ),
        Career(
          id: 2,
          name: 'Logística y Almacenes',
          description:
              'Especialista en administración de inventarios, cadena de suministro y comercio.',
          durationInWeeks: 104,
          moduleNames: [],
          branchNames: [],
          moduleIds: [4, 5],
          branchIds: [1],
        ),
        Career(
          id: 3,
          name: 'Gastronomía y Arte Culinario',
          description:
              'Formación integral en técnicas culinarias nacionales e internacionales y gestión de restaurantes.',
          durationInWeeks: 104,
          moduleNames: [],
          branchNames: [],
          moduleIds: [6, 7],
          branchIds: [1, 2],
        ),
      ];

  List<Module> _seedModules() {
    Module m(int id, String name, String desc, int weeks) => Module(
          id: id,
          name: name,
          description: desc,
          durationInWeeks: weeks,
          courseNames: const [],
        );
    return [
      m(1, 'Ofimática Empresarial', 'Word, Excel avanzado, PowerPoint y gestión de documentos en la nube.', 8),
      m(2, 'Ensamblaje y Mantenimiento de PC', 'Diagnóstico de hardware, armado de computadoras, instalación de SO y redes LAN.', 8),
      m(3, 'Redes y Conectividad', 'Topologías, cableado estructurado, configuración de routers y seguridad básica.', 8),
      m(4, 'Cajero Bancario y Comercial', 'Detección de billetes, sistemas financieros, arqueo de caja y servicio al cliente.', 8),
      m(5, 'Gestión de Almacenes e Inventarios', 'Control de inventarios, métodos Kardex, compras y logística de distribución.', 8),
      m(6, 'Pastelería y Repostería Fina', 'Técnicas de masas, horneado, decoración y postres internacionales.', 12),
      m(7, 'Cocina Peruana e Internacional', 'Técnicas básicas de corte, fondos, guisos tradicionales y cocina fusión.', 12),
    ];
  }

  Career _careerView(Career c) => Career(
        id: c.id,
        name: c.name,
        description: c.description,
        durationInWeeks: c.durationInWeeks,
        moduleIds: c.moduleIds,
        branchIds: c.branchIds,
        moduleNames: [
          for (final id in c.moduleIds)
            _modules.where((m) => m.id == id).firstOrNull?.name ?? '—',
        ],
        branchNames: [
          for (final id in c.branchIds) _anyBranch(id)?.name ?? '—',
        ],
      );

  Module _moduleView(Module m) {
    final cs = _courses.where((c) => c.moduleId == m.id).toList();
    return Module(
      id: m.id,
      name: m.name,
      description: m.description,
      durationInWeeks: m.durationInWeeks,
      courseIds: [for (final c in cs) c.id],
      courseNames: [for (final c in cs) c.name],
    );
  }

  Course _courseView(Course c) => Course(
        id: c.id,
        name: c.name,
        description: c.description,
        durationInWeeks: c.durationInWeeks,
        isWorkshop: c.isWorkshop,
        moduleId: c.moduleId,
        moduleName: _modules.where((m) => m.id == c.moduleId).firstOrNull?.name,
      );

  @override
  Future<List<Career>> careers() async {
    await Future<void>.delayed(_latency);
    return [for (final c in _careers) _careerView(c)];
  }

  @override
  Future<List<Module>> modules() async {
    await Future<void>.delayed(_latency);
    return [for (final m in _modules) _moduleView(m)];
  }

  void _requireAdmin(String what) {
    if (!_isGlobalAdmin) {
      throw ForbiddenException('Solo la Dirección General puede $what.');
    }
  }

  /// Nombre único (sin distinguir mayúsculas) y largo máximo.
  String? _nameError(String raw, Iterable<String> others, String noun) {
    final n = raw.trim();
    if (n.isEmpty) return 'El nombre $noun es obligatorio.';
    if (n.length > 255) return 'Máximo 255 caracteres.';
    if (others.any((o) => _norm(o) == _norm(n))) {
      return 'Ya existe $noun registrado con ese nombre.';
    }
    return null;
  }

  // ── Carreras ──
  Map<String, String> _careerErrors(CareerInput i, {int? exceptId}) {
    final e = <String, String>{};
    final nameErr = _nameError(
      i.name,
      _careers.where((c) => c.id != exceptId).map((c) => c.name),
      'una carrera técnica',
    );
    if (nameErr != null) {
      e['name'] = nameErr.startsWith('El nombre')
          ? 'El nombre de la carrera técnica es obligatorio.'
          : nameErr.replaceFirst('una carrera técnica registrado', 'una carrera técnica registrada');
    }
    if (i.description.trim().length > 1000) e['description'] = 'Máximo 1000 caracteres.';
    final d = i.durationInWeeks;
    if (d != null && (d < 1 || d > 200)) e['duration_in_weeks'] = 'Debe estar entre 1 y 200.';
    if (i.moduleIds.toSet().length != i.moduleIds.length) {
      e['modules'] = 'No se puede asignar el mismo módulo más de una vez en la misma carrera.';
    } else if (i.moduleIds.any((id) => !_modules.any((m) => m.id == id))) {
      e['modules'] = 'Uno de los módulos no existe.';
    }
    if (i.branchIds.any((id) => !_branches.any((b) => b.id == id))) {
      e['branch_ids'] = 'Una de las sedes no existe o no está activa.';
    }
    return e;
  }

  int _careerWeeks(CareerInput i) =>
      i.durationInWeeks ??
      i.moduleIds.fold(
          0, (s, id) => s + _modules.firstWhere((m) => m.id == id).durationInWeeks);

  @override
  Future<void> createCareer(CareerInput i) async {
    await Future<void>.delayed(_latency);
    _requireAdmin('crear carreras técnicas');
    final e = _careerErrors(i);
    if (e.isNotEmpty) throw ValidationException(e);
    _careers.add(Career(
      id: _nextCareerId++,
      name: i.name.trim(),
      description: i.description.trim(),
      durationInWeeks: _careerWeeks(i),
      moduleNames: const [],
      branchNames: const [],
      moduleIds: List.of(i.moduleIds),
      branchIds: List.of(i.branchIds),
    ));
  }

  @override
  Future<void> updateCareer(int id, CareerInput i) async {
    await Future<void>.delayed(_latency);
    _requireAdmin('modificar carreras técnicas');
    final k = _careers.indexWhere((c) => c.id == id);
    if (k < 0) throw const BusinessRuleException('La carrera no existe.');
    final e = _careerErrors(i, exceptId: id);
    if (e.isNotEmpty) throw ValidationException(e);
    _careers[k] = Career(
      id: id,
      name: i.name.trim(),
      description: i.description.trim(),
      durationInWeeks: _careerWeeks(i),
      moduleNames: const [],
      branchNames: const [],
      moduleIds: List.of(i.moduleIds),
      branchIds: List.of(i.branchIds),
    );
  }

  @override
  Future<void> deleteCareer(int id) async {
    await Future<void>.delayed(_latency);
    _requireAdmin('eliminar carreras técnicas');
    // En el servidor se bloquea si hay alumnos admitidos (student_careers);
    // esta demostración no los modela. Los módulos compartidos se conservan.
    _careers.removeWhere((c) => c.id == id);
  }

  // ── Módulos ──
  Map<String, String> _moduleErrors(ModuleInput i, {int? exceptId}) {
    final e = <String, String>{};
    final nameErr = _nameError(
      i.name,
      _modules.where((m) => m.id != exceptId).map((m) => m.name),
      'un módulo formativo',
    );
    if (nameErr != null) {
      e['name'] = nameErr.startsWith('El nombre')
          ? 'El nombre del módulo formativo es obligatorio.'
          : nameErr;
    }
    if (i.description.trim().length > 1000) e['description'] = 'Máximo 1000 caracteres.';
    if (i.durationInWeeks < 1) {
      e['duration_in_weeks'] = 'La duración mínima es de 1 semana.';
    } else if (i.durationInWeeks > 52) {
      e['duration_in_weeks'] = 'Máximo 52 semanas.';
    }
    final cs = [for (final id in i.courseIds) _courses.where((c) => c.id == id).firstOrNull];
    if (cs.any((c) => c == null)) {
      e['course_ids'] = 'Uno de los cursos no existe.';
    } else if (cs.any((c) => c!.isWorkshop)) {
      e['course_ids'] = 'No es posible asignar talleres libres dentro de un módulo formativo.';
    } else if (cs.any((c) => c!.moduleId != null && c.moduleId != exceptId)) {
      e['course_ids'] =
          'Uno o más cursos seleccionados ya pertenecen a otro módulo formativo y no pueden pertenecer a dos módulos a la vez.';
    }
    return e;
  }

  void _assignCourses(int moduleId, List<int> ids) {
    for (var k = 0; k < _courses.length; k++) {
      final c = _courses[k];
      final inList = ids.contains(c.id);
      if (inList && c.moduleId != moduleId) {
        _courses[k] = _withModule(c, moduleId);
      } else if (!inList && c.moduleId == moduleId) {
        _courses[k] = _withModule(c, null);
      }
    }
  }

  Course _withModule(Course c, int? moduleId) => Course(
        id: c.id,
        name: c.name,
        description: c.description,
        durationInWeeks: c.durationInWeeks,
        isWorkshop: c.isWorkshop,
        moduleId: moduleId,
      );

  @override
  Future<void> createModule(ModuleInput i) async {
    await Future<void>.delayed(_latency);
    _requireAdmin('crear módulos');
    final e = _moduleErrors(i);
    if (e.isNotEmpty) throw ValidationException(e);
    final id = _nextModuleId++;
    _modules.add(Module(
      id: id,
      name: i.name.trim(),
      description: i.description.trim(),
      durationInWeeks: i.durationInWeeks,
      courseNames: const [],
    ));
    _assignCourses(id, i.courseIds);
  }

  @override
  Future<void> updateModule(int id, ModuleInput i) async {
    await Future<void>.delayed(_latency);
    _requireAdmin('modificar módulos');
    final k = _modules.indexWhere((m) => m.id == id);
    if (k < 0) throw const BusinessRuleException('El módulo no existe.');
    final e = _moduleErrors(i, exceptId: id);
    if (e.isNotEmpty) throw ValidationException(e);
    _modules[k] = Module(
      id: id,
      name: i.name.trim(),
      description: i.description.trim(),
      durationInWeeks: i.durationInWeeks,
      courseNames: const [],
    );
    _assignCourses(id, i.courseIds); // los que salen quedan sin módulo
  }

  @override
  Future<void> deleteModule(int id) async {
    await Future<void>.delayed(_latency);
    _requireAdmin('eliminar módulos');
    final tieneCursos = _courses.any((c) => c.moduleId == id);
    final enCarreras = _careers.any((c) => c.moduleIds.contains(id));
    if (tieneCursos || enCarreras) {
      throw const BusinessRuleException(
          'No se puede eliminar el módulo formativo porque tiene cursos, carreras o matrículas asociadas.');
    }
    _modules.removeWhere((m) => m.id == id);
  }

  // ── Cursos y talleres ──
  Map<String, String> _courseErrors(CourseInput i, {int? exceptId}) {
    final e = <String, String>{};
    final nameErr = _nameError(
      i.name,
      _courses.where((c) => c.id != exceptId).map((c) => c.name),
      'un curso o taller',
    );
    if (nameErr != null) {
      e['name'] = nameErr.startsWith('El nombre')
          ? 'El nombre del curso o taller es obligatorio.'
          : nameErr;
    }
    if (i.description.trim().length > 1000) e['description'] = 'Máximo 1000 caracteres.';
    if (i.durationInWeeks < 1) {
      e['duration_in_weeks'] = 'La duración mínima es de 1 semana.';
    } else if (i.durationInWeeks > 52) {
      e['duration_in_weeks'] = 'Máximo 52 semanas.';
    }
    if (i.moduleId != null && !_modules.any((m) => m.id == i.moduleId)) {
      e['module_id'] = 'El módulo no existe.';
    }
    return e;
  }

  /// Aperturas vigentes o matrículas activas/preinscritas (WorkshopController).
  bool _courseHasActivity(int courseId) {
    final t = DateTime.now();
    final today = DateTime(t.year, t.month, t.day);
    final aps = _aperturas.where((a) => a.courseId == courseId).toList();
    if (aps.any((a) => !a.endDate.isBefore(today))) return true;
    final ids = aps.map((a) => a.id).toSet();
    return _enrollments.any((x) => ids.contains(x.scheduledCourseId) && _occupies(x.status));
  }

  @override
  Future<void> createCourse(CourseInput i) async {
    await Future<void>.delayed(_latency);
    _requireAdmin('crear cursos o talleres');
    final e = _courseErrors(i);
    if (e.isNotEmpty) throw ValidationException(e);
    _courses.add(Course(
      id: _nextCourseId++,
      name: i.name.trim(),
      description: i.description.trim(),
      durationInWeeks: i.durationInWeeks,
      isWorkshop: i.isWorkshop,
      moduleId: i.isWorkshop ? null : i.moduleId,
    ));
  }

  @override
  Future<void> updateCourse(int id, CourseInput i) async {
    await Future<void>.delayed(_latency);
    _requireAdmin('modificar cursos o talleres');
    final k = _courses.indexWhere((c) => c.id == id);
    if (k < 0) throw const BusinessRuleException('El curso no existe.');
    final cur = _courses[k];
    final e = _courseErrors(i, exceptId: id);
    if (e.isNotEmpty) throw ValidationException(e);
    final newModule = i.isWorkshop ? null : i.moduleId;
    final structureChanged = newModule != cur.moduleId || i.isWorkshop != cur.isWorkshop;
    if (structureChanged && _courseHasActivity(id)) {
      throw ValidationException({
        'module_id':
            'No es posible cambiar el módulo o el tipo de "${cur.name}" porque tiene cursos aperturados vigentes o matrículas en curso.',
      });
    }
    _courses[k] = Course(
      id: id,
      name: i.name.trim(),
      description: i.description.trim(),
      durationInWeeks: i.durationInWeeks,
      isWorkshop: i.isWorkshop,
      moduleId: newModule,
    );
  }

  @override
  Future<void> deleteCourse(int id) async {
    await Future<void>.delayed(_latency);
    _requireAdmin('eliminar cursos o talleres');
    final cur = _courses.firstWhere(
      (c) => c.id == id,
      orElse: () => throw const BusinessRuleException('El curso no existe.'),
    );
    if (_aperturas.any((a) => a.courseId == id)) {
      throw BusinessRuleException(
          'No es posible eliminar el ${cur.isWorkshop ? 'taller' : 'curso'} "${cur.name}" porque tiene aperturas o clases programadas.');
    }
    _courses.removeWhere((c) => c.id == id);
  }

  @override
  Future<List<Course>> courses() async {
    await Future<void>.delayed(_latency);
    return [for (final c in _courses) _courseView(c)];
  }

  // ── Aperturas y matrículas (estado en memoria) ─────────────────────────
  // Reflejan ScheduledCourseController: storeEnrollment, updateEnrollmentStatus,
  // destroyEnrollment y searchStudentByDni.
  late final List<Enrollment> _enrollments = _seedEnrollments();
  late final Map<String, Student> _students = _seedStudents();
  final Map<int, int> _delta = {}; // cambios de ocupación por apertura
  int _nextEnrollmentId = 9;

  static bool _occupies(EnrollmentStatus s) =>
      s == EnrollmentStatus.activo || s == EnrollmentStatus.preinscrito;

  static final _emailRe = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');
  static final _dniRe = RegExp(r'^[0-9]{8}$');

  // ── Aperturas (estado en memoria) ──────────────────────────────────────
  // Reflejan ScheduledCourseController::store/update/destroy. Las referencias
  // son por id (aula, sede, curso, docente): los nombres se resuelven al leer,
  // así un cambio en Sedes y aulas se ve aquí.
  late final List<ScheduledCourse> _aperturas = _seedScheduled();
  int _nextApId = 5;

  static const _days = [
    'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo',
  ];

  static const _teachers = [
    Teacher(
        id: 1,
        fullName: 'Marco Antonio Rojas Quispe',
        specialty: 'Ingeniería de Sistemas y Soporte Técnico'),
    Teacher(
        id: 2,
        fullName: 'Carlos Alberto Gomez',
        specialty: 'Estilismo Masculino y Barbería Profesional'),
    Teacher(
        id: 3,
        fullName: 'Lucia Fernanda Vega',
        specialty: 'Gastronomía y Pastelería Internacional'),
    Teacher(
        id: 4,
        fullName: 'Roberto Carlos Huaman',
        specialty: 'Finanzas, Caja Bancaria y Administración de Almacenes'),
    Teacher(
        id: 5,
        fullName: 'Patricia Navarro',
        specialty: 'Estética de Uñas y Cuidado de la Piel'),
  ];

  static const _seedCourses = [
    Course(id: 1, name: 'Excel Avanzado y Macros', description: 'Fórmulas lógicas, tablas dinámicas, dashboards y automatización VBA.', durationInWeeks: 4, isWorkshop: false, moduleId: 1, moduleName: 'Ofimática Empresarial'),
    Course(id: 2, name: 'Word Empresarial y Gestión Documental', description: 'Correspondencia combinada, tablas de contenido y diseño de documentos institucionales.', durationInWeeks: 4, isWorkshop: false, moduleId: 1, moduleName: 'Ofimática Empresarial'),
    Course(id: 3, name: 'Arquitectura de Hardware y Diagnóstico', description: 'Detección de fallas en placas madre, memorias RAM, fuentes y almacenamiento.', durationInWeeks: 4, isWorkshop: false, moduleId: 2, moduleName: 'Ensamblaje y Mantenimiento de PC'),
    Course(id: 4, name: 'Mantenimiento Preventivo y Correctivo de PC', description: 'Limpieza de componentes, optimización de sistemas operativos y resolución de fallas comunes.', durationInWeeks: 4, isWorkshop: false, moduleId: 2, moduleName: 'Ensamblaje y Mantenimiento de PC'),
    Course(id: 5, name: 'Redes LAN y Cableado Estructurado', description: 'Crimpeado de cables RJ-45, configuración de routers y switchs en red local.', durationInWeeks: 4, isWorkshop: false, moduleId: 3, moduleName: 'Redes y Conectividad'),
    Course(id: 6, name: 'Técnicas de Conteo y Detección de Billetes', description: 'Reconocimiento de elementos de seguridad en moneda nacional y moneda extranjera.', durationInWeeks: 4, isWorkshop: false, moduleId: 4, moduleName: 'Cajero Bancario y Comercial'),
    Course(id: 7, name: 'Operaciones de Caja y Arqueo Financiero', description: 'Cuadre diario, registro en POS, manejo de cheques y transferencias.', durationInWeeks: 4, isWorkshop: false, moduleId: 4, moduleName: 'Cajero Bancario y Comercial'),
    Course(id: 8, name: 'Control de Inventarios y Kardex', description: 'Métodos PEPS, promedio ponderado, ingresos y salidas en almacén comercial.', durationInWeeks: 4, isWorkshop: false, moduleId: 5, moduleName: 'Gestión de Almacenes e Inventarios'),
    Course(id: 9, name: 'Masas Básicas, Bizcochuelos y Queques', description: 'Batidos aireados y pesados, técnicas de horneado y formulación de recetas.', durationInWeeks: 6, isWorkshop: false, moduleId: 6, moduleName: 'Pastelería y Repostería Fina'),
    Course(id: 10, name: 'Decoración y Pastelería Fina en Masa Elástica', description: 'Modelado de figuras, forrado de tortas de pisos y técnicas de boquillas.', durationInWeeks: 6, isWorkshop: false, moduleId: 6, moduleName: 'Pastelería y Repostería Fina'),
    Course(id: 11, name: 'Taller de Barber Shop y Cortes Urbanos', description: 'Técnicas de degradado, fade, afeitado tradicional y perfilado de barba.', durationInWeeks: 6, isWorkshop: true),
    Course(id: 12, name: 'Taller de Uñas Acrílicas y Polygel', description: 'Esculpido en acrílico, encapsulados, mano alzada y 3D.', durationInWeeks: 4, isWorkshop: true),
    Course(id: 13, name: 'Taller de Reparación de Celulares y Tablets', description: 'Cambio de pantallas, micro-soldadura, diagnóstico de placas y flasheo.', durationInWeeks: 8, isWorkshop: true),
    Course(id: 14, name: 'Taller de Maquillaje Profesional y Social', description: 'Preparación de la piel, visagismo, técnicas de ojos ahumados y maquillaje para eventos.', durationInWeeks: 4, isWorkshop: true),
    Course(id: 15, name: 'Taller de Panadería Artesanal y Masas Fermentadas', description: 'Elaboración de panes comerciales, masa madre y fermentaciones prolongadas.', durationInWeeks: 6, isWorkshop: true),
  ];

  List<ScheduledCourse> _seedScheduled() {
    final t = DateTime.now();
    final today = DateTime(t.year, t.month, t.day);
    DateTime d(int offset) => today.add(Duration(days: offset));
    ScheduledCourse s(int id, int course, int branch, int room, int? teacher,
            int from, int to, String day, String a, String b, int base) =>
        ScheduledCourse(
          id: id,
          courseId: course,
          branchId: branch,
          classroomId: room,
          teacherId: teacher,
          courseName: '',
          isWorkshop: false,
          branchName: '',
          classroomName: '',
          teacherName: null,
          startDate: d(from),
          endDate: d(to),
          days: [day],
          startTime: a,
          endTime: b,
          capacity: 0,
          enrolled: base, // alumnos que ya había antes de esta demostración
        );
    return [
      s(1, 1, 1, 2, 1, -14, 14, 'Lunes', '18:00', '21:00', 21),
      s(2, 11, 2, 5, null, -7, 35, 'Sábado', '09:00', '13:00', 12),
      s(3, 5, 2, 6, 1, 10, 38, 'Miércoles', '19:00', '22:00', 4),
      s(4, 15, 1, 3, null, 21, 63, 'Viernes', '15:00', '19:00', 0),
    ];
  }

  Branch? _anyBranch(int id) =>
      [..._branches, ..._inactive].where((b) => b.id == id).firstOrNull;

  /// Rellena nombres, aforo y ocupación a partir del estado actual.
  ScheduledCourse _resolve(ScheduledCourse s) {
    final course = _courses.firstWhere((c) => c.id == s.courseId);
    final branch = _anyBranch(s.branchId);
    final room = branch?.classrooms
        .where((c) => c.id == s.classroomId)
        .firstOrNull;
    final teacher = _teachers.where((x) => x.id == s.teacherId).firstOrNull;
    return s.copyWith(
      courseName: course.name,
      isWorkshop: course.isWorkshop,
      branchName: branch?.name ?? '—',
      classroomName: room?.name ?? '—',
      teacherName: teacher?.fullName,
      capacity: room?.capacity ?? s.capacity,
      enrolled: s.enrolled + (_delta[s.id] ?? 0),
    );
  }

  @override
  Future<List<ScheduledCourse>> scheduledCourses() async {
    await Future<void>.delayed(_latency);
    return [for (final s in _aperturas) _resolve(s)];
  }

  @override
  Future<List<Teacher>> teachers() async {
    await Future<void>.delayed(_latency);
    return _teachers;
  }

  Map<String, String> _scheduleErrors(ScheduledCourseInput i, {int? exceptId}) {
    final e = <String, String>{};
    final course = _courses.where((c) => c.id == i.courseId).firstOrNull;
    if (course == null) e['course_id'] = 'El curso no existe.';
    final branch = _branches.where((b) => b.id == i.branchId).firstOrNull;
    if (branch == null) e['branch_id'] = 'La sede no existe o no está activa.';
    if (branch != null &&
        !branch.classrooms.any((c) => c.id == i.classroomId)) {
      e['classroom_id'] = 'El aula no pertenece a la sede seleccionada.';
    }
    if (i.teacherId != null && !_teachers.any((t) => t.id == i.teacherId)) {
      e['teacher_id'] = 'El docente no existe.';
    }
    if (i.days.isEmpty) {
      e['days'] = 'Elige al menos un día de clase.';
    } else if (i.days.length > 7 ||
        i.days.toSet().length != i.days.length ||
        !i.days.every(_days.contains)) {
      e['days'] = 'Los días deben ser distintos y válidos (de 1 a 7).';
    }
    final from = parseTime(i.startTime);
    final to = parseTime(i.endTime);
    if (from == null) e['start_time'] = 'Hora inválida (HH:mm).';
    if (to == null) {
      e['end_time'] = 'Hora inválida (HH:mm).';
    } else if (from != null && from >= to) {
      e['end_time'] = 'La hora de finalización debe ser posterior a la hora de inicio.';
    }
    if (e.isNotEmpty) return e;

    // Choques de aula y de docente: basta un día en común.
    final start = DateTime(i.startDate.year, i.startDate.month, i.startDate.day);
    final end = start.add(Duration(days: course!.durationInWeeks * 7));
    for (final o in _aperturas) {
      if (o.id == exceptId || !o.days.any(i.days.contains)) continue;
      final oFrom = parseTime(o.startTime)!;
      final oTo = parseTime(o.endTime)!;
      if (!(from! < oTo && oFrom < to!)) continue;
      if (!(o.startDate.isBefore(end) && start.isBefore(o.endDate))) continue;
      final name = _courses.firstWhere((c) => c.id == o.courseId).name;
      if (o.classroomId == i.classroomId) {
        e['classroom_id'] = 'El aula ya está ocupada ese día y horario por «$name».';
      }
      if (i.teacherId != null && o.teacherId == i.teacherId) {
        e['teacher_id'] = 'El docente ya tiene clase ese día y horario («$name»).';
      }
    }
    return e;
  }

  bool _hasStudents(ScheduledCourse s) =>
      s.enrolled > 0 || _enrollments.any((x) => x.scheduledCourseId == s.id);

  ScheduledCourse _apertura(int id) => _aperturas.firstWhere(
        (x) => x.id == id,
        orElse: () => throw const BusinessRuleException('La apertura no existe.'),
      );

  ScheduledCourse _fromInput(int id, ScheduledCourseInput i, int base) {
    final course = _courses.firstWhere((c) => c.id == i.courseId);
    final start = DateTime(i.startDate.year, i.startDate.month, i.startDate.day);
    return ScheduledCourse(
      id: id,
      courseId: i.courseId,
      branchId: i.branchId,
      classroomId: i.classroomId,
      teacherId: i.teacherId,
      courseName: '',
      isWorkshop: course.isWorkshop,
      branchName: '',
      classroomName: '',
      teacherName: null,
      startDate: start,
      // El servidor ignora el fin enviado: inicio + semanas del curso.
      endDate: start.add(Duration(days: course.durationInWeeks * 7)),
      days: [for (final d in _days) if (i.days.contains(d)) d],
      startTime: i.startTime,
      endTime: i.endTime,
      capacity: 0,
      enrolled: base,
    );
  }

  @override
  Future<void> createScheduledCourse(ScheduledCourseInput i) async {
    await Future<void>.delayed(_latency);
    if (_actor == null || !_actor!.canManageSchedule(i.branchId)) {
      throw const ForbiddenException(
          'Solo Coordinación de la sede o la Dirección General puede aperturar cursos.');
    }
    final errors = _scheduleErrors(i);
    if (errors.isNotEmpty) throw ValidationException(errors);
    _aperturas.add(_fromInput(_nextApId++, i, 0));
  }

  @override
  Future<void> updateScheduledCourse(int id, ScheduledCourseInput i) async {
    await Future<void>.delayed(_latency);
    final cur = _apertura(id);
    if (_actor == null ||
        !_actor!.canManageSchedule(cur.branchId) ||
        !_actor!.canManageSchedule(i.branchId)) {
      throw const ForbiddenException(
          'Solo Coordinación de la sede o la Dirección General puede modificar cursos aperturados.');
    }
    final errors = _scheduleErrors(i, exceptId: id);
    if (errors.isNotEmpty) throw ValidationException(errors);

    final resolved = _resolve(cur);
    if (_hasStudents(resolved)) {
      if (i.courseId != cur.courseId) {
        throw const BusinessRuleException(
            'No se puede cambiar el curso de una apertura con alumnos matriculados.');
      }
      if (i.branchId != cur.branchId) {
        throw const BusinessRuleException(
            'No se puede mover de sede una apertura con alumnos matriculados.');
      }
      final room = _branch(i.branchId)
          .classrooms
          .firstWhere((c) => c.id == i.classroomId);
      if (room.capacity < resolved.enrolled) {
        throw BusinessRuleException(
            'El aula elegida tiene ${room.capacity} vacantes y la apertura ya tiene ${resolved.enrolled} alumnos.');
      }
    }
    final k = _aperturas.indexWhere((x) => x.id == id);
    _aperturas[k] = _fromInput(id, i, cur.enrolled);
  }

  @override
  Future<void> deleteScheduledCourse(int id) async {
    await Future<void>.delayed(_latency);
    final cur = _apertura(id);
    if (_actor == null || !_actor!.canManageSchedule(cur.branchId)) {
      throw const ForbiddenException(
          'Solo Coordinación de la sede o la Dirección General puede eliminar cursos aperturados.');
    }
    if (_hasStudents(_resolve(cur))) {
      throw const BusinessRuleException(
          'No es posible eliminar el curso aperturado porque ya cuenta con estudiantes matriculados.');
    }
    _aperturas.removeWhere((x) => x.id == id);
    _delta.remove(id);
  }

  Map<String, Student> _seedStudents() {
    Student s(String doc, String first, String pat, String mat,
            {DateTime? birth, String phone = '', String email = '', Guardian? g}) =>
        Student(
          document: doc,
          firstName: first,
          paternalSurname: pat,
          maternalSurname: mat,
          birthDate: birth,
          phone: phone,
          email: email,
          guardian: g,
        );
    final list = [
      s('71234567', 'Diego', 'Quispe', 'Huamán', phone: '987111222'),
      s('72345678', 'Camila', 'Ramos', 'Villanueva', phone: '987333444'),
      s('70456789', 'Jorge', 'Mendoza', 'Paredes'),
      s('73567890', 'Rosa', 'Salazar', 'Ochoa'),
      s('74678901', 'Luis', 'Flores', 'Cárdenas'),
      s('75789012', 'Andrea', 'Vega', 'Torres',
          birth: DateTime(2009, 5, 14),
          g: const Guardian(
            document: '40123456',
            firstName: 'Carmen',
            paternalSurname: 'Torres',
            maternalSurname: 'Ruiz',
            phone: '987555666',
          )),
      s('76890123', 'Pedro', 'Huaman', 'Díaz'),
      s('77901234', 'Lucía', 'Castillo', 'Rivas'),
      s('78012345', 'Mateo', 'Paredes', 'Gómez', phone: '987777888'),
      s('79123456', 'Valeria', 'Rojas', 'Núñez', email: 'valeria.rojas@correo.pe'),
    ];
    return {for (final x in list) x.document: x};
  }

  List<Enrollment> _seedEnrollments() {
    final t = DateTime.now();
    final today = DateTime(t.year, t.month, t.day);
    final students = _seedStudents();
    Enrollment e(int id, String doc, int apertura, String course, String branch,
            EnrollmentStatus st, int daysAgo) =>
        Enrollment(
          id: id,
          scheduledCourseId: apertura,
          studentName: students[doc]!.fullName,
          document: doc,
          courseName: course,
          branchName: branch,
          status: st,
          date: today.subtract(Duration(days: daysAgo)),
        );
    const comas = 'Sede Comas';
    const pup = 'Sede Puente Piedra';
    const excel = 'Excel Avanzado y Macros';
    const barber = 'Taller de Barber Shop y Cortes Urbanos';
    const redes = 'Redes LAN y Cableado Estructurado';
    return [
      e(1, '71234567', 1, excel, comas, EnrollmentStatus.activo, 16),
      e(2, '72345678', 1, excel, comas, EnrollmentStatus.activo, 15),
      e(3, '70456789', 1, excel, comas, EnrollmentStatus.convalidado, 15),
      e(4, '73567890', 2, barber, pup, EnrollmentStatus.activo, 9),
      e(5, '74678901', 2, barber, pup, EnrollmentStatus.retirado, 8),
      e(6, '75789012', 3, redes, pup, EnrollmentStatus.preinscrito, 3),
      e(7, '76890123', 1, excel, comas, EnrollmentStatus.completado, 40),
      e(8, '77901234', 2, barber, pup, EnrollmentStatus.anulado, 6),
    ];
  }

  @override
  Future<List<Enrollment>> enrollments() async {
    await Future<void>.delayed(_latency);
    return List.of(_enrollments.reversed); // las más recientes primero
  }

  bool _sameBranch(String branchName) =>
      _actor != null && (_actor!.isGlobal || _actor!.branchName == branchName);

  @override
  Future<StudentLookup> lookupStudent(String document,
      {int? scheduledCourseId}) async {
    await Future<void>.delayed(const Duration(milliseconds: 200));
    if (_actor == null || !_actor!.hasLevel(1)) throw const ForbiddenException();
    final doc = document.trim();
    final st = _students[doc];
    if (doc.length < 6 || st == null) return const StudentLookup.notFound();
    final enrolled = _enrollments
        .where((e) =>
            e.document == doc &&
            e.status != EnrollmentStatus.retirado &&
            e.status != EnrollmentStatus.anulado)
        .map((e) => e.scheduledCourseId)
        .toList();
    return StudentLookup(
      student: st,
      alreadyEnrolled:
          scheduledCourseId != null && enrolled.contains(scheduledCourseId),
      enrolledScheduledIds: enrolled,
    );
  }

  @override
  Future<void> enroll(EnrollmentInput i) async {
    await Future<void>.delayed(_latency);
    final apertura = (await scheduledCourses())
        .where((s) => s.id == i.scheduledCourseId)
        .firstOrNull;
    if (apertura == null) {
      throw const BusinessRuleException('La apertura seleccionada no existe.');
    }
    if (_actor == null ||
        !_actor!.hasLevel(2) ||
        !_sameBranch(apertura.branchName)) {
      throw const ForbiddenException(
          'No tienes autorización para registrar matrículas en esta sede.');
    }
    if (!_actor!.canSetStatus(i.status)) {
      throw ForbiddenException(
          'Tu cargo no puede registrar matrículas como «${i.status.label}».');
    }

    final e = <String, String>{};
    final doc = i.document.trim();
    if (!_dniRe.hasMatch(doc)) e['student_document'] = 'El DNI debe tener 8 dígitos.';
    final existing = _students[doc];
    if (existing == null && _dniRe.hasMatch(doc)) {
      if (i.firstName.trim().isEmpty) {
        e['first_name'] = 'El nombre del estudiante es obligatorio.';
      }
      if (i.paternalSurname.trim().isEmpty) {
        e['paternal_surname'] = 'El apellido paterno es obligatorio.';
      }
    }
    if (i.email.trim().isNotEmpty && !_emailRe.hasMatch(i.email.trim())) {
      e['email'] = 'Ingresa un correo válido.';
    }
    if (i.phone.trim().length > 30) e['phone'] = 'Máximo 30 caracteres.';
    final g = i.guardian;
    if (g != null) {
      final gd = g.document.trim();
      if (!_dniRe.hasMatch(gd)) {
        e['guardian_document'] = 'El DNI del apoderado debe tener 8 dígitos.';
      } else if (gd == doc) {
        e['guardian_document'] = 'El estudiante no puede ser su propio apoderado.';
      }
      if (g.firstName.trim().isEmpty) {
        e['guardian_first_name'] = 'El nombre del apoderado es obligatorio.';
      }
      if (g.paternalSurname.trim().isEmpty) {
        e['guardian_paternal_surname'] = 'El apellido paterno es obligatorio.';
      }
      if (g.email.trim().isNotEmpty && !_emailRe.hasMatch(g.email.trim())) {
        e['guardian_email'] = 'Ingresa un correo válido.';
      }
    }
    if (e.isNotEmpty) throw ValidationException(e);

    if (apertura.stateAt(DateTime.now()) == ScheduleState.finalizado) {
      throw const BusinessRuleException('El curso ya finalizó.');
    }

    // Matrícula previa en la misma apertura.
    final previa = _enrollments.where(
        (x) => x.document == doc && x.scheduledCourseId == apertura.id);
    final vigente = previa.where((x) =>
        x.status != EnrollmentStatus.retirado &&
        x.status != EnrollmentStatus.anulado);
    if (vigente.isNotEmpty) {
      throw ValidationException({
        'student_document':
            'El estudiante ${vigente.first.studentName} ya está matriculado en este curso aperturado.',
      });
    }
    if (previa.isNotEmpty) {
      throw const BusinessRuleException(
          'El alumno tiene una matrícula retirada o anulada en este curso. Cambia su estado desde la lista.');
    }
    if (_occupies(i.status) && apertura.vacancies <= 0) {
      throw const BusinessRuleException('La apertura no tiene vacantes libres.');
    }

    // Alumno: se crea, o solo se completan sus datos de contacto.
    final Guardian? newGuardian = g == null
        ? null
        : Guardian(
            document: g.document.trim(),
            firstName: g.firstName.trim(),
            paternalSurname: g.paternalSurname.trim(),
            maternalSurname: g.maternalSurname.trim(),
            phone: g.phone.trim(),
            email: g.email.trim(),
          );
    final student = existing == null
        ? Student(
            document: doc,
            firstName: i.firstName.trim(),
            paternalSurname: i.paternalSurname.trim(),
            maternalSurname: i.maternalSurname.trim(),
            birthDate: i.birthDate,
            phone: i.phone.trim(),
            email: i.email.trim(),
            guardian: newGuardian,
          )
        : existing.copyWith(
            birthDate: i.birthDate,
            phone: i.phone.trim().isEmpty ? null : i.phone.trim(),
            email: i.email.trim().isEmpty ? null : i.email.trim(),
            guardian: newGuardian,
          );
    _students[doc] = student;

    final t = DateTime.now();
    _enrollments.add(Enrollment(
      id: _nextEnrollmentId++,
      scheduledCourseId: apertura.id,
      studentName: student.fullName,
      document: doc,
      courseName: apertura.courseName,
      branchName: apertura.branchName,
      status: i.status,
      date: i.enrollmentDate ?? DateTime(t.year, t.month, t.day),
    ));
    if (_occupies(i.status)) _delta[apertura.id] = (_delta[apertura.id] ?? 0) + 1;
  }

  Enrollment _enrollment(int id) => _enrollments.firstWhere(
        (x) => x.id == id,
        orElse: () => throw const BusinessRuleException('La matrícula no existe.'),
      );

  @override
  Future<void> changeEnrollmentStatus(int id, EnrollmentStatus status) async {
    await Future<void>.delayed(_latency);
    final cur = _enrollment(id);
    if (_actor == null ||
        !_actor!.hasLevel(2) ||
        !_sameBranch(cur.branchName)) {
      throw const ForbiddenException(
          'No tienes autorización para modificar matrículas en esta sede.');
    }
    if (!_actor!.canSetStatus(status)) {
      throw ForbiddenException(
          'Tu cargo no puede cambiar una matrícula a «${status.label}».');
    }
    if (cur.status == status) return;
    if (!_occupies(cur.status) && _occupies(status)) {
      final ap = (await scheduledCourses())
          .firstWhere((s) => s.id == cur.scheduledCourseId);
      if (ap.vacancies <= 0) {
        throw const BusinessRuleException(
            'La apertura no tiene vacantes libres para reactivar esta matrícula.');
      }
    }
    final i = _enrollments.indexWhere((x) => x.id == id);
    _enrollments[i] = cur.copyWith(status: status);
    final id0 = cur.scheduledCourseId;
    if (_occupies(cur.status) && !_occupies(status)) {
      _delta[id0] = (_delta[id0] ?? 0) - 1;
    } else if (!_occupies(cur.status) && _occupies(status)) {
      _delta[id0] = (_delta[id0] ?? 0) + 1;
    }
  }

  @override
  Future<void> deleteEnrollment(int id) async {
    await Future<void>.delayed(_latency);
    final cur = _enrollment(id);
    if (_actor == null || !_actor!.canDeleteEnrollment) {
      throw const ForbiddenException(
          'Solo la Dirección General elimina matrículas.');
    }
    _enrollments.removeWhere((x) => x.id == id);
    if (_occupies(cur.status)) {
      _delta[cur.scheduledCourseId] = (_delta[cur.scheduledCourseId] ?? 0) - 1;
    }
  }
}
