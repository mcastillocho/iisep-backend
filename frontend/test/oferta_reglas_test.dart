import 'package:flutter_test/flutter_test.dart';
import 'package:iisep_app/data/mock_repository.dart';
import 'package:iisep_app/data/repositories.dart';

Future<MockRepository> entrar(String usuario) async {
  final r = MockRepository();
  await r.login(usuario, 'admin123');
  return r;
}

Matcher campos(List<String> keys) => throwsA(isA<ValidationException>()
    .having((e) => e.fields.keys, 'campos', containsAll(keys)));

void main() {
  group('Permisos', () {
    test('solo la Dirección General administra el catálogo (403)', () async {
      for (final u in ['storres', 'mcastro', 'lflores']) {
        final r = await entrar(u);
        await expectLater(
            r.createCareer(const CareerInput(name: 'X')), throwsA(isA<ForbiddenException>()));
        await expectLater(
            r.createModule(const ModuleInput(name: 'X', durationInWeeks: 4)),
            throwsA(isA<ForbiddenException>()));
        await expectLater(
            r.createCourse(const CourseInput(name: 'X', durationInWeeks: 4)),
            throwsA(isA<ForbiddenException>()));
      }
    });
  });

  group('Carreras', () {
    test('crea con módulos en orden y suma las semanas si no se indica', () async {
      final r = await entrar('admin');
      await r.createCareer(const CareerInput(
          name: 'Ofimática Aplicada', moduleIds: [3, 1], branchIds: [2]));
      final c = (await r.careers()).last;
      expect(c.moduleNames, ['Redes y Conectividad', 'Ofimática Empresarial']);
      expect(c.branchNames, ['Sede Puente Piedra']);
      expect(c.durationInWeeks, 16); // 8 + 8
    });

    test('con duración indicada se respeta', () async {
      final r = await entrar('admin');
      await r.createCareer(const CareerInput(
          name: 'Corta', moduleIds: [1], durationInWeeks: 30));
      expect((await r.careers()).last.durationInWeeks, 30);
    });

    test('nombre único, duración 1..200 y módulo repetido', () async {
      final r = await entrar('admin');
      await expectLater(r.createCareer(const CareerInput(name: 'computación e informática')),
          campos(['name']));
      await expectLater(
          r.createCareer(const CareerInput(name: 'A', durationInWeeks: 201)),
          campos(['duration_in_weeks']));
      await expectLater(
          r.createCareer(const CareerInput(name: 'B', moduleIds: [1, 1])),
          campos(['modules']));
      await expectLater(r.createCareer(const CareerInput(name: '')), campos(['name']));
    });

    test('editar puede quitar y reordenar módulos; eliminar conserva los módulos',
        () async {
      final r = await entrar('admin');
      await r.updateCareer(
          1, const CareerInput(name: 'Computación e Informática', moduleIds: [3, 2]));
      var c = (await r.careers()).firstWhere((x) => x.id == 1);
      expect(c.moduleIds, [3, 2]);
      await r.deleteCareer(1);
      expect((await r.careers()).any((x) => x.id == 1), isFalse);
      expect((await r.modules()).length, 7); // los módulos siguen
    });
  });

  group('Módulos', () {
    test('crea un módulo con cursos modulares libres', () async {
      final r = await entrar('admin');
      // Cursos 2 (Word) ya está en el módulo 1; creamos uno libre.
      await r.createCourse(const CourseInput(name: 'Power BI', durationInWeeks: 4));
      final libre = (await r.courses()).last;
      await r.createModule(ModuleInput(
          name: 'Análisis de Datos', durationInWeeks: 8, courseIds: [libre.id]));
      final m = (await r.modules()).last;
      expect(m.courseNames, ['Power BI']);
      expect((await r.courses()).last.moduleName, 'Análisis de Datos');
    });

    test('no admite talleres libres ni cursos de otro módulo', () async {
      final r = await entrar('admin');
      await expectLater(
          r.createModule(const ModuleInput(
              name: 'M1', durationInWeeks: 8, courseIds: [11])), // taller
          campos(['course_ids']));
      await expectLater(
          r.createModule(const ModuleInput(
              name: 'M2', durationInWeeks: 8, courseIds: [1])), // ya es del módulo 1
          campos(['course_ids']));
    });

    test('duración 1..52 y nombre único', () async {
      final r = await entrar('admin');
      await expectLater(
          r.createModule(const ModuleInput(name: 'Redes y conectividad', durationInWeeks: 8)),
          campos(['name']));
      await expectLater(
          r.createModule(const ModuleInput(name: 'N', durationInWeeks: 53)),
          campos(['duration_in_weeks']));
      await expectLater(
          r.createModule(const ModuleInput(name: 'N', durationInWeeks: 0)),
          campos(['duration_in_weeks']));
    });

    test('al editar, los cursos que salen quedan sin módulo', () async {
      final r = await entrar('admin');
      await r.updateModule(1,
          const ModuleInput(name: 'Ofimática Empresarial', durationInWeeks: 8, courseIds: [1]));
      final word = (await r.courses()).firstWhere((c) => c.id == 2);
      expect(word.moduleId, isNull);
    });

    test('no se elimina si tiene cursos o está en una carrera', () async {
      final r = await entrar('admin');
      await expectLater(r.deleteModule(1), throwsA(isA<BusinessRuleException>()));
      // Módulo 7 no tiene cursos pero está en Gastronomía.
      await expectLater(r.deleteModule(7), throwsA(isA<BusinessRuleException>()));
      await r.createModule(const ModuleInput(name: 'Suelto', durationInWeeks: 4));
      final id = (await r.modules()).last.id;
      await r.deleteModule(id);
      expect((await r.modules()).any((m) => m.id == id), isFalse);
    });
  });

  group('Cursos y talleres', () {
    test('un taller libre siempre queda sin módulo', () async {
      final r = await entrar('admin');
      await r.createCourse(const CourseInput(
          name: 'Taller X', durationInWeeks: 3, isWorkshop: true, moduleId: 1));
      final c = (await r.courses()).last;
      expect(c.isWorkshop, isTrue);
      expect(c.moduleId, isNull);
    });

    test('nombre único y duración 1..52', () async {
      final r = await entrar('admin');
      await expectLater(
          r.createCourse(const CourseInput(name: 'excel avanzado y macros', durationInWeeks: 4)),
          campos(['name']));
      await expectLater(
          r.createCourse(const CourseInput(name: 'Z', durationInWeeks: 60)),
          campos(['duration_in_weeks']));
    });

    test('con aperturas vigentes no se cambia el módulo ni el tipo', () async {
      final r = await entrar('admin');
      // Excel (curso 1) tiene la apertura 1 en curso.
      await expectLater(
        r.updateCourse(
            1,
            const CourseInput(
                name: 'Excel Avanzado y Macros', durationInWeeks: 4, moduleId: 2)),
        campos(['module_id']),
      );
      await expectLater(
        r.updateCourse(
            1,
            const CourseInput(
                name: 'Excel Avanzado y Macros', durationInWeeks: 4, isWorkshop: true)),
        campos(['module_id']),
      );
      // Pero sí se puede renombrar y cambiar la duración.
      await r.updateCourse(
          1,
          const CourseInput(
              name: 'Excel Pro', durationInWeeks: 5, moduleId: 1));
      expect((await r.courses()).firstWhere((c) => c.id == 1).name, 'Excel Pro');
    });

    test('un curso sin actividad cambia de módulo libremente', () async {
      final r = await entrar('admin');
      await r.updateCourse(
          2,
          const CourseInput(
              name: 'Word Empresarial y Gestión Documental',
              durationInWeeks: 4,
              moduleId: 2));
      expect((await r.courses()).firstWhere((c) => c.id == 2).moduleName,
          'Ensamblaje y Mantenimiento de PC');
    });

    test('no se elimina un curso con aperturas; uno libre sí', () async {
      final r = await entrar('admin');
      await expectLater(r.deleteCourse(1), throwsA(isA<BusinessRuleException>()));
      await r.deleteCourse(14); // Maquillaje, sin aperturas
      expect((await r.courses()).any((c) => c.id == 14), isFalse);
    });

    test('renombrar un curso se ve en sus aperturas', () async {
      final r = await entrar('admin');
      await r.updateCourse(
          1,
          const CourseInput(
              name: 'Excel Nuevo Nombre', durationInWeeks: 4, moduleId: 1));
      final s = (await r.scheduledCourses()).firstWhere((x) => x.id == 1);
      expect(s.courseName, 'Excel Nuevo Nombre');
    });
  });
}
