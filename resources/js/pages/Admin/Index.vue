<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Sidebar from '../../components/Admin/Sidebar/Sidebar.vue';
import OfertaAcademicaView from '../../components/Admin/Pages/OfertaAcademicaView.vue';
import SedesAulasView from '../../components/Admin/Pages/SedesAulasView.vue';
import AperturasView from '../../components/Admin/Pages/AperturasView.vue';
import MatriculasView, {
    type EnrollmentRecord,
    type ScheduledCourseItem,
    type StudentRecord,
    type TeacherItem,
} from '../../components/Admin/Pages/MatriculasView.vue';
import { isStudentInBranch } from '../../utils/studentBranchFilter';
import AsistenciasView from '../../components/Admin/Pages/AsistenciasView.vue';

// --- INTERFACES ---

interface Branch {
    id: number;
    name: string;
    code: string;
    address: string;
    phone: string;
}

interface Classroom {
    id: number;
    branch_id: number;
    branch_name: string;
    name: string;
    capacity: number | null;
    current_scheduled_courses: string[];
}

interface CareerModuleItem {
    id: number;
    name: string;
    duration_in_weeks: number;
    order: number;
}

interface Career {
    id: number;
    name: string;
    description: string;
    available_branch_ids: number[]; // Sedes marcables
    modules: CareerModuleItem[];
}

interface ModuleItem {
    id: number;
    name: string;
    description: string;
    duration_in_weeks: number;
    careers_associated: string[];
    courses_count: number;
}

interface CourseItem {
    id: number;
    module_id: number | null;
    module_name?: string;
    name: string;
    description: string;
    duration_in_weeks: number;
    is_workshop: boolean;
    has_scheduled_courses?: boolean;
    scheduled_courses_count?: number;
}

// --- ESTADOS DE NAVEGACIÓN ---
type MainTab =
    | 'infraestructura'
    | 'curricular'
    | 'matriculas'
    | 'aperturas'
    | 'asistencia'
    | 'resumen';
const activeMainTab = ref<MainTab>('infraestructura');
const isMobileSidebarOpen = ref(false);

const page = usePage();
const userStaffBranchId = computed<number | null>(() => {
    return (page.props.auth as any)?.user?.staff?.branch_id ?? null;
});
const canViewAllBranches = computed(() => userStaffBranchId.value === null);

const selectedBranchId = ref<number | 'all'>(
    userStaffBranchId.value !== null ? userStaffBranchId.value : 'all',
);

type CurricularTab = 'carreras' | 'modulos' | 'talleres';
const activeCurricularTab = ref<CurricularTab>('carreras');

interface Props {
    branches?: Branch[];
    classrooms?: Classroom[];
    careers?: Career[];
    allModulesPool?: ModuleItem[];
    coursesList?: CourseItem[];
    teachers?: TeacherItem[];
    scheduledCourses?: ScheduledCourseItem[];
    enrollments?: EnrollmentRecord[];
    students?: StudentRecord[];
}

const props = defineProps<Props>();

// Datos de Sedes, Aulas y Oferta Académica obtenidos exclusivamente de la base de datos
const branches = computed<Branch[]>(() => props.branches ?? []);
const classrooms = computed<Classroom[]>(() => props.classrooms ?? []);
const careers = computed<Career[]>(() => props.careers ?? []);
const allModulesPool = computed<ModuleItem[]>(() => props.allModulesPool ?? []);
const coursesList = computed<CourseItem[]>(() => props.coursesList ?? []);
const teachers = computed<TeacherItem[]>(() => props.teachers ?? []);
const scheduledCourses = computed<ScheduledCourseItem[]>(() => props.scheduledCourses ?? []);
const enrollments = computed<EnrollmentRecord[]>(() => props.enrollments ?? []);
const students = computed<StudentRecord[]>(() => props.students ?? []);

const currentBranchStudentsCount = computed(() => {
    const list = students.value;
    return list.filter((s) => isStudentInBranch(s, selectedBranchId.value, list)).length;
});

function handleMainTabChange(tab: string) {
    if (tab === 'curricular') {
        router.visit('/admin/oferta-academica');
    } else if (tab === 'infraestructura') {
        router.visit('/admin/sedes-aulas');
    } else if (tab === 'matriculas' || tab === 'alumnos') {
        router.visit('/admin/matriculas');
    } else if (tab === 'aperturas') {
        router.visit('/admin/matriculas?tab=aperturas');
    } else if (tab === 'asistencia') {
        router.visit('/admin/asistencias');
    }
}

// --- TOAST NOTIFICATIONS ---
const toastMessage = ref('');
const showToast = ref(false);

function triggerToast(msg: string) {
    toastMessage.value = msg;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 4000);
}
</script>

<template>
    <Head title="Panel de Administración - IISEP" />

    <div
        class="flex min-h-screen flex-col bg-[#f4f7f5] font-sans text-base text-slate-800 antialiased selection:bg-[#2e8b57] selection:text-white md:flex-row"
    >
        <!-- Toast Notification -->
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showToast"
                class="fixed top-5 right-5 z-50 flex items-center gap-3 rounded-xl border border-[#2e8b57]/40 bg-white px-5 py-4 text-slate-900 shadow-xl"
            >
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-[#2e8b57]"
                >
                    <svg
                        class="h-6 w-6"
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
                </div>
                <div class="text-sm font-bold text-slate-900">
                    {{ toastMessage }}
                </div>
            </div>
        </Transition>

        <!-- Mobile Top Bar with Hamburger -->
        <div
            class="sticky top-0 z-40 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3.5 md:hidden"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#2e8b57] text-lg font-black text-white"
                >
                    II
                </div>
                <span class="text-xl font-black text-[#2e8b57]">IISEP</span>
            </div>
            <button
                @click="isMobileSidebarOpen = !isMobileSidebarOpen"
                class="rounded-lg p-2 text-slate-700 hover:bg-slate-100"
                aria-label="Toggle Menu"
            >
                <svg
                    class="h-7 w-7"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"
                    />
                </svg>
            </button>
        </div>

        <!-- SIDEBAR VERTICAL INDEPENDIENTE -->
        <Sidebar
            v-model:active-main-tab="activeMainTab"
            v-model:active-curricular-tab="activeCurricularTab"
            v-model:selected-branch-id="selectedBranchId"
            v-model:is-mobile-open="isMobileSidebarOpen"
            :branches="branches"
            :classrooms-count="classrooms.length"
            :careers-count="careers.length"
            :modules-count="allModulesPool.length"
            :courses-count="coursesList.length"
            :enrollments-count="enrollments.length"
            :students-count="currentBranchStudentsCount"
            :scheduled-courses-count="scheduledCourses.length"
            @update:active-main-tab="handleMainTabChange"
            @clear-editing-career="() => {}"
        />

        <!-- CONTENIDO PRINCIPAL A LA DERECHA -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Top bar -->
            <header class="border-b border-slate-200 bg-white shadow-2xs">
                <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-8 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-black text-slate-900">
                            <span v-if="activeMainTab === 'infraestructura'"
                                >Sedes & Aulas</span
                            >
                            <span v-else-if="activeMainTab === 'curricular'">
                                <span v-if="activeCurricularTab === 'carreras'"
                                    >Carreras</span
                                >
                                <span v-else-if="activeCurricularTab === 'modulos'"
                                    >Módulos</span
                                >
                                <span v-else>Talleres</span>
                            </span>
                            <span v-else-if="activeMainTab === 'matriculas'"
                                >Matrículas & Convalidaciones</span
                            >
                            <span v-else-if="activeMainTab === 'aperturas'"
                                >Apertura de Cursos y Aulas</span
                            >
                            <span v-else-if="activeMainTab === 'asistencia'"
                                >Sesiones & Asistencias</span
                            >
                            <span v-else>Panel Resumen</span>
                        </h1>
                        <p class="mt-0.5 text-sm font-medium text-slate-500">
                            <span v-if="activeMainTab === 'infraestructura'"
                                >Control de ambientes físicos y disponibilidad por
                                sede</span
                            >
                            <span v-else-if="activeMainTab === 'curricular'">
                                <span v-if="activeCurricularTab === 'carreras'"
                                    >Catálogo de carreras técnicas</span
                                >
                                <span v-else-if="activeCurricularTab === 'modulos'"
                                    >Módulos certificados del CETPRO</span
                                >
                                <span v-else>Cursos y talleres libres</span>
                            </span>
                            <span v-else-if="activeMainTab === 'matriculas'"
                                >Inscripciones de estudiantes y
                                convalidaciones</span
                            >
                            <span v-else-if="activeMainTab === 'aperturas'"
                                >Programación de periodos formativos y asignación
                                física</span
                            >
                            <span v-else-if="activeMainTab === 'asistencia'"
                                >Control de asistencia presencial</span
                            >
                            <span v-else>Métricas de retención institucional</span>
                        </p>
                    </div>

                    <!-- Sede Filter Selector -->
                    <div class="flex items-center gap-2.5">
                        <span
                            class="hidden text-sm font-semibold text-slate-600 sm:inline"
                            >Sede:</span
                        >
                        <div
                            class="flex items-center gap-2 rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-bold text-slate-800 shadow-2xs"
                        >
                            <svg
                                class="h-4 w-4 text-[#2e8b57]"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                />
                            </svg>
                            <select
                                id="branch-selector"
                                v-model="selectedBranchId"
                                class="cursor-pointer bg-transparent text-sm font-bold text-slate-800 focus:outline-none"
                            >
                                <option v-if="canViewAllBranches" :value="'all'">Todas las Sedes</option>
                                <option
                                    v-for="branch in (userStaffBranchId ? branches.filter(b => b.id === userStaffBranchId) : branches)"
                                    :key="branch.id"
                                    :value="branch.id"
                                >
                                    {{ branch.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </header>

            <!-- MAIN CONTENT AREA -->
            <main class="mx-auto w-full max-w-7xl flex-1 space-y-6 p-8">
                <!-- 1. SECCIÓN INDEPENDIENTE: SEDES & AULAS -->
                <div v-if="activeMainTab === 'infraestructura'">
                    <SedesAulasView
                        :branches="branches"
                        :classrooms="classrooms"
                        v-model:selected-branch-id="selectedBranchId"
                        @notify="triggerToast"
                    />
                </div>

                <!-- 2. SECCIÓN INDEPENDIENTE: OFERTA ACADÉMICA (CARRERAS / MÓDULOS / TALLERES) -->
                <div v-if="activeMainTab === 'curricular'">
                    <OfertaAcademicaView
                        v-model:active-tab="activeCurricularTab"
                        :branches="branches"
                        :careers="careers"
                        :all-modules-pool="allModulesPool"
                        :courses-list="coursesList"
                        @notify="triggerToast"
                    />
                </div>

                <!-- 3. SECCIÓN INDEPENDIENTE: MATRÍCULAS -->
                <div v-if="activeMainTab === 'matriculas'">
                    <MatriculasView
                        :branches="branches"
                        :classrooms="classrooms"
                        :careers="careers"
                        :courses="coursesList"
                        :scheduled-courses="scheduledCourses"
                        :enrollments="enrollments"
                        :students="students"
                        :selected-branch-id="selectedBranchId"
                        @notify="triggerToast"
                    />
                </div>

                <!-- 4. SECCIÓN INDEPENDIENTE: APERTURA DE CURSOS Y AULAS (LISTA) -->
                <div v-if="activeMainTab === 'aperturas'">
                    <AperturasView
                        :branches="branches"
                        :classrooms="classrooms"
                        :courses="coursesList"
                        :teachers="teachers"
                        :scheduled-courses="scheduledCourses"
                        :enrollments="enrollments"
                        :selected-branch-id="selectedBranchId"
                        @notify="triggerToast"
                    />
                </div>

                <!-- 5. SECCIÓN INDEPENDIENTE: ASISTENCIAS Y SESIONES -->
                <div v-if="activeMainTab === 'asistencia'">
                    <AsistenciasView
                        :branches="branches"
                        :selected-branch-id="selectedBranchId"
                        @notify="triggerToast"
                    />
                </div>

                <!-- 5. SECCIÓN: RESUMEN Y AUDITORÍA DE CONVALIDACIONES -->
                <div v-if="activeMainTab === 'resumen'" class="space-y-6">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xs"
                        >
                            <span
                                class="text-xs font-bold text-slate-500 uppercase"
                                >Aulas Operativas</span
                            >
                            <div
                                class="mt-1 text-2xl font-black text-slate-900"
                            >
                                {{ classrooms.length }} Ambientes
                            </div>
                            <span class="text-xs font-semibold text-emerald-600"
                                >En ambas sedes</span
                            >
                        </div>
                        <div
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xs"
                        >
                            <span
                                class="text-xs font-bold text-slate-500 uppercase"
                                >Sedes Presenciales</span
                            >
                            <div
                                class="mt-1 text-2xl font-black text-[#2e8b57]"
                            >
                                {{ branches.length }} Sedes
                            </div>
                            <span class="text-xs text-slate-500"
                                >Comas y Puente Piedra</span
                            >
                        </div>
                        <div
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xs"
                        >
                            <span
                                class="text-xs font-bold text-slate-500 uppercase"
                                >Cursos & Talleres</span
                            >
                            <div
                                class="mt-1 text-2xl font-black text-slate-900"
                            >
                                {{ coursesList.length }} Unidades
                            </div>
                            <span class="text-xs font-semibold text-purple-700"
                                >Duración en semanas</span
                            >
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs"
                    >
                        <div
                            class="flex items-center gap-2 border-b border-slate-100 pb-3"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-[#2e8b57]"
                            >
                                <svg
                                    class="h-6 w-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                            <h3 class="text-base font-bold text-slate-900">
                                Certificación y Acreditación Modular
                            </h3>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-slate-700">
                            En el CETPRO IISEP, los estudiantes acreditan sus módulos formativos completando los cursos dictados en cada periodo o presentando convalidaciones reconocidas por resolución institucional.
                        </p>
                        <div
                            class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4"
                        >
                            <h4
                                class="text-xs font-bold tracking-wider text-[#2e8b57] uppercase"
                            >
                                Requisito de Emisión de Certificado
                            </h4>
                            <p class="mt-2 text-xs text-slate-600">
                                Cuando un estudiante tiene todos los cursos del módulo en condición regular completada o formalmente convalidada, se habilita la emisión del Certificado Modular oficial sin requerir recursado de contenidos ya aprobados.
                            </p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
