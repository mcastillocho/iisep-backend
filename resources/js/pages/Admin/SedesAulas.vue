<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SedesAulasView, {
    Branch,
    Classroom,
} from '../../components/Admin/Pages/SedesAulasView.vue';
import Sidebar from '../../components/Admin/Sidebar/Sidebar.vue';

interface Props {
    branches?: Branch[];
    classrooms?: Classroom[];
}

const props = defineProps<Props>();

// Datos obtenidos exclusivamente de la base de datos
const branches = computed<Branch[]>(() => props.branches ?? []);
const classrooms = computed<Classroom[]>(() => props.classrooms ?? []);


type CurricularTab = 'carreras' | 'modulos' | 'talleres';
const activeCurricularTab = ref<CurricularTab>('carreras');
const isMobileSidebarOpen = ref(false);
const activeMainTab = ref<
    'infraestructura' | 'curricular' | 'matriculas' | 'asistencia' | 'resumen'
>('infraestructura');

// Control de acceso territorial por Sede
const page = usePage();
const userStaffBranchId = computed<number | null>(() => {
    return (page.props.auth as any)?.user?.staff?.branch_id ?? null;
});

const canViewAllBranches = computed(() => userStaffBranchId.value === null);

// Si tiene sede asignada, preselecciona su sede; si es admin global (null), selecciona 'all'
const selectedBranchId = ref<number | 'all'>(
    userStaffBranchId.value !== null ? userStaffBranchId.value : 'all',
);

function handleMainTabChange(tab: string) {
    if (tab === 'curricular') {
        router.visit('/admin/oferta-academica');
    } else if (tab === 'aperturas') {
        router.visit('/admin/matriculas?tab=aperturas');
    } else if (tab === 'matriculas') {
        router.visit('/admin/matriculas');
    } else if (tab === 'asistencia') {
        router.visit('/admin/asistencias');
    } else if (tab !== 'infraestructura') {
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
    <Head title="Sedes & Aulas - CETPRO IISEP" />

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
            :courses-count="5"
            :enrollments-count="4"
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
                        Sedes & Aulas
                        <span
                            v-if="selectedBranchId !== 'all'"
                            class="text-lg font-bold text-[#2e8b57]"
                        >
                            &bull;
                            {{
                                branches.find((b) => b.id === selectedBranchId)
                                    ?.name
                            }}
                        </span>
                    </h1>
                    <p class="mt-0.5 text-sm font-medium text-slate-500">
                        Administración de infraestructura física, sedes
                        oficiales y disponibilidad de ambientes de clase
                    </p>
                </div>

                <!-- Selector de Sede en la Barra Superior -->
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2">
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
                            v-model="selectedBranchId"
                            class="cursor-pointer bg-transparent text-sm font-bold text-slate-800 focus:outline-none"
                        >
                            <option v-if="canViewAllBranches" :value="'all'">Todas las Sedes</option>
                            <option
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </header>

            <!-- MAIN BODY: PÁGINA INDEPENDIENTE DE SEDES Y AULAS -->
            <main class="max-w-7xl flex-1 space-y-6 p-8">
                <SedesAulasView
                    :branches="branches"
                    :classrooms="classrooms"
                    v-model:selected-branch-id="selectedBranchId"
                    @notify="triggerToast"
                />
            </main>
        </div>
    </div>
</template>
