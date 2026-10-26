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

export interface CourseOption {
    id: number;
    name: string;
    duration_in_weeks: number;
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
    classrooms: Classroom[];
    courses: CourseOption[];
    teachers?: TeacherItem[];
    scheduledCourses?: ScheduledCourseItem[];
    enrollments: EnrollmentRecord[];
    selectedBranchId?: number | 'all';
    activeTab?: 'inscripciones' | 'aperturas';
    showSubtabsNav?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    teachers: () => [],
    scheduledCourses: () => [],
    selectedBranchId: 'all',
    activeTab: 'inscripciones',
    showSubtabsNav: false,
});

const emit = defineEmits<{
    (e: 'notify', message: string): void;
    (e: 'update:activeTab', tab: 'inscripciones' | 'aperturas'): void;
}>();

// Pestaña activa del módulo de Matrículas (sincronizada)
type MatriculasTab = 'inscripciones' | 'aperturas';
const currentTab = ref<MatriculasTab>(props.activeTab);

watch(
    () => props.activeTab,
    (newVal) => {
        if (newVal) currentTab.value = newVal;
    },
);

function changeTab(tab: MatriculasTab) {
    currentTab.value = tab;
    emit('update:activeTab', tab);
}

// Control de Perfiles y Autorizaciones
const page = usePage<{ auth?: { user?: AuthUser } }>();

const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    if (!user?.staff) return false;
    return user.staff.branch_id === null;
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

// REGLA CLAVE: "la secretaria puede matricular pero solo coordinacion puede crear uno nuevo curso/aula"
const canManageAperturas = computed(() => {
    if (isAdmin.value) return true;
    if (isCoordinador.value) {
        const staffBranch = page.props.auth?.user?.staff?.branch_id;
        if (props.selectedBranchId === 'all') return true;
        return staffBranch === props.selectedBranchId;
    }
    return false;
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

// --- ZONA HORARIA DE PERÚ (America/Lima) ---
function getPeruToday(): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima' }).format(new Date());
}

function getPeruFutureDate(daysAhead: number): string {
    const d = new Date();
    d.setDate(d.getDate() + daysAhead);
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima' }).format(d);
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

// Cursos programados computados desde props
const scheduledCourses = computed(() => props.scheduledCourses);

// --- 1. FILTROS Y ESTADOS PARA MATRÍCULAS ---
const searchEnrollmentQuery = ref('');
const statusEnrollmentFilter = ref<'all' | EnrollmentStatus>('all');

const filteredEnrollments = computed(() => {
    return props.enrollments.filter((record) => {
        if (props.selectedBranchId !== 'all') {
            const currentBranch = props.branches.find(
                (b) => b.id === props.selectedBranchId,
            );
            if (currentBranch && record.branch_name !== currentBranch.name) {
                return false;
            }
        }
        if (
            statusEnrollmentFilter.value !== 'all' &&
            record.status !== statusEnrollmentFilter.value
        ) {
            return false;
        }
        if (searchEnrollmentQuery.value.trim() !== '') {
            const q = searchEnrollmentQuery.value.toLowerCase();
            return (
                record.student_full_name.toLowerCase().includes(q) ||
                record.student_document.includes(q) ||
                record.course_name.toLowerCase().includes(q)
            );
        }
        return true;
    });
});

// --- 2. MODAL NUEVA MATRÍCULA / CONVALIDACIÓN ---
const showEnrollmentModal = ref(false);
const enrollmentForm = useForm({
    scheduled_course_id: 1,
    student_full_name: '',
    student_document: '',
    enrollment_date: getPeruToday(),
    status: 'activo' as EnrollmentStatus,
    notes: '',
});

function openNewEnrollmentModal(preselectedCourseId?: number) {
    if (!canEnroll.value) {
        emit('notify', 'No cuenta con permisos para registrar matrículas en esta sede.');
        return;
    }
    enrollmentForm.reset();
    enrollmentForm.clearErrors();
    enrollmentForm.scheduled_course_id =
        preselectedCourseId || scheduledCourses.value[0]?.id || 1;
    enrollmentForm.enrollment_date = getPeruToday();
    enrollmentForm.status = 'activo';
    enrollmentForm.notes = '';
    showEnrollmentModal.value = true;
}

function saveNewEnrollment() {
    if (!canEnroll.value) return;
    enrollmentForm.post('/admin/matriculas', {
        preserveScroll: true,
        onSuccess: () => {
            showEnrollmentModal.value = false;
            emit('notify', `Matrícula registrada con éxito para ${enrollmentForm.student_full_name}.`);
        },
    });
}

// --- 3. MODAL CAMBIAR ESTADO DE MATRÍCULA ---
const showChangeStatusModal = ref(false);
const selectedEnrollment = ref<EnrollmentRecord | null>(null);
const newStatusToApply = ref<EnrollmentStatus>('activo');
const statusChangeNotes = ref('');

function openChangeStatusModal(record: EnrollmentRecord) {
    if (!canEnroll.value) {
        emit('notify', 'No cuenta con permisos para modificar matrículas.');
        return;
    }
    selectedEnrollment.value = record;
    newStatusToApply.value = record.status;
    statusChangeNotes.value = record.notes || '';
    showChangeStatusModal.value = true;
}

function applyStatusChange() {
    if (!selectedEnrollment.value || !canEnroll.value) return;
    router.put(
        `/admin/matriculas/${selectedEnrollment.value.id}/status`,
        {
            status: newStatusToApply.value,
            notes: statusChangeNotes.value.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showChangeStatusModal.value = false;
                emit('notify', `Estado de ${selectedEnrollment.value?.student_full_name} actualizado.`);
            },
        },
    );
}

// --- 4. MODAL APERTURAR / EDITAR CURSO Y AULA (scheduled_courses) ---
const showScheduleCourseModal = ref(false);
const editingScheduleCourseId = ref<number | null>(null);

const scheduleForm = useForm({
    course_id: 1,
    branch_id: 1,
    classroom_id: 1,
    teacher_id: null as number | null,
    start_date: getPeruToday(),
    end_date: getPeruFutureDate(30),
    days: ['lunes', 'miercoles'] as string[],
    start_time: '18:00',
    end_time: '20:00',
});

const weekDays = [
    { value: 'lunes', label: 'Lunes' },
    { value: 'martes', label: 'Martes' },
    { value: 'miercoles', label: 'Miércoles' },
    { value: 'jueves', label: 'Jueves' },
    { value: 'viernes', label: 'Viernes' },
    { value: 'sabado', label: 'Sábado' },
    { value: 'domingo', label: 'Domingo' },
];

function toggleDay(dayVal: string) {
    const idx = scheduleForm.days.indexOf(dayVal);
    if (idx >= 0) {
        if (scheduleForm.days.length > 1) {
            scheduleForm.days.splice(idx, 1);
        }
    } else {
        scheduleForm.days.push(dayVal);
    }
}

// Aulas disponibles según la sede seleccionada en el formulario
const availableClassroomsForBranch = computed(() => {
    return props.classrooms.filter(
        (c) => c.branch_id === scheduleForm.branch_id,
    );
});

// Sincronizar aula al cambiar la sede
watch(
    () => scheduleForm.branch_id,
    (newBranchId) => {
        const rooms = props.classrooms.filter((c) => c.branch_id === newBranchId);
        if (rooms.length > 0 && !rooms.some((r) => r.id === scheduleForm.classroom_id)) {
            scheduleForm.classroom_id = rooms[0].id;
        }
    },
);

function openNewScheduleCourseModal() {
    if (!canManageAperturas.value) {
        emit('notify', 'Solo el personal de Coordinación o Dirección General puede aperturar cursos.');
        return;
    }
    editingScheduleCourseId.value = null;
    scheduleForm.reset();
    scheduleForm.clearErrors();

    const initialBranchId =
        props.selectedBranchId !== 'all'
            ? props.selectedBranchId
            : props.branches[0]?.id || 1;

    scheduleForm.course_id = props.courses[0]?.id || 1;
    scheduleForm.branch_id = initialBranchId;

    const initialRooms = props.classrooms.filter((c) => c.branch_id === initialBranchId);
    scheduleForm.classroom_id = initialRooms[0]?.id || props.classrooms[0]?.id || 1;

    scheduleForm.teacher_id = props.teachers[0]?.id || null;
    scheduleForm.start_date = getPeruToday();
    scheduleForm.end_date = getPeruFutureDate(30);
    scheduleForm.days = ['lunes', 'miercoles'];
    scheduleForm.start_time = '18:00';
    scheduleForm.end_time = '20:00';

    showScheduleCourseModal.value = true;
}

function openEditScheduleCourseModal(sched: ScheduledCourseItem) {
    if (!canManageAperturas.value) {
        emit('notify', 'Solo el personal de Coordinación o Dirección General puede modificar aperturas.');
        return;
    }
    editingScheduleCourseId.value = sched.id;
    scheduleForm.clearErrors();

    scheduleForm.course_id = sched.course_id;
    scheduleForm.branch_id = sched.branch_id;
    scheduleForm.classroom_id = sched.classroom_id;
    scheduleForm.teacher_id = sched.teacher_id ?? null;
    scheduleForm.start_date = sched.start_date;
    scheduleForm.end_date = sched.end_date;
    scheduleForm.days = sched.days && sched.days.length > 0 ? [...sched.days] : ['lunes', 'miercoles'];
    scheduleForm.start_time = sched.start_time || '18:00';
    scheduleForm.end_time = sched.end_time || '20:00';

    showScheduleCourseModal.value = true;
}

function saveScheduleCourse() {
    if (!canManageAperturas.value) return;

    if (editingScheduleCourseId.value) {
        scheduleForm.put(`/admin/cursos-aperturados/${editingScheduleCourseId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                showScheduleCourseModal.value = false;
                emit('notify', 'Curso aperturado y horario actualizados con éxito.');
            },
        });
    } else {
        scheduleForm.post('/admin/cursos-aperturados', {
            preserveScroll: true,
            onSuccess: () => {
                showScheduleCourseModal.value = false;
                emit('notify', 'Curso aperturado exitosamente en la sede y aula asignada.');
            },
        });
    }
}

function deleteScheduleCourse(sched: ScheduledCourseItem) {
    if (!canManageAperturas.value) return;
    if (sched.enrolled_count > 0) {
        emit('notify', 'No se puede eliminar la apertura porque ya cuenta con estudiantes matriculados.');
        return;
    }

    if (confirm(`¿Está seguro de eliminar la apertura del curso "${sched.course_name}" en ${sched.branch_name}?`)) {
        router.delete(`/admin/cursos-aperturados/${sched.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('notify', 'Apertura de curso eliminada del sistema.');
            },
        });
    }
}

// Búsqueda y filtros en Cursos Aperturados
const searchScheduledQuery = ref('');
const scheduledStatusFilter = ref<'all' | 'en_curso' | 'programado' | 'finalizado'>('all');
const scheduledDayFilter = ref<'all' | 'domingo' | 'sabado' | 'semana'>('all');

const filteredScheduledCourses = computed(() => {
    return scheduledCourses.value.filter((sched) => {
        if (
            props.selectedBranchId !== 'all' &&
            sched.branch_id !== props.selectedBranchId
        ) {
            return false;
        }
        if (
            scheduledStatusFilter.value !== 'all' &&
            sched.status !== scheduledStatusFilter.value
        ) {
            return false;
        }
        if (scheduledDayFilter.value !== 'all') {
            const days = sched.days || [];
            const dayText = (sched.day_of_week || '').toLowerCase();
            if (scheduledDayFilter.value === 'domingo') {
                if (!days.includes('domingo') && !dayText.includes('domingo')) return false;
            } else if (scheduledDayFilter.value === 'sabado') {
                if (!days.includes('sabado') && !dayText.includes('sábado') && !dayText.includes('sabado')) return false;
            } else if (scheduledDayFilter.value === 'semana') {
                const isOnlyWeekend = days.every((d) => d === 'sabado' || d === 'domingo');
                if (isOnlyWeekend && days.length > 0) return false;
            }
        }
        if (searchScheduledQuery.value.trim() !== '') {
            const q = searchScheduledQuery.value.toLowerCase();
            return (
                sched.course_name.toLowerCase().includes(q) ||
                sched.classroom_name.toLowerCase().includes(q) ||
                sched.teacher_name.toLowerCase().includes(q) ||
                sched.branch_name.toLowerCase().includes(q) ||
                sched.day_of_week.toLowerCase().includes(q)
            );
        }
        return true;
    });
});
</script>

<template>
    <div class="space-y-6">
        <!-- Sub-pestañas opcionales (cuando se usa con selector interno) -->
        <div v-if="showSubtabsNav" class="border-b border-slate-200">
            <nav class="flex space-x-8" aria-label="Tabs">
                <button
                    @click="changeTab('inscripciones')"
                    :class="[
                        currentTab === 'inscripciones'
                            ? 'border-[#2e8b57] font-bold text-[#2e8b57]'
                            : 'border-transparent font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700',
                        'flex cursor-pointer items-center gap-2 border-b-2 px-1 py-3 text-sm whitespace-nowrap',
                    ]"
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
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                        />
                    </svg>
                    <span>Inscripciones & Convalidaciones</span>
                    <span
                        class="rounded-full bg-slate-100 px-2.5 py-0.5 font-mono text-xs text-slate-600"
                    >
                        {{ enrollments.length }}
                    </span>
                </button>

                <button
                    @click="changeTab('aperturas')"
                    :class="[
                        currentTab === 'aperturas'
                            ? 'border-[#2e8b57] font-bold text-[#2e8b57]'
                            : 'border-transparent font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700',
                        'flex cursor-pointer items-center gap-2 border-b-2 px-1 py-3 text-sm whitespace-nowrap',
                    ]"
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
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                    <span>Apertura de Cursos / Aulas</span>
                    <span
                        class="rounded-full bg-slate-100 px-2.5 py-0.5 font-mono text-xs text-slate-600"
                    >
                        {{ scheduledCourses.length }}
                    </span>
                </button>
            </nav>
        </div>

        <!-- ========================================== -->
        <!-- SECCIÓN 1: INSCRIPCIONES & CONVALIDACIONES -->
        <!-- ========================================== -->
        <div v-if="currentTab === 'inscripciones'" class="space-y-6">
            <!-- Barra de Búsqueda, Filtros de Estado y Botón de Nueva Matrícula -->
            <div
                class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
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
                            v-model="searchEnrollmentQuery"
                            type="text"
                            placeholder="Buscar alumno por DNI, nombres o curso..."
                            class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-11 text-sm text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:ring-1 focus:ring-[#2e8b57] focus:outline-none"
                        />
                    </div>

                    <button
                        v-if="canEnroll"
                        @click="openNewEnrollmentModal()"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347]"
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
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                            />
                        </svg>
                        <span>+ Nueva Matrícula / Convalidación</span>
                    </button>
                </div>

                <!-- Filtros rápidos por estado de matrícula (enum_enrollment_status) -->
                <div
                    class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-2"
                >
                    <span
                        class="mr-1 text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Filtrar Estado:</span
                    >
                    <button
                        @click="statusEnrollmentFilter = 'all'"
                        :class="[
                            statusEnrollmentFilter === 'all'
                                ? 'bg-slate-800 font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Todos
                    </button>
                    <button
                        @click="statusEnrollmentFilter = 'activo'"
                        :class="[
                            statusEnrollmentFilter === 'activo'
                                ? 'bg-[#2e8b57] font-bold text-white'
                                : 'bg-emerald-50 font-semibold text-[#2e8b57] hover:bg-emerald-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Activos
                    </button>
                    <button
                        @click="statusEnrollmentFilter = 'convalidado'"
                        :class="[
                            statusEnrollmentFilter === 'convalidado'
                                ? 'bg-purple-700 font-bold text-white'
                                : 'bg-purple-50 font-semibold text-purple-700 hover:bg-purple-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Convalidados
                    </button>
                    <button
                        @click="statusEnrollmentFilter = 'preinscrito'"
                        :class="[
                            statusEnrollmentFilter === 'preinscrito'
                                ? 'bg-amber-600 font-bold text-white'
                                : 'bg-amber-50 font-semibold text-amber-700 hover:bg-amber-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Preinscritos
                    </button>
                    <button
                        @click="statusEnrollmentFilter = 'completado'"
                        :class="[
                            statusEnrollmentFilter === 'completado'
                                ? 'bg-blue-600 font-bold text-white'
                                : 'bg-blue-50 font-semibold text-blue-700 hover:bg-blue-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Completados
                    </button>
                    <button
                        @click="statusEnrollmentFilter = 'retirado'"
                        :class="[
                            statusEnrollmentFilter === 'retirado'
                                ? 'bg-slate-600 font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-700 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Retirados
                    </button>
                </div>
            </div>

            <!-- Tabla de Inscripciones y Convalidaciones -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-700">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 text-xs font-bold tracking-wider text-slate-600 uppercase"
                        >
                            <tr>
                                <th scope="col" class="py-4 pr-4 pl-6">
                                    Estudiante / DNI
                                </th>
                                <th scope="col" class="px-4 py-4">
                                    Curso y Módulo Asignado
                                </th>
                                <th scope="col" class="px-4 py-4">
                                    Sede y Fecha
                                </th>
                                <th scope="col" class="px-4 py-4 text-center">
                                    Estado de Matrícula
                                </th>
                                <th
                                    scope="col"
                                    class="py-4 pr-6 pl-4 text-right"
                                >
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="record in filteredEnrollments"
                                :key="record.id"
                                class="transition hover:bg-emerald-50/40"
                            >
                                <td class="py-4 pr-4 pl-6">
                                    <div
                                        class="text-sm font-bold text-slate-900"
                                    >
                                        {{ record.student_full_name }}
                                    </div>
                                    <div
                                        class="font-mono text-xs text-slate-500"
                                    >
                                        DNI: {{ record.student_document }}
                                    </div>
                                    <div
                                        v-if="record.notes"
                                        class="mt-0.5 text-[11px] text-purple-700 italic"
                                    >
                                        Obs: {{ record.notes }}
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="font-bold text-slate-900">
                                        {{ record.course_name }}
                                    </div>
                                    <div
                                        class="text-xs font-medium text-slate-500"
                                    >
                                        {{
                                            record.module_name || 'Curso Libre'
                                        }}
                                        <span
                                            v-if="record.career_name"
                                            class="text-slate-400"
                                            >&bull;
                                            {{ record.career_name }}</span
                                        >
                                    </div>
                                </td>

                                <td class="px-4 py-4 text-xs whitespace-nowrap">
                                    <div class="font-semibold text-slate-800">
                                        {{ record.branch_name }}
                                    </div>
                                    <div class="font-mono text-slate-400">
                                        Inscrito: {{ formatPeruDate(record.enrollment_date) }}
                                    </div>
                                </td>

                                <td
                                    class="px-4 py-4 text-center whitespace-nowrap"
                                >
                                    <span
                                        v-if="record.status === 'activo'"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-[#2e8b57]"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-[#2e8b57]"
                                        ></span>
                                        Activo
                                    </span>
                                    <span
                                        v-else-if="
                                            record.status === 'convalidado'
                                        "
                                        class="inline-flex items-center gap-1.5 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-bold text-purple-700"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-purple-700"
                                        ></span>
                                        Convalidado
                                    </span>
                                    <span
                                        v-else-if="
                                            record.status === 'preinscrito'
                                        "
                                        class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-amber-600"
                                        ></span>
                                        Preinscrito
                                    </span>
                                    <span
                                        v-else-if="
                                            record.status === 'completado'
                                        "
                                        class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-blue-600"
                                        ></span>
                                        Completado
                                    </span>
                                    <span
                                        v-else-if="record.status === 'retirado'"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600"
                                    >
                                        Retirado
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-bold text-red-600"
                                    >
                                        Anulado
                                    </span>
                                </td>

                                <td
                                    class="py-4 pr-6 pl-4 text-right whitespace-nowrap"
                                >
                                    <button
                                        v-if="canEnroll"
                                        @click="openChangeStatusModal(record)"
                                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-[#2e8b57]"
                                    >
                                        <svg
                                            class="h-4 w-4 text-slate-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                            />
                                        </svg>
                                        <span>Modificar Estado</span>
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="filteredEnrollments.length === 0">
                                <td
                                    colspan="5"
                                    class="py-12 text-center font-medium text-slate-400"
                                >
                                    No se encontraron registros de matrícula con
                                    los filtros seleccionados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================================================== -->
        <!-- SECCIÓN 2: APERTURA DE CURSOS Y AULAS (LISTA/TABLA)  -->
        <!-- ================================================== -->
        <div v-else class="space-y-6">
            <!-- Barra Superior: Búsqueda, Filtros de Estado y Botón de Apertura -->
            <div
                class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
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
                            v-model="searchScheduledQuery"
                            type="text"
                            placeholder="Buscar curso aperturado, aula, docente o sede..."
                            class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-11 text-sm text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        />
                    </div>

                    <button
                        v-if="canManageAperturas"
                        @click="openNewScheduleCourseModal"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347]"
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
                        <span>+ Aperturar Nuevo Curso / Aula</span>
                    </button>

                    <div
                        v-else-if="isSecretaria"
                        class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50/70 px-3.5 py-2 text-xs font-medium text-emerald-800"
                    >
                        <svg class="h-4 w-4 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Secretaría: Habilitada para matricular alumnos en cursos aperturados por Coordinación.</span>
                    </div>
                </div>

                <!-- Filtros por estado del curso programado -->
                <div
                    class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-2"
                >
                    <span
                        class="mr-1 text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Estado de Apertura:</span
                    >
                    <button
                        @click="scheduledStatusFilter = 'all'"
                        :class="[
                            scheduledStatusFilter === 'all'
                                ? 'bg-slate-800 font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Todos ({{ scheduledCourses.length }})
                    </button>
                    <button
                        @click="scheduledStatusFilter = 'en_curso'"
                        :class="[
                            scheduledStatusFilter === 'en_curso'
                                ? 'bg-[#2e8b57] font-bold text-white'
                                : 'bg-emerald-50 font-semibold text-[#2e8b57] hover:bg-emerald-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        En Dictado Activo
                    </button>
                    <button
                        @click="scheduledStatusFilter = 'programado'"
                        :class="[
                            scheduledStatusFilter === 'programado'
                                ? 'bg-amber-600 font-bold text-white'
                                : 'bg-amber-50 font-semibold text-amber-700 hover:bg-amber-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Programados
                    </button>
                    <button
                        @click="scheduledStatusFilter = 'finalizado'"
                        :class="[
                            scheduledStatusFilter === 'finalizado'
                                ? 'bg-slate-700 font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Finalizados
                    </button>

                    <!-- Divisor y Filtros por Día de Dictado (Lunes a Domingo) -->
                    <span class="mx-1 text-slate-300">|</span>
                    <span
                        class="mr-1 text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Días de Clase:</span
                    >
                    <button
                        @click="scheduledDayFilter = 'all'"
                        :class="[
                            scheduledDayFilter === 'all'
                                ? 'bg-slate-800 font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Todos
                    </button>
                    <button
                        @click="scheduledDayFilter = 'domingo'"
                        :class="[
                            scheduledDayFilter === 'domingo'
                                ? 'bg-purple-700 font-bold text-white shadow-xs'
                                : 'bg-purple-50 font-semibold text-purple-700 hover:bg-purple-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Solo Domingos
                    </button>
                    <button
                        @click="scheduledDayFilter = 'sabado'"
                        :class="[
                            scheduledDayFilter === 'sabado'
                                ? 'bg-indigo-700 font-bold text-white shadow-xs'
                                : 'bg-indigo-50 font-semibold text-indigo-700 hover:bg-indigo-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Solo Sábados
                    </button>
                    <button
                        @click="scheduledDayFilter = 'semana'"
                        :class="[
                            scheduledDayFilter === 'semana'
                                ? 'bg-[#2e8b57] font-bold text-white shadow-xs'
                                : 'bg-emerald-50 font-semibold text-[#2e8b57] hover:bg-emerald-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Lunes a Viernes
                    </button>
                </div>
            </div>

            <!-- TABLA / LISTA DE CURSOS APERTURADOS -->
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
                                    Curso / Materia Aperturada
                                </th>
                                <th class="px-4 py-4">Sede & Aula Física</th>
                                <th class="px-4 py-4">Periodo de Vigencia</th>
                                <th class="px-4 py-4">Horarios & Docente</th>
                                <th class="px-4 py-4">Aforo & Vacantes</th>
                                <th class="py-4 pr-6 pl-4 text-right">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="sched in filteredScheduledCourses"
                                :key="sched.id"
                                class="transition hover:bg-emerald-50/30"
                            >
                                <!-- Columna 1: Curso / Materia -->
                                <td class="py-4 pr-4 pl-6">
                                    <div class="mb-1 flex items-center gap-2">
                                        <span
                                            :class="[
                                                sched.status === 'en_curso'
                                                    ? 'border-emerald-200 bg-emerald-50 text-[#2e8b57]'
                                                    : sched.status === 'programado'
                                                    ? 'border-amber-200 bg-amber-50 text-amber-700'
                                                    : 'border-slate-200 bg-slate-100 text-slate-600',
                                                'rounded border px-2 py-0.5 font-mono text-[11px] font-bold uppercase',
                                            ]"
                                        >
                                            {{ sched.status === 'en_curso' ? 'En Dictado' : sched.status === 'programado' ? 'Programado' : 'Finalizado' }}
                                        </span>
                                        <span
                                            class="font-mono text-xs font-medium text-slate-400"
                                            >SCH-{{ sched.id }}</span
                                        >
                                    </div>
                                    <div
                                        class="text-sm font-bold text-slate-900"
                                    >
                                        {{ sched.course_name }}
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
                                                sched.branch_name.includes(
                                                    'Comas',
                                                )
                                                    ? 'bg-[#2e8b57]'
                                                    : 'bg-teal-600'
                                            "
                                        ></span>
                                        {{ sched.branch_name }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-xs font-medium text-slate-500"
                                    >
                                        {{ sched.classroom_name }}
                                    </div>
                                </td>

                                <!-- Columna 3: Periodo de Vigencia -->
                                <td class="px-4 py-4 text-xs whitespace-nowrap">
                                    <div
                                        class="flex items-center gap-1.5 font-mono font-bold text-slate-800"
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
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                        </svg>
                                        <span>{{ formatPeruDate(sched.start_date) }}</span>
                                        <span class="font-normal text-slate-400"
                                            >al</span
                                        >
                                        <span>{{ formatPeruDate(sched.end_date) }}</span>
                                    </div>
                                    <div
                                        class="mt-0.5 text-[11px] text-slate-400"
                                    >
                                        Programación activa
                                    </div>
                                </td>

                                <!-- Columna 4: Horarios & Docente -->
                                <td class="px-4 py-4 text-xs">
                                    <div class="font-bold text-[#2e8b57]">
                                        {{ sched.day_of_week }}
                                    </div>
                                    <div
                                        class="mt-0.5 font-mono text-slate-600"
                                    >
                                        {{ sched.time_range }}
                                    </div>
                                    <div
                                        class="mt-1 text-[11px] font-medium text-slate-500"
                                    >
                                        Prof: {{ sched.teacher_name }}
                                    </div>
                                </td>

                                <!-- Columna 5: Aforo & Ocupabilidad -->
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div
                                        class="mb-1 flex items-center justify-between gap-2 text-xs"
                                    >
                                        <span
                                            class="font-mono font-bold text-slate-800"
                                            >{{ sched.enrolled_count }} /
                                            {{ sched.capacity }}</span
                                        >
                                        <span
                                            class="font-mono text-[11px] font-bold text-slate-500"
                                            >{{
                                                Math.round(
                                                    (sched.enrolled_count /
                                                        sched.capacity) *
                                                        100,
                                                )
                                            }}%</span
                                        >
                                    </div>
                                    <div
                                        class="h-2 w-32 overflow-hidden rounded-full border border-slate-200 bg-slate-100"
                                    >
                                        <div
                                            class="h-2 rounded-full bg-[#2e8b57] transition-all duration-300"
                                            :style="{
                                                width: `${Math.min(100, Math.round((sched.enrolled_count / sched.capacity) * 100))}%`,
                                            }"
                                        ></div>
                                    </div>
                                    <div
                                        class="mt-1 text-[11px] text-slate-400"
                                    >
                                        {{
                                            sched.capacity -
                                            sched.enrolled_count
                                        }}
                                        vacantes disp.
                                    </div>
                                </td>

                                <!-- Columna 6: Acciones -->
                                <td
                                    class="py-4 pr-6 pl-4 text-right whitespace-nowrap"
                                >
                                    <div class="inline-flex items-center gap-2">
                                        <!-- Matricular (Secretaría, Coordinación, Admin) -->
                                        <button
                                            v-if="canEnroll"
                                            @click="
                                                openNewEnrollmentModal(sched.id)
                                            "
                                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-[#2e8b57] px-3 py-1.5 text-xs font-bold text-white shadow-2xs transition hover:bg-[#257347]"
                                            title="Matricular alumno a este curso"
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
                                                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                                                />
                                            </svg>
                                            <span>Matricular</span>
                                        </button>

                                        <!-- Editar Apertura (Solo Coordinación y Admin) -->
                                        <button
                                            v-if="canManageAperturas"
                                            @click="openEditScheduleCourseModal(sched)"
                                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-[#2e8b57]"
                                            title="Editar programación del curso y aula"
                                        >
                                            <svg
                                                class="h-3.5 w-3.5 text-slate-500"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                                />
                                            </svg>
                                            <span>Editar</span>
                                        </button>

                                        <!-- Eliminar Apertura (Solo Coordinación y Admin) -->
                                        <button
                                            v-if="canManageAperturas"
                                            @click="deleteScheduleCourse(sched)"
                                            :disabled="sched.enrolled_count > 0"
                                            :class="[
                                                sched.enrolled_count > 0
                                                    ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'
                                                    : 'cursor-pointer border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100',
                                                'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold shadow-2xs transition'
                                            ]"
                                            :title="sched.enrolled_count > 0 ? 'No se puede eliminar: tiene alumnos matriculados' : 'Eliminar apertura de curso'"
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
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                />
                                            </svg>
                                            <span>Eliminar</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filteredScheduledCourses.length === 0">
                                <td
                                    colspan="6"
                                    class="py-12 text-center font-medium text-slate-400"
                                >
                                    No se encontraron cursos aperturados para el
                                    filtro seleccionado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: NUEVA MATRÍCULA / CONVALIDACIÓN -->
        <!-- ============================================== -->
        <div
            v-if="showEnrollmentModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showEnrollmentModal = false"
        >
            <div
                class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 pb-4"
                >
                    <h3 class="text-lg font-bold text-slate-900">
                        Registrar Matrícula o Convalidación
                    </h3>
                    <button
                        @click="showEnrollmentModal = false"
                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        &times;
                    </button>
                </div>

                <form
                    @submit.prevent="saveNewEnrollment"
                    class="mt-5 space-y-4"
                >
                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Apellidos y Nombres del Estudiante</label
                        >
                        <input
                            v-model="enrollmentForm.student_full_name"
                            type="text"
                            required
                            placeholder="Ej. Mendoza Salazar, Diego"
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-sm font-bold text-slate-800"
                                >Documento DNI</label
                            >
                            <input
                                v-model="enrollmentForm.student_document"
                                type="text"
                                required
                                placeholder="8 dígitos"
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-bold text-slate-800"
                                >Fecha de Matrícula</label
                            >
                            <input
                                v-model="enrollmentForm.enrollment_date"
                                type="date"
                                required
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Curso Aperturado / Aula</label
                        >
                        <select
                            v-model="enrollmentForm.scheduled_course_id"
                            class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        >
                            <option
                                v-for="sched in scheduledCourses"
                                :key="sched.id"
                                :value="sched.id"
                            >
                                {{ sched.course_name }} &bull;
                                {{ sched.branch_name }} ({{
                                    sched.classroom_name
                                }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Estado de Matrícula</label
                        >
                        <select
                            v-model="enrollmentForm.status"
                            class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        >
                            <option value="activo">
                                Activo (Dictado Regular)
                            </option>
                            <option value="convalidado">
                                Convalidado (Aprobado por Convalidación)
                            </option>
                            <option value="preinscrito">
                                Preinscrito (Reserva de vacante)
                            </option>
                            <option value="completado">Completado</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Observaciones / Justificación de
                            Convalidación</label
                        >
                        <textarea
                            v-model="enrollmentForm.notes"
                            rows="2"
                            placeholder="Ej. Convalidado por estudios técnicos previos en Instituto / RD 045-2026"
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        ></textarea>
                    </div>

                    <!-- Alerta de Errores de Validación -->
                    <div
                        v-if="enrollmentForm.hasErrors"
                        class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 space-y-1"
                    >
                        <p class="font-bold">Por favor revise los siguientes campos:</p>
                        <p v-for="(err, key) in enrollmentForm.errors" :key="key">&bull; {{ err }}</p>
                    </div>

                    <div
                        class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-5"
                    >
                        <button
                            type="button"
                            @click="showEnrollmentModal = false"
                            class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="enrollmentForm.processing"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347] disabled:opacity-50"
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
                            <span>Registrar Matrícula</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: CAMBIAR ESTADO DE MATRÍCULA -->
        <!-- ============================================== -->
        <div
            v-if="showChangeStatusModal && selectedEnrollment"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showChangeStatusModal = false"
        >
            <div
                class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 pb-4"
                >
                    <h3 class="text-lg font-bold text-slate-900">
                        Modificar Estado de Matrícula
                    </h3>
                    <button
                        @click="showChangeStatusModal = false"
                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        &times;
                    </button>
                </div>

                <div class="space-y-4 py-4">
                    <div
                        class="space-y-1 rounded-lg border border-slate-200 bg-slate-50 p-3.5 text-xs"
                    >
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
                        <label class="block text-sm font-bold text-slate-800"
                            >Nuevo Estado</label
                        >
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
                        <label class="block text-sm font-bold text-slate-800"
                            >Nota / Motivo del Cambio</label
                        >
                        <textarea
                            v-model="statusChangeNotes"
                            rows="2"
                            placeholder="Ej. Solicitud de convalidación aprobada según expediente 1024."
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        ></textarea>
                    </div>
                </div>

                <div
                    class="mt-2 flex items-center justify-end gap-3 border-t border-slate-100 pt-4"
                >
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

        <!-- ============================================== -->
        <!-- MODAL: APERTURAR / EDITAR CURSO Y AULA (scheduled_courses) -->
        <!-- ============================================== -->
        <div
            v-if="showScheduleCourseModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showScheduleCourseModal = false"
        >
            <div
                class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 pb-4"
                >
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">
                            {{
                                editingScheduleCourseId
                                    ? 'Editar Programación de Curso y Aula'
                                    : 'Aperturar Curso y Aula por Periodo'
                            }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            Programa la oferta de un curso en un ambiente físico
                            con sus horarios semanales (Zona horaria: Perú).
                        </p>
                    </div>
                    <button
                        @click="showScheduleCourseModal = false"
                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        &times;
                    </button>
                </div>

                <form
                    @submit.prevent="saveScheduleCourse"
                    class="mt-5 space-y-4"
                >
                    <!-- Alerta de Errores de Validación -->
                    <div
                        v-if="scheduleForm.hasErrors"
                        class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 space-y-1"
                    >
                        <p class="font-bold">Por favor revise los siguientes campos:</p>
                        <p v-for="(err, key) in scheduleForm.errors" :key="key">&bull; {{ err }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Curso o Taller a Aperturar</label
                        >
                        <select
                            v-model="scheduleForm.course_id"
                            required
                            class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        >
                            <option
                                v-for="c in courses"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }} ({{ c.duration_in_weeks }} semanas)
                                {{ c.is_workshop ? '&bull; Taller Libre' : (c.module_name ? `&bull; Módulo: ${c.module_name}` : '') }}
                            </option>
                        </select>
                        <p v-if="scheduleForm.errors.course_id" class="mt-1 text-xs text-rose-600">{{ scheduleForm.errors.course_id }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-sm font-bold text-slate-800"
                                >Sede</label
                            >
                            <select
                                v-model="scheduleForm.branch_id"
                                required
                                class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            >
                                <option
                                    v-for="b in branches"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ b.name }} ({{ b.code }})
                                </option>
                            </select>
                            <p v-if="scheduleForm.errors.branch_id" class="mt-1 text-xs text-rose-600">{{ scheduleForm.errors.branch_id }}</p>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-bold text-slate-800"
                                >Aula / Ambiente Físico</label
                            >
                            <select
                                v-model="scheduleForm.classroom_id"
                                required
                                class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            >
                                <option
                                    v-for="room in availableClassroomsForBranch"
                                    :key="room.id"
                                    :value="room.id"
                                >
                                    {{ room.name }} ({{ room.capacity ?? 'Sin aforo' }} vacantes)
                                </option>
                            </select>
                            <p v-if="availableClassroomsForBranch.length === 0" class="mt-1 text-[11px] text-amber-600">
                                No hay aulas disponibles en esta sede.
                            </p>
                            <p v-if="scheduleForm.errors.classroom_id" class="mt-1 text-xs text-rose-600">{{ scheduleForm.errors.classroom_id }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-sm font-bold text-slate-800"
                                >Fecha de Inicio (Periodo)</label
                            >
                            <input
                                v-model="scheduleForm.start_date"
                                type="date"
                                required
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                            <p v-if="scheduleForm.errors.start_date" class="mt-1 text-xs text-rose-600">{{ scheduleForm.errors.start_date }}</p>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-bold text-slate-800"
                                >Fecha de Fin (Periodo)</label
                            >
                            <input
                                v-model="scheduleForm.end_date"
                                type="date"
                                required
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                            <p v-if="scheduleForm.errors.end_date" class="mt-1 text-xs text-rose-600">{{ scheduleForm.errors.end_date }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Días de Clase (Horarios semanales)</label
                        >
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                v-for="d in weekDays"
                                :key="d.value"
                                @click="toggleDay(d.value)"
                                :class="[
                                    scheduleForm.days.includes(d.value)
                                        ? 'bg-[#2e8b57] text-white font-bold shadow-xs'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold',
                                    'cursor-pointer rounded-lg px-3.5 py-1.5 text-xs transition'
                                ]"
                            >
                                {{ d.label }}
                            </button>
                        </div>
                        <p v-if="scheduleForm.errors.days" class="mt-1 text-xs text-rose-600">{{ scheduleForm.errors.days }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-sm font-bold text-slate-800"
                                >Hora de Inicio</label
                            >
                            <input
                                v-model="scheduleForm.start_time"
                                type="time"
                                required
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                            <p v-if="scheduleForm.errors.start_time" class="mt-1 text-xs text-rose-600">{{ scheduleForm.errors.start_time }}</p>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-bold text-slate-800"
                                >Hora de Fin</label
                            >
                            <input
                                v-model="scheduleForm.end_time"
                                type="time"
                                required
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                            <p v-if="scheduleForm.errors.end_time" class="mt-1 text-xs text-rose-600">{{ scheduleForm.errors.end_time }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Docente Asignado (Opcional)</label
                        >
                        <select
                            v-model="scheduleForm.teacher_id"
                            class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        >
                            <option :value="null">-- Docente por asignar / Sin docente --</option>
                            <option
                                v-for="t in teachers"
                                :key="t.id"
                                :value="t.id"
                            >
                                {{ t.full_name }} ({{ t.specialty }})
                            </option>
                        </select>
                        <p v-if="scheduleForm.errors.teacher_id" class="mt-1 text-xs text-rose-600">{{ scheduleForm.errors.teacher_id }}</p>
                    </div>

                    <div
                        class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-5"
                    >
                        <button
                            type="button"
                            @click="showScheduleCourseModal = false"
                            class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="scheduleForm.processing"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347] disabled:opacity-50"
                        >
                            <svg
                                v-if="scheduleForm.processing"
                                class="h-4 w-4 animate-spin text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{
                                editingScheduleCourseId
                                    ? 'Guardar Cambios'
                                    : 'Aperturar Curso'
                            }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
