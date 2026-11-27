import 'models.dart';

/// Contratos de datos. La UI SOLO depende de estas interfaces.
///
/// Hoy las implementa `MockRepository` (datos de demostración). Cuando el
/// backend exponga `/api/*` (ver docs/API_NECESARIA.md), se agrega una
/// implementación HTTP y se cambia en `providers.dart`; las pantallas no se
/// tocan.
abstract class AuthRepository {
  /// Lanza [AuthException] si las credenciales son inválidas.
  Future<AuthUser> login(String login, String password);
  Future<void> logout();
}

abstract class AcademicRepository {
  Future<List<Branch>> branches();
  Future<List<Career>> careers();
  Future<List<Module>> modules();
  Future<List<Course>> courses();
  Future<List<ScheduledCourse>> scheduledCourses();
  Future<List<Enrollment>> enrollments();
}

class AuthException implements Exception {
  const AuthException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// Campos de una sede. Mismos nombres que el backend (BranchController).
class BranchInput {
  const BranchInput({
    required this.name,
    this.code = '',
    this.address = '',
    this.phone = '',
  });
  final String name;
  final String code; // vacío = el servidor genera 3 letras
  final String address;
  final String phone;
}

class ClassroomInput {
  const ClassroomInput({
    required this.name,
    required this.capacity,
    required this.branchId,
  });
  final String name;
  final int capacity;
  final int branchId;
}

/// 422: errores por campo (`{'name': 'Ya existe...'}`).
class ValidationException implements Exception {
  const ValidationException(this.fields);
  final Map<String, String> fields;
  @override
  String toString() => fields.values.join(' ');
}

/// Regla de negocio que impide la acción (p. ej. no se puede borrar una sede
/// con cursos vigentes). El backend responde con un mensaje legible.
class BusinessRuleException implements Exception {
  const BusinessRuleException(this.message);
  final String message;
  @override
  String toString() => message;
}

/// 403: el cargo no tiene permiso.
class ForbiddenException implements Exception {
  const ForbiddenException([this.message = 'No tienes permiso para hacer esto.']);
  final String message;
  @override
  String toString() => message;
}

/// Sedes y aulas: escritura. Reglas en ARBOL_ENDPOINTS.md (/admin/sedes y
/// /admin/aulas); el servidor es quien manda, el cliente solo las refleja.
abstract class InfrastructureRepository {
  Future<List<Branch>> inactiveBranches();
  Future<void> createBranch(BranchInput input);
  Future<void> updateBranch(int id, BranchInput input);
  Future<void> deleteBranch(int id);
  Future<void> restoreBranch(int id);
  Future<void> createClassroom(ClassroomInput input);
  Future<void> updateClassroom(int id, ClassroomInput input);
  Future<void> deleteClassroom(int id);
  Future<List<ClassroomSchedule>> classroomSchedules(int id);
}

class GuardianInput {
  const GuardianInput({
    required this.document,
    required this.firstName,
    required this.paternalSurname,
    this.maternalSurname = '',
    this.phone = '',
    this.email = '',
  });
  final String document;
  final String firstName;
  final String paternalSurname;
  final String maternalSurname;
  final String phone;
  final String email;
}

/// Cuerpo de POST /admin/matriculas.
class EnrollmentInput {
  const EnrollmentInput({
    required this.scheduledCourseId,
    required this.document,
    this.firstName = '',
    this.paternalSurname = '',
    this.maternalSurname = '',
    this.birthDate,
    this.phone = '',
    this.email = '',
    this.guardian,
    this.enrollmentDate,
    this.status = EnrollmentStatus.activo,
  });
  final int scheduledCourseId;
  final String document;
  final String firstName;
  final String paternalSurname;
  final String maternalSurname;
  final DateTime? birthDate;
  final String phone;
  final String email;
  final GuardianInput? guardian;
  final DateTime? enrollmentDate; // null = hoy (hora de Lima en el servidor)
  final EnrollmentStatus status;
}

/// Matrículas: escritura y búsqueda de alumno por DNI.
abstract class EnrollmentRepository {
  /// Solo lectura. (El endpoint actual del backend crea al alumno al buscar:
  /// debe corregirse, ver docs/API_NECESARIA.md.)
  Future<StudentLookup> lookupStudent(String document, {int? scheduledCourseId});
  Future<void> enroll(EnrollmentInput input);
  Future<void> changeEnrollmentStatus(int id, EnrollmentStatus status);
  Future<void> deleteEnrollment(int id);
}

/// Cuerpo de POST/PUT /admin/cursos-aperturados.
/// El backend admite de 1 a 7 días distintos por semana y calcula la fecha de
/// fin (inicio + semanas del curso); por eso no hay `endDate`.
class ScheduledCourseInput {
  const ScheduledCourseInput({
    required this.courseId,
    required this.branchId,
    required this.classroomId,
    this.teacherId,
    required this.startDate,
    required this.days,
    required this.startTime,
    required this.endTime,
  });
  final int courseId;
  final int branchId;
  final int classroomId;
  final int? teacherId;
  final DateTime startDate;
  final List<String> days; // 1 a 7 de 'Lunes'…'Domingo', sin repetir
  final String startTime; // HH:mm
  final String endTime; // HH:mm
}

/// Aperturas de cursos: escritura (Coordinación de la sede o Dirección).
abstract class ScheduleRepository {
  Future<List<Teacher>> teachers();
  Future<void> createScheduledCourse(ScheduledCourseInput input);
  Future<void> updateScheduledCourse(int id, ScheduledCourseInput input);
  Future<void> deleteScheduledCourse(int id);
}

/// Cuerpo de POST/PUT /admin/carreras.
class CareerInput {
  const CareerInput({
    required this.name,
    this.description = '',
    this.durationInWeeks, // null = suma de los módulos
    this.moduleIds = const [], // en orden cronológico
    this.branchIds = const [],
  });
  final String name;
  final String description;
  final int? durationInWeeks;
  final List<int> moduleIds;
  final List<int> branchIds;
}

/// Cuerpo de POST/PUT /admin/modulos.
class ModuleInput {
  const ModuleInput({
    required this.name,
    this.description = '',
    required this.durationInWeeks,
    this.courseIds = const [],
  });
  final String name;
  final String description;
  final int durationInWeeks;
  final List<int> courseIds;
}

/// Cuerpo de POST/PUT /admin/talleres (curso modular o taller libre).
class CourseInput {
  const CourseInput({
    required this.name,
    this.description = '',
    required this.durationInWeeks,
    this.isWorkshop = false,
    this.moduleId,
  });
  final String name;
  final String description;
  final int durationInWeeks;
  final bool isWorkshop;
  final int? moduleId; // un taller libre siempre queda sin módulo
}

/// Oferta académica: escritura. Exclusiva de la Dirección General.
abstract class CatalogRepository {
  Future<void> createCareer(CareerInput input);
  Future<void> updateCareer(int id, CareerInput input);
  Future<void> deleteCareer(int id);
  Future<void> createModule(ModuleInput input);
  Future<void> updateModule(int id, ModuleInput input);
  Future<void> deleteModule(int id);
  Future<void> createCourse(CourseInput input);
  Future<void> updateCourse(int id, CourseInput input);
  Future<void> deleteCourse(int id);
}
