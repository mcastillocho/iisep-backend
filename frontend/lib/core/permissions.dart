import '../data/models.dart';

/// Permisos de sedes y aulas según ROLES_Y_PERMISOS.md. Solo ocultan botones:
/// el servidor vuelve a validar cada acción.
extension InfraPermissions on AuthUser {
  bool get isGlobalAdmin => isGlobal && position == StaffPosition.admin;

  bool _inBranch(int id) => isGlobal || branchId == id;

  bool get canCreateBranch => isGlobalAdmin;
  bool get canDeleteBranch => isGlobalAdmin;
  bool get canRestoreBranch => isGlobalAdmin;

  /// Dirección y Coordinación de esa sede (nivel 3+).
  bool canEditBranch(int id) => hasLevel(3) && _inBranch(id);

  /// Solo Dirección cambia nombre y código.
  bool get canEditBranchIdentity => isGlobalAdmin;

  bool canCreateClassroom(int id) => hasLevel(3) && _inBranch(id);

  /// Secretaría también puede editar el aforo.
  bool canEditClassroom(int id) => hasLevel(2) && _inBranch(id);
  bool canRenameClassroom(int id) => hasLevel(3) && _inBranch(id);
  bool get canTransferClassroom => isGlobalAdmin;
  bool canDeleteClassroom(int id) => hasLevel(3) && _inBranch(id);
}

/// Matrículas según ROLES_Y_PERMISOS.md: Secretaría matricula; Coordinación
/// maneja estados extraordinarios (retirado, convalidado); la anulación y el
/// borrado son de la Dirección General. El backend actual deja a Secretaría
/// hacer todo: es un hueco que debe cerrarse en el servidor.
extension EnrollmentPermissions on AuthUser {
  bool get canEnroll => hasLevel(2);
  bool get canDeleteEnrollment => hasLevel(4);

  int _levelFor(EnrollmentStatus s) => switch (s) {
        EnrollmentStatus.preinscrito ||
        EnrollmentStatus.activo ||
        EnrollmentStatus.completado =>
          2,
        EnrollmentStatus.retirado || EnrollmentStatus.convalidado => 3,
        EnrollmentStatus.anulado => 4,
      };

  bool canSetStatus(EnrollmentStatus s) => hasLevel(_levelFor(s));

  List<EnrollmentStatus> get settableStatuses =>
      [for (final s in EnrollmentStatus.values) if (canSetStatus(s)) s];
}

/// Aperturas: solo Coordinación de la sede o Dirección General.
extension SchedulePermissions on AuthUser {
  bool canManageSchedule(int branchId) =>
      hasLevel(3) && (isGlobal || this.branchId == branchId);
  bool get canCreateSchedule => hasLevel(3);
}

/// Carreras, módulos, cursos y talleres: exclusivo de la Dirección General.
extension CatalogPermissions on AuthUser {
  bool get canManageCatalog => isGlobalAdmin;
}
