<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AperturasView from '../../components/Admin/Pages/AperturasView.vue';
import MatriculasView, {
    Branch,
    CareerOption,
    Classroom,
    CourseOption,
    EnrollmentRecord,
    ScheduledCourseItem,
    StudentRecord,
    TeacherItem,
} from '../../components/Admin/Pages/MatriculasView.vue';
import Sidebar from '../../components/Admin/Sidebar/Sidebar.vue';

interface Props {
    branches: Branch[];
    classrooms: Classroom[];
    careers?: CareerOption[];
    coursesList: CourseOption[];
    teachers: TeacherItem[];
    scheduledCourses: ScheduledCourseItem[];
    enrollments: EnrollmentRecord[];
    students?: StudentRecord[];
    initialTab?: 'inscripciones' | 'aperturas';
}

const props = defineProps<Props>();

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
>(props.initialTab === 'aperturas' ? 'aperturas' : 'matriculas');
const selectedBranchId = ref<number | 'all'>('all');

function handleMainTabChange(tab: string) {
    if (tab === 'curricular') {
        router.visit('/admin/oferta-academica');
    } else if (tab === 'infraestructura') {
        router.visit('/admin/sedes-aulas');
    } else if (tab === 'asistencia') {
        router.visit('/admin/asistencias');
    } else if (tab === 'matriculas' || tab === 'alumnos') {
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
            :branches="props.branches"
            :classrooms-count="props.classrooms.length"
            :careers-count="3"
            :modules-count="5"
            :courses-count="props.coursesList.length"
            :enrollments-count="props.enrollments.length"
            :students-count="props.students?.length ?? props.enrollments.length"
            :scheduled-courses-count="props.scheduledCourses.length"
            @update:active-main-tab="handleMainTabChange"
        />

        <!-- CONTENIDO PRINCIPAL A LA DERECHA -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Top bar -->
            <header class="border-b border-slate-200 bg-white shadow-2xs">
                <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-8 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-black text-slate-900">
                            <span v-if="activeMainTab === 'aperturas'"
                                >Apertura de Cursos y Aulas</span
                            >
                            <span v-else>Alumnos &amp; Matrículas</span>
                        </h1>
                        <p class="mt-0.5 text-sm font-medium text-slate-500">
                            <span v-if="activeMainTab === 'aperturas'"
                                >Programación de periodos formativos, horarios y
                                asignación de aulas físicas</span
                            >
                            <span v-else
                                >Directorio de estudiantes, apoderados y gestión de cursos y matrículas</span
                            >
                        </p>
                    </div>
                </div>
            </header>

            <!-- MAIN BODY: PÁGINA INDEPENDIENTE DE MATRÍCULAS O APERTURAS -->
            <main class="mx-auto w-full max-w-7xl flex-1 space-y-6 p-8">
                <AperturasView
                    v-if="activeMainTab === 'aperturas'"
                    :branches="props.branches"
                    :classrooms="props.classrooms"
                    :courses="props.coursesList"
                    :teachers="props.teachers"
                    :scheduled-courses="props.scheduledCourses"
                    :enrollments="props.enrollments"
                    :selected-branch-id="selectedBranchId"
                    @notify="triggerToast"
                />

                <MatriculasView
                    v-else
                    :branches="props.branches"
                    :classrooms="props.classrooms"
                    :careers="props.careers"
                    :courses="props.coursesList"
                    :scheduled-courses="props.scheduledCourses"
                    :enrollments="props.enrollments"
                    :students="props.students"
                    :selected-branch-id="selectedBranchId"
                    @notify="triggerToast"
                />
            </main>
        </div>
    </div>
</template>
