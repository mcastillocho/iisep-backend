<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Sidebar from '../../components/Admin/Sidebar/Sidebar.vue';
import OfertaAcademicaView from '../../components/Admin/Pages/OfertaAcademicaView.vue';
import SedesAulasView from '../../components/Admin/Pages/SedesAulasView.vue';
import MatriculasView, {
    type EnrollmentRecord,
} from '../../components/Admin/Pages/MatriculasView.vue';
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
    estimated_capacity: number;
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

const selectedBranchId = ref<number | 'all'>('all');

type CurricularTab = 'carreras' | 'modulos' | 'talleres';
const activeCurricularTab = ref<CurricularTab>('carreras');

// --- DATOS MOCKEADOS REALES ---

// 1. Sedes (branches)
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

// 2. Aulas (classrooms)
const classrooms = ref<Classroom[]>([
    {
        id: 1,
        branch_id: 1,
        branch_name: 'Sede Comas',
        name: 'Aula 101 - Teoría',
        capacity: 35,
        current_scheduled_courses: [
            'Excel Avanzado y Macros (Martes y Jueves 18:00 - 20:00)',
            'Taller de Gestión de Archivos (Sábados 08:00 - 12:00)',
        ],
    },
    {
        id: 2,
        branch_id: 1,
        branch_name: 'Sede Comas',
        name: 'Laboratorio de Cómputo 1',
        capacity: 24,
        current_scheduled_courses: [
            'Arquitectura de Hardware y Diagnóstico (Lunes y Miércoles 08:00 - 10:00)',
            'Ensamblaje de Redes LAN (Viernes 14:00 - 18:00)',
        ],
    },
    {
        id: 3,
        branch_id: 2,
        branch_name: 'Sede Puente Piedra',
        name: 'Aula 201 - Multiusos',
        capacity: 30,
        current_scheduled_courses: [
            'Taller de Barber Shop y Cortes Urbanos (Sábados 14:00 - 18:00)',
        ],
    },
    {
        id: 4,
        branch_id: 2,
        branch_name: 'Sede Puente Piedra',
        name: 'Taller de Estética y Cosmetología',
        capacity: 20,
        current_scheduled_courses: [
            'Taller de Uñas Acrílicas y Polygel (Lunes y Miércoles 16:00 - 19:00)',
        ],
    },
]);

// 3. Catálogo Global de Módulos (para asignación a carreras)
const allModulesPool = ref<ModuleItem[]>([
    {
        id: 1,
        name: 'Ofimática Empresarial',
        description:
            'Word, Excel avanzado, PowerPoint y gestión de documentos en la nube.',
        duration_in_weeks: 8,
        careers_associated: ['Computación e Informática'],
        courses_count: 1,
    },
    {
        id: 2,
        name: 'Ensamblaje y Mantenimiento de PC',
        description:
            'Diagnóstico de hardware, armado de computadoras, instalación de SO y redes LAN.',
        duration_in_weeks: 8,
        careers_associated: ['Computación e Informática'],
        courses_count: 1,
    },
    {
        id: 3,
        name: 'Cajero Bancario y Comercial',
        description:
            'Detección de billetes, sistemas financieros, arqueo de caja y servicio al cliente.',
        duration_in_weeks: 8,
        careers_associated: ['Logística y Almacenes'],
        courses_count: 0,
    },
    {
        id: 4,
        name: 'Pastelería y Repostería Fina',
        description:
            'Técnicas de masas, horneado, decoración y postres internacionales.',
        duration_in_weeks: 12,
        careers_associated: ['Gastronomía y Arte Culinario'],
        courses_count: 0,
    },
    {
        id: 5,
        name: 'Desarrollo Web y Programación Front-End',
        description:
            'HTML5, CSS3, JavaScript y desarrollo de aplicaciones web interactivas.',
        duration_in_weeks: 16,
        careers_associated: [],
        courses_count: 2,
    },
]);

// 4. Carreras Técnicas (careers)
const careers = ref<Career[]>([
    {
        id: 1,
        name: 'Computación e Informática',
        description:
            'Carrera técnica profesional de desarrollo de software, administración de redes y soporte técnico.',
        estimated_capacity: 40,
        available_branch_ids: [1, 2],
        modules: [
            {
                id: 1,
                name: 'Ofimática Empresarial',
                duration_in_weeks: 8,
                order: 1,
            },
            {
                id: 2,
                name: 'Ensamblaje y Mantenimiento de PC',
                duration_in_weeks: 8,
                order: 2,
            },
        ],
    },
    {
        id: 2,
        name: 'Logística y Almacenes',
        description:
            'Especialista en administración de inventarios, cadena de suministro y comercio exterior.',
        estimated_capacity: 30,
        available_branch_ids: [1],
        modules: [
            {
                id: 3,
                name: 'Cajero Bancario y Comercial',
                duration_in_weeks: 8,
                order: 1,
            },
        ],
    },
    {
        id: 3,
        name: 'Gastronomía y Arte Culinario',
        description:
            'Formación integral en técnicas culinarias nacionales e internacionales y gestión de restaurantes.',
        estimated_capacity: 25,
        available_branch_ids: [1, 2],
        modules: [
            {
                id: 4,
                name: 'Pastelería y Repostería Fina',
                duration_in_weeks: 12,
                order: 1,
            },
        ],
    },
]);

// 5. Cursos y Talleres Libres
const coursesList = ref<CourseItem[]>([
    {
        id: 1,
        module_id: 1,
        module_name: 'Ofimática Empresarial',
        name: 'Excel Avanzado y Macros',
        description:
            'Fórmulas lógicas, tablas dinámicas, dashboards y automatización VBA.',
        duration_in_weeks: 4,
        is_workshop: false,
    },
    {
        id: 2,
        module_id: 2,
        module_name: 'Ensamblaje y Mantenimiento de PC',
        name: 'Arquitectura de Hardware y Diagnóstico',
        description:
            'Detección de fallas en placas, memorias, fuentes y almacenamiento.',
        duration_in_weeks: 4,
        is_workshop: false,
    },
    {
        id: 3,
        module_id: null,
        name: 'Taller de Barber Shop y Cortes Urbanos',
        description:
            'Técnicas de degradado, fade, afeitado tradicional y perfilado de barba.',
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
        description:
            'Cambio de pantallas, micro-soldadura, diagnóstico de placas y flasheo.',
        duration_in_weeks: 8,
        is_workshop: true,
    },
]);

// 6. Matrículas
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
            :scheduled-courses-count="4"
            @clear-editing-career="() => {}"
        />

        <!-- CONTENIDO PRINCIPAL A LA DERECHA -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Top bar -->
            <header
                class="flex flex-col gap-4 border-b border-slate-200 bg-white px-8 py-5 shadow-2xs sm:flex-row sm:items-center sm:justify-between"
            >
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
                            <option :value="'all'">Todas las Sedes</option>
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
                        active-tab="inscripciones"
                        :branches="branches"
                        :classrooms="classrooms"
                        :courses="coursesList"
                        :enrollments="enrollments"
                        :selected-branch-id="selectedBranchId"
                        :show-subtabs-nav="false"
                        @notify="triggerToast"
                    />
                </div>

                <!-- 4. SECCIÓN INDEPENDIENTE: APERTURA DE CURSOS Y AULAS (LISTA) -->
                <div v-if="activeMainTab === 'aperturas'">
                    <MatriculasView
                        active-tab="aperturas"
                        :branches="branches"
                        :classrooms="classrooms"
                        :courses="coursesList"
                        :enrollments="enrollments"
                        :selected-branch-id="selectedBranchId"
                        :show-subtabs-nav="false"
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
                                class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 text-purple-700"
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
                                Lógica de Convalidación Atómica en Base de Datos
                            </h3>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-slate-700">
                            En el CETPRO IISEP, las convalidaciones se almacenan
                            directamente en la tabla
                            <code
                                class="rounded bg-slate-100 px-1 py-0.5 font-mono font-bold text-slate-900"
                                >enrollments</code
                            >
                            mediante el valor de enum
                            <code
                                class="rounded bg-purple-50 px-1 py-0.5 font-mono font-bold text-purple-700"
                                >'convalidado'</code
                            >.
                        </p>
                        <div
                            class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4"
                        >
                            <h4
                                class="text-xs font-bold tracking-wider text-[#2e8b57] uppercase"
                            >
                                Cálculo de Certificado Modular Oficial
                            </h4>
                            <code
                                class="mt-2 block rounded-lg border border-emerald-200 bg-white p-3 font-mono text-sm font-bold text-[#2e8b57] shadow-2xs"
                            >
                                status IN ('completado', 'convalidado')
                            </code>
                            <p class="mt-2 text-xs text-slate-600">
                                Si todos los cursos pertenecientes al módulo
                                cumplen esta condición, el sistema acredita el
                                módulo sin requerir repetir materias ya
                                sustentadas.
                            </p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
