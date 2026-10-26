<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import OfertaAcademicaMenu from './OfertaAcademicaMenu.vue';

interface Branch {
    id: number;
    name: string;
    code: string;
    address: string;
    phone: string;
}

type MainTab =
    | 'infraestructura'
    | 'curricular'
    | 'matriculas'
    | 'aperturas'
    | 'asistencia'
    | 'resumen';
type CurricularTab = 'carreras' | 'modulos' | 'talleres';

interface Props {
    activeMainTab: MainTab;
    activeCurricularTab: CurricularTab;
    selectedBranchId: number | 'all';
    branches: Branch[];
    classroomsCount: number;
    careersCount: number;
    modulesCount: number;
    coursesCount: number;
    enrollmentsCount: number;
    scheduledCoursesCount?: number;
    isMobileOpen: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:activeMainTab', tab: MainTab): void;
    (e: 'update:activeCurricularTab', tab: CurricularTab): void;
    (e: 'update:selectedBranchId', branchId: number | 'all'): void;
    (e: 'update:isMobileOpen', isOpen: boolean): void;
    (e: 'clear-editing-career'): void;
}>();

// Estado independiente para desplegar/colapsar Sedes & Aulas
const isSedesMenuOpen = ref(true);

function selectMainTab(tab: MainTab) {
    emit('update:activeMainTab', tab);
    emit('update:isMobileOpen', false);
    emit('clear-editing-career');
}

function selectBranch(branchId: number | 'all') {
    emit('update:selectedBranchId', branchId);
    emit('update:activeMainTab', 'infraestructura');
    emit('update:isMobileOpen', false);
    emit('clear-editing-career');
}

function handleCurricularTabSelect(tab: CurricularTab) {
    emit('update:activeCurricularTab', tab);
    emit('update:activeMainTab', 'curricular');
    emit('update:isMobileOpen', false);
    emit('clear-editing-career');
}

// Datos del usuario autenticado desde Inertia
const page = usePage();
const currentUser = computed(() => (page.props.auth as any)?.user);
const canViewAllBranches = computed(() => {
    // Solo puede ver 'Todas las Sedes' si no tiene sede asignada (branch_id es null)
    return !currentUser.value?.staff || currentUser.value.staff.branch_id === null;
});

const userInitials = computed(() => {
    if (!currentUser.value) return 'ST';
    const first = currentUser.value.first_name?.[0] || 'A';
    const last = currentUser.value.paternal_surname?.[0] || 'D';
    return (first + last).toUpperCase();
});

const userPositionLabel = computed(() => {
    const pos = currentUser.value?.staff?.position;
    if (pos === 'admin') return 'Dirección General';
    if (pos === 'coord') return 'Coordinación de Sede';
    if (pos === 'secre') return 'Secretaría de Sede';
    return pos || 'Personal Administrativo';
});

function handleLogout() {
    router.post('/logout');
}
</script>

<template>
    <!-- Backdrop móvil para cerrar drawer -->
    <div
        v-if="isMobileOpen"
        @click="emit('update:isMobileOpen', false)"
        class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-xs transition-opacity md:hidden"
    ></div>

    <aside
        :class="[
            isMobileOpen
                ? 'translate-x-0'
                : '-translate-x-full md:translate-x-0',
            'fixed inset-y-0 top-0 left-0 z-50 flex h-screen w-72 shrink-0 flex-col justify-between border-r border-slate-200 bg-white shadow-xs transition-transform duration-200 ease-in-out md:sticky md:w-80',
        ]"
    >
        <!-- Contenedor scrollable independiente del menú -->
        <div class="flex flex-1 flex-col overflow-y-auto">
            <!-- Brand Header -->
            <div
                class="sticky top-0 z-10 flex shrink-0 items-center gap-3.5 border-b border-slate-100 bg-white p-6"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#2e8b57] font-black text-white shadow-md shadow-emerald-700/20"
                >
                    <span class="text-xl tracking-wider">II</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            class="text-xl font-black tracking-tight text-[#2e8b57]"
                            >IISEP</span
                        >
                        <span
                            class="rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-bold text-[#2e8b57]"
                        >
                            CETPRO
                        </span>
                    </div>
                    <p class="text-xs font-medium text-slate-500">
                        Gestión Académica Oficial
                    </p>
                </div>
            </div>

            <!-- Navigation Groups -->
            <nav class="space-y-4 px-4 py-4">
                <!-- GRUPO 1: ASISTENCIAS (MÁXIMA PRIORIDAD Y OPERATIVA DIARIA) -->
                <div>
                    <div
                        class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase"
                    >
                        Asistencias
                    </div>

                    <div class="space-y-1">
                        <button
                            @click="selectMainTab('asistencia')"
                            :class="[
                                activeMainTab === 'asistencia'
                                    ? 'border-l-4 border-[#2e8b57] bg-emerald-50 font-bold text-[#2e8b57]'
                                    : 'font-semibold text-slate-700 hover:bg-slate-50',
                                'flex w-full cursor-pointer items-center justify-between rounded-lg px-3.5 py-2.5 text-sm transition',
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <svg
                                    class="h-5 w-5 text-[#2e8b57]"
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
                                <span>Asistencias</span>
                            </div>
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black tracking-wide text-[#2e8b57] uppercase"
                            >
                                <span
                                    class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#2e8b57]"
                                ></span>
                                En vivo
                            </span>
                        </button>
                    </div>
                </div>

                <!-- GRUPO 2: MATRÍCULAS -->
                <div>
                    <div
                        class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase"
                    >
                        Matrículas
                    </div>

                    <div class="space-y-1">
                        <!-- Matrículas (e Inscripciones / Convalidaciones) -->
                        <button
                            @click="selectMainTab('matriculas')"
                            :class="[
                                activeMainTab === 'matriculas'
                                    ? 'border-l-4 border-[#2e8b57] bg-emerald-50 font-bold text-[#2e8b57]'
                                    : 'font-semibold text-slate-700 hover:bg-slate-50',
                                'flex w-full cursor-pointer items-center justify-between rounded-lg px-3.5 py-2.5 text-sm transition',
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <svg
                                    class="h-5 w-5 text-[#2e8b57]"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                                <span>Matrículas</span>
                            </div>
                            <span
                                class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-[#2e8b57]"
                            >
                                {{ enrollmentsCount }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- GRUPO 3: CURSOS -->
                <div>
                    <div
                        class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase"
                    >
                        Cursos
                    </div>

                    <div class="space-y-1">
                        <!-- 1. Apertura de Cursos y Aulas -->
                        <button
                            @click="selectMainTab('aperturas')"
                            :class="[
                                activeMainTab === 'aperturas'
                                    ? 'border-l-4 border-[#2e8b57] bg-emerald-50 font-bold text-[#2e8b57]'
                                    : 'font-semibold text-slate-700 hover:bg-slate-50',
                                'flex w-full cursor-pointer items-center justify-between rounded-lg px-3.5 py-2.5 text-sm transition',
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <svg
                                    class="h-5 w-5 text-[#2e8b57]"
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
                                <span>Apertura de Cursos</span>
                            </div>
                            <span
                                class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600"
                            >
                                {{ scheduledCoursesCount ?? 4 }}
                            </span>
                        </button>

                        <!-- 2. Oferta Académica (Carreras / Módulos / Talleres) -->
                        <OfertaAcademicaMenu
                            :active-main-tab="activeMainTab"
                            :active-curricular-tab="activeCurricularTab"
                            :careers-count="careersCount"
                            :modules-count="modulesCount"
                            :courses-count="coursesCount"
                            @select-curricular="handleCurricularTabSelect"
                        />
                    </div>
                </div>

                <!-- GRUPO 4: AMBIENTES / SEDES & AULAS -->
                <div>
                    <div
                        class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase"
                    >
                        Ambientes
                    </div>

                    <div class="space-y-1">
                        <button
                            @click="
                                isSedesMenuOpen = !isSedesMenuOpen;
                                selectMainTab('infraestructura');
                            "
                            :class="[
                                activeMainTab === 'infraestructura'
                                    ? 'bg-emerald-50 font-bold text-[#2e8b57]'
                                    : 'font-semibold text-slate-700 hover:bg-slate-50',
                                'flex w-full cursor-pointer items-center justify-between rounded-lg px-3.5 py-2.5 text-sm transition',
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <svg
                                    class="h-5 w-5 text-[#2e8b57]"
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
                                <span class="text-sm">Sedes & Aulas</span>
                            </div>
                            <svg
                                :class="isSedesMenuOpen ? 'rotate-90' : ''"
                                class="h-4 w-4 text-slate-400 transition-transform duration-200"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5l7 7-7 7"
                                />
                            </svg>
                        </button>

                        <!-- Submenú desplegable de Sedes -->
                        <div
                            v-show="isSedesMenuOpen"
                            class="ml-5 space-y-1.5 border-l-2 border-emerald-200 pt-1.5 pr-1 pl-4"
                        >
                            <button
                                v-if="canViewAllBranches"
                                @click="selectBranch('all')"
                                :class="[
                                    selectedBranchId === 'all' &&
                                    activeMainTab === 'infraestructura'
                                        ? 'bg-white font-bold text-[#2e8b57] shadow-xs'
                                        : 'font-medium text-slate-600 hover:text-slate-900',
                                    'flex w-full cursor-pointer items-center justify-between rounded-md px-3 py-2 text-left text-xs transition',
                                ]"
                            >
                                <span>Todas las Sedes</span>
                                <span
                                    class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600"
                                >
                                    {{ classroomsCount }} aulas
                                </span>
                            </button>
                            <button
                                v-for="branch in branches"
                                :key="branch.id"
                                @click="selectBranch(branch.id)"
                                :class="[
                                    selectedBranchId === branch.id &&
                                    activeMainTab === 'infraestructura'
                                        ? 'bg-white font-bold text-[#2e8b57] shadow-xs'
                                        : 'font-medium text-slate-600 hover:text-slate-900',
                                    'flex w-full cursor-pointer items-center justify-between rounded-md px-3 py-2 text-left text-xs transition',
                                ]"
                            >
                                <span class="truncate">{{ branch.name }}</span>
                                <span
                                    class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold text-[#2e8b57]"
                                >
                                    {{ branch.code }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- GRUPO 5: DIRECCIÓN -->
                <div>
                    <div
                        class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase"
                    >
                        Dirección
                    </div>

                    <button
                        @click="selectMainTab('resumen')"
                        :class="[
                            activeMainTab === 'resumen'
                                ? 'border-l-4 border-[#2e8b57] bg-emerald-50 font-bold text-[#2e8b57]'
                                : 'font-semibold text-slate-700 hover:bg-slate-50',
                            'flex w-full cursor-pointer items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm transition',
                        ]"
                    >
                        <svg
                            class="h-5 w-5 text-[#2e8b57]"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"
                            />
                        </svg>
                        <span>Panel Resumen & KPIs</span>
                    </button>
                </div>
            </nav>
        </div>

        <!-- User Info at Bottom of Sidebar (Fixed) -->
        <div class="shrink-0 border-t border-slate-100 bg-slate-50/80 p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-emerald-300 bg-emerald-100 text-xs font-black text-[#2e8b57]"
                    >
                        {{ userInitials }}
                    </div>
                    <div class="overflow-hidden leading-tight">
                        <p class="truncate text-xs font-bold text-slate-900">
                            {{ currentUser?.full_name || 'Sofia Torres Castro' }}
                        </p>
                        <p class="truncate text-[11px] text-slate-500">
                            {{ userPositionLabel }}
                        </p>
                    </div>
                </div>

                <!-- Botón de Cerrar Sesión -->
                <button
                    @click="handleLogout"
                    type="button"
                    class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 transition cursor-pointer shrink-0"
                    title="Cerrar Sesión"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </div>
        </div>
    </aside>
</template>
