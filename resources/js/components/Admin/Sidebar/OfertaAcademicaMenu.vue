<script setup lang="ts">
import { ref } from 'vue';

type CurricularTab = 'carreras' | 'modulos' | 'talleres';

interface Props {
    activeMainTab: string;
    activeCurricularTab: CurricularTab;
    careersCount: number;
    modulesCount: number;
    coursesCount: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'select-curricular', tab: CurricularTab): void;
}>();

// Estado independiente de colapso/despliegue del menú Oferta Académica
const isOpen = ref(true);

function toggleMenu() {
    isOpen.value = !isOpen.value;
}

function selectTab(tab: CurricularTab) {
    emit('select-curricular', tab);
}
</script>

<template>
    <div class="space-y-1">
        <!-- Título Principal Unificado (Como en Sedes & Aulas) -->
        <button
            @click="toggleMenu"
            :class="[
                activeMainTab === 'curricular'
                    ? 'bg-emerald-50/70 text-[#2e8b57] font-bold'
                    : 'text-slate-700 hover:bg-slate-50 font-semibold',
                'w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-sm transition cursor-pointer'
            ]"
        >
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <span class="text-sm">Oferta Académica</span>
            </div>
            <svg
                :class="isOpen ? 'rotate-90' : ''"
                class="h-4 w-4 text-slate-400 transition-transform duration-200"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <!-- Submenú Desplegable con Pestañas Hijas (Carreras / Módulos / Talleres) -->
        <div v-show="isOpen" class="pl-4 pr-1 space-y-1.5 pt-1.5 border-l-2 border-emerald-200 ml-5">
            <!-- Carreras -->
            <button
                @click="selectTab('carreras')"
                :class="[
                    activeMainTab === 'curricular' && activeCurricularTab === 'carreras'
                        ? 'text-[#2e8b57] font-bold bg-white shadow-xs'
                        : 'text-slate-600 hover:text-slate-900 font-medium',
                    'w-full text-left px-3 py-2 rounded-md text-xs flex items-center justify-between transition cursor-pointer'
                ]"
            >
                <span>Carreras</span>
                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-mono">{{ careersCount }}</span>
            </button>

            <!-- Módulos -->
            <button
                @click="selectTab('modulos')"
                :class="[
                    activeMainTab === 'curricular' && activeCurricularTab === 'modulos'
                        ? 'text-[#2e8b57] font-bold bg-white shadow-xs'
                        : 'text-slate-600 hover:text-slate-900 font-medium',
                    'w-full text-left px-3 py-2 rounded-md text-xs flex items-center justify-between transition cursor-pointer'
                ]"
            >
                <span>Módulos</span>
                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-mono">{{ modulesCount }}</span>
            </button>

            <!-- Talleres -->
            <button
                @click="selectTab('talleres')"
                :class="[
                    activeMainTab === 'curricular' && activeCurricularTab === 'talleres'
                        ? 'text-[#2e8b57] font-bold bg-white shadow-xs'
                        : 'text-slate-600 hover:text-slate-900 font-medium',
                    'w-full text-left px-3 py-2 rounded-md text-xs flex items-center justify-between transition cursor-pointer'
                ]"
            >
                <span>Talleres</span>
                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-mono">{{ coursesCount }}</span>
            </button>
        </div>
    </div>
</template>
