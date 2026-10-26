<script setup lang="ts">
import { computed, ref } from 'vue';

export interface Branch {
    id: number;
    name: string;
    code: string;
}

export interface AttendanceStudentEntry {
    student_id: number;
    full_name: string;
    document_number: string;
    career_name: string | null;
    is_cross_attendance: boolean;
    origin_scheduled_course_id?: number | null;
    origin_course_name?: string | null;
    status: 'presente' | 'tardanza' | 'falta' | 'justificado';
    registered_at?: string;
    notes?: string | null;
}

export interface AttendanceSessionItem {
    id: number; // scheduled_course_id
    course_name: string;
    career_name?: string | null;
    module_name?: string | null;
    branch_id: number;
    branch_name: string;
    classroom_id: number;
    classroom_name: string;
    teacher_name: string;
    day_of_week: string;
    start_time: string;
    end_time: string;
    time_range: string;
    is_currently_active: boolean; // En dictado ahora mismo
    is_today: boolean;
    is_past?: boolean; // Fechas anteriores (regularización por olvido)
    students: AttendanceStudentEntry[];
}

interface Props {
    branches?: Branch[];
    selectedBranchId?: number | 'all';
}

const props = withDefaults(defineProps<Props>(), {
    selectedBranchId: 'all',
});

const emit = defineEmits<{
    (e: 'notify', message: string): void;
}>();

// --- DATOS MOCK INSTITUCIONALES (Basados en scheduled_courses, course_schedules y attendances) ---
const sessions = ref<AttendanceSessionItem[]>([
    {
        id: 1,
        course_name: 'Arquitectura de Hardware y Diagnóstico',
        career_name: 'Computación e Informática',
        module_name: 'Ensamblaje y Mantenimiento de PC',
        branch_id: 1,
        branch_name: 'Sede Comas',
        classroom_id: 2,
        classroom_name: 'Laboratorio de Cómputo 1',
        teacher_name: 'Marco Antonio Rojas Quispe',
        day_of_week: 'Lunes y Miércoles',
        start_time: '16:00',
        end_time: '18:00',
        time_range: '16:00 - 18:00',
        is_currently_active: true, // Prioridad 1: En curso ahora
        is_today: true,
        students: [
            {
                student_id: 1,
                full_name: 'Diego Mendoza Salazar',
                document_number: '76543210',
                career_name: 'Computación e Informática',
                is_cross_attendance: false,
                status: 'presente',
                registered_at: '16:05',
                notes: null,
            },
            {
                student_id: 2,
                full_name: 'Valeria Rios Flores',
                document_number: '78901234',
                career_name: 'Computación e Informática',
                is_cross_attendance: false,
                status: 'presente',
                registered_at: '16:08',
                notes: null,
            },
        ],
    },
    {
        id: 2,
        course_name: 'Excel Avanzado y Macros',
        career_name: 'Computación e Informática',
        module_name: 'Ofimática Empresarial',
        branch_id: 1,
        branch_name: 'Sede Comas',
        classroom_id: 1,
        classroom_name: 'Aula 101 - Teoría',
        teacher_name: 'Marco Antonio Rojas Quispe',
        day_of_week: 'Martes y Jueves',
        start_time: '18:00',
        end_time: '20:00',
        time_range: '18:00 - 20:00',
        is_currently_active: false,
        is_today: true, // Prioridad 2: Hoy más tarde
        students: [
            {
                student_id: 3,
                full_name: 'Carlos Alberto Gomez Paredes',
                document_number: '34567890',
                career_name: null,
                is_cross_attendance: false,
                status: 'falta',
                registered_at: undefined,
                notes: null,
            },
            {
                student_id: 2,
                full_name: 'Valeria Rios Flores',
                document_number: '78901234',
                career_name: 'Computación e Informática',
                is_cross_attendance: false,
                status: 'falta',
                registered_at: undefined,
                notes: null,
            },
        ],
    },
    {
        id: 3,
        course_name: 'Taller de Barber Shop y Cortes Urbanos',
        career_name: null,
        module_name: null,
        branch_id: 2,
        branch_name: 'Sede Puente Piedra',
        classroom_id: 3,
        classroom_name: 'Aula 201 - Multiusos',
        teacher_name: 'Carlos Alberto Gomez Paredes',
        day_of_week: 'Sábados',
        start_time: '14:00',
        end_time: '18:00',
        time_range: '14:00 - 18:00',
        is_currently_active: false,
        is_today: false, // Programado otro día
        students: [
            {
                student_id: 4,
                full_name: 'Mateo Sebastián Quispe Ramos',
                document_number: '45678912',
                career_name: null,
                is_cross_attendance: false,
                status: 'falta',
                registered_at: undefined,
                notes: null,
            },
        ],
    },
    {
        id: 4,
        course_name: 'Taller de Uñas Acrílicas y Polygel',
        career_name: null,
        module_name: null,
        branch_id: 2,
        branch_name: 'Sede Puente Piedra',
        classroom_id: 4,
        classroom_name: 'Taller de Estética y Cosmetología',
        teacher_name: 'Patricia Silva Alvarado',
        day_of_week: 'Viernes',
        start_time: '15:00',
        end_time: '19:00',
        time_range: '15:00 - 19:00',
        is_currently_active: false,
        is_today: false,
        is_past: false,
        students: [
            {
                student_id: 5,
                full_name: 'Lucía Fernández Morales',
                document_number: '71234567',
                career_name: null,
                is_cross_attendance: false,
                status: 'falta',
                registered_at: undefined,
                notes: null,
            },
        ],
    },
    {
        id: 5,
        course_name: 'Arquitectura de Hardware y Diagnóstico',
        career_name: 'Computación e Informática',
        module_name: 'Ensamblaje y Mantenimiento de PC',
        branch_id: 1,
        branch_name: 'Sede Comas',
        classroom_id: 2,
        classroom_name: 'Laboratorio de Cómputo 1',
        teacher_name: 'Marco Antonio Rojas Quispe',
        day_of_week: 'Miércoles 22 Octubre (Sesión Pasada)',
        start_time: '16:00',
        end_time: '18:00',
        time_range: '16:00 - 18:00',
        is_currently_active: false,
        is_today: false,
        is_past: true,
        students: [
            {
                student_id: 1,
                full_name: 'Diego Mendoza Salazar',
                document_number: '76543210',
                career_name: 'Computación e Informática',
                is_cross_attendance: false,
                status: 'presente',
                registered_at: '16:10',
                notes: null,
            },
        ],
    },
    {
        id: 6,
        course_name: 'Excel Avanzado y Macros',
        career_name: 'Computación e Informática',
        module_name: 'Ofimática Empresarial',
        branch_id: 1,
        branch_name: 'Sede Comas',
        classroom_id: 1,
        classroom_name: 'Aula 101 - Teoría',
        teacher_name: 'Marco Antonio Rojas Quispe',
        day_of_week: 'Martes 21 Octubre (Sesión Pasada)',
        start_time: '18:00',
        end_time: '20:00',
        time_range: '18:00 - 20:00',
        is_currently_active: false,
        is_today: false,
        is_past: true,
        students: [
            {
                student_id: 3,
                full_name: 'Carlos Alberto Gomez Paredes',
                document_number: '34567890',
                career_name: null,
                is_cross_attendance: false,
                status: 'presente',
                registered_at: '18:15',
                notes: null,
            },
        ],
    },
    {
        id: 7,
        course_name: 'Decoración y Pastelería Fina en Masa Elástica',
        career_name: 'Gastronomía y Arte Culinario',
        module_name: 'Pastelería y Repostería Fina',
        branch_id: 2,
        branch_name: 'Sede Puente Piedra',
        classroom_id: 3,
        classroom_name: 'Aula 201 - Multiusos',
        teacher_name: 'Lucia Fernanda Vega Morales',
        day_of_week: 'Domingos',
        start_time: '09:00',
        end_time: '13:00',
        time_range: '09:00 - 13:00',
        is_currently_active: false,
        is_today: true,
        students: [
            {
                student_id: 7,
                full_name: 'Maria Elena Cordova Benites',
                document_number: '75678901',
                career_name: 'Gastronomía y Arte Culinario',
                is_cross_attendance: false,
                status: 'presente',
                registered_at: '09:05',
                notes: null,
            },
        ],
    },
    {
        id: 8,
        course_name: 'Taller de Barber Shop y Cortes Urbanos',
        career_name: null,
        module_name: null,
        branch_id: 2,
        branch_name: 'Sede Puente Piedra',
        classroom_id: 3,
        classroom_name: 'Aula 201 - Multiusos',
        teacher_name: 'Carlos Alberto Gomez Paredes',
        day_of_week: 'Domingo 19 Octubre (Sesión Pasada)',
        start_time: '14:00',
        end_time: '18:00',
        time_range: '14:00 - 18:00',
        is_currently_active: false,
        is_today: false,
        is_past: true,
        students: [
            {
                student_id: 6,
                full_name: 'Jose Luis Quispe Fernandez',
                document_number: '74567890',
                career_name: null,
                is_cross_attendance: false,
                status: 'presente',
                registered_at: '14:10',
                notes: null,
            },
        ],
    },
]);

// Directorio institucional de alumnos para validación por DNI
interface StudentLookup {
    id: number;
    full_name: string;
    document_number: string;
    career_name: string | null;
    module_name: string | null;
    enrolled_scheduled_course_id: number;
    enrolled_course_name: string;
    branch_name: string;
}

const studentsDirectory = ref<StudentLookup[]>([
    {
        id: 1,
        full_name: 'Diego Mendoza Salazar',
        document_number: '76543210',
        career_name: 'Computación e Informática',
        module_name: 'Ensamblaje y Mantenimiento de PC',
        enrolled_scheduled_course_id: 1,
        enrolled_course_name: 'Arquitectura de Hardware y Diagnóstico',
        branch_name: 'Sede Comas',
    },
    {
        id: 2,
        full_name: 'Valeria Rios Flores',
        document_number: '78901234',
        career_name: 'Computación e Informática',
        module_name: 'Ensamblaje y Mantenimiento de PC',
        enrolled_scheduled_course_id: 1,
        enrolled_course_name: 'Arquitectura de Hardware y Diagnóstico',
        branch_name: 'Sede Comas',
    },
    {
        id: 3,
        full_name: 'Carlos Alberto Gomez Paredes',
        document_number: '34567890',
        career_name: 'Computación e Informática',
        module_name: 'Ofimática Empresarial',
        enrolled_scheduled_course_id: 2,
        enrolled_course_name: 'Excel Avanzado y Macros',
        branch_name: 'Sede Comas',
    },
    {
        id: 4,
        full_name: 'Mateo Sebastián Quispe Ramos',
        document_number: '45678912',
        career_name: null,
        module_name: null,
        enrolled_scheduled_course_id: 3,
        enrolled_course_name: 'Taller de Barber Shop y Cortes Urbanos',
        branch_name: 'Sede Puente Piedra',
    },
    {
        id: 5,
        full_name: 'Lucía Fernández Morales',
        document_number: '71234567',
        career_name: null,
        module_name: null,
        enrolled_scheduled_course_id: 4,
        enrolled_course_name: 'Taller de Uñas Acrílicas y Polygel',
        branch_name: 'Sede Puente Piedra',
    },
]);

// Separación de sesiones: Activas en este momento / turnos de hoy vs Fechas anteriores (regularización)
const activeSessions = computed(() => sessions.value.filter((s) => !s.is_past));
const pastSessions = computed(() => sessions.value.filter((s) => s.is_past));

// Curso destino seleccionado en el selector (autoseleccionado al buscar o editable)
const selectedDestinationCourseId = ref<number | null>(null);

const selectedSession = computed(() => {
    if (selectedDestinationCourseId.value !== null) {
        const found = sessions.value.find(
            (s) => s.id === selectedDestinationCourseId.value,
        );
        if (found) return found;
    }
    return (
        sessions.value.find((s) => s.is_currently_active) || sessions.value[0]
    );
});

function setTargetSession(id: number) {
    selectedDestinationCourseId.value = id;
    handleDestinationCourseChange();
}

function handleDestinationCourseChange() {
    if (selectedSession.value?.is_past) {
        crossAttendanceReason.value =
            'Regularización por olvido en fecha anterior';
    } else if (!isMatchingCourse.value) {
        crossAttendanceReason.value = 'Recuperación de clase';
    }
}

// Ordenamiento de sesiones para la tabla inferior
const searchSessionQuery = ref('');
const sessionFilter = ref<'all' | 'en_curso' | 'hoy'>('all');

const prioritizedSessions = computed(() => {
    return sessions.value
        .filter((session) => {
            if (
                props.selectedBranchId !== 'all' &&
                session.branch_id !== props.selectedBranchId
            ) {
                return false;
            }
            if (
                sessionFilter.value === 'en_curso' &&
                !session.is_currently_active
            ) {
                return false;
            }
            if (sessionFilter.value === 'hoy' && !session.is_today) {
                return false;
            }
            if (searchSessionQuery.value.trim() !== '') {
                const q = searchSessionQuery.value.toLowerCase();
                return (
                    session.course_name.toLowerCase().includes(q) ||
                    session.classroom_name.toLowerCase().includes(q) ||
                    session.teacher_name.toLowerCase().includes(q) ||
                    session.branch_name.toLowerCase().includes(q)
                );
            }
            return true;
        })
        .sort((a, b) => {
            // 1. En curso ahora primero
            if (a.is_currently_active && !b.is_currently_active) return -1;
            if (!a.is_currently_active && b.is_currently_active) return 1;
            // 2. Programadas para hoy después
            if (a.is_today && !b.is_today) return -1;
            if (!a.is_today && b.is_today) return 1;
            return a.id - b.id;
        });
});

// --- LÓGICA DE BÚSQUEDA POR DNI EN ADMINISTRACIÓN ---
const dniInput = ref('');
const lookupResult = ref<StudentLookup | null>(null);
const lookupHasSearched = ref(false);
const crossAttendanceReason = ref('Recuperación de clase');
const customCrossNotes = ref('');

function searchStudentByDni() {
    lookupHasSearched.value = true;
    const cleanDni = dniInput.value.trim();
    if (!cleanDni) {
        lookupResult.value = null;
        selectedDestinationCourseId.value = null;
        return;
    }

    const found = studentsDirectory.value.find(
        (s) => s.document_number === cleanDni,
    );
    lookupResult.value = found || null;

    if (found) {
        // PASO 2: Autoseleccionar automáticamente el curso en el que está matriculado en ese horario
        selectedDestinationCourseId.value = found.enrolled_scheduled_course_id;
        crossAttendanceReason.value = 'Recuperación de clase';
        customCrossNotes.value = '';
    } else {
        selectedDestinationCourseId.value = null;
    }
}

// Verifica si el alumno pertenece a la sesión de destino elegida en el selector
const isMatchingCourse = computed(() => {
    if (!lookupResult.value || !selectedDestinationCourseId.value) return false;
    return (
        lookupResult.value.enrolled_scheduled_course_id ===
        selectedDestinationCourseId.value
    );
});

// Registrar asistencia desde el módulo administrativo
function registerAttendance(status: 'presente' | 'tardanza' = 'presente') {
    if (!lookupResult.value || !selectedSession.value) return;

    const student = lookupResult.value;
    const session = selectedSession.value;
    const nowTime = new Date().toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
    });

    // Verificar si ya está en la lista de la sesión
    const existingIndex = session.students.findIndex(
        (s) => s.document_number === student.document_number,
    );

    if (isMatchingCourse.value) {
        // Coincidencia exacta regular
        if (existingIndex >= 0) {
            session.students[existingIndex].status = status;
            session.students[existingIndex].registered_at = nowTime;
        } else {
            session.students.push({
                student_id: student.id,
                full_name: student.full_name,
                document_number: student.document_number,
                career_name: student.career_name,
                is_cross_attendance: false,
                status: status,
                registered_at: nowTime,
                notes: null,
            });
        }
        emit(
            'notify',
            `Asistencia registrada para ${student.full_name} (${status.toUpperCase()}) en ${session.course_name}.`,
        );
    } else {
        // Asistencia Modificada / Cruzada / Fecha Anterior (origin_scheduled_course_id en BD)
        const noteText = customCrossNotes.value.trim()
            ? `${crossAttendanceReason.value}: ${customCrossNotes.value.trim()}`
            : crossAttendanceReason.value;

        if (existingIndex >= 0) {
            session.students[existingIndex].status = status;
            session.students[existingIndex].is_cross_attendance = true;
            session.students[existingIndex].origin_scheduled_course_id =
                student.enrolled_scheduled_course_id;
            session.students[existingIndex].origin_course_name =
                student.enrolled_course_name;
            session.students[existingIndex].notes = noteText;
            session.students[existingIndex].registered_at = nowTime;
        } else {
            session.students.push({
                student_id: student.id,
                full_name: student.full_name,
                document_number: student.document_number,
                career_name: student.career_name,
                is_cross_attendance: true,
                origin_scheduled_course_id:
                    student.enrolled_scheduled_course_id,
                origin_course_name: student.enrolled_course_name,
                status: status,
                registered_at: nowTime,
                notes: noteText,
            });
        }
        emit(
            'notify',
            `Asistencia autorizada para ${student.full_name} en ${session.course_name} (${noteText}).`,
        );
    }

    // Limpiar entrada
    dniInput.value = '';
    lookupResult.value = null;
    lookupHasSearched.value = false;
    customCrossNotes.value = '';
    selectedDestinationCourseId.value = null;
}

// --- MODAL DE PASAR LISTA COMPLETA ---
const isRollCallModalOpen = ref(false);
const activeRollCallSession = ref<AttendanceSessionItem | null>(null);

// Asistencia por excepción / DNI dentro del modal de pase de lista
const isModalDniPanelOpen = ref(false);
const modalDniInput = ref('');
const modalLookupResult = ref<StudentLookup | null>(null);
const modalLookupSearched = ref(false);
const modalCrossReason = ref('Recuperación de clase');
const modalCustomNotes = ref('');

function searchStudentInModal() {
    modalLookupSearched.value = true;
    const cleanDni = modalDniInput.value.trim();
    if (!cleanDni) {
        modalLookupResult.value = null;
        return;
    }
    const found = studentsDirectory.value.find(
        (s) => s.document_number === cleanDni,
    );
    modalLookupResult.value = found || null;
}

function quickFillModalDni(dni: string) {
    modalDniInput.value = dni;
    searchStudentInModal();
}

const isModalMatchingCourse = computed(() => {
    if (!modalLookupResult.value || !activeRollCallSession.value) return false;
    return (
        modalLookupResult.value.enrolled_scheduled_course_id ===
        activeRollCallSession.value.id
    );
});

function registerModalAttendance(status: 'presente' | 'tardanza' = 'presente') {
    if (!modalLookupResult.value || !activeRollCallSession.value) return;
    const student = modalLookupResult.value;
    const session = activeRollCallSession.value;
    const nowTime = new Date().toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
    });
    const existingIndex = session.students.findIndex(
        (s) => s.document_number === student.document_number,
    );

    if (isModalMatchingCourse.value) {
        if (existingIndex >= 0) {
            session.students[existingIndex].status = status;
            session.students[existingIndex].registered_at = nowTime;
        } else {
            session.students.push({
                student_id: student.id,
                full_name: student.full_name,
                document_number: student.document_number,
                career_name: student.career_name,
                is_cross_attendance: false,
                status: status,
                registered_at: nowTime,
                notes: null,
            });
        }
        emit(
            'notify',
            `Asistencia registrada para ${student.full_name} (${status.toUpperCase()}).`,
        );
    } else {
        const noteText = modalCustomNotes.value.trim()
            ? `${modalCrossReason.value}: ${modalCustomNotes.value.trim()}`
            : modalCrossReason.value;

        if (existingIndex >= 0) {
            session.students[existingIndex].status = status;
            session.students[existingIndex].is_cross_attendance = true;
            session.students[existingIndex].origin_scheduled_course_id =
                student.enrolled_scheduled_course_id;
            session.students[existingIndex].origin_course_name =
                student.enrolled_course_name;
            session.students[existingIndex].notes = noteText;
            session.students[existingIndex].registered_at = nowTime;
        } else {
            session.students.push({
                student_id: student.id,
                full_name: student.full_name,
                document_number: student.document_number,
                career_name: student.career_name,
                is_cross_attendance: true,
                origin_scheduled_course_id:
                    student.enrolled_scheduled_course_id,
                origin_course_name: student.enrolled_course_name,
                status: status,
                registered_at: nowTime,
                notes: noteText,
            });
        }
        emit(
            'notify',
            `Asistencia cruzada autorizada para ${student.full_name} en ${session.course_name}.`,
        );
    }

    modalDniInput.value = '';
    modalLookupResult.value = null;
    modalLookupSearched.value = false;
    modalCustomNotes.value = '';
}

function openRollCallModal(session: AttendanceSessionItem) {
    activeRollCallSession.value = session;
    isRollCallModalOpen.value = true;
}

function updateStudentStatus(
    student: AttendanceStudentEntry,
    newStatus: 'presente' | 'tardanza' | 'falta' | 'justificado',
) {
    student.status = newStatus;
    if (newStatus === 'presente' || newStatus === 'tardanza') {
        student.registered_at = new Date().toLocaleTimeString('es-PE', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }
}

function markAllPresent() {
    if (!activeRollCallSession.value) return;
    const nowTime = new Date().toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
    });
    activeRollCallSession.value.students.forEach((s) => {
        s.status = 'presente';
        s.registered_at = nowTime;
    });
    emit(
        'notify',
        `Todos los alumnos de "${activeRollCallSession.value.course_name}" marcados como presentes.`,
    );
}

function saveRollCall() {
    if (!activeRollCallSession.value) return;
    const presentCount = activeRollCallSession.value.students.filter(
        (s) => s.status === 'presente' || s.status === 'tardanza',
    ).length;
    emit(
        'notify',
        `Nómina guardada: ${presentCount} presentes en ${activeRollCallSession.value.course_name}.`,
    );
    isRollCallModalOpen.value = false;
}
</script>

<template>
    <div class="space-y-6">
        <!-- ============================================================== -->
        <!-- 1. MÓDULO ADMINISTRATIVO DE MARCACIÓN POR DNI                  -->
        <!-- ============================================================== -->
        <div
            class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-xs"
        >
            <div
                class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-3 md:flex-row md:items-center"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-3 w-3 animate-pulse rounded-full bg-[#2e8b57]"
                        ></span>
                        <h2
                            class="text-base font-black tracking-tight text-slate-900"
                        >
                            Marcación de Asistencia por DNI
                        </h2>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Ingrese el número de DNI del carnet entregado por el
                        estudiante para validar su matrícula y registrar su
                        asistencia.
                    </p>
                </div>

                <!-- Indicador visual rápido -->
                <div
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600"
                >
                    <span
                        class="h-2 w-2 animate-pulse rounded-full bg-[#2e8b57]"
                    ></span>
                    <span
                        >Sistema de validación y regularización automática</span
                    >
                </div>
            </div>

            <!-- Formulario de entrada de DNI -->
            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <svg
                        class="pointer-events-none absolute top-3 left-3.5 h-5 w-5 text-slate-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"
                        />
                    </svg>
                    <input
                        v-model="dniInput"
                        @keyup.enter="searchStudentByDni"
                        type="text"
                        maxlength="8"
                        placeholder="Ingresar número de DNI del carnet entregado por el estudiante..."
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-11 font-mono text-sm text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:ring-1 focus:ring-[#2e8b57] focus:outline-none"
                    />
                </div>

                <button
                    @click="searchStudentByDni"
                    class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg bg-[#2e8b57] px-5 py-2.5 text-sm font-bold text-white shadow-2xs transition hover:bg-[#257347]"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                    <span>Buscar Alumno</span>
                </button>
            </div>

            <!-- Mensaje guía previo a la búsqueda -->
            <p v-if="!lookupResult" class="text-xs text-slate-400 italic">
                * Ingrese el DNI para autoseleccionar el curso matriculado del
                estudiante. Podrá cambiar el curso destino (cursos activos o
                fechas pasadas para regularización) y justificar el motivo si
                asiste por excepción.
            </p>

            <!-- RESULTADO DE BÚSQUEDA Y SELECTOR DE CURSO DESTINO -->
            <div
                v-if="lookupHasSearched && lookupResult"
                class="space-y-4 rounded-xl border p-5 transition-all"
                :class="
                    isMatchingCourse
                        ? 'border-emerald-200 bg-emerald-50/40'
                        : 'border-amber-300 bg-amber-50/60'
                "
            >
                <!-- Ficha del Alumno -->
                <div
                    class="flex flex-col justify-between gap-3 border-b pb-3 md:flex-row md:items-center"
                    :class="
                        isMatchingCourse
                            ? 'border-emerald-200'
                            : 'border-amber-200'
                    "
                >
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="text-base font-bold text-slate-900">{{
                                lookupResult.full_name
                            }}</span>
                            <span
                                class="rounded border border-slate-200 bg-white px-2 py-0.5 font-mono text-xs font-bold text-slate-700 shadow-2xs"
                            >
                                DNI: {{ lookupResult.document_number }}
                            </span>
                            <span
                                class="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-[#2e8b57]"
                            >
                                Matrícula Activa
                            </span>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-600"
                        >
                            <span
                                ><strong>Sede:</strong>
                                {{ lookupResult.branch_name }}</span
                            >
                            <span v-if="lookupResult.career_name"
                                >&bull; <strong>Carrera:</strong>
                                {{ lookupResult.career_name }}</span
                            >
                            <span v-if="lookupResult.module_name"
                                >&bull; <strong>Módulo:</strong>
                                {{ lookupResult.module_name }}</span
                            >
                        </div>
                        <div class="text-xs text-slate-500">
                            Curso asignado oficialmente:
                            <strong class="font-bold text-slate-900">{{
                                lookupResult.enrolled_course_name
                            }}</strong>
                        </div>
                    </div>

                    <!-- Badge de Estado de Coincidencia -->
                    <div>
                        <span
                            v-if="isMatchingCourse"
                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-100 px-3 py-1 text-xs font-bold text-[#2e8b57]"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                            Curso Oficial Autoseleccionado
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900"
                        >
                            <svg
                                class="h-4 w-4 text-amber-600"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            Curso Destino Modificado (Excepción)
                        </span>
                    </div>
                </div>

                <!-- SELECTOR DE CURSO DESTINO DIVIDIDO EN 2 SECCIONES -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700">
                        Curso / Sesión de Asistencia Destino:
                        <span class="font-normal text-slate-500"
                            >(Autoseleccionado por defecto. Modifíquelo si
                            asistirá a otro curso, sede o regulariza una fecha
                            anterior)</span
                        >
                    </label>

                    <div class="relative">
                        <select
                            v-model="selectedDestinationCourseId"
                            @change="handleDestinationCourseChange"
                            class="w-full cursor-pointer rounded-lg border bg-white py-2.5 pr-8 pl-3 text-xs font-semibold text-slate-900 transition focus:outline-none"
                            :class="
                                isMatchingCourse
                                    ? 'border-emerald-300 focus:border-[#2e8b57] focus:ring-1 focus:ring-[#2e8b57]'
                                    : 'border-amber-400 bg-amber-50/30 focus:border-amber-500 focus:ring-1 focus:ring-amber-500'
                            "
                        >
                            <!-- SECCIÓN 1: CURSOS ACTIVOS EN ESTE MOMENTO Y TURNOS DE HOY -->
                            <optgroup
                                label="🟢 CURSOS ACTIVOS EN ESTE MOMENTO Y TURNOS DE HOY"
                            >
                                <option
                                    v-for="session in activeSessions"
                                    :key="session.id"
                                    :value="session.id"
                                >
                                    {{ session.course_name }} —
                                    {{ session.classroom_name }} ({{
                                        session.branch_name
                                    }}) [{{ session.day_of_week }}
                                    {{ session.time_range }}]
                                    {{
                                        session.is_currently_active
                                            ? '★ EN CURSO AHORA'
                                            : ''
                                    }}
                                </option>
                            </optgroup>

                            <!-- SECCIÓN 2: FECHAS ANTERIORES (REGULARIZACIÓN POR OLVIDO) -->
                            <optgroup
                                label="📅 FECHAS ANTERIORES (REGULARIZACIÓN DE ASISTENCIA POR OLVIDO)"
                            >
                                <option
                                    v-for="session in pastSessions"
                                    :key="session.id"
                                    :value="session.id"
                                >
                                    {{ session.day_of_week }}:
                                    {{ session.course_name }} —
                                    {{ session.classroom_name }} ({{
                                        session.branch_name
                                    }})
                                </option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <!-- ACCIONES Y JUSTIFICACIÓN -->
                <!-- Caso 1: Coincide con su matrícula regular -->
                <div
                    v-if="isMatchingCourse"
                    class="flex flex-col justify-between gap-3 pt-2 sm:flex-row sm:items-center"
                >
                    <div class="text-xs text-slate-600">
                        El estudiante está registrado en su grupo oficial:
                        <strong>{{ selectedSession.course_name }}</strong
                        >.
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            @click="registerAttendance('presente')"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-[#2e8b57] px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-[#257347]"
                        >
                            ✓ Marcar Presente
                        </button>
                        <button
                            @click="registerAttendance('tardanza')"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-amber-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-amber-700"
                        >
                            ⏱ Tardanza
                        </button>
                    </div>
                </div>

                <!-- Caso 2: Modificó el curso autoseleccionado (Excepción, otra sede, otro horario o fecha anterior) -->
                <div v-else class="space-y-3 border-t border-amber-200 pt-2">
                    <div
                        class="space-y-1 rounded-lg border border-amber-300 bg-amber-100/70 p-3 text-xs text-amber-900"
                    >
                        <div class="flex items-center gap-1.5 font-bold">
                            <span>⚠️ Excepción de Asistencia:</span>
                            <span
                                >Asistirá a "{{ selectedSession.course_name }}"
                                ({{ selectedSession.classroom_name }} -
                                {{ selectedSession.branch_name }})</span
                            >
                        </div>
                        <p class="text-[11px] text-amber-800">
                            El curso seleccionado difiere de su matrícula ("{{
                                lookupResult.enrolled_course_name
                            }}"). Debe indicar el motivo para registrar la
                            excepción en el historial de la base de datos.
                        </p>
                    </div>

                    <div
                        class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center"
                    >
                        <div class="flex-1 space-y-1">
                            <label
                                class="block text-xs font-bold text-slate-700"
                                >Motivo de la excepción / justificación:</label
                            >
                            <select
                                v-model="crossAttendanceReason"
                                class="w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none"
                            >
                                <option value="Recuperación de clase">
                                    Recuperación de clase
                                </option>
                                <option value="Cambio temporal de grupo">
                                    Cambio temporal de grupo / horario
                                </option>
                                <option
                                    value="Regularización por olvido en fecha anterior"
                                >
                                    Regularización por olvido en fecha anterior
                                </option>
                                <option value="Práctica en ambiente libre">
                                    Práctica en ambiente libre
                                </option>
                                <option
                                    value="Autorización verbal de Dirección"
                                >
                                    Autorización verbal de Dirección
                                </option>
                            </select>
                        </div>

                        <div class="flex-1 space-y-1">
                            <label
                                class="block text-xs font-medium text-slate-600"
                                >Observación adicional (opcional):</label
                            >
                            <input
                                v-model="customCrossNotes"
                                type="text"
                                placeholder="Ej: Autorizado por Coordinación, recuperación de clase..."
                                class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 focus:border-[#2e8b57] focus:outline-none"
                            />
                        </div>

                        <div class="self-end pt-1">
                            <button
                                @click="registerAttendance('presente')"
                                class="inline-flex w-full shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-amber-600 px-5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-amber-700 sm:w-auto"
                            >
                                ✓ Autorizar y Marcar Asistencia
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Caso: No encontrado -->
            <div
                v-else-if="lookupHasSearched && !lookupResult"
                class="flex items-center justify-between rounded-xl border border-red-200 bg-red-50/50 p-4 text-xs font-semibold text-red-700"
            >
                <span
                    >No se encontró ningún alumno registrado con el DNI
                    ingresado en la base de datos del CETPRO. Verifique el
                    carnet o registre su matrícula previa.</span
                >
                <button
                    @click="lookupHasSearched = false"
                    class="ml-3 cursor-pointer font-bold text-red-500 hover:text-red-800"
                >
                    &times;
                </button>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- 2. LISTA DE SESIONES / CURSOS DE ASISTENCIA (PRIORIZADOS)       -->
        <!-- ============================================================== -->
        <div class="space-y-4">
            <!-- Barra de Filtros y Búsqueda de Sesiones -->
            <div
                class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="relative flex-1 sm:max-w-md">
                    <svg
                        class="pointer-events-none absolute top-3 left-3.5 h-5 w-5 text-slate-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                    <input
                        v-model="searchSessionQuery"
                        type="text"
                        placeholder="Buscar por materia, aula física, docente o sede..."
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-11 text-sm text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                </div>

                <!-- Filtros rápidos por estado de horario -->
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        @click="sessionFilter = 'all'"
                        :class="[
                            sessionFilter === 'all'
                                ? 'bg-slate-800 font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Todas las Sesiones ({{ sessions.length }})
                    </button>
                    <button
                        @click="sessionFilter = 'en_curso'"
                        :class="[
                            sessionFilter === 'en_curso'
                                ? 'bg-[#2e8b57] font-bold text-white'
                                : 'bg-emerald-50 font-semibold text-[#2e8b57] hover:bg-emerald-100',
                            'flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        <span
                            class="h-2 w-2 animate-pulse rounded-full bg-[#2e8b57]"
                        ></span>
                        En Dictado Ahora ({{
                            sessions.filter((s) => s.is_currently_active)
                                .length
                        }})
                    </button>
                    <button
                        @click="sessionFilter = 'hoy'"
                        :class="[
                            sessionFilter === 'hoy'
                                ? 'bg-blue-700 font-bold text-white'
                                : 'bg-blue-50 font-semibold text-blue-700 hover:bg-blue-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Programadas Hoy ({{
                            sessions.filter((s) => s.is_today).length
                        }})
                    </button>
                </div>
            </div>

            <!-- TABLA / LISTA DE SESIONES DE CURSOS -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-700">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 text-xs font-bold tracking-wider text-slate-600 uppercase"
                        >
                            <tr>
                                <th class="py-4 pr-4 pl-6">
                                    Curso / Turno Formativo
                                </th>
                                <th class="px-4 py-4">Sede & Aula Física</th>
                                <th class="px-4 py-4">Horario & Días</th>
                                <th class="px-4 py-4">Docente</th>
                                <th class="px-4 py-4">Asistencia Registrada</th>
                                <th class="py-4 pr-6 pl-4 text-right">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="session in prioritizedSessions"
                                :key="session.id"
                                :class="[
                                    session.is_currently_active
                                        ? 'border-l-4 border-l-[#2e8b57] bg-emerald-50/40'
                                        : 'hover:bg-slate-50/60',
                                    'transition',
                                ]"
                            >
                                <!-- Columna 1: Curso / Turno -->
                                <td class="py-4 pr-4 pl-6">
                                    <div class="mb-1 flex items-center gap-2">
                                        <span
                                            v-if="session.is_currently_active"
                                            class="inline-flex animate-pulse items-center gap-1 rounded-full border border-emerald-300 bg-emerald-100 px-2.5 py-0.5 text-[11px] font-black tracking-wider text-[#2e8b57] uppercase"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-[#2e8b57]"
                                            ></span>
                                            EN DICTADO AHORA
                                        </span>
                                        <span
                                            v-else-if="session.is_today"
                                            class="rounded border border-blue-200 bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700 uppercase"
                                        >
                                            Hoy Próximo Turno
                                        </span>
                                        <span
                                            v-else
                                            class="rounded border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600 uppercase"
                                        >
                                            Programado
                                        </span>
                                        <span
                                            class="font-mono text-xs text-slate-400"
                                            >SCH-{{ session.id }}</span
                                        >
                                    </div>

                                    <div
                                        class="text-sm font-bold text-slate-900"
                                    >
                                        {{ session.course_name }}
                                    </div>
                                    <div
                                        v-if="session.career_name"
                                        class="mt-0.5 text-xs font-medium text-slate-500"
                                    >
                                        {{ session.career_name }} &bull;
                                        {{ session.module_name }}
                                    </div>
                                    <div
                                        v-else
                                        class="mt-0.5 text-xs font-semibold text-amber-700"
                                    >
                                        Taller Libre Institucional
                                    </div>
                                </td>

                                <!-- Columna 2: Sede & Aula Física -->
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div
                                        class="flex items-center gap-2 text-xs font-bold text-slate-800"
                                    >
                                        <span
                                            class="h-2 w-2 rounded-full"
                                            :class="
                                                session.branch_name.includes(
                                                    'Comas',
                                                )
                                                    ? 'bg-[#2e8b57]'
                                                    : 'bg-teal-600'
                                            "
                                        ></span>
                                        {{ session.branch_name }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-xs font-semibold text-slate-600"
                                    >
                                        {{ session.classroom_name }}
                                    </div>
                                </td>

                                <!-- Columna 3: Horario & Días -->
                                <td class="px-4 py-4 text-xs whitespace-nowrap">
                                    <div
                                        class="font-bold"
                                        :class="
                                            session.is_currently_active
                                                ? 'text-[#2e8b57]'
                                                : 'text-slate-800'
                                        "
                                    >
                                        {{ session.day_of_week }}
                                    </div>
                                    <div
                                        class="mt-0.5 flex items-center gap-1 font-mono text-slate-500"
                                    >
                                        <svg
                                            class="h-3.5 w-3.5 text-slate-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                            />
                                        </svg>
                                        {{ session.time_range }}
                                    </div>
                                </td>

                                <!-- Columna 4: Docente -->
                                <td
                                    class="px-4 py-4 text-xs font-medium whitespace-nowrap text-slate-800"
                                >
                                    {{ session.teacher_name }}
                                </td>

                                <!-- Columna 5: Asistencia Registrada -->
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div
                                        class="mb-1 flex items-center justify-between gap-2 text-xs"
                                    >
                                        <span
                                            class="font-mono font-bold text-slate-800"
                                        >
                                            {{
                                                session.students.filter(
                                                    (s) =>
                                                        s.status ===
                                                            'presente' ||
                                                        s.status === 'tardanza',
                                                ).length
                                            }}
                                            /
                                            {{ session.students.length }}
                                            presentes
                                        </span>
                                        <span
                                            class="font-mono text-[11px] font-bold text-slate-500"
                                        >
                                            {{
                                                session.students.length > 0
                                                    ? Math.round(
                                                          (session.students.filter(
                                                              (s) =>
                                                                  s.status ===
                                                                      'presente' ||
                                                                  s.status ===
                                                                      'tardanza',
                                                          ).length /
                                                              session.students
                                                                  .length) *
                                                              100,
                                                      )
                                                    : 0
                                            }}%
                                        </span>
                                    </div>
                                    <div
                                        class="h-2 w-28 overflow-hidden rounded-full border border-slate-200 bg-slate-100"
                                    >
                                        <div
                                            class="h-2 rounded-full bg-[#2e8b57] transition-all duration-300"
                                            :style="{
                                                width: `${session.students.length > 0 ? Math.min(100, Math.round((session.students.filter((s) => s.status === 'presente' || s.status === 'tardanza').length / session.students.length) * 100)) : 0}%`,
                                            }"
                                        ></div>
                                    </div>
                                </td>

                                <!-- Columna 6: Acciones -->
                                <td
                                    class="py-4 pr-6 pl-4 text-right whitespace-nowrap"
                                >
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            v-if="
                                                session.id !==
                                                selectedSession.id
                                            "
                                            @click="
                                                setTargetSession(session.id)
                                            "
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-[#2e8b57] hover:text-[#2e8b57]"
                                            title="Establecer esta sesión como destino de la marcación por DNI"
                                        >
                                            <span>Marcar DNI aquí</span>
                                        </button>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-xs font-bold text-[#2e8b57]"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-[#2e8b57]"
                                            ></span>
                                            <span>Destino DNI</span>
                                        </span>

                                        <button
                                            @click="openRollCallModal(session)"
                                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-[#2e8b57] px-3.5 py-1.5 text-xs font-bold text-white shadow-2xs transition hover:bg-[#257347]"
                                        >
                                            <svg
                                                class="h-3.5 w-3.5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                                                />
                                            </svg>
                                            <span>Tomar Lista</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="prioritizedSessions.length === 0">
                                <td
                                    colspan="6"
                                    class="py-12 text-center font-medium text-slate-400"
                                >
                                    No se encontraron sesiones programadas con
                                    los filtros seleccionados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tarjeta explicativa de la regla de asistencia cruzada -->
            <div
                class="space-y-3 rounded-xl border border-slate-200 bg-white p-6 shadow-xs"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-[#2e8b57]"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">
                        Validación de Asistencia Cruzada en Base de Datos
                        (CETPRO IISEP)
                    </h3>
                </div>
                <p class="text-xs leading-relaxed text-slate-600">
                    En la tabla
                    <code
                        class="rounded bg-slate-100 px-1 py-0.5 font-mono font-bold text-slate-900"
                        >attendances</code
                    >, cuando un estudiante acude a una sesión o aula distinta
                    de su grupo oficial por motivos de recuperación de clase o
                    contingencia física, el sistema registra
                    <code
                        class="rounded bg-purple-50 px-1 py-0.5 font-mono font-bold text-purple-700"
                        >origin_scheduled_course_id</code
                    >
                    junto con el curso temporal en
                    <code
                        class="rounded bg-emerald-50 px-1 py-0.5 font-mono font-bold text-[#2e8b57]"
                        >scheduled_course_id</code
                    >
                    y su justificación en
                    <code
                        class="rounded bg-slate-100 px-1 py-0.5 font-mono font-bold text-slate-700"
                        >notes</code
                    >.
                </p>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- 3. MODAL: PASAR LISTA COMPLETA DE ALUMNOS DEL CURSO            -->
        <!-- ============================================================== -->
        <div
            v-if="isRollCallModalOpen && activeRollCallSession"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="isRollCallModalOpen = false"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-3xl flex-col space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <!-- Header del Modal -->
                <div
                    class="flex shrink-0 items-start justify-between border-b border-slate-100 pb-4"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="activeRollCallSession.is_currently_active"
                                class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold text-[#2e8b57]"
                            >
                                En Curso Ahora
                            </span>
                            <span
                                class="font-mono text-xs font-bold text-slate-400"
                                >SCH-{{ activeRollCallSession.id }}</span
                            >
                        </div>
                        <h3 class="mt-1 text-lg font-black text-slate-900">
                            {{ activeRollCallSession.course_name }}
                        </h3>
                        <p class="text-xs font-medium text-slate-500">
                            {{ activeRollCallSession.branch_name }} &bull;
                            {{ activeRollCallSession.classroom_name }} &bull;
                            {{ activeRollCallSession.day_of_week }} ({{
                                activeRollCallSession.time_range
                            }})
                        </p>
                    </div>

                    <button
                        @click="isRollCallModalOpen = false"
                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        &times;
                    </button>
                </div>

                <!-- Resumen de Asistencia y Acción Rápida -->
                <div
                    class="flex shrink-0 flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-4 text-xs font-semibold">
                        <span class="text-slate-700"
                            >Total:
                            <strong>{{
                                activeRollCallSession.students.length
                            }}</strong></span
                        >
                        <span class="text-[#2e8b57]"
                            >Presentes:
                            <strong>{{
                                activeRollCallSession.students.filter(
                                    (s) => s.status === 'presente',
                                ).length
                            }}</strong></span
                        >
                        <span class="text-amber-700"
                            >Tardanzas:
                            <strong>{{
                                activeRollCallSession.students.filter(
                                    (s) => s.status === 'tardanza',
                                ).length
                            }}</strong></span
                        >
                        <span class="text-red-600"
                            >Faltas:
                            <strong>{{
                                activeRollCallSession.students.filter(
                                    (s) => s.status === 'falta',
                                ).length
                            }}</strong></span
                        >
                    </div>

                    <button
                        @click="markAllPresent"
                        class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-[#2e8b57] bg-emerald-50 px-3 py-1.5 text-xs font-bold text-[#2e8b57] transition hover:bg-emerald-100"
                    >
                        ✓ Marcar Todos Presentes
                    </button>
                </div>

                <!-- Botón colapsable: + Agregar Alumno por DNI / Carnet (Excepción o Regular) -->
                <div
                    class="shrink-0 space-y-3 rounded-xl border border-dashed border-slate-300 bg-slate-50/60 p-3.5"
                >
                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            @click="isModalDniPanelOpen = !isModalDniPanelOpen"
                            class="inline-flex cursor-pointer items-center gap-2 text-xs font-bold text-[#2e8b57] transition hover:text-[#257347]"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>
                            <span>{{
                                isModalDniPanelOpen
                                    ? 'Ocultar registro de alumno por DNI'
                                    : '+ Marcar alumno por DNI / Carnet (Excepción, Recuperación o Regular)'
                            }}</span>
                        </button>
                        <span class="text-[11px] text-slate-500"
                            >¿Llegó un alumno de otro curso o no aparece en la
                            lista?</span
                        >
                    </div>

                    <div
                        v-if="isModalDniPanelOpen"
                        class="space-y-3 border-t border-slate-200/80 pt-2"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <input
                                v-model="modalDniInput"
                                @keyup.enter="searchStudentInModal"
                                type="text"
                                maxlength="8"
                                placeholder="Ingresar DNI del carnet..."
                                class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-mono text-xs text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                            />
                            <button
                                type="button"
                                @click="searchStudentInModal"
                                class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-[#2e8b57] px-3.5 py-1.5 text-xs font-bold text-white transition hover:bg-[#257347]"
                            >
                                Buscar
                            </button>
                        </div>

                        <!-- Botones de ejemplo rápido en el modal -->
                        <div
                            class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500"
                        >
                            <span>Demostración con 1 clic:</span>
                            <button
                                type="button"
                                @click="quickFillModalDni('34567890')"
                                class="cursor-pointer rounded border border-amber-300 bg-amber-100 px-2 py-0.5 font-mono font-bold text-amber-900 transition hover:bg-amber-200"
                            >
                                34567890 (⚠️ Alumno de otro curso)
                            </button>
                            <button
                                type="button"
                                @click="quickFillModalDni('76543210')"
                                class="cursor-pointer rounded bg-slate-200 px-2 py-0.5 font-mono font-bold text-slate-800 transition hover:bg-slate-300"
                            >
                                76543210 (Regular)
                            </button>
                        </div>

                        <!-- Resultado búsqueda en el modal -->
                        <div
                            v-if="modalLookupSearched && modalLookupResult"
                            class="rounded-lg border p-3 text-xs"
                            :class="
                                isModalMatchingCourse
                                    ? 'border-emerald-200 bg-emerald-50/60'
                                    : 'border-amber-300 bg-amber-50/80'
                            "
                        >
                            <div
                                class="flex flex-col justify-between gap-3 md:flex-row md:items-center"
                            >
                                <div>
                                    <div
                                        class="flex items-center gap-2 font-bold text-slate-900"
                                    >
                                        <span>{{
                                            modalLookupResult.full_name
                                        }}</span>
                                        <span
                                            class="font-mono text-[11px] font-normal text-slate-600"
                                            >DNI:
                                            {{
                                                modalLookupResult.document_number
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        class="mt-0.5 text-[11px] text-slate-600"
                                    >
                                        Curso Oficial:
                                        <strong>{{
                                            modalLookupResult.enrolled_course_name
                                        }}</strong>
                                    </div>
                                </div>

                                <div
                                    v-if="isModalMatchingCourse"
                                    class="flex items-center gap-2"
                                >
                                    <span
                                        class="text-[11px] font-bold text-[#2e8b57]"
                                        >✓ Alumno regular de este curso</span
                                    >
                                    <button
                                        type="button"
                                        @click="
                                            registerModalAttendance('presente')
                                        "
                                        class="cursor-pointer rounded-md bg-[#2e8b57] px-3 py-1 font-bold text-white transition hover:bg-[#257347]"
                                    >
                                        Marcar Presente
                                    </button>
                                </div>

                                <div v-else class="space-y-2">
                                    <div
                                        class="flex items-center gap-1 text-[11px] font-bold text-amber-900"
                                    >
                                        <span
                                            >⚠️ Excepción: Pertenece a "{{
                                                modalLookupResult.enrolled_course_name
                                            }}" pero asiste aquí</span
                                        >
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <select
                                            v-model="modalCrossReason"
                                            class="rounded border border-amber-300 bg-white px-2 py-1 text-xs font-bold text-slate-800"
                                        >
                                            <option
                                                value="Recuperación de clase"
                                            >
                                                Recuperación de clase
                                            </option>
                                            <option
                                                value="Cambio temporal de grupo"
                                            >
                                                Cambio temporal de grupo
                                            </option>
                                            <option
                                                value="Práctica en ambiente libre"
                                            >
                                                Práctica en ambiente libre
                                            </option>
                                            <option
                                                value="Autorización verbal de Dirección"
                                            >
                                                Autorización de Dirección
                                            </option>
                                        </select>
                                        <button
                                            type="button"
                                            @click="
                                                registerModalAttendance(
                                                    'presente',
                                                )
                                            "
                                            class="cursor-pointer rounded bg-amber-600 px-3 py-1 text-xs font-bold text-white shadow-2xs transition hover:bg-amber-700"
                                        >
                                            ✓ Autorizar e Incluir en la Lista
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="
                                modalLookupSearched && !modalLookupResult
                            "
                            class="rounded-lg border border-red-200 bg-red-50 p-2.5 text-[11px] font-semibold text-red-700"
                        >
                            No se encontró ningún estudiante con ese DNI
                            registrado en la base de datos del CETPRO.
                        </div>
                    </div>
                </div>

                <!-- Lista de Alumnos para Marcado -->
                <div
                    class="flex-1 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200"
                >
                    <div
                        v-for="student in activeRollCallSession.students"
                        :key="student.student_id"
                        class="flex flex-col gap-3 p-4 transition hover:bg-slate-50/70 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-sm font-bold text-slate-900"
                                    >{{ student.full_name }}</span
                                >
                                <span
                                    class="font-mono text-xs font-medium text-slate-500"
                                    >DNI: {{ student.document_number }}</span
                                >
                                <span
                                    v-if="student.is_cross_attendance"
                                    class="rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-[10px] font-bold text-purple-700"
                                >
                                    Asistencia Cruzada
                                </span>
                            </div>

                            <div
                                v-if="student.notes"
                                class="mt-0.5 text-xs text-purple-700 italic"
                            >
                                Motivo: {{ student.notes }} (Origen:
                                {{
                                    student.origin_course_name || 'Otro curso'
                                }})
                            </div>
                            <div
                                v-if="student.registered_at"
                                class="mt-0.5 font-mono text-[11px] text-slate-400"
                            >
                                Hora de marcado: {{ student.registered_at }}
                            </div>
                        </div>

                        <!-- Botones de Marcado -->
                        <div class="flex shrink-0 items-center gap-1.5">
                            <button
                                @click="
                                    updateStudentStatus(student, 'presente')
                                "
                                :class="[
                                    student.status === 'presente'
                                        ? 'bg-[#2e8b57] font-bold text-white shadow-2xs'
                                        : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                                    'cursor-pointer rounded-md px-2.5 py-1.5 text-xs transition',
                                ]"
                            >
                                Presente
                            </button>
                            <button
                                @click="
                                    updateStudentStatus(student, 'tardanza')
                                "
                                :class="[
                                    student.status === 'tardanza'
                                        ? 'bg-amber-600 font-bold text-white shadow-2xs'
                                        : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                                    'cursor-pointer rounded-md px-2.5 py-1.5 text-xs transition',
                                ]"
                            >
                                Tardanza
                            </button>
                            <button
                                @click="updateStudentStatus(student, 'falta')"
                                :class="[
                                    student.status === 'falta'
                                        ? 'bg-red-600 font-bold text-white shadow-2xs'
                                        : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                                    'cursor-pointer rounded-md px-2.5 py-1.5 text-xs transition',
                                ]"
                            >
                                Falta
                            </button>
                            <button
                                @click="
                                    updateStudentStatus(student, 'justificado')
                                "
                                :class="[
                                    student.status === 'justificado'
                                        ? 'bg-blue-600 font-bold text-white shadow-2xs'
                                        : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                                    'cursor-pointer rounded-md px-2.5 py-1.5 text-xs transition',
                                ]"
                            >
                                Justificado
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer del Modal -->
                <div
                    class="flex shrink-0 items-center justify-end gap-3 border-t border-slate-100 pt-3"
                >
                    <button
                        @click="isRollCallModalOpen = false"
                        class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                    >
                        Cerrar
                    </button>
                    <button
                        @click="saveRollCall"
                        class="cursor-pointer rounded-lg bg-[#2e8b57] px-5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-[#257347]"
                    >
                        Guardar Registro de Asistencia
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
