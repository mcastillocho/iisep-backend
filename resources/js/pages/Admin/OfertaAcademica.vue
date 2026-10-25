<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import OfertaAcademicaView, { Branch, Career, CourseItem, ModuleItem } from '../../components/Admin/Pages/OfertaAcademicaView.vue';
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

const careers = ref<Career[]>([
    {
        id: 1,
        name: 'Computación e Informática',
        description: 'Carrera técnica profesional de desarrollo de software, administración de redes y soporte técnico.',
        estimated_capacity: 40,
        available_branch_ids: [1, 2],
        modules: [
            { id: 1, name: 'Ofimática Empresarial', duration_in_weeks: 8, order: 1 },
            { id: 2, name: 'Ensamblaje y Mantenimiento de PC', duration_in_weeks: 8, order: 2 },
        ],
    },
    {
        id: 2,
        name: 'Logística y Almacenes',
        description: 'Especialista en administración de inventarios, cadena de suministro y comercio exterior.',
        estimated_capacity: 30,
        available_branch_ids: [1],
        modules: [
            { id: 3, name: 'Cajero Bancario y Comercial', duration_in_weeks: 8, order: 1 },
        ],
    },
    {
        id: 3,
        name: 'Gastronomía y Arte Culinario',
        description: 'Formación integral en técnicas culinarias nacionales e internacionales y gestión de restaurantes.',
        estimated_capacity: 25,
        available_branch_ids: [1, 2],
        modules: [
            { id: 4, name: 'Pastelería y Repostería Fina', duration_in_weeks: 12, order: 1 },
        ],
    },
]);

const allModulesPool = ref<ModuleItem[]>([
    {
        id: 1,
        name: 'Ofimática Empresarial',
        description: 'Word, Excel avanzado, PowerPoint y gestión de documentos en la nube.',
        duration_in_weeks: 8,
        careers_associated: ['Computación e Informática'],
        courses_count: 1,
    },
    {
        id: 2,
        name: 'Ensamblaje y Mantenimiento de PC',
        description: 'Diagnóstico de hardware, armado de computadoras, instalación de SO y redes LAN.',
        duration_in_weeks: 8,
        careers_associated: ['Computación e Informática'],
        courses_count: 1,
    },
    {
        id: 3,
        name: 'Cajero Bancario y Comercial',
        description: 'Detección de billetes, sistemas financieros, arqueo de caja y servicio al cliente.',
        duration_in_weeks: 8,
        careers_associated: ['Logística y Almacenes'],
        courses_count: 0,
    },
    {
        id: 4,
        name: 'Pastelería y Repostería Fina',
        description: 'Técnicas de masas, horneado, decoración y postres internacionales.',
        duration_in_weeks: 12,
        careers_associated: ['Gastronomía y Arte Culinario'],
        courses_count: 0,
    },
    {
        id: 5,
        name: 'Desarrollo Web y Programación Front-End',
        description: 'HTML5, CSS3, JavaScript y desarrollo de aplicaciones web interactivas.',
        duration_in_weeks: 16,
        careers_associated: [],
        courses_count: 2,
    },
]);

const coursesList = ref<CourseItem[]>([
    {
        id: 1,
        module_id: 1,
        module_name: 'Ofimática Empresarial',
        name: 'Excel Avanzado y Macros',
        description: 'Fórmulas lógicas, tablas dinámicas, dashboards y automatización VBA.',
        duration_in_weeks: 4,
        is_workshop: false,
    },
    {
        id: 2,
        module_id: 2,
        module_name: 'Ensamblaje y Mantenimiento de PC',
        name: 'Arquitectura de Hardware y Diagnóstico',
        description: 'Detección de fallas en placas, memorias, fuentes y almacenamiento.',
        duration_in_weeks: 4,
        is_workshop: false,
    },
    {
        id: 3,
        module_id: null,
        name: 'Taller de Barber Shop y Cortes Urbanos',
        description: 'Técnicas de degradado, fade, afeitado tradicional y perfilado de barba.',
        duration_in_weeks: 6,
        is_workshop: true,
    },
    {
        id: 4,
        module_id: null,
        name: 'Taller de Uñas Acrílicas y Polygel',
        description: 'Esculpido en acrílico, encapsulados, mano alzada y 3D.',
        duration_in_weeks: 4,
        is_workshop: true,
    },
    {
        id: 5,
        module_id: null,
        name: 'Taller de Reparación de Celulares y Tablets',
        description: 'Cambio de pantallas, micro-soldadura, diagnóstico de placas y flasheo.',
        duration_in_weeks: 8,
        is_workshop: true,
    },
]);

type CurricularTab = 'carreras' | 'modulos' | 'talleres';
const activeCurricularTab = ref<CurricularTab>('carreras');
const isMobileSidebarOpen = ref(false);
const activeMainTab = ref<'infraestructura' | 'curricular' | 'matriculas' | 'asistencia' | 'resumen'>('curricular');
const selectedBranchId = ref<number | 'all'>('all');

function handleMainTabChange(tab: string) {
    if (tab !== 'curricular') {
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
    <Head title="Oferta Académica - CETPRO IISEP" />

    <div class="min-h-screen bg-[#f4f7f5] text-slate-800 flex flex-col md:flex-row font-sans">
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
                class="fixed bottom-5 right-5 z-50 flex items-center gap-3 rounded-xl bg-slate-900 px-5 py-3 text-white shadow-2xl border border-slate-700"
            >
                <div class="h-2.5 w-2.5 rounded-full bg-[#2e8b57] animate-pulse"></div>
                <p class="text-sm font-semibold">{{ toastMessage }}</p>
                <button @click="toastMessage = null" class="ml-2 text-slate-400 hover:text-white cursor-pointer">&times;</button>
            </div>
        </transition>

        <!-- Mobile header trigger -->
        <div class="md:hidden bg-white border-b border-slate-200 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-lg font-black tracking-tight text-[#2e8b57]">IISEP</span>
                <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold text-[#2e8b57]">CETPRO</span>
            </div>
            <button
                @click="isMobileSidebarOpen = !isMobileSidebarOpen"
                class="p-2 rounded-lg text-slate-600 hover:bg-slate-100"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
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
            :classrooms-count="4"
            :careers-count="careers.length"
            :modules-count="allModulesPool.length"
            :courses-count="coursesList.length"
            :enrollments-count="4"
            @update:active-main-tab="handleMainTabChange"
        />

        <!-- CONTENIDO PRINCIPAL A LA DERECHA -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top bar -->
            <header class="bg-white border-b border-slate-200 px-8 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-2xs">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">
                        Oferta Académica:
                        <span class="text-[#2e8b57] capitalize">{{ activeCurricularTab }}</span>
                    </h1>
                    <p class="text-sm text-slate-500 font-medium mt-0.5">
                        Catálogo de formación técnica, módulos certificables y talleres prácticos del CETPRO IISEP
                    </p>
                </div>
            </header>

            <!-- MAIN BODY: PÁGINA INDEPENDIENTE DE OFERTA ACADÉMICA -->
            <main class="flex-1 p-8 space-y-6 max-w-7xl">
                <OfertaAcademicaView
                    v-model:active-tab="activeCurricularTab"
                    :branches="branches"
                    :careers="careers"
                    :all-modules-pool="allModulesPool"
                    :courses-list="coursesList"
                    @notify="triggerToast"
                />
            </main>
        </div>
    </div>
</template>
