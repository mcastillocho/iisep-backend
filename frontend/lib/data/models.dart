library;

/// Modelos de dominio. Reflejan las tablas del backend (iisep-backend):
/// branches, classrooms, careers, modules, courses, scheduled_courses,
/// enrollments y users/staff.

enum EnrollmentStatus {
  preinscrito('Preinscrito'),
  activo('Activo'),
  completado('Completado'),
  convalidado('Convalidado'),
  retirado('Retirado'),
  anulado('Anulado');

  const EnrollmentStatus(this.label);
  final String label;
}

/// Cargo del personal. Nivel jerárquico según ROLES_Y_PERMISOS.md.
enum StaffPosition {
  recepcion(1, 'Recepción'),
  secre(2, 'Secretaría Académica'),
  coord(3, 'Coordinación Académica'),
  admin(4, 'Dirección General');

  const StaffPosition(this.level, this.label);
  final int level;
  final String label;

  static StaffPosition parse(String raw) => StaffPosition.values.firstWhere(
        (p) => p.name == raw,
        orElse: () => StaffPosition.recepcion,
      );
}

class AuthUser {
  const AuthUser({
    required this.id,
    required this.fullName,
    required this.username,
    required this.position,
    this.branchId, // null = acceso global
    this.branchName,
  });

  final int id;
  final String fullName;
  final String username;
  final StaffPosition position;
  final int? branchId;
  final String? branchName;

  bool get isGlobal => branchId == null;
  bool hasLevel(int level) => position.level >= level;

  String get initials {
    final parts = fullName.trim().split(RegExp(r'\s+'));
    if (parts.length == 1) return parts.first[0].toUpperCase();
    return (parts.first[0] + parts.last[0]).toUpperCase();
  }
}

class Classroom {
  const Classroom({
    required this.id,
    required this.branchId,
    required this.name,
    required this.capacity,
  });
  final int id;
  final int branchId;
  final String name;
  final int capacity;
}

class Branch {
  const Branch({
    required this.id,
    required this.name,
    required this.code,
    required this.address,
    required this.phone,
    required this.classrooms,
  });
  final int id;
  final String name;
  final String code;
  final String address;
  final String phone;
  final List<Classroom> classrooms;

  int get totalCapacity => classrooms.fold(0, (s, c) => s + c.capacity);
}

class Career {
  const Career({
    required this.id,
    required this.name,
    required this.description,
    required this.durationInWeeks,
    required this.moduleNames,
    required this.branchNames,
    this.moduleIds = const [],
    this.branchIds = const [],
  });
  final int id;
  final String name;
  final String description;
  final int durationInWeeks;

  /// Módulos en orden cronológico (tabla pivote career_module.order).
  final List<String> moduleNames;
  final List<String> branchNames;
  final List<int> moduleIds; // mismo orden que moduleNames
  final List<int> branchIds;
}

class Module {
  const Module({
    required this.id,
    required this.name,
    required this.description,
    required this.durationInWeeks,
    required this.courseNames,
    this.courseIds = const [],
  });
  final int id;
  final String name;
  final String description;
  final int durationInWeeks;
  final List<String> courseNames;
  final List<int> courseIds;
}

/// Curso modular o taller libre (tabla courses).
class Course {
  const Course({
    required this.id,
    required this.name,
    required this.description,
    required this.durationInWeeks,
    required this.isWorkshop,
    this.moduleName,
    this.moduleId,
  });
  final int id;
  final String name;
  final String description;
  final int durationInWeeks;
  final bool isWorkshop;
  final String? moduleName;
  final int? moduleId;
}

const Object _keep = Object();

enum ScheduleState { programado, enCurso, finalizado }

class ScheduledCourse {
  const ScheduledCourse({
    required this.id,
    required this.courseId,
    required this.branchId,
    required this.classroomId,
    this.teacherId,
    required this.courseName,
    required this.isWorkshop,
    required this.branchName,
    required this.classroomName,
    required this.teacherName,
    required this.startDate,
    required this.endDate,
    required this.days,
    required this.startTime,
    required this.endTime,
    required this.capacity,
    required this.enrolled,
  });
  final int id;
  final int courseId;
  final int branchId;
  final int classroomId;
  final int? teacherId;
  final String courseName;
  final bool isWorkshop;
  final String branchName;
  final String classroomName;
  final String? teacherName;
  final DateTime startDate;
  final DateTime endDate;
  final List<String> days;
  final String startTime;
  final String endTime;
  final int capacity;
  final int enrolled;

  int get vacancies => capacity - enrolled;
  double get occupancy => capacity == 0 ? 0 : enrolled / capacity;

  ScheduledCourse copyWith({
    int? courseId,
    int? branchId,
    int? classroomId,
    Object? teacherId = _keep,
    String? courseName,
    bool? isWorkshop,
    String? branchName,
    String? classroomName,
    Object? teacherName = _keep,
    DateTime? startDate,
    DateTime? endDate,
    List<String>? days,
    String? startTime,
    String? endTime,
    int? capacity,
    int? enrolled,
  }) =>
      ScheduledCourse(
        id: id,
        courseId: courseId ?? this.courseId,
        branchId: branchId ?? this.branchId,
        classroomId: classroomId ?? this.classroomId,
        teacherId: identical(teacherId, _keep) ? this.teacherId : teacherId as int?,
        courseName: courseName ?? this.courseName,
        isWorkshop: isWorkshop ?? this.isWorkshop,
        branchName: branchName ?? this.branchName,
        classroomName: classroomName ?? this.classroomName,
        teacherName:
            identical(teacherName, _keep) ? this.teacherName : teacherName as String?,
        startDate: startDate ?? this.startDate,
        endDate: endDate ?? this.endDate,
        days: days ?? this.days,
        startTime: startTime ?? this.startTime,
        endTime: endTime ?? this.endTime,
        capacity: capacity ?? this.capacity,
        enrolled: enrolled ?? this.enrolled,
      );

  ScheduledCourse withEnrolled(int value) => copyWith(enrolled: value);

  ScheduleState stateAt(DateTime now) {
    final today = DateTime(now.year, now.month, now.day);
    if (today.isBefore(startDate)) return ScheduleState.programado;
    if (today.isAfter(endDate)) return ScheduleState.finalizado;
    return ScheduleState.enCurso;
  }
}

class Enrollment {
  const Enrollment({
    required this.id,
    required this.scheduledCourseId,
    required this.studentName,
    required this.document,
    required this.courseName,
    required this.branchName,
    required this.status,
    required this.date,
  });
  final int id;
  final int scheduledCourseId;
  final String studentName;
  final String document;
  final String courseName;
  final String branchName;
  final EnrollmentStatus status;
  final DateTime date;

  Enrollment copyWith({EnrollmentStatus? status}) => Enrollment(
        id: id,
        scheduledCourseId: scheduledCourseId,
        studentName: studentName,
        document: document,
        courseName: courseName,
        branchName: branchName,
        status: status ?? this.status,
        date: date,
      );
}

/// Horario ocupado de un aula (modal "Ver disponibilidad").
class ClassroomSchedule {
  const ClassroomSchedule({
    required this.courseName,
    required this.teacherName,
    required this.day,
    required this.startTime,
    required this.endTime,
    required this.startDate,
    required this.endDate,
    required this.state,
  });
  final String courseName;
  final String? teacherName;
  final String day;
  final String startTime;
  final String endTime;
  final DateTime startDate;
  final DateTime endDate;
  final ScheduleState state;
}

class Guardian {
  const Guardian({
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

  String get fullName => _fullName(firstName, paternalSurname, maternalSurname);
}

class Student {
  const Student({
    required this.document,
    required this.firstName,
    required this.paternalSurname,
    this.maternalSurname = '',
    this.birthDate,
    this.phone = '',
    this.email = '',
    this.guardian,
  });
  final String document;
  final String firstName;
  final String paternalSurname;
  final String maternalSurname;
  final DateTime? birthDate;
  final String phone;
  final String email;
  final Guardian? guardian;

  String get fullName => _fullName(firstName, paternalSurname, maternalSurname);

  bool isMinorAt(DateTime now) {
    final b = birthDate;
    if (b == null) return false;
    var age = now.year - b.year;
    if (now.month < b.month || (now.month == b.month && now.day < b.day)) age--;
    return age < 18;
  }

  /// Solo reemplaza los datos que llegan (como el backend: no borra contacto).
  Student copyWith({
    DateTime? birthDate,
    String? phone,
    String? email,
    Guardian? guardian,
  }) =>
      Student(
        document: document,
        firstName: firstName,
        paternalSurname: paternalSurname,
        maternalSurname: maternalSurname,
        birthDate: birthDate ?? this.birthDate,
        phone: phone ?? this.phone,
        email: email ?? this.email,
        guardian: guardian ?? this.guardian,
      );
}

String _fullName(String first, String pat, String mat) =>
    '${mat.isEmpty ? pat : '$pat $mat'}, $first';

/// Resultado de buscar un alumno por DNI (searchStudentByDni).
class StudentLookup {
  const StudentLookup({
    required Student this.student,
    this.alreadyEnrolled = false,
    this.enrolledScheduledIds = const [],
  });
  const StudentLookup.notFound()
      : student = null,
        alreadyEnrolled = false,
        enrolledScheduledIds = const [];

  final Student? student;
  final bool alreadyEnrolled;
  final List<int> enrolledScheduledIds;

  bool get found => student != null;
}

/// Docente asignable a una apertura.
class Teacher {
  const Teacher({
    required this.id,
    required this.fullName,
    required this.specialty,
  });
  final int id;
  final String fullName;
  final String specialty;
}
