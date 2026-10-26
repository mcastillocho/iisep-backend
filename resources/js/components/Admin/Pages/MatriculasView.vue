<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
    classrooms?: Classroom[];
    courses?: CourseOption[];
    scheduledCourses?: ScheduledCourseItem[];
    enrollments: EnrollmentRecord[];
    selectedBranchId?: number | 'all';
}

const props = withDefaults(defineProps<Props>(), {
    classrooms: () => [],
    courses: () => [],
    scheduledCourses: () => [],
    selectedBranchId: 'all',
});

const emit = defineEmits<{
    (e: 'notify', message: string): void;
}>();

// Auth y Roles
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

// Filtros y Búsqueda de Matrículas
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
                record.course_name.toLowerCase().includes(q) ||
                (record.module_name && record.module_name.toLowerCase().includes(q))
            );
        }
        return true;
    });
});

// ========================================================
// MODAL: NUEVA MATRÍCULA / CONVALIDACIÓN (COMPLETA)
// ========================================================
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
        preselectedCourseId || props.scheduledCourses[0]?.id || 1;
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

// ========================================================
// MODAL: CAMBIAR ESTADO DE MATRÍCULA
// ========================================================
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

function withdrawEnrollment(record: EnrollmentRecord) {
    if (!canEnroll.value) return;
    if (!confirm(`¿Está seguro de retirar la matrícula de "${record.student_full_name}" en ${record.course_name}?`)) return;

    router.delete(`/admin/matriculas/${record.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('notify', `Matrícula de ${record.student_full_name} retirada del sistema.`);
        },
    });
}
</script>

<template>
    <div class="space-y-6">
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
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-11 text-sm text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
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

            <!-- Filtros por estado de matrícula -->
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
                    Todos ({{ enrollments.length }})
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
                <table class="w-full text-left text-sm text-slate-600">
                    <thead
                        class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="py-4 pr-3 pl-6">Estudiante</th>
                            <th class="px-4 py-4">Curso & Sede</th>
                            <th class="px-4 py-4">Módulo / Carrera</th>
                            <th class="px-4 py-4">Fecha Matrícula</th>
                            <th class="px-4 py-4">Estado</th>
                            <th class="py-4 pr-6 pl-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="record in filteredEnrollments"
                            :key="record.id"
                            class="transition hover:bg-slate-50/80"
                        >
                            <!-- Estudiante -->
                            <td class="py-4 pr-3 pl-6">
                                <div class="font-bold text-slate-900">
                                    {{ record.student_full_name }}
                                </div>
                                <div
                                    class="mt-0.5 font-mono text-xs text-slate-500"
                                >
                                    DNI: {{ record.student_document }}
                                </div>
                            </td>

                            <!-- Curso & Sede -->
                            <td class="px-4 py-4 text-xs">
                                <div class="font-bold text-slate-800">
                                    {{ record.course_name }}
                                </div>
                                <div
                                    class="mt-0.5 flex items-center gap-1.5 text-slate-500"
                                >
                                    <span class="font-medium text-emerald-700">{{
                                        record.branch_name
                                    }}</span>
                                    <span v-if="record.classroom_name"
                                        >&bull; {{ record.classroom_name }}</span
                                    >
                                </div>
                            </td>

                            <!-- Módulo / Carrera -->
                            <td class="px-4 py-4 text-xs">
                                <div
                                    v-if="record.career_name"
                                    class="font-semibold text-slate-700"
                                >
                                    {{ record.career_name }}
                                </div>
                                <div
                                    v-if="record.module_name"
                                    class="text-slate-500"
                                >
                                    Módulo: {{ record.module_name }}
                                </div>
                                <div
                                    v-if="!record.career_name && !record.module_name"
                                    class="font-medium text-purple-700"
                                >
                                    Taller Independiente
                                </div>
                            </td>

                            <!-- Fecha Matrícula -->
                            <td
                                class="px-4 py-4 font-mono text-xs text-slate-600 whitespace-nowrap"
                            >
                                {{ formatPeruDate(record.enrollment_date) }}
                            </td>

                            <!-- Estado con Badge -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span
                                    v-if="record.status === 'activo'"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Activo
                                </span>
                                <span
                                    v-else-if="record.status === 'convalidado'"
                                    class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-bold text-purple-800"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-purple-500"></span>
                                    Convalidado
                                </span>
                                <span
                                    v-else-if="record.status === 'preinscrito'"
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    Preinscrito
                                </span>
                                <span
                                    v-else-if="record.status === 'completado'"
                                    class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-blue-800"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                    Completado
                                </span>
                                <span
                                    v-else-if="record.status === 'retirado'"
                                    class="inline-flex items-center gap-1 rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-bold text-slate-700"
                                >
                                    Retirado
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-800"
                                >
                                    Anulado
                                </span>

                                <div
                                    v-if="record.notes"
                                    class="mt-1 max-w-xs truncate text-[11px] text-slate-400"
                                    :title="record.notes"
                                >
                                    {{ record.notes }}
                                </div>
                            </td>

                            <!-- Acciones -->
                            <td class="py-4 pr-6 pl-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        v-if="canEnroll"
                                        @click="openChangeStatusModal(record)"
                                        class="cursor-pointer rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-[#2e8b57]"
                                    >
                                        Cambiar Estado
                                    </button>
                                    <button
                                        v-if="canEnroll"
                                        @click="withdrawEnrollment(record)"
                                        class="cursor-pointer rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 shadow-2xs transition hover:bg-rose-100"
                                        title="Retirar matrícula"
                                    >
                                        Retirar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="filteredEnrollments.length === 0">
                            <td
                                colspan="6"
                                class="py-12 text-center font-medium text-slate-400"
                            >
                                No se encontraron registros de matrícula.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: REGISTRAR NUEVA MATRÍCULA               -->
        <!-- ============================================== -->
        <div
            v-if="showEnrollmentModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showEnrollmentModal = false"
        >
            <div
                class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
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

                <form @submit.prevent="saveNewEnrollment" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-800">
                            Apellidos y Nombres del Estudiante
                        </label>
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
                            <label class="block text-sm font-bold text-slate-800">
                                Documento DNI
                            </label>
                            <input
                                v-model="enrollmentForm.student_document"
                                type="text"
                                required
                                placeholder="8 dígitos"
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-800">
                                Fecha de Matrícula
                            </label>
                            <input
                                v-model="enrollmentForm.enrollment_date"
                                type="date"
                                required
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800">
                            Curso Aperturado / Aula
                        </label>
                        <select
                            v-model="enrollmentForm.scheduled_course_id"
                            class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        >
                            <option
                                v-for="sched in scheduledCourses"
                                :key="sched.id"
                                :value="sched.id"
                            >
                                {{ sched.course_name }} &bull; {{ sched.branch_name }} ({{ sched.classroom_name }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800">
                            Estado de Matrícula
                        </label>
                        <select
                            v-model="enrollmentForm.status"
                            class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        >
                            <option value="activo">Activo (Dictado Regular)</option>
                            <option value="convalidado">Convalidado (Aprobado por Convalidación)</option>
                            <option value="preinscrito">Preinscrito (Reserva de vacante)</option>
                            <option value="completado">Completado</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800">
                            Observaciones / Justificación de Convalidación
                        </label>
                        <textarea
                            v-model="enrollmentForm.notes"
                            rows="2"
                            placeholder="Ej. Convalidado por estudios técnicos previos en Instituto / RD 045-2026"
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        ></textarea>
                    </div>

                    <div
                        v-if="enrollmentForm.hasErrors"
                        class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 space-y-1"
                    >
                        <p class="font-bold">Por favor revise los siguientes campos:</p>
                        <p v-for="(err, key) in enrollmentForm.errors" :key="key">&bull; {{ err }}</p>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
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
        <!-- MODAL: CAMBIAR ESTADO DE MATRÍCULA             -->
        <!-- ============================================== -->
        <div
            v-if="showChangeStatusModal && selectedEnrollment"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showChangeStatusModal = false"
        >
            <div
                class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
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
