import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'mock_repository.dart';
import 'models.dart';
import 'repositories.dart';

/// Único punto donde se decide el origen de los datos.
/// Para pasar al backend real: reemplazar `MockRepository()` por la
/// implementación HTTP (ver docs/API_NECESARIA.md).
final _mockProvider = Provider<MockRepository>((_) => MockRepository());

final authRepositoryProvider =
    Provider<AuthRepository>((ref) => ref.watch(_mockProvider));
final academicRepositoryProvider =
    Provider<AcademicRepository>((ref) => ref.watch(_mockProvider));

final branchesProvider = FutureProvider<List<Branch>>(
    (ref) => ref.watch(academicRepositoryProvider).branches());
final careersProvider = FutureProvider<List<Career>>(
    (ref) => ref.watch(academicRepositoryProvider).careers());
final modulesProvider = FutureProvider<List<Module>>(
    (ref) => ref.watch(academicRepositoryProvider).modules());
final coursesProvider = FutureProvider<List<Course>>(
    (ref) => ref.watch(academicRepositoryProvider).courses());
final scheduledCoursesProvider = FutureProvider<List<ScheduledCourse>>(
    (ref) => ref.watch(academicRepositoryProvider).scheduledCourses());
final enrollmentsProvider = FutureProvider<List<Enrollment>>(
    (ref) => ref.watch(academicRepositoryProvider).enrollments());

final infrastructureRepositoryProvider = Provider<InfrastructureRepository>(
    (ref) => ref.watch(_mockProvider));
final inactiveBranchesProvider = FutureProvider<List<Branch>>(
    (ref) => ref.watch(infrastructureRepositoryProvider).inactiveBranches());
final classroomSchedulesProvider =
    FutureProvider.autoDispose.family<List<ClassroomSchedule>, int>((ref, id) =>
        ref.watch(infrastructureRepositoryProvider).classroomSchedules(id));

final enrollmentRepositoryProvider =
    Provider<EnrollmentRepository>((ref) => ref.watch(_mockProvider));

final scheduleRepositoryProvider =
    Provider<ScheduleRepository>((ref) => ref.watch(_mockProvider));
final teachersProvider = FutureProvider<List<Teacher>>(
    (ref) => ref.watch(scheduleRepositoryProvider).teachers());

final catalogRepositoryProvider =
    Provider<CatalogRepository>((ref) => ref.watch(_mockProvider));
