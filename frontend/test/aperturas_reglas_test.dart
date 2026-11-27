import 'package:flutter_test/flutter_test.dart';
import 'package:iisep_app/data/mock_repository.dart';
import 'package:iisep_app/data/repositories.dart';

Future<MockRepository> entrar(String usuario) async {
  final r = MockRepository();
  await r.login(usuario, 'admin123');
  return r;
}

DateTime hoy() {
  final t = DateTime.now();
  return DateTime(t.year, t.month, t.day);
}

/// Excel (4 semanas) en el Aula 101 de Comas, martes por la tarde.
ScheduledCourseInput nueva({
  int curso = 1,
  int sede = 1,
  int aula = 1,
  int? docente,
  List<String> dias = const ['Martes'],
  String desde = '15:00',
  String hasta = '17:00',
  DateTime? inicio,
}) =>
    ScheduledCourseInput(
      courseId: curso,
      branchId: sede,
      classroomId: aula,
      teacherId: docente,
      startDate: inicio ?? hoy(),
      days: dias,
      startTime: desde,
      endTime: hasta,
    );

void main() {
  group('Varios días por semana', () {
    test('una apertura puede tener de 1 a 7 días y se guardan en orden de semana',
        () async {
      final r = await entrar('storres');
      await r.createScheduledCourse(nueva(dias: ['Jueves', 'Lunes', 'Miércoles']));
      expect((await r.scheduledCourses()).last.days,
          ['Lunes', 'Miércoles', 'Jueves']);
    });

    test('sin días, repetidos o inválidos se rechaza', () async {
      final r = await entrar('storres');
      for (final dias in [
        <String>[],
        ['Lunes', 'Lunes'],
        ['Funday'],
      ]) {
        await expectLater(r.createScheduledCourse(nueva(dias: dias)),
            throwsA(isA<ValidationException>()));
      }
    });

    test('el choque se detecta con un solo día en común', () async {
      final r = await entrar('storres');
      // La apertura 1 es Lunes 18:00–21:00 en el Aula 102 de Comas.
      await expectLater(
        r.createScheduledCourse(nueva(
            aula: 2, dias: ['Martes', 'Lunes'], desde: '19:00', hasta: '20:00')),
        throwsA(isA<ValidationException>()),
      );
      await r.createScheduledCourse(nueva(
          aula: 2, dias: ['Martes', 'Jueves'], desde: '19:00', hasta: '20:00'));
    });
  });

  group('Crear apertura', () {
    test('Coordinación crea en su sede: fin = inicio + semanas, aforo = aula',
        () async {
      final r = await entrar('storres');
      await r.createScheduledCourse(nueva());
      final s = (await r.scheduledCourses()).last;
      expect(s.courseName, 'Excel Avanzado y Macros');
      expect(s.classroomName, 'Aula 101 - Teoría');
      expect(s.capacity, 35);
      expect(s.enrolled, 0);
      expect(s.endDate, hoy().add(const Duration(days: 28)));
      expect(s.days, ['Martes']);
    });

    test('Secretaría y Coordinación de otra sede no pueden (403)', () async {
      await expectLater((await entrar('mcastro')).createScheduledCourse(nueva()),
          throwsA(isA<ForbiddenException>()));
      await expectLater((await entrar('lflores')).createScheduledCourse(nueva()),
          throwsA(isA<ForbiddenException>()));
    });

    test('Dirección crea en cualquier sede', () async {
      final r = await entrar('admin');
      await r.createScheduledCourse(nueva(sede: 2, aula: 4));
      expect((await r.scheduledCourses()).last.branchName, 'Sede Puente Piedra');
    });

    test('el aula debe ser de la sede elegida', () async {
      final r = await entrar('admin');
      await expectLater(
        r.createScheduledCourse(nueva(sede: 1, aula: 4)), // aula 4 es de PP
        throwsA(isA<ValidationException>()
            .having((e) => e.fields.keys, 'campos', contains('classroom_id'))),
      );
    });

    test('la hora de fin debe ser posterior y el formato HH:mm', () async {
      final r = await entrar('admin');
      await expectLater(
        r.createScheduledCourse(nueva(desde: '17:00', hasta: '15:00')),
        throwsA(isA<ValidationException>()
            .having((e) => e.fields.keys, 'campos', contains('end_time'))),
      );
      await expectLater(
        r.createScheduledCourse(nueva(desde: '25:00', hasta: '26:00')),
        throwsA(isA<ValidationException>().having((e) => e.fields.keys, 'campos',
            containsAll(['start_time', 'end_time']))),
      );
    });

    test('choque de aula: mismo día, horario y fechas que otra apertura',
        () async {
      final r = await entrar('admin');
      // Apertura 1: Lab de Cómputo 1 (aula 2), lunes 18:00–21:00.
      await expectLater(
        r.createScheduledCourse(
            nueva(aula: 2, dias: ['Lunes'], desde: '19:00', hasta: '20:00')),
        throwsA(isA<ValidationException>()
            .having((e) => e.fields['classroom_id'], 'msg', contains('ocupada'))),
      );
      // Justo después no choca.
      await r.createScheduledCourse(
          nueva(aula: 2, dias: ['Lunes'], desde: '21:00', hasta: '22:00'));
    });

    test('choque de docente en otra aula', () async {
      final r = await entrar('admin');
      await expectLater(
        r.createScheduledCourse(nueva(
            aula: 1, docente: 1, dias: ['Lunes'], desde: '19:00', hasta: '20:00')),
        throwsA(isA<ValidationException>()
            .having((e) => e.fields.keys, 'campos', contains('teacher_id'))),
      );
    });

    test('mismo horario pero fechas que no se cruzan: sí se permite', () async {
      final r = await entrar('admin');
      await r.createScheduledCourse(nueva(
          aula: 2,
          dias: ['Lunes'],
          desde: '18:00',
          hasta: '21:00',
          inicio: hoy().add(const Duration(days: 60))));
    });
  });

  group('Editar y eliminar', () {
    test('sin alumnos se puede cambiar curso y aula', () async {
      final r = await entrar('storres');
      // Apertura 4 (Panadería, Comas) no tiene alumnos.
      await r.updateScheduledCourse(4, nueva(curso: 3, aula: 1, dias: ['Jueves']));
      final s = (await r.scheduledCourses()).firstWhere((x) => x.id == 4);
      expect(s.courseName, 'Arquitectura de Hardware y Diagnóstico');
      expect(s.days, ['Jueves']);
    });

    test('con alumnos no se cambia el curso ni la sede', () async {
      final r = await entrar('admin');
      await expectLater(
        r.updateScheduledCourse(1, nueva(curso: 2, aula: 2, dias: ['Lunes'], desde: '18:00', hasta: '21:00')),
        throwsA(isA<BusinessRuleException>()),
      );
      await expectLater(
        r.updateScheduledCourse(1, nueva(curso: 1, sede: 2, aula: 4, dias: ['Lunes'])),
        throwsA(isA<BusinessRuleException>()),
      );
    });

    test('no se pasa a un aula con menos vacantes que alumnos', () async {
      final r = await entrar('admin');
      await r.createClassroom(
          const ClassroomInput(name: 'Mini', capacity: 5, branchId: 2));
      final mini = (await r.branches())
          .firstWhere((b) => b.id == 2)
          .classrooms
          .firstWhere((c) => c.name == 'Mini');
      // Apertura 2 (Barber, 12 alumnos).
      await expectLater(
        r.updateScheduledCourse(
            2,
            nueva(
                curso: 11,
                sede: 2,
                aula: mini.id,
                dias: ['Sábado'],
                desde: '09:00',
                hasta: '13:00',
                inicio: hoy().subtract(const Duration(days: 7)))),
        throwsA(isA<BusinessRuleException>()),
      );
    });

    test('Coordinación solo edita aperturas de su sede', () async {
      final r = await entrar('storres'); // Comas
      await expectLater(
        r.updateScheduledCourse(2, nueva(curso: 11, sede: 2, aula: 5)), // PP
        throwsA(isA<ForbiddenException>()),
      );
    });

    test('eliminar: libre sí, con alumnos no', () async {
      final r = await entrar('storres');
      await expectLater(
          r.deleteScheduledCourse(1), throwsA(isA<BusinessRuleException>()));
      await r.deleteScheduledCourse(4);
      expect((await r.scheduledCourses()).any((s) => s.id == 4), isFalse);
    });

    test('una apertura a la que se matriculó alguien ya no se puede eliminar',
        () async {
      final r = await entrar('admin');
      await r.enroll(const EnrollmentInput(
          scheduledCourseId: 4,
          document: '80000001',
          firstName: 'Ana',
          paternalSurname: 'Lopez'));
      await expectLater(
          r.deleteScheduledCourse(4), throwsA(isA<BusinessRuleException>()));
    });

    test('renombrar un aula se refleja en sus aperturas', () async {
      final r = await entrar('admin');
      await r.updateClassroom(
          2,
          const ClassroomInput(
              name: 'Laboratorio Nuevo', capacity: 24, branchId: 1));
      final s = (await r.scheduledCourses()).firstWhere((x) => x.id == 1);
      expect(s.classroomName, 'Laboratorio Nuevo');
    });

    test('los docentes y el catálogo están disponibles', () async {
      final r = await entrar('admin');
      expect((await r.teachers()).length, 5);
      expect((await r.courses()).length, 15);
    });
  });
}
