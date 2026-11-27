import 'package:flutter_test/flutter_test.dart';
import 'package:iisep_app/data/mock_repository.dart';
import 'package:iisep_app/data/repositories.dart';

Future<MockRepository> entrar(String usuario) async {
  final r = MockRepository();
  await r.login(usuario, 'admin123');
  return r;
}

void main() {
  group('Sedes', () {
    test('Dirección crea una sede y se genera el código', () async {
      final r = await entrar('admin');
      await r.createBranch(const BranchInput(name: 'Sede Independencia'));
      final b = (await r.branches()).last;
      expect(b.name, 'Sede Independencia');
      expect(b.code, 'SED'); // 3 letras del nombre
    });

    test('rechaza nombre repetido y código repetido (422)', () async {
      final r = await entrar('admin');
      await expectLater(
        r.createBranch(const BranchInput(name: 'sede comas')),
        throwsA(isA<ValidationException>()
            .having((e) => e.fields.keys, 'campos', contains('name'))),
      );
      await expectLater(
        r.createBranch(const BranchInput(name: 'Otra', code: 'com')),
        throwsA(isA<ValidationException>()
            .having((e) => e.fields.keys, 'campos', contains('code'))),
      );
    });

    test('Coordinación no puede crear ni dar de baja sedes (403)', () async {
      final r = await entrar('storres');
      await expectLater(r.createBranch(const BranchInput(name: 'X')),
          throwsA(isA<ForbiddenException>()));
      await expectLater(
          r.deleteBranch(1), throwsA(isA<ForbiddenException>()));
    });

    test('Coordinación edita dirección y teléfono pero no nombre ni código',
        () async {
      final r = await entrar('storres');
      await r.updateBranch(
          1,
          const BranchInput(
              name: 'Nombre nuevo', code: 'ZZZ', address: 'Calle 1', phone: '999'));
      final b = (await r.branches()).firstWhere((b) => b.id == 1);
      expect(b.name, 'Sede Comas');
      expect(b.code, 'COM');
      expect(b.address, 'Calle 1');
      expect(b.phone, '999');
    });

    test('Coordinación no edita la sede de otra (403)', () async {
      final r = await entrar('storres');
      await expectLater(r.updateBranch(2, const BranchInput(name: 'x')),
          throwsA(isA<ForbiddenException>()));
    });

    test('no se da de baja una sede con personal asignado', () async {
      final r = await entrar('admin');
      await expectLater(
          r.deleteBranch(1), throwsA(isA<BusinessRuleException>()));
    });

    test('baja y reactivación de una sede nueva, con sus aulas', () async {
      final r = await entrar('admin');
      await r.createBranch(const BranchInput(name: 'Sede Temporal'));
      final id = (await r.branches()).last.id;
      await r.createClassroom(
          ClassroomInput(name: 'Aula A', capacity: 20, branchId: id));
      await r.deleteBranch(id);
      expect((await r.branches()).any((b) => b.id == id), isFalse);
      final bajas = await r.inactiveBranches();
      expect(bajas.single.classrooms.length, 1);
      await r.restoreBranch(id);
      final vuelta = (await r.branches()).firstWhere((b) => b.id == id);
      expect(vuelta.classrooms.length, 1);
    });
  });

  group('Aulas', () {
    test('aforo fuera de 1..500 se rechaza', () async {
      final r = await entrar('admin');
      for (final n in [0, 501]) {
        await expectLater(
          r.createClassroom(
              ClassroomInput(name: 'Nueva $n', capacity: n, branchId: 1)),
          throwsA(isA<ValidationException>()
              .having((e) => e.fields.keys, 'campos', contains('capacity'))),
        );
      }
    });

    test('nombre único por sede, pero repetible en otra sede', () async {
      final r = await entrar('admin');
      await expectLater(
        r.createClassroom(const ClassroomInput(
            name: 'aula 101 - teoría', capacity: 10, branchId: 1)),
        throwsA(isA<ValidationException>()),
      );
      await r.createClassroom(const ClassroomInput(
          name: 'Aula 101 - Teoría', capacity: 10, branchId: 2));
    });

    test('Coordinación solo crea aulas en su sede', () async {
      final r = await entrar('storres');
      await expectLater(
        r.createClassroom(
            const ClassroomInput(name: 'Intrusa', capacity: 10, branchId: 2)),
        throwsA(isA<ForbiddenException>()),
      );
      await r.createClassroom(
          const ClassroomInput(name: 'Propia', capacity: 10, branchId: 1));
    });

    test('Secretaría cambia el aforo, pero el nombre no', () async {
      final r = await entrar('mcastro');
      await r.updateClassroom(1,
          const ClassroomInput(name: 'Renombrada', capacity: 40, branchId: 1));
      final c = (await r.branches())
          .firstWhere((b) => b.id == 1)
          .classrooms
          .firstWhere((c) => c.id == 1);
      expect(c.capacity, 40);
      expect(c.name, 'Aula 101 - Teoría');
      await expectLater(r.deleteClassroom(1), throwsA(isA<ForbiddenException>()));
    });

    test('un aula con horarios no se elimina ni se transfiere', () async {
      final r = await entrar('admin');
      // Laboratorio de Cómputo 1 (id 2) tiene la apertura de Excel.
      await expectLater(
          r.deleteClassroom(2), throwsA(isA<BusinessRuleException>()));
      await expectLater(
        r.updateClassroom(2,
            const ClassroomInput(name: 'Laboratorio de Cómputo 1', capacity: 24, branchId: 2)),
        throwsA(isA<BusinessRuleException>()),
      );
      expect((await r.classroomSchedules(2)).isNotEmpty, isTrue);
    });

    test('un aula libre se transfiere y se elimina', () async {
      final r = await entrar('admin');
      expect(await r.classroomSchedules(1), isEmpty);
      await r.updateClassroom(1,
          const ClassroomInput(name: 'Aula 101 - Teoría', capacity: 35, branchId: 2));
      final pp = (await r.branches()).firstWhere((b) => b.id == 2);
      expect(pp.classrooms.any((c) => c.id == 1), isTrue);
      await r.deleteClassroom(1);
      final todas = (await r.branches()).expand((b) => b.classrooms);
      expect(todas.any((c) => c.id == 1), isFalse);
    });
  });
}
