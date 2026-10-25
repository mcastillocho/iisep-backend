<script setup lang="ts">
import { ref } from 'vue';
import OfertaAcademicaMenu from './OfertaAcademicaMenu.vue';

interface Branch {
    id: number;
    name: string;
    code: string;
    address: string;
    phone: string;
}

type MainTab = 'infraestructura' | 'curricular' | 'matriculas' | 'asistencia' | 'resumen';
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
</script>

<template>
    <!-- Backdrop móvil para cerrar drawer -->
    <div
        v-if="isMobileOpen"
        @click="emit('update:isMobileOpen', false)"
        class="fixed inset-0 bg-slate-900/40 z-40 md:hidden backdrop-blur-xs transition-opacity"
    ></div>

    <aside
        :class="[
            isMobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
            'fixed md:sticky top-0 inset-y-0 left-0 z-50 h-screen w-72 md:w-80 shrink-0 bg-white border-r border-slate-200 flex flex-col justify-between transition-transform duration-200 ease-in-out shadow-xs'
        ]"
    >
        <!-- Contenedor scrollable independiente del menú -->
        <div class="overflow-y-auto flex-1 flex flex-col">
            <!-- Brand Header -->
            <div class="p-6 border-b border-slate-100 flex items-center gap-3.5 shrink-0 bg-white sticky top-0 z-10">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#2e8b57] font-black text-white shadow-md shadow-emerald-700/20">
                    <span class="text-xl tracking-wider">II</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xl font-black tracking-tight text-[#2e8b57]">IISEP</span>
                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold text-[#2e8b57] border border-emerald-200">
                            CETPRO
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">Gestión Académica Oficial</p>
                </div>
            </div>

            <!-- Navigation Groups -->
            <nav class="px-4 py-4 space-y-4">
                <!-- GRUPO 1: OFERTA ACADÉMICA / CURSOS (ALTA FRECUENCIA) -->
                <div>
                    <div class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase">
                        Cursos
                    </div>

                    <OfertaAcademicaMenu
                        :active-main-tab="activeMainTab"
                        :active-curricular-tab="activeCurricularTab"
                        :careers-count="careersCount"
                        :modules-count="modulesCount"
                        :courses-count="coursesCount"
                        @select-curricular="handleCurricularTabSelect"
                    />
                </div>

                <!-- GRUPO 2: SEDES & AULAS (BAJA FRECUENCIA DE MODIFICACIÓN) -->
                <div>
                    <div class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase">
                        Ambientes
                    </div>

                    <div class="space-y-1">
                        <button
                            @click="isSedesMenuOpen = !isSedesMenuOpen; selectMainTab('infraestructura')"
                            :class="[
                                activeMainTab === 'infraestructura'
                                    ? 'bg-emerald-50 text-[#2e8b57] font-bold'
                                    : 'text-slate-700 hover:bg-slate-50 font-semibold',
                                'w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-sm transition cursor-pointer'
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <svg class="h-5 w-5 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        <!-- Submenú desplegable de Sedes -->
                        <div v-show="isSedesMenuOpen" class="pl-4 pr-1 space-y-1.5 pt-1.5 border-l-2 border-emerald-200 ml-5">
                            <button
                                @click="selectBranch('all')"
                                :class="[
                                    selectedBranchId === 'all' && activeMainTab === 'infraestructura'
                                        ? 'text-[#2e8b57] font-bold bg-white shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900 font-medium',
                                    'w-full text-left px-3 py-2 rounded-md text-xs flex items-center justify-between transition cursor-pointer'
                                ]"
                            >
                                <span>Todas las Sedes</span>
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-mono">
                                    {{ classroomsCount }} aulas
                                </span>
                            </button>
                            <button
                                v-for="branch in branches"
                                :key="branch.id"
                                @click="selectBranch(branch.id)"
                                :class="[
                                    selectedBranchId === branch.id && activeMainTab === 'infraestructura'
                                        ? 'text-[#2e8b57] font-bold bg-white shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900 font-medium',
                                    'w-full text-left px-3 py-2 rounded-md text-xs flex items-center justify-between transition cursor-pointer'
                                ]"
                            >
                                <span class="truncate">{{ branch.name }}</span>
                                <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs text-[#2e8b57] font-bold">
                                    {{ branch.code }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- GRUPO 3: OPERACIONES DIARIAS -->
                <div>
                    <div class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase">
                        Operaciones Diarias
                    </div>

                    <div class="space-y-1">
                        <button
                            @click="selectMainTab('matriculas')"
                            :class="[
                                activeMainTab === 'matriculas'
                                    ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                    : 'text-slate-700 hover:bg-slate-50 font-semibold',
                                'w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-sm transition cursor-pointer'
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <svg class="h-5 w-5 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Matrículas & Conval.</span>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-[#2e8b57]">
                                {{ enrollmentsCount }}
                            </span>
                        </button>

                        <button
                            @click="selectMainTab('asistencia')"
                            :class="[
                                activeMainTab === 'asistencia'
                                    ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                    : 'text-slate-700 hover:bg-slate-50 font-semibold',
                                'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm transition cursor-pointer'
                            ]"
                        >
                            <svg class="h-5 w-5 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Sesiones & Asistencias</span>
                        </button>
                    </div>
                </div>

                <!-- GRUPO 4: DIRECCIÓN -->
                <div>
                    <div class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase">
                        Dirección
                    </div>

                    <button
                        @click="selectMainTab('resumen')"
                        :class="[
                            activeMainTab === 'resumen'
                                ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                : 'text-slate-700 hover:bg-slate-50 font-semibold',
                            'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm transition cursor-pointer'
                        ]"
                    >
                        <svg class="h-5 w-5 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                        </svg>
                        <span>Panel Resumen & KPIs</span>
                    </button>
                </div>
            </nav>
        </div>

        <!-- User Info at Bottom of Sidebar (Fixed) -->
        <div class="shrink-0 p-5 border-t border-slate-100 bg-slate-50/80">
            <div class="flex items-center gap-3.5">
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-100 border border-emerald-300 text-sm font-bold text-[#2e8b57]">
                    ST
                </div>
                <div class="leading-tight overflow-hidden">
                    <p class="text-sm font-bold text-slate-900 truncate">Sofia Torres Castro</p>
                    <p class="text-xs text-slate-500 truncate">Coordinación Sede Comas</p>
                </div>
            </div>
        </div>
    </aside>
</template>
