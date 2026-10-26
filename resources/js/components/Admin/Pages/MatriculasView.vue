<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

export interface Branch {
    id: number;
    name: string;
    code: string;
    address: string;
    phone: string;
}

export interface Classroom {
    id: number;
    branch_id: number;
    branch_name?: string;
    name: string;
    capacity: number | null;
}

export interface CareerOption {
    id: number;
    name: string;
    description?: string | null;
}

export interface CourseOption {
    id: number;
    name: string;
    duration_in_weeks: number;
    career_id?: number | null;
    career_name?: string | null;
    module_id?: number | null;
    module_name?: string;
    is_workshop: boolean;
}

export interface TeacherItem {
    id: number;
    full_name: string;
    specialty: string;
}

export interface ScheduledCourseItem {
    id: number;
    course_id: number;
    course_name: string;
    career_id?: number | null;
    career_name?: string | null;
    is_workshop?: boolean;
    branch_id: number;
    branch_name: string;
    classroom_id: number;
    classroom_name: string;
    teacher_id?: number | null;
    teacher_name: string;
    start_date: string;
    end_date: string;
    day_of_week: string;
    days?: string[];
    start_time?: string;
    end_time?: string;
    time_range: string;
    capacity: number;
    enrolled_count: number;
    status: 'en_curso' | 'programado' | 'finalizado' | 'cancelado';
}

export type EnrollmentStatus =
    | 'activo'
    | 'convalidado'
    | 'completado'
    | 'preinscrito'
    | 'retirado'
    | 'anulado';

export interface GuardianInfo {
    id: number;
    document: string;
    first_name: string;
    paternal_surname: string;
    maternal_surname: string | null;
    full_name: string;
    phone: string;
    email: string;
}

export interface StudentEnrollmentItem {
    id: number;
    scheduled_course_id: number;
    course_id?: number;
    course_name: string;
    career_id?: number;
    career_name?: string;
    is_workshop: boolean;
    branch_id?: number;
    branch_name: string;
    classroom_name: string;
    start_date: string;
    end_date: string;
    day_of_week: string;
    time_range: string;
    enrollment_date: string;
    status: EnrollmentStatus;
    notes?: string | null;
}

export interface StudentRecord {
    id: number;
    user_id: number;
    document: string;
    document_type: string;
    first_name: string;
    paternal_surname: string;
    maternal_surname: string | null;
    full_name: string;
    birth_date?: string | null;
    phone: string;
    email: string;
    guardian: GuardianInfo | null;
    enrollments: StudentEnrollmentItem[];
}

export interface EnrollmentRecord {
    id: number;
    scheduled_course_id: number;
    student_full_name: string;
    student_document: string;
    course_name: string;
    module_name: string | null;
    career_name: string | null;
    branch_name: string;
    classroom_name?: string;
    enrollment_date: string;
    status: EnrollmentStatus;
    notes: string | null;
}

interface AuthUser {
    id: number;
    username: string;
    staff?: {
        id: number;
        branch_id: number | null;
        position: string | null;
    } | null;
}

interface Props {
    branches: Branch[];
    classrooms?: Classroom[];
    careers?: CareerOption[];
    courses?: CourseOption[];
    scheduledCourses?: ScheduledCourseItem[];
    enrollments: EnrollmentRecord[];
    students?: StudentRecord[];
    selectedBranchId?: number | 'all';
}

const props = withDefaults(defineProps<Props>(), {
    classrooms: () => [],
    careers: () => [],
    courses: () => [],
    scheduledCourses: () => [],
    students: () => [],
    selectedBranchId: 'all',
});

const emit = defineEmits<{
    (e: 'notify', message: string): void;
}>();

// Auth y Roles
const page = usePage<{ auth?: { user?: AuthUser } }>();

const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return false;
    if (!user.staff) return true;
    return user.staff.branch_id === null || user.staff.position === 'admin';
});

const isCoordinador = computed(() => {
    const user = page.props.auth?.user;
    if (!user?.staff) return false;
    if (isAdmin.value) return true;
    return user.staff.branch_id !== null && user.staff.position === 'coord';
});

const isSecretaria = computed(() => {
    const user = page.props.auth?.user;
    if (!user?.staff) return false;
    return user.staff.branch_id !== null && user.staff.position === 'secre';
});

// Permiso para crear o editar datos de alumno y apoderado (desde secretaria hacia arriba)
const canEditStudent = computed(() => {
    return isAdmin.value || isCoordinador.value || isSecretaria.value;
});

const canEnroll = computed(() => {
    if (isAdmin.value) return true;
    const staffBranch = page.props.auth?.user?.staff?.branch_id;
    if (isCoordinador.value || isSecretaria.value) {
        if (props.selectedBranchId === 'all') return true;
        return staffBranch === props.selectedBranchId;
    }
    return false;
});

// Zona horaria de Perú
function getPeruToday(): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima' }).format(new Date());
}

function formatPeruDate(dateString: string): string {
    if (!dateString) return '---';
    const parts = dateString.split('-');
    if (parts.length < 3) return dateString;
    const year = Number(parts[0]);
    const month = Number(parts[1]);
    const day = Number(parts[2]);
    if (!year || !month || !day) return dateString;
    const date = new Date(year, month - 1, day);
    return new Intl.DateTimeFormat('es-PE', {
        timeZone: 'America/Lima',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

// ========================================================
// MODOS DE VISTA: Lista de Alumnos | Ficha Alumno | Asistente Matrícula
// ========================================================
type ViewMode = 'students_list' | 'student_detail' | 'enrollment_wizard';
const currentView = ref<ViewMode>('students_list');
const selectedStudent = ref<StudentRecord | null>(null);

// ========================================================
// BÚSQUEDA Y FILTROS DE ALUMNOS
// ========================================================
const searchStudentQuery = ref('');
const studentFilterStatus = ref<'all' | 'with_active' | 'without_active'>('all');
const selectedBranchFilter = ref<number | 'all'>('all');

watch(
    () => props.selectedBranchId,
    (newVal) => {
        if (newVal !== undefined) {
            selectedBranchFilter.value = newVal;
        }
    },
    { immediate: true },
);

const filteredStudents = computed(() => {
    const list = props.students || [];
    return list.filter((student) => {
        // Filtrado por sede si se seleccionó una sede específica
        if (selectedBranchFilter.value !== 'all') {
            const enrollments = student.enrollments || [];
            const hasEnrollmentInBranch = enrollments.some(
                (e) => String(e.branch_id) === String(selectedBranchFilter.value),
            );
            if (enrollments.length > 0 && !hasEnrollmentInBranch) {
                return false;
            }
        }

        // Filtro por estado de matrícula
        const enrollments = student.enrollments || [];
        if (studentFilterStatus.value === 'with_active') {
            const hasActive = enrollments.some((e) =>
                ['activo', 'convalidado', 'preinscrito'].includes(e.status),
            );
            if (!hasActive) return false;
        } else if (studentFilterStatus.value === 'without_active') {
            const hasActive = enrollments.some((e) =>
                ['activo', 'convalidado', 'preinscrito'].includes(e.status),
            );
            if (hasActive) return false;
        }

        // Búsqueda por texto (DNI, nombres del alumno, apoderado o cursos)
        if (searchStudentQuery.value.trim() !== '') {
            const q = searchStudentQuery.value.toLowerCase();
            const fullName = (student.full_name || `${student.first_name || ''} ${student.paternal_surname || ''}`).toLowerCase();
            const doc = student.document || '';
            const phone = student.phone || '';
            const email = (student.email || '').toLowerCase();

            const matchStudent =
                fullName.includes(q) ||
                doc.includes(q) ||
                phone.includes(q) ||
                email.includes(q);

            const matchGuardian = student.guardian
                ? (student.guardian.full_name || '').toLowerCase().includes(q) ||
                  (student.guardian.document || '').includes(q) ||
                  (student.guardian.phone || '').includes(q)
                : false;

            const matchCourses = enrollments.some((e) =>
                (e.course_name || '').toLowerCase().includes(q) ||
                (e.career_name && e.career_name.toLowerCase().includes(q)),
            );

            return matchStudent || matchGuardian || matchCourses;
        }

        return true;
    });
});

function viewStudentDetail(student: StudentRecord) {
    selectedStudent.value = student;
    currentView.value = 'student_detail';
}

function closeStudentDetail() {
    currentView.value = 'students_list';
}

// ========================================================
// MODAL: REGISTRO / EDICIÓN DE ALUMNO Y APODERADO
// ========================================================
const showStudentModal = ref(false);
const isEditingStudent = ref(false);
const existingStudentDniName = ref('');
const guardianFoundName = ref('');
let studentDniTimer: ReturnType<typeof setTimeout> | null = null;
let guardianDniTimer: ReturnType<typeof setTimeout> | null = null;

const studentForm = useForm({
    id: 0,
    first_name: '',
    paternal_surname: '',
    maternal_surname: '',
    document: '',
    birth_date: '',
    phone: '',
    email: '',
    has_guardian: false,
    guardian_first_name: '',
    guardian_paternal_surname: '',
    guardian_maternal_surname: '',
    guardian_document: '',
    guardian_phone: '',
    guardian_email: '',
});

function handleStudentFormDniInput() {
    existingStudentDniName.value = '';
    const doc = studentForm.document.trim();
    if (doc.length >= 8 && !isEditingStudent.value) {
        if (studentDniTimer) clearTimeout(studentDniTimer);
        studentDniTimer = setTimeout(async () => {
            try {
                const res = await fetch(`/admin/estudiantes/buscar-dni?document=${encodeURIComponent(doc)}`);
                const data = await res.json();
                if (data.found) {
                    existingStudentDniName.value = data.full_name;
                    studentForm.errors.document = `El DNI ya pertenece a ${data.full_name}.`;
                }
            } catch {
                // ignore
            }
        }, 300);
    }
}

function handleGuardianFormDniInput() {
    guardianFoundName.value = '';
    const doc = studentForm.guardian_document.trim();
    if (doc.length >= 8) {
        if (doc === studentForm.document.trim()) {
            studentForm.errors.guardian_document = 'El apoderado no puede tener el mismo DNI que el estudiante.';
            return;
        }
        if (guardianDniTimer) clearTimeout(guardianDniTimer);
        guardianDniTimer = setTimeout(async () => {
            try {
                const res = await fetch(`/admin/estudiantes/buscar-dni?document=${encodeURIComponent(doc)}`);
                const data = await res.json();
                if (data.found) {
                    guardianFoundName.value = data.full_name;
                    studentForm.guardian_first_name = data.first_name || '';
                    studentForm.guardian_paternal_surname = data.paternal_surname || '';
                    studentForm.guardian_maternal_surname = data.maternal_surname || '';
                    if (data.phone) studentForm.guardian_phone = data.phone;
                    if (data.email) studentForm.guardian_email = data.email;
                    delete studentForm.errors.guardian_document;
                }
            } catch {
                // ignore
            }
        }, 300);
    }
}

function openCreateStudentModal() {
    if (!canEditStudent.value) {
        emit('notify', 'No cuenta con permisos para registrar nuevos alumnos.');
        return;
    }
    isEditingStudent.value = false;
    existingStudentDniName.value = '';
    guardianFoundName.value = '';
    studentForm.reset();
    studentForm.clearErrors();
    studentForm.has_guardian = false;
    showStudentModal.value = true;
}

function openEditStudentModal(student: StudentRecord) {
    if (!canEditStudent.value) {
        emit('notify', 'No cuenta con permisos para modificar datos de alumnos.');
        return;
    }
    isEditingStudent.value = true;
    existingStudentDniName.value = '';
    guardianFoundName.value = student.guardian ? student.guardian.full_name : '';
    studentForm.clearErrors();
    studentForm.id = student.id;
    studentForm.first_name = student.first_name;
    studentForm.paternal_surname = student.paternal_surname;
    studentForm.maternal_surname = student.maternal_surname || '';
    studentForm.document = student.document;
    studentForm.birth_date = student.birth_date || '';
    studentForm.phone = student.phone || '';
    studentForm.email = student.email || '';

    if (student.guardian) {
        studentForm.has_guardian = true;
        studentForm.guardian_first_name = student.guardian.first_name;
        studentForm.guardian_paternal_surname = student.guardian.paternal_surname;
        studentForm.guardian_maternal_surname = student.guardian.maternal_surname || '';
        studentForm.guardian_document = student.guardian.document;
        studentForm.guardian_phone = student.guardian.phone || '';
        studentForm.guardian_email = student.guardian.email || '';
    } else {
        studentForm.has_guardian = false;
        studentForm.guardian_first_name = '';
        studentForm.guardian_paternal_surname = '';
        studentForm.guardian_maternal_surname = '';
        studentForm.guardian_document = '';
        studentForm.guardian_phone = '';
        studentForm.guardian_email = '';
    }

    showStudentModal.value = true;
}

function saveStudent() {
    if (!canEditStudent.value) return;

    if (studentForm.has_guardian && studentForm.guardian_document && studentForm.guardian_document === studentForm.document) {
        studentForm.errors.guardian_document = 'El apoderado no puede tener el mismo DNI que el estudiante.';
        return;
    }

    if (!isEditingStudent.value && existingStudentDniName.value) {
        studentForm.errors.document = `El DNI ya pertenece a ${existingStudentDniName.value}.`;
        return;
    }

    if (isEditingStudent.value) {
        studentForm.put(`/admin/alumnos/${studentForm.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showStudentModal.value = false;
                emit('notify', `Datos del alumno ${studentForm.first_name} ${studentForm.paternal_surname} actualizados.`);
                if (selectedStudent.value && selectedStudent.value.id === studentForm.id) {
                    const updated = props.students.find((s) => s.id === studentForm.id);
                    if (updated) selectedStudent.value = updated;
                }
            },
        });
    } else {
        studentForm.post('/admin/alumnos', {
            preserveScroll: true,
            onSuccess: () => {
                showStudentModal.value = false;
                emit('notify', `Alumno ${studentForm.first_name} ${studentForm.paternal_surname} registrado con éxito.`);
            },
        });
    }
}

// ========================================================
// ASISTENTE DE MATRÍCULA INTELIGENTE
// ========================================================
const selectedEnrollmentBranchId = ref<number>(1);
const selectedFormationType = ref<'carrera' | 'taller'>('carrera');
const selectedCareerId = ref<number | null>(null);
const selectedCourseId = ref<number | null>(null);

const isSearchingDni = ref(false);
const dniLookupStatus = ref<'idle' | 'found' | 'not_found'>('idle');
const studentPreviousCourseIds = ref<number[]>([]);
let dniLookupTimer: ReturnType<typeof setTimeout> | null = null;

const enrollmentForm = useForm({
    scheduled_course_id: 1,
    first_name: '',
    paternal_surname: '',
    maternal_surname: '',
    student_document: '',
    enrollment_date: getPeruToday(),
    status: 'activo' as EnrollmentStatus,
    notes: '',
});

// Cursos de la carrera seleccionada
const coursesInSelectedCareer = computed(() => {
    if (!selectedCareerId.value) return [];
    return props.courses.filter(
        (c) => !c.is_workshop && c.career_id === selectedCareerId.value,
    );
});

// Talleres libres disponibles
const availableWorkshops = computed(() => {
    return props.courses.filter((c) => c.is_workshop);
});

// Sugerencia del siguiente curso en progresión curricular
const suggestedCourseInfo = computed(() => {
    if (selectedFormationType.value === 'taller') {
        const workshop = availableWorkshops.value.find((w) => w.id === selectedCourseId.value);
        return {
            course: workshop || availableWorkshops.value[0] || null,
            reason: 'Taller libre independiente seleccionado.',
            isSuggestedNext: false,
        };
    }

    const careerCourses = coursesInSelectedCareer.value;
    if (careerCourses.length === 0) {
        return { course: null, reason: 'No hay cursos asignados a esta carrera.', isSuggestedNext: false };
    }

    const takenIds = new Set(studentPreviousCourseIds.value);
    const nextCourse = careerCourses.find((c) => !takenIds.has(c.id));

    if (nextCourse) {
        return {
            course: nextCourse,
            reason: takenIds.size > 0
                ? `Curso sugerido automáticamente según progresión académica del alumno (${takenIds.size} cursados previamente).`
                : 'Primer curso curricular sugerido de la carrera.',
            isSuggestedNext: true,
        };
    }

    return {
        course: careerCourses[0],
        reason: 'El alumno ya cuenta con todos los cursos de esta carrera matriculados o completados.',
        isSuggestedNext: false,
    };
});

// Horarios aperturados disponibles para el curso y la sede seleccionada
const availableSchedulesForSelection = computed(() => {
    if (!selectedCourseId.value) return [];
    return props.scheduledCourses.filter((sc) => {
        if (sc.course_id !== selectedCourseId.value) return false;
        if (sc.branch_id !== selectedEnrollmentBranchId.value) return false;
        if (sc.status === 'finalizado') return false;
        return true;
    });
});

function recalculateCourseSuggestion() {
    if (selectedFormationType.value === 'carrera') {
        const suggestion = suggestedCourseInfo.value.course;
        if (suggestion) {
            selectedCourseId.value = suggestion.id;
        } else if (coursesInSelectedCareer.value.length > 0) {
            selectedCourseId.value = coursesInSelectedCareer.value[0].id;
        }
    } else if (selectedFormationType.value === 'taller') {
        if (availableWorkshops.value.length > 0) {
            if (
                !selectedCourseId.value ||
                !availableWorkshops.value.some((w) => w.id === selectedCourseId.value)
            ) {
                selectedCourseId.value = availableWorkshops.value[0].id;
            }
        }
    }
    updateFirstAvailableSchedule();
}

function updateFirstAvailableSchedule() {
    const schedules = availableSchedulesForSelection.value;
    if (schedules.length > 0) {
        enrollmentForm.scheduled_course_id = schedules[0].id;
    } else {
        enrollmentForm.scheduled_course_id = 0;
    }
}

function onDniInputChanged() {
    const doc = enrollmentForm.student_document.trim();
    if (doc.length >= 8) {
        if (dniLookupTimer) clearTimeout(dniLookupTimer);
        dniLookupTimer = setTimeout(() => {
            searchStudentDni();
        }, 300);
    } else {
        dniLookupStatus.value = 'idle';
        studentPreviousCourseIds.value = [];
    }
}

async function searchStudentDni() {
    const doc = enrollmentForm.student_document.trim();
    if (doc.length < 6) return;
    isSearchingDni.value = true;
    try {
        const res = await fetch(`/admin/estudiantes/buscar-dni?document=${encodeURIComponent(doc)}`);
        const data = await res.json();
        if (data.found) {
            dniLookupStatus.value = 'found';
            enrollmentForm.first_name = data.first_name || '';
            enrollmentForm.paternal_surname = data.paternal_surname || '';
            enrollmentForm.maternal_surname = data.maternal_surname || '';
            studentPreviousCourseIds.value = data.enrolled_course_ids || [];
            recalculateCourseSuggestion();
        } else {
            dniLookupStatus.value = 'not_found';
            studentPreviousCourseIds.value = [];
            recalculateCourseSuggestion();
        }
    } catch {
        dniLookupStatus.value = 'idle';
    } finally {
        isSearchingDni.value = false;
    }
}

function openEnrollmentForStudent(student: StudentRecord) {
    if (!canEnroll.value) {
        emit('notify', 'No cuenta con permisos para matricular alumnos en esta sede.');
        return;
    }
    selectedStudent.value = student;
    currentView.value = 'enrollment_wizard';

    enrollmentForm.reset();
    enrollmentForm.clearErrors();
    enrollmentForm.enrollment_date = getPeruToday();
    enrollmentForm.status = 'activo';
    enrollmentForm.notes = '';

    enrollmentForm.student_document = student.document;
    enrollmentForm.first_name = student.first_name;
    enrollmentForm.paternal_surname = student.paternal_surname;
    enrollmentForm.maternal_surname = student.maternal_surname || '';

    studentPreviousCourseIds.value = student.enrollments
        .map((e) => e.course_id)
        .filter((id): id is number => typeof id === 'number');

    dniLookupStatus.value = 'found';

    selectedEnrollmentBranchId.value =
        props.selectedBranchId !== 'all'
            ? props.selectedBranchId
            : props.branches[0]?.id || 1;

    selectedFormationType.value = 'carrera';
    selectedCareerId.value = props.careers[0]?.id || null;
    recalculateCourseSuggestion();
}

function openGeneralEnrollmentView() {
    if (!canEnroll.value) {
        emit('notify', 'No cuenta con permisos para registrar matrículas en esta sede.');
        return;
    }
    selectedStudent.value = null;
    currentView.value = 'enrollment_wizard';

    enrollmentForm.reset();
    enrollmentForm.clearErrors();
    enrollmentForm.enrollment_date = getPeruToday();
    enrollmentForm.status = 'activo';
    enrollmentForm.notes = '';

    dniLookupStatus.value = 'idle';
    studentPreviousCourseIds.value = [];

    selectedEnrollmentBranchId.value =
        props.selectedBranchId !== 'all'
            ? props.selectedBranchId
            : props.branches[0]?.id || 1;

    selectedFormationType.value = 'carrera';
    selectedCareerId.value = props.careers[0]?.id || null;
    recalculateCourseSuggestion();
}

function closeEnrollmentWizard() {
    if (selectedStudent.value) {
        currentView.value = 'student_detail';
    } else {
        currentView.value = 'students_list';
    }
    enrollmentForm.clearErrors();
}

function saveNewEnrollment() {
    if (!canEnroll.value) return;
    if (!enrollmentForm.scheduled_course_id) {
        emit('notify', 'Por favor seleccione un horario aperturado disponible en la sede.');
        return;
    }
    enrollmentForm.post('/admin/matriculas', {
        preserveScroll: true,
        onSuccess: () => {
            const fullName = `${enrollmentForm.first_name} ${enrollmentForm.paternal_surname}`.trim();
            emit('notify', `Matrícula registrada con éxito para ${fullName}.`);
            if (selectedStudent.value) {
                const updated = props.students.find((s) => s.id === selectedStudent.value?.id);
                if (updated) selectedStudent.value = updated;
                currentView.value = 'student_detail';
            } else {
                currentView.value = 'students_list';
            }
        },
    });
}

watch([selectedCourseId, selectedEnrollmentBranchId], () => {
    updateFirstAvailableSchedule();
});

watch(selectedCareerId, () => {
    recalculateCourseSuggestion();
});

watch(selectedFormationType, () => {
    recalculateCourseSuggestion();
});

// ========================================================
// MODAL: CAMBIAR ESTADO DE MATRÍCULA
// ========================================================
const showChangeStatusModal = ref(false);
const selectedEnrollment = ref<EnrollmentRecord | null>(null);
const newStatusToApply = ref<EnrollmentStatus>('activo');
const statusChangeNotes = ref('');

function openChangeStatusModal(record: EnrollmentRecord | StudentEnrollmentItem, studentName?: string) {
    if (!canEnroll.value) {
        emit('notify', 'No cuenta con permisos para modificar matrículas.');
        return;
    }
    selectedEnrollment.value = {
        id: record.id,
        scheduled_course_id: record.scheduled_course_id,
        student_full_name: studentName || ('student_full_name' in record ? record.student_full_name : selectedStudent.value?.full_name || 'Estudiante'),
        student_document: 'student_document' in record ? record.student_document : selectedStudent.value?.document || '',
        course_name: record.course_name,
        module_name: 'module_name' in record ? record.module_name : null,
        career_name: record.career_name || null,
        branch_name: record.branch_name,
        classroom_name: record.classroom_name,
        enrollment_date: record.enrollment_date,
        status: record.status,
        notes: record.notes || null,
    };
    newStatusToApply.value = record.status;
    statusChangeNotes.value = record.notes || '';
    showChangeStatusModal.value = true;
}

function applyStatusChange() {
    if (!selectedEnrollment.value) return;

    router.put(
        `/admin/matriculas/${selectedEnrollment.value.id}/status`,
        {
            status: newStatusToApply.value,
            notes: statusChangeNotes.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showChangeStatusModal.value = false;
                emit('notify', `Estado de ${selectedEnrollment.value?.student_full_name} actualizado.`);
                if (selectedStudent.value) {
                    const enr = selectedStudent.value.enrollments.find((e) => e.id === selectedEnrollment.value?.id);
                    if (enr) {
                        enr.status = newStatusToApply.value;
                        enr.notes = statusChangeNotes.value;
                    }
                }
            },
        },
    );
}

function withdrawEnrollment(enr: { id: number; course_name: string }, studentName?: string) {
    if (!canEnroll.value) return;
    const name = studentName || selectedStudent.value?.full_name || 'el estudiante';
    if (!confirm(`¿Está seguro de retirar la matrícula de "${name}" en ${enr.course_name}?`)) return;

    router.delete(`/admin/matriculas/${enr.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('notify', `Matrícula retirada del sistema.`);
            if (selectedStudent.value) {
                selectedStudent.value.enrollments = selectedStudent.value.enrollments.filter((e) => e.id !== enr.id);
            }
        },
    });
}
</script>

<template>
    <div class="space-y-6">
        <!-- ======================================================== -->
        <!-- 1. VISTA: ASISTENTE DE MATRÍCULA INTELIGENTE             -->
        <!-- ======================================================== -->
        <div v-if="currentView === 'enrollment_wizard'" class="space-y-6">
            <!-- Barra Superior y Cabecera -->
            <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <button
                            type="button"
                            @click="closeEnrollmentWizard"
                            class="mb-3 inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:border-[#2e8b57] hover:bg-white hover:text-[#2e8b57]"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            <span v-if="selectedStudent">Volver a Ficha de {{ selectedStudent.first_name }}</span>
                            <span v-else>Volver a Directorio de Alumnos</span>
                        </button>
                        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                            Registro de Matrícula
                            <span v-if="selectedStudent" class="text-[#2e8b57]"> &bull; {{ selectedStudent.full_name }}</span>
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Seleccione la sede y la carrera/taller; el sistema sugerirá automáticamente el curso correspondiente para luego asignar el horario disponible.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="closeEnrollmentWizard"
                            class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            @click="saveNewEnrollment"
                            :disabled="enrollmentForm.processing || !enrollmentForm.scheduled_course_id || !enrollmentForm.student_document"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-[#257347] disabled:opacity-50"
                        >
                            <svg
                                v-if="enrollmentForm.processing"
                                class="h-4 w-4 animate-spin text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Confirmar Matrícula</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Grid de Configuración de la Matrícula -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Columna Izquierda: Datos del Estudiante y Selección de Carrera / Sede (2 columnas) -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- PASO 1: Datos del Estudiante -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
                        <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#2e8b57] text-xs font-bold text-white">1</span>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                                    Estudiante &amp; Documento de Identidad
                                </h3>
                            </div>
                            <span v-if="selectedStudent" class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Alumno Preseleccionado
                            </span>
                            <span v-else-if="dniLookupStatus === 'found'" class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Estudiante Registrado
                            </span>
                            <span v-else-if="dniLookupStatus === 'not_found'" class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-[11px] font-bold text-blue-800">
                                Nuevo Estudiante
                            </span>
                        </div>

                        <!-- Si el alumno ya fue preseleccionado desde la lista, mostrar ficha fija -->
                        <div v-if="selectedStudent" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-lg bg-emerald-50/60 border border-emerald-200 p-4">
                            <div>
                                <p class="text-sm font-black text-slate-900">{{ selectedStudent.full_name }}</p>
                                <p class="font-mono text-xs font-semibold text-[#2e8b57]">DNI: {{ selectedStudent.document }}</p>
                                <p v-if="selectedStudent.guardian" class="mt-1 text-xs text-slate-600">
                                    <span class="font-bold">Apoderado:</span> {{ selectedStudent.guardian.full_name }} ({{ selectedStudent.guardian.document }})
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="rounded-md bg-white px-2.5 py-1 text-xs font-bold text-slate-700 border border-slate-200 shadow-2xs">
                                    {{ selectedStudent.enrollments.length }} matrículas previas
                                </span>
                            </div>
                        </div>

                        <!-- Si se ingresó en modo global, permitir buscar por DNI -->
                        <div v-else class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase">
                                    DNI del Estudiante (8 dígitos) *
                                </label>
                                <div class="relative mt-1.5">
                                    <input
                                        v-model="enrollmentForm.student_document"
                                        type="text"
                                        maxlength="8"
                                        @input="onDniInputChanged"
                                        placeholder="Ej. 74819203"
                                        class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-10 pl-3.5 text-sm font-mono font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                    />
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                        <svg v-if="isSearchingDni" class="h-4 w-4 animate-spin text-[#2e8b57]" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <p v-if="enrollmentForm.errors.student_document" class="mt-1 text-xs font-bold text-red-600">
                                    {{ enrollmentForm.errors.student_document }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Nombres *</label>
                                    <input
                                        v-model="enrollmentForm.first_name"
                                        type="text"
                                        placeholder="Nombres"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Apellido Paterno *</label>
                                    <input
                                        v-model="enrollmentForm.paternal_surname"
                                        type="text"
                                        placeholder="Paterno"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Apellido Materno</label>
                                    <input
                                        v-model="enrollmentForm.maternal_surname"
                                        type="text"
                                        placeholder="Materno"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 2: Selección de Sede, Formación y Curso Sugerido -->
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
                        <div class="mb-4 flex items-center gap-2 border-b border-slate-100 pb-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#2e8b57] text-xs font-bold text-white">2</span>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                                Sede, Carrera / Taller &amp; Curso Sugerido
                            </h3>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Sede de Ejecución -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700">Sede de Estudios *</label>
                                <select
                                    v-model="selectedEnrollmentBranchId"
                                    class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                >
                                    <option v-for="b in props.branches" :key="b.id" :value="b.id">
                                        {{ b.name }} ({{ b.code }})
                                    </option>
                                </select>
                            </div>

                            <!-- Modalidad: Carrera vs Taller -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700">Tipo de Formación *</label>
                                <div class="mt-1.5 flex gap-2">
                                    <button
                                        type="button"
                                        @click="selectedFormationType = 'carrera'"
                                        :class="[
                                            selectedFormationType === 'carrera'
                                                ? 'bg-[#2e8b57] text-white font-bold shadow-2xs'
                                                : 'bg-slate-100 text-slate-600 font-semibold hover:bg-slate-200',
                                            'flex-1 rounded-lg py-2 text-xs transition cursor-pointer',
                                        ]"
                                    >
                                        Carrera Técnica
                                    </button>
                                    <button
                                        type="button"
                                        @click="selectedFormationType = 'taller'"
                                        :class="[
                                            selectedFormationType === 'taller'
                                                ? 'bg-[#2e8b57] text-white font-bold shadow-2xs'
                                                : 'bg-slate-100 text-slate-600 font-semibold hover:bg-slate-200',
                                            'flex-1 rounded-lg py-2 text-xs transition cursor-pointer',
                                        ]"
                                    >
                                        Taller Libre
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Selector de Carrera (si aplica) -->
                        <div v-if="selectedFormationType === 'carrera'" class="mt-4">
                            <label class="block text-xs font-bold text-slate-700">Seleccionar Carrera *</label>
                            <select
                                v-model="selectedCareerId"
                                class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            >
                                <option v-for="car in props.careers" :key="car.id" :value="car.id">
                                    {{ car.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Tarjeta de Curso Sugerido -->
                        <div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-emerald-800 uppercase tracking-wide">
                                    Curso a Matricular
                                </span>
                                <span v-if="suggestedCourseInfo.isSuggestedNext" class="rounded-full bg-emerald-200/60 px-2 py-0.5 text-[10px] font-bold text-emerald-900">
                                    Sugerencia de Progresión
                                </span>
                            </div>

                            <p class="mt-1 text-sm font-black text-slate-900">
                                {{ suggestedCourseInfo.course?.name || 'Seleccione una carrera' }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-600">
                                {{ suggestedCourseInfo.reason }}
                            </p>

                            <!-- Selector manual alternativo de curso dentro de la carrera -->
                            <div v-if="selectedFormationType === 'carrera' && coursesInSelectedCareer.length > 0" class="mt-3 border-t border-emerald-200/60 pt-3">
                                <label class="block text-[11px] font-bold text-slate-600">
                                    ¿Desea cambiar el curso de la carrera manualmente?
                                </label>
                                <select
                                    v-model="selectedCourseId"
                                    class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 focus:border-[#2e8b57] focus:outline-none"
                                >
                                    <option v-for="c in coursesInSelectedCareer" :key="c.id" :value="c.id">
                                        {{ c.name }} ({{ c.module_name || 'Sin módulo' }})
                                    </option>
                                </select>
                            </div>

                            <!-- Selector si es taller libre -->
                            <div v-else-if="selectedFormationType === 'taller' && availableWorkshops.length > 0" class="mt-3 border-t border-emerald-200/60 pt-3">
                                <label class="block text-[11px] font-bold text-slate-600">Seleccionar Taller Libre:</label>
                                <select
                                    v-model="selectedCourseId"
                                    class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 focus:border-[#2e8b57] focus:outline-none"
                                >
                                    <option v-for="w in availableWorkshops" :key="w.id" :value="w.id">
                                        {{ w.name }} (Duración: {{ w.duration_in_weeks }} semanas)
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: PASO 3 - Selección de Horarios Aperturados -->
                <div class="space-y-6">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
                        <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#2e8b57] text-xs font-bold text-white">3</span>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                                    Horarios Disponibles
                                </h3>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600">
                                {{ availableSchedulesForSelection.length }} opciones
                            </span>
                        </div>

                        <!-- Lista de horarios seleccionables -->
                        <div v-if="availableSchedulesForSelection.length === 0" class="rounded-lg border border-dashed border-amber-300 bg-amber-50/50 p-6 text-center">
                            <svg class="mx-auto h-8 w-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="mt-2 text-xs font-bold text-amber-900">
                                No hay aperturas activas para este curso en la sede seleccionada.
                            </p>
                            <p class="mt-1 text-[11px] text-amber-700">
                                Cambie de sede o diríjase a "Apertura de Cursos" para programar un nuevo grupo.
                            </p>
                        </div>

                        <div v-else class="space-y-2.5">
                            <div
                                v-for="sched in availableSchedulesForSelection"
                                :key="sched.id"
                                @click="enrollmentForm.scheduled_course_id = sched.id"
                                :class="[
                                    enrollmentForm.scheduled_course_id === sched.id
                                        ? 'border-[#2e8b57] bg-emerald-50/40 ring-2 ring-[#2e8b57]/20'
                                        : 'border-slate-200 bg-slate-50/60 hover:border-slate-300 hover:bg-slate-50',
                                    'cursor-pointer rounded-xl border p-3.5 transition',
                                ]"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-slate-900">
                                        {{ sched.day_of_week }}
                                    </span>
                                    <span
                                        :class="[
                                            enrollmentForm.scheduled_course_id === sched.id
                                                ? 'bg-[#2e8b57] text-white'
                                                : 'bg-slate-200 text-slate-600',
                                            'rounded-full px-2 py-0.5 text-[10px] font-bold',
                                        ]"
                                    >
                                        {{ sched.time_range }}
                                    </span>
                                </div>

                                <div class="mt-2 space-y-1 text-xs text-slate-600">
                                    <p class="flex items-center gap-1.5">
                                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        <span>Aula: <strong class="text-slate-800">{{ sched.classroom_name }}</strong></span>
                                    </p>
                                    <p class="flex items-center gap-1.5">
                                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span>Docente: <strong class="text-slate-800">{{ sched.teacher_name }}</strong></span>
                                    </p>
                                    <p class="flex items-center justify-between text-[11px] pt-1 border-t border-slate-200/50">
                                        <span class="text-slate-500">Vacantes: {{ sched.enrolled_count }} / {{ sched.capacity }}</span>
                                        <span class="font-bold text-emerald-700">Inicio: {{ formatPeruDate(sched.start_date) }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones / Tipo de Matrícula -->
                        <div class="mt-5 border-t border-slate-100 pt-4 space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700">Estado Inicial</label>
                                <select
                                    v-model="enrollmentForm.status"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-800 focus:border-[#2e8b57] focus:outline-none"
                                >
                                    <option value="activo">Activo (Cursando Regular)</option>
                                    <option value="convalidado">Convalidado (Aprobado)</option>
                                    <option value="preinscrito">Preinscrito (Reserva)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700">Notas / Observaciones</label>
                                <textarea
                                    v-model="enrollmentForm.notes"
                                    rows="2"
                                    placeholder="Ej. Convalidación previa aprobada por dirección académica..."
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 p-2 text-xs text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- 2. VISTA: FICHA DETALLADA DEL ALUMNO (HISTORIAL MATRÍCULAS) -->
        <!-- ======================================================== -->
        <div v-else-if="currentView === 'student_detail' && selectedStudent" class="space-y-6">
            <!-- Barra Superior de Navegación -->
            <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <button
                            type="button"
                            @click="closeStudentDetail"
                            class="mb-3 inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:border-[#2e8b57] hover:bg-white hover:text-[#2e8b57]"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            <span>Volver al Directorio de Alumnos</span>
                        </button>
                        <h2 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900">
                            {{ selectedStudent.full_name }}
                        </h2>
                        <p class="mt-0.5 text-xs font-mono font-bold text-[#2e8b57]">
                            DNI: {{ selectedStudent.document }} &bull; Ficha Académica &amp; Matrículas
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="canEditStudent"
                            type="button"
                            @click="openEditStudentModal(selectedStudent)"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs"
                        >
                            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>Editar Datos (Alumno / Apoderado)</span>
                        </button>
                        <button
                            v-if="canEnroll"
                            type="button"
                            @click="openEnrollmentForStudent(selectedStudent)"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-[#2e8b57] px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-[#257347]"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>+ Matricular en Curso</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ficha Resumen: Alumno y Apoderado -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Tarjeta Estudiante -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Información del Estudiante</h3>
                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-[#2e8b57]">Alumno Regular</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-slate-400 block">DNI:</span>
                            <span class="font-mono font-bold text-slate-800">{{ selectedStudent.document }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Fecha de Nacimiento:</span>
                            <span class="font-semibold text-slate-800">{{ formatPeruDate(selectedStudent.birth_date || '') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Teléfono / Celular:</span>
                            <span class="font-semibold text-slate-800">{{ selectedStudent.phone || 'No registrado' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Correo Electrónico:</span>
                            <span class="font-semibold text-slate-800">{{ selectedStudent.email || 'No registrado' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta Apoderado -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Información del Apoderado</h3>
                        <span v-if="selectedStudent.guardian" class="rounded bg-blue-100 px-2 py-0.5 text-[11px] font-bold text-blue-800">
                            Apoderado Asignado
                        </span>
                        <span v-else class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                            Sin apoderado
                        </span>
                    </div>

                    <div v-if="selectedStudent.guardian" class="grid grid-cols-2 gap-3 text-xs">
                        <div class="col-span-2">
                            <span class="text-slate-400 block">Nombres y Apellidos:</span>
                            <span class="font-bold text-slate-900 text-sm">{{ selectedStudent.guardian.full_name }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">DNI Apoderado:</span>
                            <span class="font-mono font-bold text-slate-800">{{ selectedStudent.guardian.document }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block">Teléfono:</span>
                            <span class="font-semibold text-slate-800">{{ selectedStudent.guardian.phone || 'No registrado' }}</span>
                        </div>
                    </div>

                    <div v-else class="py-2 text-center text-xs text-slate-500">
                        <p>No se ha registrado un apoderado para este estudiante.</p>
                        <button
                            v-if="canEditStudent"
                            type="button"
                            @click="openEditStudentModal(selectedStudent)"
                            class="mt-2 text-xs font-bold text-[#2e8b57] hover:underline cursor-pointer"
                        >
                            + Asignar apoderado ahora
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Cursos y Matrículas del Alumno -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                            Historial Académico &amp; Cursos Matriculados
                        </h3>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600">
                            {{ selectedStudent.enrollments.length }} matrículas
                        </span>
                    </div>
                    <button
                        v-if="canEnroll"
                        type="button"
                        @click="openEnrollmentForStudent(selectedStudent)"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-[#2e8b57] px-3.5 py-1.5 text-xs font-bold text-white shadow-2xs hover:bg-[#257347]"
                    >
                        <span>+ Nueva Matrícula</span>
                    </button>
                </div>

                <div v-if="selectedStudent.enrollments.length === 0" class="rounded-lg border border-dashed border-slate-200 p-8 text-center">
                    <p class="text-sm font-bold text-slate-700">El alumno no cuenta con matrículas registradas aún.</p>
                    <p class="mt-1 text-xs text-slate-500">Pulse en "+ Nueva Matrícula" para inscribirlo en un curso disponible.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 font-bold text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th class="p-3">Curso &amp; Carrera / Taller</th>
                                <th class="p-3">Sede &amp; Ambiente</th>
                                <th class="p-3">Días &amp; Horario</th>
                                <th class="p-3">Fecha Matrícula</th>
                                <th class="p-3 text-center">Estado</th>
                                <th class="p-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-for="enr in selectedStudent.enrollments" :key="enr.id" class="hover:bg-slate-50/80 transition">
                                <td class="p-3">
                                    <p class="font-bold text-slate-900 text-sm">{{ enr.course_name }}</p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ enr.career_name ? `Carrera: ${enr.career_name}` : 'Taller Libre' }}
                                    </p>
                                </td>
                                <td class="p-3">
                                    <p class="font-semibold text-slate-800">{{ enr.branch_name }}</p>
                                    <p class="text-[11px] text-slate-500">{{ enr.classroom_name }}</p>
                                </td>
                                <td class="p-3">
                                    <p class="font-bold text-slate-800">{{ enr.day_of_week }}</p>
                                    <p class="font-mono text-[11px] text-slate-500">{{ enr.time_range }}</p>
                                </td>
                                <td class="p-3 font-mono text-slate-600">
                                    {{ formatPeruDate(enr.enrollment_date) }}
                                </td>
                                <td class="p-3 text-center">
                                    <button
                                        type="button"
                                        @click="openChangeStatusModal(enr, selectedStudent.full_name)"
                                        :class="[
                                            enr.status === 'activo' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : '',
                                            enr.status === 'completado' ? 'bg-blue-100 text-blue-800 border-blue-200' : '',
                                            enr.status === 'convalidado' ? 'bg-purple-100 text-purple-800 border-purple-200' : '',
                                            enr.status === 'preinscrito' ? 'bg-amber-100 text-amber-800 border-amber-200' : '',
                                            enr.status === 'retirado' ? 'bg-rose-100 text-rose-800 border-rose-200' : '',
                                            enr.status === 'anulado' ? 'bg-slate-100 text-slate-600 border-slate-300' : '',
                                            'inline-flex cursor-pointer items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-bold uppercase transition hover:opacity-80',
                                        ]"
                                    >
                                        <span>{{ enr.status }}</span>
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </td>
                                <td class="p-3 text-right">
                                    <button
                                        v-if="canEnroll"
                                        type="button"
                                        @click="withdrawEnrollment(enr, selectedStudent.full_name)"
                                        title="Retirar matrícula"
                                        class="cursor-pointer rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- 3. VISTA PRINCIPAL: DIRECTORIO DE ALUMNOS Y APODERADOS   -->
        <!-- ======================================================== -->
        <div v-else class="space-y-6">
            <!-- Barra de Búsqueda y Botones de Acción -->
            <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-xs md:flex-row md:items-center md:justify-between">
                <!-- Buscador de Alumnos y Apoderados -->
                <div class="relative flex-1">
                    <svg
                        class="pointer-events-none absolute inset-y-0 left-0 my-auto ml-3.5 h-4 w-4 text-slate-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        v-model="searchStudentQuery"
                        type="text"
                        placeholder="Buscar por DNI, nombres de alumno o apoderado..."
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-11 text-sm text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="canEditStudent"
                        type="button"
                        @click="openCreateStudentModal"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50"
                    >
                        <svg class="h-4 w-4 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        <span>+ Registrar Alumno</span>
                    </button>

                    <button
                        v-if="canEnroll"
                        type="button"
                        @click="openGeneralEnrollmentView"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347]"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Matrícula Rápida</span>
                    </button>
                </div>
            </div>

            <!-- Filtros Rápidos por Estado Académico y Sede -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="mr-1 text-xs font-bold tracking-wider text-slate-400 uppercase">Filtrar Alumnos:</span>
                    <button
                        type="button"
                        @click="studentFilterStatus = 'all'"
                        :class="[
                            studentFilterStatus === 'all'
                                ? 'bg-slate-800 font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Todos ({{ (props.students || []).length }})
                    </button>
                    <button
                        type="button"
                        @click="studentFilterStatus = 'with_active'"
                        :class="[
                            studentFilterStatus === 'with_active'
                                ? 'bg-[#2e8b57] font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Con Matrícula Activa
                    </button>
                    <button
                        type="button"
                        @click="studentFilterStatus = 'without_active'"
                        :class="[
                            studentFilterStatus === 'without_active'
                                ? 'bg-amber-600 font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Sin Matrícula Activa
                    </button>
                </div>

                <!-- Filtro por Sede -->
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold tracking-wider text-slate-400 uppercase">Sede:</span>
                    <select
                        v-model="selectedBranchFilter"
                        class="cursor-pointer rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 shadow-2xs focus:border-[#2e8b57] focus:outline-none"
                    >
                        <option value="all">Todas las Sedes</option>
                        <option v-for="b in props.branches" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- TABLA DE ALUMNOS Y APODERADOS -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                <div v-if="filteredStudents.length === 0" class="py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="mt-3 text-sm font-bold text-slate-700">No se encontraron alumnos registrados</p>
                    <p class="mt-1 text-xs text-slate-500">Pruebe modificando los términos de búsqueda o registre un nuevo alumno.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 font-bold text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th class="p-3.5">Estudiante (Alumno)</th>
                                <th class="p-3.5">Apoderado</th>
                                <th class="p-3.5">Cursos / Matrículas</th>
                                <th class="p-3.5 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-for="student in filteredStudents" :key="student.id" class="hover:bg-slate-50/80 transition">
                                <!-- Estudiante -->
                                <td class="p-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-black text-[#2e8b57]">
                                            {{ (student.first_name || 'A').charAt(0) }}{{ (student.paternal_surname || 'L').charAt(0) }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-900 text-sm hover:text-[#2e8b57] cursor-pointer" @click="viewStudentDetail(student)">
                                                {{ student.full_name || `${student.first_name || ''} ${student.paternal_surname || ''}`.trim() || 'Estudiante' }}
                                            </p>
                                            <div class="flex flex-wrap items-center gap-2 mt-0.5">
                                                <span class="font-mono text-[11px] font-bold text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
                                                    DNI: {{ student.document || '---' }}
                                                </span>
                                                <span v-if="student.phone" class="text-[11px] text-slate-500">
                                                    Tel: {{ student.phone }}
                                                </span>
                                                <span v-if="student.email" class="text-[11px] text-slate-500 truncate max-w-[150px]">
                                                    {{ student.email }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Apoderado -->
                                <td class="p-3.5">
                                    <div v-if="student.guardian">
                                        <p class="font-bold text-slate-800 text-xs">
                                            {{ student.guardian.full_name || `${student.guardian.first_name || ''} ${student.guardian.paternal_surname || ''}`.trim() || 'Apoderado' }}
                                        </p>
                                        <p class="font-mono text-[11px] text-slate-500">
                                            DNI: {{ student.guardian.document || '---' }}
                                            <span v-if="student.guardian.phone"> &bull; Tel: {{ student.guardian.phone }}</span>
                                        </p>
                                    </div>
                                    <span v-else class="inline-flex rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                                        Sin apoderado
                                    </span>
                                </td>

                                <!-- Matrículas / Cursos -->
                                <td class="p-3.5">
                                    <div v-if="student.enrollments && student.enrollments.length > 0" class="space-y-1">
                                        <div class="flex flex-wrap gap-1">
                                            <span
                                                v-for="enr in student.enrollments.slice(0, 2)"
                                                :key="enr.id"
                                                :class="[
                                                    enr.status === 'activo' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200',
                                                    'inline-flex items-center rounded border px-2 py-0.5 text-[10px] font-semibold truncate max-w-[200px]',
                                                ]"
                                            >
                                                {{ enr.course_name }} ({{ enr.status }})
                                            </span>
                                            <span v-if="student.enrollments.length > 2" class="text-[10px] font-bold text-slate-400 self-center">
                                                +{{ student.enrollments.length - 2 }} más
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-400 font-medium">
                                            {{ student.enrollments.length }} matrícula{{ student.enrollments.length === 1 ? '' : 's' }} registrada{{ student.enrollments.length === 1 ? '' : 's' }}
                                        </p>
                                    </div>
                                    <span v-else class="text-[11px] text-slate-400 italic">
                                        Sin matrículas aún
                                    </span>
                                </td>

                                <!-- Acciones -->
                                <td class="p-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="viewStudentDetail(student)"
                                            class="cursor-pointer rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-bold text-[#2e8b57] hover:bg-emerald-100 transition"
                                        >
                                            Ver Matrículas ({{ student.enrollments.length }})
                                        </button>
                                        <button
                                            v-if="canEnroll"
                                            type="button"
                                            @click="openEnrollmentForStudent(student)"
                                            title="Matricular a este alumno"
                                            class="cursor-pointer rounded-lg border border-[#2e8b57] bg-white px-2 py-1.5 text-xs font-bold text-[#2e8b57] hover:bg-emerald-50 transition"
                                        >
                                            + Matricular
                                        </button>
                                        <button
                                            v-if="canEditStudent"
                                            type="button"
                                            @click="openEditStudentModal(student)"
                                            title="Editar datos de alumno y apoderado"
                                            class="cursor-pointer rounded-lg border border-slate-200 p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL: CREAR / EDITAR ALUMNO Y APODERADO                 -->
        <!-- ======================================================== -->
        <div
            v-if="showStudentModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs overflow-y-auto"
        >
            <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl space-y-5 my-8">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-900">
                        {{ isEditingStudent ? 'Editar Datos de Alumno & Apoderado' : 'Registrar Nuevo Alumno' }}
                    </h3>
                    <button
                        type="button"
                        @click="showStudentModal = false"
                        class="cursor-pointer rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    >
                        &times;
                    </button>
                </div>

                <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
                    <!-- SECCIÓN 1: DATOS DEL ESTUDIANTE -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                            1. Datos del Estudiante
                        </h4>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-bold text-slate-700">DNI (8 dígitos) *</label>
                                <input
                                    v-model="studentForm.document"
                                    type="text"
                                    maxlength="8"
                                    @input="handleStudentFormDniInput"
                                    placeholder="Ej. 74819203"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-mono font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                />
                                <p v-if="existingStudentDniName && !isEditingStudent" class="mt-1 text-xs text-amber-700 font-bold flex items-center gap-1">
                                    <span>⚠️ DNI ya registrado: {{ existingStudentDniName }}</span>
                                </p>
                                <p v-if="studentForm.errors.document" class="mt-1 text-xs text-red-600 font-bold">
                                    {{ studentForm.errors.document }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700">Fecha de Nacimiento</label>
                                <input
                                    v-model="studentForm.birth_date"
                                    type="date"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700">Nombres *</label>
                                <input
                                    v-model="studentForm.first_name"
                                    type="text"
                                    placeholder="Nombres completos"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                />
                                <p v-if="studentForm.errors.first_name" class="mt-1 text-xs text-red-600 font-bold">
                                    {{ studentForm.errors.first_name }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700">Apellido Paterno *</label>
                                <input
                                    v-model="studentForm.paternal_surname"
                                    type="text"
                                    placeholder="Primer apellido"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                />
                                <p v-if="studentForm.errors.paternal_surname" class="mt-1 text-xs text-red-600 font-bold">
                                    {{ studentForm.errors.paternal_surname }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700">Apellido Materno</label>
                                <input
                                    v-model="studentForm.maternal_surname"
                                    type="text"
                                    placeholder="Segundo apellido"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700">Teléfono / Celular</label>
                                <input
                                    v-model="studentForm.phone"
                                    type="text"
                                    placeholder="Ej. 987654321"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700">Correo Electrónico</label>
                                <input
                                    v-model="studentForm.email"
                                    type="email"
                                    placeholder="alumno@ejemplo.com"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: DATOS DEL APODERADO -->
                    <div class="border-t border-slate-100 pt-3 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                2. Datos del Apoderado
                            </h4>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-[#2e8b57]">
                                <input
                                    v-model="studentForm.has_guardian"
                                    type="checkbox"
                                    class="rounded text-[#2e8b57] focus:ring-[#2e8b57]"
                                />
                                <span>{{ studentForm.has_guardian ? 'Apoderado Habilitado' : 'Sin apoderado' }}</span>
                            </label>
                        </div>

                        <div v-if="studentForm.has_guardian" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-3">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">DNI Apoderado (8 dígitos) *</label>
                                    <input
                                        v-model="studentForm.guardian_document"
                                        type="text"
                                        maxlength="8"
                                        @input="handleGuardianFormDniInput"
                                        placeholder="Ej. 09876543"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-mono font-bold text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                    />
                                    <p v-if="guardianFoundName" class="mt-1 text-xs text-emerald-700 font-bold flex items-center gap-1">
                                        <span>✓ Apoderado encontrado: {{ guardianFoundName }}</span>
                                    </p>
                                    <p v-if="studentForm.errors.guardian_document" class="mt-1 text-xs text-red-600 font-bold">
                                        {{ studentForm.errors.guardian_document }}
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Teléfono Apoderado</label>
                                    <input
                                        v-model="studentForm.guardian_phone"
                                        type="text"
                                        placeholder="Ej. 912345678"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-slate-700">Nombres del Apoderado *</label>
                                    <input
                                        v-model="studentForm.guardian_first_name"
                                        type="text"
                                        placeholder="Nombres"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                    />
                                    <p v-if="studentForm.errors.guardian_first_name" class="mt-1 text-xs text-red-600 font-bold">
                                        {{ studentForm.errors.guardian_first_name }}
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Apellido Paterno Apoderado *</label>
                                    <input
                                        v-model="studentForm.guardian_paternal_surname"
                                        type="text"
                                        placeholder="Paterno"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                    />
                                    <p v-if="studentForm.errors.guardian_paternal_surname" class="mt-1 text-xs text-red-600 font-bold">
                                        {{ studentForm.errors.guardian_paternal_surname }}
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Apellido Materno Apoderado</label>
                                    <input
                                        v-model="studentForm.guardian_maternal_surname"
                                        type="text"
                                        placeholder="Materno"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-slate-700">Correo Electrónico Apoderado</label>
                                    <input
                                        v-model="studentForm.guardian_email"
                                        type="email"
                                        placeholder="apoderado@ejemplo.com"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        @click="showStudentModal = false"
                        class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="saveStudent"
                        :disabled="studentForm.processing"
                        class="cursor-pointer rounded-lg bg-[#2e8b57] px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347] disabled:opacity-50"
                    >
                        {{ isEditingStudent ? 'Guardar Cambios' : 'Registrar Alumno' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL: CAMBIAR ESTADO DE MATRÍCULA                       -->
        <!-- ======================================================== -->
        <div
            v-if="showChangeStatusModal && selectedEnrollment"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-900">
                        Cambiar Estado de Matrícula
                    </h3>
                    <button
                        type="button"
                        @click="showChangeStatusModal = false"
                        class="cursor-pointer rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    >
                        &times;
                    </button>
                </div>

                <div class="space-y-4 py-4">
                    <div class="space-y-1 rounded-lg border border-slate-200 bg-slate-50 p-3.5 text-xs">
                        <p class="text-sm font-bold text-slate-900">
                            {{ selectedEnrollment.student_full_name }}
                        </p>
                        <p class="font-mono text-slate-500">
                            DNI: {{ selectedEnrollment.student_document }}
                        </p>
                        <p class="font-semibold text-slate-700">
                            {{ selectedEnrollment.course_name }} &bull;
                            {{ selectedEnrollment.branch_name }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800">
                            Nuevo Estado
                        </label>
                        <select
                            v-model="newStatusToApply"
                            class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        >
                            <option value="activo">Activo</option>
                            <option value="convalidado">Convalidado</option>
                            <option value="completado">Completado</option>
                            <option value="preinscrito">Preinscrito</option>
                            <option value="retirado">Retirado</option>
                            <option value="anulado">Anulado</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800">
                            Nota / Motivo del Cambio
                        </label>
                        <textarea
                            v-model="statusChangeNotes"
                            rows="2"
                            placeholder="Ej. Solicitud de convalidación aprobada según expediente 1024."
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        @click="showChangeStatusModal = false"
                        class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="applyStatusChange"
                        class="cursor-pointer rounded-lg bg-[#2e8b57] px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347]"
                    >
                        Actualizar Estado
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
