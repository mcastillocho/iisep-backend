import 'package:flutter_test/flutter_test.dart';
import 'package:iisep_app/data/mock_repository.dart';
import 'package:iisep_app/data/models.dart';
import 'package:iisep_app/data/repositories.dart';

Future<MockRepository> entrar(String usuario) async {
  final r = MockRepository();
  await r.login(usuario, 'admin123');
  return r;
}

EnrollmentInput nueva({
  int apertura = 1,
  String dni = '80000001',
  String nombre = 'Ana',
  String paterno = 'Lopez',
  GuardianInput? apoderado,
  EnrollmentStatus estado = EnrollmentStatus.activo,
}) =>
    EnrollmentInput(
      scheduledCourseId: apertura,
      document: dni,
      firstName: nombre,
      paternalSurname: paterno,
      guardian: apoderado,
      status: estado,
    );

Future<int> ocupados(MockRepository r, int apertura) async =>
    (await r.scheduledCourses()).firstWhere((s) => s.id == apertura).enrolled;

void main() {
  group('Matricular', () {
    test('crea alumno nuevo, lo encuentra por DNI y ocupa una vacante', () async {
      final r = await entrar('admin');
      final antes = await ocupados(r, 1);
      await r.enroll(nueva());
      expect(await ocupados(r, 1), antes + 1);
      final f = await r.lookupStudent('80000001', scheduledCourseId: 1);
      expect(f.found, isTrue);
      expect(f.alreadyEnrolled, isTrue);
      expect(f.student!.fullName, 'Lopez, Ana');
      expect((await r.enrollments()).first.studentName, 'Lopez, Ana');
    });

    test('DNI de 8 dígitos y nombre/apellido obligatorios para alumno nuevo',
        () async {
      final r = await entrar('admin');
      await expectLater(
        r.enroll(nueva(dni: '123')),
        throwsA(isA<ValidationException>()
            .having((e) => e.fields.keys, 'campos', contains('student_document'))),
      );
      await expectLater(
        r.enroll(nueva(nombre: '', paterno: '')),
        throwsA(isA<ValidationException>().having((e) => e.fields.keys, 'campos',
            containsAll(['first_name', 'paternal_surname']))),
      );
    });

    test('el apoderado no puede ser el propio alumno ni venir incompleto',
        () async {
      final r = await entrar('admin');
      await expectLater(
        r.enroll(nueva(
            apoderado: const GuardianInput(
                document: '80000001', firstName: 'X', paternalSurname: 'Y'))),
        throwsA(isA<ValidationException>()
            .having((e) => e.fields.keys, 'campos', contains('guardian_document'))),
      );
      await expectLater(
        r.enroll(nueva(
            apoderado: const GuardianInput(
                document: '90000001', firstName: '', paternalSurname: ''))),
        throwsA(isA<ValidationException>().having((e) => e.fields.keys, 'campos',
            containsAll(['guardian_first_name', 'guardian_paternal_surname']))),
      );
    });

    test('con apoderado válido queda vinculado al alumno', () async {
      final r = await entrar('admin');
      await r.enroll(nueva(
          apoderado: const GuardianInput(
              document: '90000001',
              firstName: 'Rosa',
              paternalSurname: 'Lopez')));
      final f = await r.lookupStudent('80000001');
      expect(f.student!.guardian!.fullName, 'Lopez, Rosa');
    });

    test('no se matricula dos veces en la misma apertura', () async {
      final r = await entrar('admin');
      await r.enroll(nueva());
      await expectLater(
        r.enroll(nueva()),
        throwsA(isA<ValidationException>()
            .having((e) => e.fields['student_document'], 'msg', contains('ya está matriculado'))),
      );
    });

    test('un alumno retirado no se vuelve a matricular: se cambia su estado',
        () async {
      final r = await entrar('admin');
      // Luis Flores (74678901) está retirado en la apertura 2.
      await expectLater(
        r.enroll(nueva(apertura: 2, dni: '74678901')),
        throwsA(isA<BusinessRuleException>()),
      );
    });

    test('sin vacantes no se matricula', () async {
      final r = await entrar('admin');
      // Apertura 4: 20 vacantes libres.
      for (var n = 0; n < 20; n++) {
        await r.enroll(nueva(apertura: 4, dni: '8100${(1000 + n)}'));
      }
      expect(await ocupados(r, 4), 20);
      await expectLater(
        r.enroll(nueva(apertura: 4, dni: '81009999')),
        throwsA(isA<BusinessRuleException>()),
      );
    });

    test('un alumno existente solo completa su contacto', () async {
      final r = await entrar('admin');
      await r.enroll(EnrollmentInput(
        scheduledCourseId: 1,
        document: '78012345',
        email: 'mateo@correo.pe',
        // nombres distintos: se ignoran porque ya está registrado
        firstName: 'Otro',
        paternalSurname: 'Nombre',
      ));
      final s = (await r.lookupStudent('78012345')).student!;
      expect(s.fullName, 'Paredes Gómez, Mateo');
      expect(s.email, 'mateo@correo.pe');
      expect(s.phone, '987777888'); // lo que ya tenía se conserva
    });
  });

  group('Permisos', () {
    test('Secretaría matricula solo en su sede', () async {
      final r = await entrar('mcastro'); // Comas
      await r.enroll(nueva(apertura: 1));
      await expectLater(r.enroll(nueva(apertura: 2, dni: '80000002')),
          throwsA(isA<ForbiddenException>()));
    });

    test('Secretaría no registra como convalidado; Coordinación sí', () async {
      final sec = await entrar('mcastro');
      await expectLater(
          sec.enroll(nueva(estado: EnrollmentStatus.convalidado)),
          throwsA(isA<ForbiddenException>()));
      final coord = await entrar('storres');
      await coord.enroll(nueva(estado: EnrollmentStatus.convalidado));
    });

    test('estados por cargo: retirar es de Coordinación, anular de Dirección',
        () async {
      final sec = await entrar('mcastro');
      await expectLater(
          sec.changeEnrollmentStatus(1, EnrollmentStatus.retirado),
          throwsA(isA<ForbiddenException>()));
      final coord = await entrar('storres');
      await coord.changeEnrollmentStatus(1, EnrollmentStatus.retirado);
      await expectLater(
          coord.changeEnrollmentStatus(2, EnrollmentStatus.anulado),
          throwsA(isA<ForbiddenException>()));
      final admin = await entrar('admin');
      await admin.changeEnrollmentStatus(2, EnrollmentStatus.anulado);
    });

    test('Coordinación de otra sede no toca matrículas ajenas', () async {
      final r = await entrar('lflores'); // Puente Piedra
      await expectLater(
          r.changeEnrollmentStatus(1, EnrollmentStatus.completado), // Comas
          throwsA(isA<ForbiddenException>()));
    });

    test('solo la Dirección elimina matrículas', () async {
      final coord = await entrar('storres');
      await expectLater(
          coord.deleteEnrollment(1), throwsA(isA<ForbiddenException>()));
      final admin = await entrar('admin');
      await admin.deleteEnrollment(1);
      expect((await admin.enrollments()).any((e) => e.id == 1), isFalse);
    });
  });

  group('Ocupación', () {
    test('retirar libera la vacante y reactivar la vuelve a ocupar', () async {
      final r = await entrar('admin');
      final base = await ocupados(r, 1);
      await r.changeEnrollmentStatus(1, EnrollmentStatus.retirado);
      expect(await ocupados(r, 1), base - 1);
      await r.changeEnrollmentStatus(1, EnrollmentStatus.activo);
      expect(await ocupados(r, 1), base);
    });

    test('eliminar una matrícula vigente libera la vacante', () async {
      final r = await entrar('admin');
      final base = await ocupados(r, 1);
      await r.deleteEnrollment(2); // activa en apertura 1
      expect(await ocupados(r, 1), base - 1);
    });
  });
}
