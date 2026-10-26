export interface StudentLike {
    id: number;
    user_id: number;
    guardian?: { id: number } | null;
    enrollments?: Array<{
        branch_id?: number | string | null;
        status?: string;
    }>;
}

/**
 * Determina si un alumno o apoderado pertenece a una sede específica.
 * Si branchId === 'all', siempre retorna true.
 *
 * Criterios:
 * 1. Si el alumno tiene matrículas directas en cursos de esa sede -> true.
 * 2. Si tiene matrículas en otras sedes pero ninguna en esta -> false.
 * 3. Si no tiene matrículas directas (ej. apoderado registrado como alumno):
 *    - Si es apoderado de algún alumno matriculado en esta sede -> true.
 *    - Si es apoderado de alumnos matriculados en otras sedes pero no en esta -> false.
 * 4. Si no tiene matrículas ni apoderados matriculados en otra sede (ej. recién registrado) -> true.
 */
export function isStudentInBranch<T extends StudentLike>(
    student: T,
    branchId: number | 'all',
    allStudents: T[] = [],
): boolean {
    if (branchId === 'all') return true;
    const branchStr = String(branchId);

    // 1. Matrículas directas del alumno en la sede seleccionada
    const enrollments = student.enrollments || [];
    const hasDirectBranchEnrollment = enrollments.some(
        (e) => String(e.branch_id) === branchStr,
    );

    if (hasDirectBranchEnrollment) {
        return true;
    }

    // Si tiene matrículas en otras sedes pero ninguna en esta sede
    if (enrollments.length > 0) {
        return false;
    }

    // 2. Si no tiene matrículas directas (ej. apoderado registrado como estudiante):
    // Verificar si es apoderado de alumnos matriculados en esta sede
    const wards = allStudents.filter(
        (s) => s.guardian && (s.guardian.id === student.user_id || s.guardian.id === student.id),
    );

    if (wards.length > 0) {
        return wards.some((ward) =>
            (ward.enrollments || []).some((e) => String(e.branch_id) === branchStr),
        );
    }

    // 3. Estudiante sin matrículas y sin vínculos como apoderado en otra sede
    return true;
}

/**
 * Determina si el alumno cuenta con matrícula activa (activo, convalidado, preinscrito)
 * en la sede especificada (o en cualquiera si branchId === 'all').
 */
export function hasActiveEnrollmentInBranch<T extends StudentLike>(
    student: T,
    branchId: number | 'all',
): boolean {
    const enrollments = student.enrollments || [];
    return enrollments.some((e) => {
        const matchesBranch = branchId === 'all' || String(e.branch_id) === String(branchId);
        return (
            matchesBranch &&
            typeof e.status === 'string' &&
            ['activo', 'convalidado', 'preinscrito'].includes(e.status)
        );
    });
}
