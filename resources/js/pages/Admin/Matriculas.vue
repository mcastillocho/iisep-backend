<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import MatriculasView, {
    Branch,
    Classroom,
    CourseOption,
    EnrollmentRecord,
} from '../../components/Admin/Pages/MatriculasView.vue';
import Sidebar from '../../components/Admin/Sidebar/Sidebar.vue';

// --- DATOS MOCK INSTITUCIONALES ---
const branches = ref<Branch[]>([
    {
        id: 1,
        name: 'Sede Comas',
        code: 'COM',
        address: 'Av. Túpac Amaru 3845 (Frente a la Reniec de Comas)',
        phone: '944206945',
    },
    {
        id: 2,
        name: 'Sede Puente Piedra',
        code: 'PUP',
        address: 'Jr. Ricardo Palma 241 (2° piso de la RENIEC)',
        phone: '987710344',
    },
]);

const classrooms = ref<Classroom[]>([
    { id: 1, branch_id: 1, name: 'Aula 101 - Teoría', capacity: 35 },
    { id: 2, branch_id: 1, name: 'Laboratorio de Cómputo 1', capacity: 24 },
    { id: 3, branch_id: 2, name: 'Aula 201 - Multiusos', capacity: 30 },
    {
        id: 4,
        branch_id: 2,
        name: 'Taller de Estética y Cosmetología',
        capacity: 20,
    },
]);

const coursesList = ref<CourseOption[]>([
    {
        id: 1,
        name: 'Excel Avanzado y Macros',
        duration_in_weeks: 4,
        module_name: 'Ofimática Empresarial',
        is_workshop: false,
    },
    {
        id: 2,
        name: 'Arquitectura de Hardware y Diagnóstico',
        duration_in_weeks: 4,
        module_name: 'Ensamblaje y Mantenimiento de PC',
        is_workshop: false,
    },
    {
        id: 3,
        name: 'Taller de Barber Shop y Cortes Urbanos',
        duration_in_weeks: 6,
        is_workshop: true,
    },
    {
        id: 4,
        name: 'Taller de Uñas Acrílicas y Polygel',
        duration_in_weeks: 4,
        is_workshop: true,
    },
    {
        id: 5,
        name: 'Taller de Reparación de Celulares y Tablets',
        duration_in_weeks: 8,
        is_workshop: true,
    },
]);

const enrollments = ref<EnrollmentRecord[]>([
    {
        id: 1,
        scheduled_course_id: 2,
        student_full_name: 'Diego Mendoza Salazar',
        student_document: '76543210',
        course_name: 'Arquitectura de Hardware y Diagnóstico',
        module_name: 'Ensamblaje y Mantenimiento de PC',
        career_name: 'Computación e Informática',
        branch_name: 'Sede Comas',
        enrollment_date: '2026-10-01',
        status: 'activo',
        notes: 'Apoderado: Juan Carlos Mendoza',
    },
    {
        id: 2,
        scheduled_course_id: 2,
        student_full_name: 'Valeria Rios Flores',
        student_document: '78901234',
        course_name: 'Arquitectura de Hardware y Diagnóstico',
        module_name: 'Ensamblaje y Mantenimiento de PC',
        career_name: 'Computación e Informática',
        branch_name: 'Sede Comas',
        enrollment_date: '2026-10-01',
        status: 'activo',
        notes: null,
    },
    {
        id: 3,
        scheduled_course_id: 1,
        student_full_name: 'Valeria Rios Flores',
        student_document: '78901234',
        course_name: 'Excel Avanzado y Macros',
        module_name: 'Ofimática Empresarial',
        career_name: 'Computación e Informática',
        branch_name: 'Sede Comas',
        enrollment_date: '2026-03-05',
        status: 'convalidado',
        notes: 'Convalidado por estudios previos',
    },
    {
        id: 4,
        scheduled_course_id: 1,
        student_full_name: 'Carlos Alberto Gomez Paredes',
        student_document: '34567890',
        course_name: 'Excel Avanzado y Macros',
        module_name: 'Ofimática Empresarial',
        career_name: null,
        branch_name: 'Sede Comas',
        enrollment_date: '2026-10-02',
        status: 'activo',
        notes: 'Módulo independiente libre (Docente IISEP)',
    },
]);

type CurricularTab = 'carreras' | 'modulos' | 'talleres';
const activeCurricularTab = ref<CurricularTab>('carreras');
const isMobileSidebarOpen = ref(false);
const activeMainTab = ref<
    | 'infraestructura'
    | 'curricular'
    | 'matriculas'
    | 'aperturas'
    | 'asistencia'
    | 'resumen'
>('matriculas');
const selectedBranchId = ref<number | 'all'>('all');

function handleMainTabChange(tab: string) {
    if (tab === 'curricular') {
        router.visit('/admin/oferta-academica');
    } else if (tab === 'infraestructura') {
        router.visit('/admin/sedes-aulas');
    } else if (tab === 'asistencia') {
        router.visit('/admin/asistencias');
    } else if (tab === 'matriculas') {
        activeMainTab.value = 'matriculas';
    } else if (tab === 'aperturas') {
        activeMainTab.value = 'aperturas';
    } else {
        router.visit('/admin');
    }
}

// Toast
const toastMessage = ref<string | null>(null);
let toastTimeout: ReturnType<typeof setTimeout> | null = null;

function triggerToast(message: string) {
    toastMessage.value = message;
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toastMessage.value = null;
    }, 3500);
}
</script>

<template>
    <Head title="Matrículas & Aperturas - CETPRO IISEP" />

    <div
        class="flex min-h-screen flex-col bg-[#f4f7f5] font-sans text-slate-800 md:flex-row"
    >
        <!-- Toast Notification -->
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toastMessage"
                class="fixed right-5 bottom-5 z-50 flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900 px-5 py-3 text-white shadow-2xl"
            >
                <div
                    class="h-2.5 w-2.5 animate-pulse rounded-full bg-[#2e8b57]"
                ></div>
                <p class="text-sm font-semibold">{{ toastMessage }}</p>
                <button
                    @click="toastMessage = null"
                    class="ml-2 cursor-pointer text-slate-400 hover:text-white"
                >
                    &times;
                </button>
            </div>
        </transition>

        <!-- Mobile header trigger -->
        <div
            class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 md:hidden"
        >
            <div class="flex items-center gap-2">
                <span class="text-lg font-black tracking-tight text-[#2e8b57]"
                    >IISEP</span
                >
                <span
                    class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold text-[#2e8b57]"
                    >CETPRO</span
                >
            </div>
            <button
                @click="isMobileSidebarOpen = !isMobileSidebarOpen"
                class="rounded-lg p-2 text-slate-600 hover:bg-slate-100"
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
            :careers-count="3"
            :modules-count="5"
            :courses-count="coursesList.length"
            :enrollments-count="enrollments.length"
            :scheduled-courses-count="4"
            @update:active-main-tab="handleMainTabChange"
        />

        <!-- CONTENIDO PRINCIPAL A LA DERECHA -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Top bar -->
            <header
                class="flex flex-col gap-4 border-b border-slate-200 bg-white px-8 py-5 shadow-2xs sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-black text-slate-900">
                        <span v-if="activeMainTab === 'aperturas'"
                            >Apertura de Cursos y Aulas</span
                        >
                        <span v-else>Matrículas & Convalidaciones</span>
                    </h1>
                    <p class="mt-0.5 text-sm font-medium text-slate-500">
                        <span v-if="activeMainTab === 'aperturas'"
                            >Programación de periodos formativos, horarios y
                            asignación de aulas físicas</span
                        >
                        <span v-else
                            >Inscripciones de estudiantes, convalidaciones de
                            módulos y seguimiento de estados</span
                        >
                    </p>
                </div>
            </header>

            <!-- MAIN BODY: PÁGINA INDEPENDIENTE DE MATRÍCULAS -->
            <main class="max-w-7xl flex-1 space-y-6 p-8">
                <MatriculasView
                    :active-tab="
                        activeMainTab === 'aperturas'
                            ? 'aperturas'
                            : 'inscripciones'
                    "
                    :branches="branches"
                    :classrooms="classrooms"
                    :courses="coursesList"
                    :enrollments="enrollments"
                    :selected-branch-id="selectedBranchId"
                    :show-subtabs-nav="false"
                    @notify="triggerToast"
                    @update:active-tab="
                        (tab) =>
                            (activeMainTab =
                                tab === 'aperturas'
                                    ? 'aperturas'
                                    : 'matriculas')
                    "
                />
            </main>
        </div>
    </div>
</template>
