<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

interface EnrollmentRecord {
    id: number;
    student_full_name: string;
    student_document: string;
    course_name: string;
    module_name: string | null;
    career_name: string | null;
    branch_name: string;
    enrollment_date: string;
    status: 'activo' | 'convalidado' | 'completado' | 'preinscrito' | 'retirado' | 'anulado';
    notes: string | null;
}

interface AttendanceSession {
    id: number;
    course_name: string;
    classroom_name: string;
    branch_name: string;
    teacher_name: string;
    day_of_week: string;
    time_range: string;
    present_count: number;
    total_count: number;
}

// --- ESTADOS DE NAVEGACIÓN ---
type MainTab = 'infraestructura' | 'curricular' | 'matriculas' | 'asistencia' | 'resumen';
const activeMainTab = ref<MainTab>('infraestructura');
const isMobileSidebarOpen = ref(false);

// Submenús desplegables
const isSedesMenuOpen = ref(true);
const selectedBranchId = ref<number | 'all'>('all');

type CurricularTab = 'carreras' | 'modulos' | 'talleres';
const activeCurricularTab = ref<CurricularTab>('carreras');
const isCurricularMenuOpen = ref(true);

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

// Búsqueda de aulas
const searchClassroomQuery = ref('');

const filteredClassrooms = computed(() => {
    return classrooms.value.filter((room) => {
        if (selectedBranchId.value !== 'all' && room.branch_id !== selectedBranchId.value) {
            return false;
        }
        if (searchClassroomQuery.value.trim() !== '') {
            const q = searchClassroomQuery.value.toLowerCase();
            return (
                room.name.toLowerCase().includes(q) ||
                room.branch_name.toLowerCase().includes(q)
            );
        }
        return true;
    });
});

// Modal de Disponibilidad de Aula
const selectedRoomForSchedule = ref<Classroom | null>(null);
const showScheduleModal = ref(false);

function viewRoomAvailability(room: Classroom) {
    selectedRoomForSchedule.value = room;
    showScheduleModal.value = true;
}

// 3. Catálogo Global de Módulos (para asignación a carreras)
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

// 4. Carreras Técnicas (careers)
const careers = ref<Career[]>([
    {
        id: 1,
        name: 'Computación e Informática',
        description: 'Carrera técnica profesional de desarrollo de software, administración de redes y soporte técnico.',
        estimated_capacity: 40,
        available_branch_ids: [1, 2], // Disponible en Comas y Puente Piedra
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
        available_branch_ids: [1], // Solo en Sede Comas
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

// --- VISTA DETALLADA DE EDICIÓN DE CARRERA ---
const editingCareer = ref<Career | null>(null);
const selectedModuleToAddId = ref<number | ''>('');

// Cantidad de semanas calculada según los módulos seleccionados (solo lectura)
const calculatedWeeks = computed(() => {
    if (!editingCareer.value || !editingCareer.value.modules) return 0;
    return editingCareer.value.modules.reduce((sum, m) => sum + m.duration_in_weeks, 0);
});

// Módulos que aún no han sido añadidos a esta carrera
const availableModulesToAdd = computed(() => {
    if (!editingCareer.value) return [];
    const assignedIds = new Set(editingCareer.value.modules.map((m) => m.id));
    return allModulesPool.value.filter((m) => !assignedIds.has(m.id));
});

function startEditingCareer(carrera: Career) {
    // Clon profundo para edición
    editingCareer.value = JSON.parse(JSON.stringify(carrera));
}

function cancelCareerEditing() {
    editingCareer.value = null;
}

function saveCareerChanges() {
    if (!editingCareer.value) return;
    const index = careers.value.findIndex((c) => c.id === editingCareer.value!.id);
    if (index !== -1) {
        careers.value[index] = JSON.parse(JSON.stringify(editingCareer.value));
    }
    triggerToast(`Carrera "${editingCareer.value.name}" actualizada con éxito.`);
    editingCareer.value = null;
}

function moveModuleUp(index: number) {
    if (!editingCareer.value || index <= 0) return;
    const list = editingCareer.value.modules;
    const temp = list[index];
    list[index] = list[index - 1];
    list[index - 1] = temp;
    list.forEach((m, idx) => (m.order = idx + 1));
}

function moveModuleDown(index: number) {
    if (!editingCareer.value || index >= editingCareer.value.modules.length - 1) return;
    const list = editingCareer.value.modules;
    const temp = list[index];
    list[index] = list[index + 1];
    list[index + 1] = temp;
    list.forEach((m, idx) => (m.order = idx + 1));
}

function removeModuleFromCareer(index: number) {
    if (!editingCareer.value) return;
    editingCareer.value.modules.splice(index, 1);
    editingCareer.value.modules.forEach((m, idx) => (m.order = idx + 1));
}

function addModuleToCareer() {
    if (!editingCareer.value || selectedModuleToAddId.value === '') return;
    const mod = allModulesPool.value.find((m) => m.id === Number(selectedModuleToAddId.value));
    if (mod) {
        editingCareer.value.modules.push({
            id: mod.id,
            name: mod.name,
            duration_in_weeks: mod.duration_in_weeks,
            order: editingCareer.value.modules.length + 1,
        });
        selectedModuleToAddId.value = '';
    }
}

// 5. Cursos y Talleres Libres
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

// 6. Matrículas
const enrollments = ref<EnrollmentRecord[]>([
    {
        id: 1,
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

const searchEnrollmentQuery = ref('');
const statusEnrollmentFilter = ref<'all' | 'activo' | 'convalidado' | 'completado' | 'preinscrito'>('all');

const filteredEnrollments = computed(() => {
    return enrollments.value.filter((record) => {
        if (selectedBranchId.value !== 'all') {
            const currentBranch = branches.value.find((b) => b.id === selectedBranchId.value);
            if (currentBranch && record.branch_name !== currentBranch.name) {
                return false;
            }
        }
        if (statusEnrollmentFilter.value !== 'all' && record.status !== statusEnrollmentFilter.value) {
            return false;
        }
        if (searchEnrollmentQuery.value.trim() !== '') {
            const q = searchEnrollmentQuery.value.toLowerCase();
            return (
                record.student_full_name.toLowerCase().includes(q) ||
                record.student_document.includes(q) ||
                record.course_name.toLowerCase().includes(q)
            );
        }
        return true;
    });
});

// 7. Asistencias
const attendanceSessions = ref<AttendanceSession[]>([
    {
        id: 1,
        course_name: 'Arquitectura de Hardware y Diagnóstico',
        classroom_name: 'Laboratorio de Cómputo 1',
        branch_name: 'Sede Comas',
        teacher_name: 'Marco Antonio Rojas Quispe',
        day_of_week: 'Lunes y Miércoles',
        time_range: '08:00 - 10:00',
        present_count: 2,
        total_count: 2,
    },
    {
        id: 2,
        course_name: 'Excel Avanzado y Macros',
        classroom_name: 'Aula 101 - Teoría',
        branch_name: 'Sede Comas',
        teacher_name: 'Marco Antonio Rojas Quispe',
        day_of_week: 'Martes y Jueves',
        time_range: '18:00 - 20:00',
        present_count: 1,
        total_count: 1,
    },
    {
        id: 3,
        course_name: 'Taller de Barber Shop y Cortes Urbanos',
        classroom_name: 'Aula 201 - Multiusos',
        branch_name: 'Sede Puente Piedra',
        teacher_name: 'Carlos Alberto Gomez Paredes',
        day_of_week: 'Sábado',
        time_range: '14:00 - 18:00',
        present_count: 1,
        total_count: 1,
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

// Modal básico
const showModal = ref(false);
const modalEntityTitle = ref('');

function openNewClassroomModal() {
    modalEntityTitle.value = 'Nueva Aula / Ambiente Físico';
    showModal.value = true;
}

function openEditClassroomModal(room: Classroom) {
    modalEntityTitle.value = `Editar Aula: ${room.name}`;
    showModal.value = true;
}

function saveModalData() {
    showModal.value = false;
    triggerToast(`Registro guardado correctamente.`);
}
</script>

<template>
    <Head title="Panel de Administración - IISEP" />

    <div class="min-h-screen bg-[#f4f7f5] font-sans text-slate-800 text-base antialiased selection:bg-[#2e8b57] selection:text-white flex flex-col md:flex-row">
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
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-[#2e8b57]">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div class="text-sm font-bold text-slate-900">{{ toastMessage }}</div>
            </div>
        </Transition>

        <!-- Mobile Top Bar with Hamburger -->
        <div class="md:hidden flex items-center justify-between bg-white border-b border-slate-200 px-5 py-3.5 sticky top-0 z-40">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#2e8b57] font-black text-white text-lg">
                    II
                </div>
                <span class="text-xl font-black text-[#2e8b57]">IISEP</span>
            </div>
            <button
                @click="isMobileSidebarOpen = !isMobileSidebarOpen"
                class="rounded-lg p-2 text-slate-700 hover:bg-slate-100"
                aria-label="Toggle Menu"
            >
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <!-- SIDEBAR VERTICAL -->
        <aside
            :class="[
                isMobileSidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
                'fixed md:static inset-y-0 left-0 z-50 w-72 md:w-80 bg-white border-r border-slate-200 flex flex-col justify-between transition-transform duration-200 ease-in-out shadow-xs'
            ]"
        >
            <div class="overflow-y-auto">
                <!-- Brand Header -->
                <div class="p-6 border-b border-slate-100 flex items-center gap-3.5">
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
                    <!-- GRUPO 1: SEDES & AULAS -->
                    <div>
                        <div class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase">
                            Ambientes & Sedes
                        </div>

                        <div class="space-y-1">
                            <button
                                @click="activeMainTab = 'infraestructura'; isSedesMenuOpen = !isSedesMenuOpen; editingCareer = null"
                                :class="[
                                    activeMainTab === 'infraestructura'
                                        ? 'bg-emerald-50 text-[#2e8b57] font-bold'
                                        : 'text-slate-700 hover:bg-slate-50 font-semibold',
                                    'w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-sm transition'
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
                                    class="h-4 w-4 text-slate-400 transition-transform"
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
                                    @click="selectedBranchId = 'all'; activeMainTab = 'infraestructura'; isMobileSidebarOpen = false; editingCareer = null"
                                    :class="[
                                        selectedBranchId === 'all' && activeMainTab === 'infraestructura'
                                            ? 'text-[#2e8b57] font-bold bg-white shadow-xs'
                                            : 'text-slate-600 hover:text-slate-900 font-medium',
                                        'w-full text-left px-3 py-2 rounded-md text-xs flex items-center justify-between transition'
                                    ]"
                                >
                                    <span>Todas las Sedes</span>
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-mono">
                                        {{ classrooms.length }} aulas
                                    </span>
                                </button>
                                <button
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    @click="selectedBranchId = branch.id; activeMainTab = 'infraestructura'; isMobileSidebarOpen = false; editingCareer = null"
                                    :class="[
                                        selectedBranchId === branch.id && activeMainTab === 'infraestructura'
                                            ? 'text-[#2e8b57] font-bold bg-white shadow-xs'
                                            : 'text-slate-600 hover:text-slate-900 font-medium',
                                        'w-full text-left px-3 py-2 rounded-md text-xs flex items-center justify-between transition'
                                    ]"
                                >
                                    <span class="truncate">{{ branch.name }}</span>
                                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs text-[#2e8b57] font-bold">
                                        {{ classrooms.filter(c => c.branch_id === branch.id).length }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- GRUPO 2: CARRERAS / MODULOS / TALLERES (NOMBRES SIMPLIFICADOS) -->
                    <div>
                        <div class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase">
                            Oferta Académica
                        </div>

                        <div class="space-y-1">
                            <!-- Botón Carreras -->
                            <button
                                @click="activeCurricularTab = 'carreras'; activeMainTab = 'curricular'; isMobileSidebarOpen = false; editingCareer = null"
                                :class="[
                                    activeMainTab === 'curricular' && activeCurricularTab === 'carreras'
                                        ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                        : 'text-slate-700 hover:bg-slate-50 font-semibold',
                                    'w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-sm transition'
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <svg class="h-5 w-5 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                    </svg>
                                    <span>Carreras</span>
                                </div>
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-mono">{{ careers.length }}</span>
                            </button>

                            <!-- Botón Módulos -->
                            <button
                                @click="activeCurricularTab = 'modulos'; activeMainTab = 'curricular'; isMobileSidebarOpen = false; editingCareer = null"
                                :class="[
                                    activeMainTab === 'curricular' && activeCurricularTab === 'modulos'
                                        ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                        : 'text-slate-700 hover:bg-slate-50 font-semibold',
                                    'w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-sm transition'
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <svg class="h-5 w-5 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    <span>Módulos</span>
                                </div>
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-mono">{{ allModulesPool.length }}</span>
                            </button>

                            <!-- Botón Talleres -->
                            <button
                                @click="activeCurricularTab = 'talleres'; activeMainTab = 'curricular'; isMobileSidebarOpen = false; editingCareer = null"
                                :class="[
                                    activeMainTab === 'curricular' && activeCurricularTab === 'talleres'
                                        ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                        : 'text-slate-700 hover:bg-slate-50 font-semibold',
                                    'w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-sm transition'
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <svg class="h-5 w-5 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span>Talleres</span>
                                </div>
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-mono">{{ coursesList.length }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- GRUPO 3: OPERACIONES DIARIAS -->
                    <div>
                        <div class="px-3 pb-1.5 text-xs font-bold tracking-wider text-slate-400 uppercase">
                            Operaciones Diarias
                        </div>

                        <div class="space-y-1">
                            <button
                                @click="activeMainTab = 'matriculas'; isMobileSidebarOpen = false; editingCareer = null"
                                :class="[
                                    activeMainTab === 'matriculas'
                                        ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                        : 'text-slate-700 hover:bg-slate-50 font-semibold',
                                    'w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-sm transition'
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <svg class="h-5 w-5 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span>Matrículas & Conval.</span>
                                </div>
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-[#2e8b57]">
                                    {{ enrollments.length }}
                                </span>
                            </button>

                            <button
                                @click="activeMainTab = 'asistencia'; isMobileSidebarOpen = false; editingCareer = null"
                                :class="[
                                    activeMainTab === 'asistencia'
                                        ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                        : 'text-slate-700 hover:bg-slate-50 font-semibold',
                                    'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm transition'
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
                            @click="activeMainTab = 'resumen'; isMobileSidebarOpen = false; editingCareer = null"
                            :class="[
                                activeMainTab === 'resumen'
                                    ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                    : 'text-slate-700 hover:bg-slate-50 font-semibold',
                                'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm transition'
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

            <!-- User Info at Bottom of Sidebar -->
            <div class="p-5 border-t border-slate-100 bg-slate-50/80">
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

        <!-- CONTENIDO PRINCIPAL -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top bar -->
            <header class="bg-white border-b border-slate-200 px-8 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-2xs">
                <div>
                    <h1 class="text-2xl font-black text-slate-900">
                        <span v-if="activeMainTab === 'infraestructura'">Sedes & Aulas</span>
                        <span v-else-if="activeMainTab === 'curricular'">
                            <span v-if="editingCareer">Editar Carrera</span>
                            <span v-else-if="activeCurricularTab === 'carreras'">Carreras</span>
                            <span v-else-if="activeCurricularTab === 'modulos'">Módulos</span>
                            <span v-else>Talleres</span>
                        </span>
                        <span v-else-if="activeMainTab === 'matriculas'">Matrículas & Convalidaciones</span>
                        <span v-else-if="activeMainTab === 'asistencia'">Sesiones & Asistencias</span>
                        <span v-else>Panel Resumen</span>
                    </h1>
                    <p class="text-sm text-slate-500 font-medium mt-0.5">
                        <span v-if="activeMainTab === 'infraestructura'">Control de ambientes físicos y disponibilidad por sede</span>
                        <span v-else-if="activeMainTab === 'curricular'">
                            <span v-if="editingCareer">Modificación de módulos, orden y sedes de impartición</span>
                            <span v-else-if="activeCurricularTab === 'carreras'">Catálogo de carreras técnicas</span>
                            <span v-else-if="activeCurricularTab === 'modulos'">Módulos certificados del CETPRO</span>
                            <span v-else>Cursos y talleres libres</span>
                        </span>
                        <span v-else-if="activeMainTab === 'matriculas'">Inscripciones de estudiantes y convalidaciones</span>
                        <span v-else-if="activeMainTab === 'asistencia'">Control de asistencia presencial</span>
                        <span v-else>Métricas de retención institucional</span>
                    </p>
                </div>

                <!-- Sede Filter Selector -->
                <div class="flex items-center gap-2.5">
                    <span class="text-sm text-slate-600 font-semibold hidden sm:inline">Sede:</span>
                    <div class="flex items-center gap-2 rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-bold text-slate-800 shadow-2xs">
                        <svg class="h-4 w-4 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <select
                            id="branch-selector"
                            v-model="selectedBranchId"
                            class="bg-transparent text-sm font-bold text-slate-800 focus:outline-none cursor-pointer"
                        >
                            <option :value="'all'">Todas las Sedes</option>
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                {{ branch.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </header>

            <!-- MAIN CONTENT AREA -->
            <main class="flex-1 p-8 max-w-7xl w-full mx-auto space-y-6">

                <!-- 1. SECCIÓN: SEDES & AULAS (CON BOTÓN VER DISPONIBILIDAD) -->
                <div v-if="activeMainTab === 'infraestructura'" class="space-y-6">
                    <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                        <!-- Buscador -->
                        <div class="relative flex-1 sm:max-w-md">
                            <svg class="pointer-events-none absolute top-3 left-3.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                id="search-classroom-input"
                                v-model="searchClassroomQuery"
                                type="text"
                                placeholder="Buscar aula por nombre o sede..."
                                class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-11 text-sm text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:ring-1 focus:ring-[#2e8b57] focus:outline-none"
                            />
                        </div>

                        <!-- Botón para registrar nueva aula -->
                        <div>
                            <button
                                id="btn-nueva-aula"
                                @click="openNewClassroomModal"
                                class="inline-flex items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#257347]"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>+ Nueva Aula</span>
                            </button>
                        </div>
                    </div>

                    <!-- Tabla de Aulas (Limpia: Nombre, Sede, Aforo, Disponibilidad de Horarios y Acciones) -->
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-slate-700">
                                <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold tracking-wider text-slate-600 uppercase">
                                    <tr>
                                        <th scope="col" class="py-4 pr-4 pl-6">Nombre del Aula</th>
                                        <th scope="col" class="px-4 py-4">Sede Asignada</th>
                                        <th scope="col" class="px-4 py-4">Capacidad (Aforo)</th>
                                        <th scope="col" class="px-4 py-4">Horarios & Disponibilidad</th>
                                        <th scope="col" class="py-4 pr-6 pl-4 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="room in filteredClassrooms"
                                        :key="room.id"
                                        class="transition hover:bg-emerald-50/40"
                                    >
                                        <!-- Room Name -->
                                        <td class="py-4 pr-4 pl-6">
                                            <div class="text-base font-bold text-slate-900">{{ room.name }}</div>
                                        </td>

                                        <!-- Branch -->
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-2 font-semibold text-slate-800">
                                                <span class="h-2.5 w-2.5 rounded-full" :class="room.branch_name.includes('Comas') ? 'bg-[#2e8b57]' : 'bg-teal-600'"></span>
                                                {{ room.branch_name }}
                                            </span>
                                        </td>

                                        <!-- Capacity -->
                                        <td class="px-4 py-4 whitespace-nowrap font-mono font-bold text-slate-900">
                                            <span v-if="room.capacity">{{ room.capacity }} vacantes</span>
                                            <span v-else class="text-slate-400 font-normal italic">Sin límite</span>
                                        </td>

                                        <!-- Ver Disponibilidad (Evita sobrecargar la tabla) -->
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <button
                                                @click="viewRoomAvailability(room)"
                                                class="inline-flex items-center gap-1.5 text-xs font-bold text-[#2e8b57] bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-3 py-1.5 rounded-md transition"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>Ver disponibilidad</span>
                                            </button>
                                        </td>

                                        <!-- Action Edit Button -->
                                        <td class="py-4 pr-6 pl-4 text-right whitespace-nowrap">
                                            <button
                                                @click="openEditClassroomModal(room)"
                                                class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-[#2e8b57] transition"
                                            >
                                                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                                <span>Editar Aula</span>
                                            </button>
                                        </td>
                                    </tr>

                                    <tr v-if="filteredClassrooms.length === 0">
                                        <td colspan="5" class="py-12 text-center text-slate-500 font-medium">
                                            No se encontraron aulas en la sede seleccionada.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 2. SECCIÓN: CARRERAS / MÓDULOS / TALLERES -->
                <div v-if="activeMainTab === 'curricular'" class="space-y-6">

                    <!-- VISTA DETALLADA: EDITAR CARRERA (Redirección interna al pulsar Editar) -->
                    <div v-if="editingCareer" class="rounded-xl border border-slate-200 bg-white p-7 shadow-xs space-y-6">
                        <!-- Cabecera de Edición -->
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div class="flex items-center gap-3">
                                <button
                                    @click="cancelCareerEditing"
                                    class="inline-flex items-center gap-1 text-sm font-bold text-slate-600 hover:text-slate-900 bg-slate-100 px-3 py-1.5 rounded-lg transition"
                                >
                                    &larr; Volver a Carreras
                                </button>
                                <h2 class="text-xl font-black text-slate-900">
                                    Editando Carrera: <span class="text-[#2e8b57]">{{ editingCareer.name }}</span>
                                </h2>
                            </div>

                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    @click="cancelCareerEditing"
                                    class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    @click="saveCareerChanges"
                                    class="px-5 py-2 bg-[#2e8b57] hover:bg-[#257347] text-white text-sm font-bold rounded-lg shadow-sm transition"
                                >
                                    Guardar Cambios
                                </button>
                            </div>
                        </div>

                        <!-- Formulario de Datos Básicos de la Carrera -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Nombre -->
                            <div>
                                <label class="block text-sm font-bold text-slate-800">Nombre de la Carrera</label>
                                <input
                                    v-model="editingCareer.name"
                                    type="text"
                                    class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                />
                            </div>

                            <!-- Aforo estimado -->
                            <div>
                                <label class="block text-sm font-bold text-slate-800">Aforo / Vacantes Estimadas</label>
                                <input
                                    v-model="editingCareer.estimated_capacity"
                                    type="number"
                                    placeholder="Ej. 40"
                                    class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                />
                            </div>

                            <!-- Duración Calculada (Solo Lectura) -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <label class="block text-sm font-bold text-slate-800">Duración Total</label>
                                    <span class="text-xs font-semibold text-slate-400 italic">Calculada automáticamente</span>
                                </div>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <input
                                        :value="`${calculatedWeeks} semanas (${Math.round((calculatedWeeks / 4) * 10) / 10} meses)`"
                                        type="text"
                                        readonly
                                        disabled
                                        class="w-full rounded-lg border border-slate-200 bg-slate-100 px-3.5 py-2.5 text-sm font-mono font-bold text-slate-700 cursor-not-allowed"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Sedes Disponibles (Marcables) -->
                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-5 space-y-2">
                            <label class="block text-sm font-bold text-slate-800">
                                Sedes Disponibles para esta Carrera
                            </label>
                            <p class="text-xs text-slate-500 mb-3">
                                Selecciona en qué sedes físicas estará aperturada esta carrera (a futuro se persistirá mediante tabla de asignación por sede):
                            </p>
                            <div class="flex flex-wrap items-center gap-6">
                                <label
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    class="flex items-center gap-2.5 cursor-pointer bg-white border border-slate-300 rounded-lg px-4 py-2 hover:border-[#2e8b57]"
                                >
                                    <input
                                        type="checkbox"
                                        :value="branch.id"
                                        v-model="editingCareer.available_branch_ids"
                                        class="h-4 w-4 rounded text-[#2e8b57] focus:ring-[#2e8b57]"
                                    />
                                    <span class="text-sm font-bold text-slate-800">{{ branch.name }}</span>
                                    <span class="text-xs text-slate-400">({{ branch.code }})</span>
                                </label>
                            </div>
                        </div>

                        <!-- PANEL DE GESTIÓN Y ORDEN DE MÓDULOS -->
                        <div class="space-y-4 pt-2">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">Módulos Componentes de la Carrera</h3>
                                    <p class="text-xs text-slate-500">
                                        Organiza los módulos en orden correlativo y cronológico de dictado (career_module).
                                    </p>
                                </div>

                                <!-- Añadir módulo existente -->
                                <div class="flex items-center gap-2">
                                    <select
                                        v-model="selectedModuleToAddId"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:border-[#2e8b57]"
                                    >
                                        <option value="">-- Seleccionar Módulo Existente --</option>
                                        <option
                                            v-for="mod in availableModulesToAdd"
                                            :key="mod.id"
                                            :value="mod.id"
                                        >
                                            {{ mod.name }} ({{ mod.duration_in_weeks }} semanas)
                                        </option>
                                    </select>
                                    <button
                                        @click="addModuleToCareer"
                                        :disabled="selectedModuleToAddId === ''"
                                        class="px-3.5 py-2 bg-[#2e8b57] hover:bg-[#257347] disabled:bg-slate-300 text-white text-xs font-bold rounded-lg transition"
                                    >
                                        + Asignar a Carrera
                                    </button>
                                </div>
                            </div>

                            <!-- Tabla / Lista de Módulos Ordenables -->
                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <table class="w-full text-left text-sm text-slate-700">
                                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                                        <tr>
                                            <th class="py-3 px-4 w-16 text-center">Orden</th>
                                            <th class="py-3 px-4">Nombre del Módulo Formativo</th>
                                            <th class="py-3 px-4">Duración</th>
                                            <th class="py-3 px-4 text-center w-36">Cambiar Orden</th>
                                            <th class="py-3 px-4 text-right w-24">Quitar</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr
                                            v-for="(mod, index) in editingCareer.modules"
                                            :key="mod.id"
                                            class="hover:bg-slate-50"
                                        >
                                            <!-- Orden -->
                                            <td class="py-3 px-4 text-center font-mono font-bold text-slate-900 bg-slate-50/50">
                                                {{ mod.order }}
                                            </td>

                                            <!-- Nombre -->
                                            <td class="py-3 px-4 font-bold text-slate-900">
                                                {{ mod.name }}
                                            </td>

                                            <!-- Duración -->
                                            <td class="py-3 px-4 font-mono font-semibold text-[#2e8b57]">
                                                {{ mod.duration_in_weeks }} semanas
                                            </td>

                                            <!-- Botones para reordenar -->
                                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                                <div class="inline-flex items-center gap-1">
                                                    <button
                                                        @click="moveModuleUp(index)"
                                                        :disabled="index === 0"
                                                        title="Mover hacia arriba"
                                                        class="p-1 rounded border border-slate-300 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed"
                                                    >
                                                        <svg class="h-4 w-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        @click="moveModuleDown(index)"
                                                        :disabled="index === editingCareer.modules.length - 1"
                                                        title="Mover hacia abajo"
                                                        class="p-1 rounded border border-slate-300 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed"
                                                    >
                                                        <svg class="h-4 w-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>

                                            <!-- Quitar -->
                                            <td class="py-3 px-4 text-right">
                                                <button
                                                    @click="removeModuleFromCareer(index)"
                                                    title="Quitar módulo de esta carrera"
                                                    class="text-red-500 hover:text-red-700 text-xs font-bold"
                                                >
                                                    ✕ Quitar
                                                </button>
                                            </td>
                                        </tr>

                                        <tr v-if="editingCareer.modules.length === 0">
                                            <td colspan="5" class="py-8 text-center text-slate-400 font-medium">
                                                Esta carrera aún no cuenta con módulos asignados. Usa el selector arriba para asignar el primer módulo.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- VISTAS REGULARES (CUANDO NO SE ESTÁ EDITANDO UNA CARRERA) -->
                    <div v-else class="space-y-6">
                        <!-- Sub-Pestañas Simplificadas: Carreras / Módulos / Talleres -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-4">
                            <div class="flex space-x-2">
                                <button
                                    @click="activeCurricularTab = 'carreras'"
                                    :class="[
                                        activeCurricularTab === 'carreras'
                                            ? 'bg-[#2e8b57] text-white font-bold shadow-xs'
                                            : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-300 font-semibold',
                                        'px-5 py-2.5 rounded-lg text-sm transition flex items-center gap-2'
                                    ]"
                                >
                                    <span>Carreras</span>
                                    <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs">{{ careers.length }}</span>
                                </button>

                                <button
                                    @click="activeCurricularTab = 'modulos'"
                                    :class="[
                                        activeCurricularTab === 'modulos'
                                            ? 'bg-[#2e8b57] text-white font-bold shadow-xs'
                                            : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-300 font-semibold',
                                        'px-5 py-2.5 rounded-lg text-sm transition flex items-center gap-2'
                                    ]"
                                >
                                    <span>Módulos</span>
                                    <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs">{{ allModulesPool.length }}</span>
                                </button>

                                <button
                                    @click="activeCurricularTab = 'talleres'"
                                    :class="[
                                        activeCurricularTab === 'talleres'
                                            ? 'bg-[#2e8b57] text-white font-bold shadow-xs'
                                            : 'bg-white text-slate-700 hover:text-slate-900 border border-slate-300 font-semibold',
                                        'px-5 py-2.5 rounded-lg text-sm transition flex items-center gap-2'
                                    ]"
                                >
                                    <span>Talleres</span>
                                    <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs">{{ coursesList.length }}</span>
                                </button>
                            </div>

                            <!-- Botones de Creación Contextual -->
                            <div>
                                <button
                                    v-if="activeCurricularTab === 'carreras'"
                                    @click="triggerToast('Abrir modal de nueva carrera')"
                                    class="inline-flex items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#257347]"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span>+ Nueva Carrera</span>
                                </button>

                                <button
                                    v-else-if="activeCurricularTab === 'modulos'"
                                    @click="triggerToast('Abrir modal de nuevo módulo')"
                                    class="inline-flex items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#257347]"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span>+ Nuevo Módulo</span>
                                </button>

                                <button
                                    v-else-if="activeCurricularTab === 'talleres'"
                                    @click="triggerToast('Abrir modal de nuevo taller')"
                                    class="inline-flex items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#257347]"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span>+ Nuevo Taller / Curso</span>
                                </button>
                            </div>
                        </div>

                        <!-- 2.1 SUB-PESTAÑA CARRERAS COMO LISTA (COMO PIDIÓ EL USUARIO) -->
                        <div v-if="activeCurricularTab === 'carreras'" class="space-y-4">
                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                                <table class="w-full text-left text-sm text-slate-700">
                                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                                        <tr>
                                            <th class="py-4 px-6">Nombre de la Carrera</th>
                                            <th class="py-4 px-4">Módulos Componentes</th>
                                            <th class="py-4 px-4">Duración Calculada</th>
                                            <th class="py-4 px-4">Sedes Disponibles</th>
                                            <th class="py-4 px-6 text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr
                                            v-for="carrera in careers"
                                            :key="carrera.id"
                                            class="hover:bg-slate-50 transition"
                                        >
                                            <td class="py-4 px-6">
                                                <div class="font-bold text-base text-slate-900">{{ carrera.name }}</div>
                                                <div class="text-xs text-slate-500 line-clamp-1 mt-0.5">{{ carrera.description }}</div>
                                            </td>

                                            <td class="py-4 px-4">
                                                <span class="font-bold text-slate-800">{{ carrera.modules.length }} módulos</span>
                                                <div class="text-xs text-slate-500 line-clamp-1">
                                                    {{ carrera.modules.map(m => m.name).join(', ') }}
                                                </div>
                                            </td>

                                            <td class="py-4 px-4 font-mono font-bold text-[#2e8b57]">
                                                {{ carrera.modules.reduce((s, m) => s + m.duration_in_weeks, 0) }} semanas
                                            </td>

                                            <td class="py-4 px-4">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span
                                                        v-for="bId in carrera.available_branch_ids"
                                                        :key="bId"
                                                        class="rounded bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs font-bold text-[#2e8b57]"
                                                    >
                                                        {{ branches.find(b => b.id === bId)?.name || 'Sede' }}
                                                    </span>
                                                </div>
                                            </td>

                                            <td class="py-4 px-6 text-right whitespace-nowrap">
                                                <button
                                                    @click="startEditingCareer(carrera)"
                                                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-[#2e8b57] transition"
                                                >
                                                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                    <span>Editar</span>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 2.2 SUB-PESTAÑA: MÓDULOS -->
                        <div v-if="activeCurricularTab === 'modulos'" class="space-y-4">
                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                                <table class="w-full text-left text-sm text-slate-700">
                                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                                        <tr>
                                            <th class="py-4 px-6">Módulo Formativo</th>
                                            <th class="py-4 px-4">Descripción</th>
                                            <th class="py-4 px-4">Duración</th>
                                            <th class="py-4 px-4">Carreras Asociadas</th>
                                            <th class="py-4 px-6 text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="mod in allModulesPool" :key="mod.id" class="hover:bg-slate-50 transition">
                                            <td class="py-4 px-6 font-bold text-slate-900">{{ mod.name }}</td>
                                            <td class="py-4 px-4 text-xs text-slate-600 max-w-sm">{{ mod.description }}</td>
                                            <td class="py-4 px-4 font-mono font-bold text-slate-800">{{ mod.duration_in_weeks }} semanas</td>
                                            <td class="py-4 px-4">
                                                <span
                                                    v-for="car in mod.careers_associated"
                                                    :key="car"
                                                    class="rounded bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs font-bold text-[#2e8b57] inline-block mr-1"
                                                >
                                                    {{ car }}
                                                </span>
                                                <span v-if="mod.careers_associated.length === 0" class="text-xs text-slate-400 italic">Módulo independiente</span>
                                            </td>
                                            <td class="py-4 px-6 text-right">
                                                <button
                                                    @click="triggerToast(`Editar módulo: ${mod.name}`)"
                                                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-[#2e8b57] transition"
                                                >
                                                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                    <span>Editar</span>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 2.3 SUB-PESTAÑA: TALLERES -->
                        <div v-if="activeCurricularTab === 'talleres'" class="space-y-4">
                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                                <table class="w-full text-left text-sm text-slate-700">
                                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                                        <tr>
                                            <th class="py-4 px-6">Nombre de Curso / Taller</th>
                                            <th class="py-4 px-4">Clasificación</th>
                                            <th class="py-4 px-4">Módulo Perteneciente</th>
                                            <th class="py-4 px-4">Duración</th>
                                            <th class="py-4 px-6 text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="curso in coursesList" :key="curso.id" class="hover:bg-slate-50 transition">
                                            <td class="py-4 px-6">
                                                <div class="font-bold text-slate-900">{{ curso.name }}</div>
                                                <div class="text-xs text-slate-500 line-clamp-1 mt-0.5">{{ curso.description }}</div>
                                            </td>
                                            <td class="py-4 px-4">
                                                <span
                                                    v-if="curso.is_workshop"
                                                    class="rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-bold text-amber-800"
                                                >
                                                    Taller Libre
                                                </span>
                                                <span
                                                    v-else
                                                    class="rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-bold text-[#2e8b57]"
                                                >
                                                    Curso Modular
                                                </span>
                                            </td>
                                            <td class="py-4 px-4 text-slate-700 font-semibold">
                                                {{ curso.module_name || 'Ninguno (Taller Independiente)' }}
                                            </td>
                                            <td class="py-4 px-4 font-mono font-bold text-slate-800">
                                                {{ curso.duration_in_weeks }} semanas
                                            </td>
                                            <td class="py-4 px-6 text-right">
                                                <button
                                                    @click="triggerToast(`Editar taller: ${curso.name}`)"
                                                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-[#2e8b57] transition"
                                                >
                                                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                    <span>Editar</span>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. SECCIÓN: MATRÍCULAS Y CONVALIDACIONES -->
                <div v-if="activeMainTab === 'matriculas'" class="space-y-6">
                    <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative flex-1 sm:max-w-md">
                            <svg class="pointer-events-none absolute top-3 left-3.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                v-model="searchEnrollmentQuery"
                                type="text"
                                placeholder="Buscar por DNI, alumno o curso..."
                                class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-11 text-sm text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:ring-1 focus:ring-[#2e8b57] focus:outline-none"
                            />
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                @click="statusEnrollmentFilter = 'all'"
                                :class="[
                                    statusEnrollmentFilter === 'all'
                                        ? 'bg-slate-800 text-white font-bold'
                                        : 'bg-slate-100 text-slate-700 hover:text-slate-900',
                                    'rounded-md px-3.5 py-1.5 text-xs transition font-semibold'
                                ]"
                            >
                                Todos ({{ enrollments.length }})
                            </button>
                            <button
                                @click="statusEnrollmentFilter = 'convalidado'"
                                :class="[
                                    statusEnrollmentFilter === 'convalidado'
                                        ? 'bg-purple-700 text-white font-bold'
                                        : 'bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200',
                                    'rounded-md px-3.5 py-1.5 text-xs transition font-semibold'
                                ]"
                            >
                                Convalidados
                            </button>
                            <button
                                @click="statusEnrollmentFilter = 'activo'"
                                :class="[
                                    statusEnrollmentFilter === 'activo'
                                        ? 'bg-[#2e8b57] text-white font-bold'
                                        : 'bg-emerald-50 text-[#2e8b57] hover:bg-emerald-100 border border-emerald-200',
                                    'rounded-md px-3.5 py-1.5 text-xs transition font-semibold'
                                ]"
                            >
                                Activos
                            </button>
                        </div>
                    </div>

                    <!-- Tabla de Matrículas -->
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                        <table class="w-full text-left text-sm text-slate-700">
                            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                                <tr>
                                    <th class="py-4 pr-4 pl-6">Estudiante / DNI</th>
                                    <th class="px-4 py-4">Materia / Taller</th>
                                    <th class="px-4 py-4">Módulo & Carrera</th>
                                    <th class="px-4 py-4">Sede</th>
                                    <th class="px-4 py-4">Fecha</th>
                                    <th class="px-4 py-4">Estado</th>
                                    <th class="py-4 pr-6 pl-4">Observaciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in filteredEnrollments" :key="item.id" class="transition hover:bg-emerald-50/40">
                                    <td class="py-4 pr-4 pl-6">
                                        <div class="font-bold text-slate-900">{{ item.student_full_name }}</div>
                                        <div class="font-mono text-xs text-slate-500">DNI: {{ item.student_document }}</div>
                                    </td>
                                    <td class="px-4 py-4 font-bold text-slate-800">{{ item.course_name }}</td>
                                    <td class="px-4 py-4">
                                        <div v-if="item.module_name" class="font-semibold text-slate-700">{{ item.module_name }}</div>
                                        <div v-if="item.career_name" class="text-xs text-slate-500 font-medium">{{ item.career_name }}</div>
                                        <span v-if="!item.module_name && !item.career_name" class="inline-block rounded-sm bg-amber-50 border border-amber-200 px-2 py-0.5 text-xs font-bold text-amber-800">
                                            Taller Independiente
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-2 font-semibold text-slate-800">
                                            <span class="h-2.5 w-2.5 rounded-full" :class="item.branch_name.includes('Comas') ? 'bg-[#2e8b57]' : 'bg-teal-600'"></span>
                                            {{ item.branch_name }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 font-mono text-xs text-slate-600 whitespace-nowrap">{{ item.enrollment_date }}</td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span v-if="item.status === 'convalidado'" class="inline-flex items-center gap-1.5 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-bold text-purple-700 shadow-2xs">
                                            <svg class="h-4 w-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            Convalidado
                                        </span>
                                        <span v-else-if="item.status === 'activo'" class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-[#2e8b57]">
                                            <span class="h-2 w-2 animate-pulse rounded-full bg-[#2e8b57]"></span>
                                            Activo
                                        </span>
                                        <span v-else-if="item.status === 'completado'" class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                                            Completado
                                        </span>
                                        <span v-else class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">
                                            Preinscrito
                                        </span>
                                    </td>
                                    <td class="py-4 pr-6 pl-4 text-xs text-slate-600">
                                        {{ item.notes || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. SECCIÓN: ASISTENCIA & HORARIOS -->
                <div v-if="activeMainTab === 'asistencia'" class="space-y-6">
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Sesiones en Dictado Actual (course_schedules)</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Control de asistencia por ambiente físico</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                            <div
                                v-for="session in attendanceSessions"
                                :key="session.id"
                                class="rounded-xl border border-slate-200 bg-slate-50/70 p-5 shadow-2xs"
                            >
                                <div class="flex items-start justify-between">
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-[#2e8b57]">
                                        {{ session.branch_name }}
                                    </span>
                                    <span class="font-mono text-xs font-bold text-[#2e8b57]">
                                        {{ session.present_count }}/{{ session.total_count }} presentes
                                    </span>
                                </div>
                                <h4 class="mt-3 text-sm font-bold text-slate-900">{{ session.course_name }}</h4>
                                <p class="mt-1 text-xs text-slate-600 font-semibold">🏫 {{ session.classroom_name }}</p>
                                <p class="text-xs text-slate-600 font-medium">👨‍🏫 Docente: {{ session.teacher_name }}</p>
                                <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-3 text-xs">
                                    <span class="text-slate-600 font-medium font-mono">⏰ {{ session.day_of_week }} ({{ session.time_range }})</span>
                                    <button
                                        @click="triggerToast('Marcado de asistencia actualizado')"
                                        class="text-[#2e8b57] hover:text-[#257347] font-bold"
                                    >
                                        Tomar Lista &rarr;
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. SECCIÓN: RESUMEN Y AUDITORÍA DE CONVALIDACIONES -->
                <div v-if="activeMainTab === 'resumen'" class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xs">
                            <span class="text-xs font-bold text-slate-500 uppercase">Aulas Operativas</span>
                            <div class="mt-1 text-2xl font-black text-slate-900">{{ classrooms.length }} Ambientes</div>
                            <span class="text-xs text-emerald-600 font-semibold">En ambas sedes</span>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xs">
                            <span class="text-xs font-bold text-slate-500 uppercase">Sedes Presenciales</span>
                            <div class="mt-1 text-2xl font-black text-[#2e8b57]">{{ branches.length }} Sedes</div>
                            <span class="text-xs text-slate-500">Comas y Puente Piedra</span>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-2xs">
                            <span class="text-xs font-bold text-slate-500 uppercase">Cursos & Talleres</span>
                            <div class="mt-1 text-2xl font-black text-slate-900">{{ coursesList.length }} Unidades</div>
                            <span class="text-xs text-purple-700 font-semibold">Duración en semanas</span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                        <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 text-purple-700">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 class="text-base font-bold text-slate-900">Lógica de Convalidación Atómica en Base de Datos</h3>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-slate-700">
                            En el CETPRO IISEP, las convalidaciones se almacenan directamente en la tabla <code class="font-mono font-bold text-slate-900 bg-slate-100 px-1 py-0.5 rounded">enrollments</code> mediante el valor de enum <code class="font-mono font-bold text-purple-700 bg-purple-50 px-1 py-0.5 rounded">'convalidado'</code>.
                        </p>
                        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4">
                            <h4 class="text-xs font-bold text-[#2e8b57] uppercase tracking-wider">Cálculo de Certificado Modular Oficial</h4>
                            <code class="mt-2 block rounded-lg bg-white border border-emerald-200 p-3 font-mono text-sm font-bold text-[#2e8b57] shadow-2xs">
                                status IN ('completado', 'convalidado')
                            </code>
                            <p class="mt-2 text-xs text-slate-600">
                                Si todos los cursos pertenecientes al módulo cumplen esta condición, el sistema acredita el módulo sin requerir repetir materias ya sustentadas.
                            </p>
                        </div>
                    </div>
                </div>

            </main>
        </div>

        <!-- MODAL: VER DISPONIBILIDAD DEL AULA (CONSULTA LIMPIA A DEMANDA) -->
        <div
            v-if="showScheduleModal && selectedRoomForSchedule"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showScheduleModal = false"
        >
            <div class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-7 shadow-2xl space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-[#2e8b57]">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-slate-900">{{ selectedRoomForSchedule.name }}</h3>
                            <p class="text-xs text-slate-500 font-medium">
                                {{ selectedRoomForSchedule.branch_name }} &bull; Aforo: {{ selectedRoomForSchedule.capacity }} vacantes
                            </p>
                        </div>
                    </div>
                    <button
                        @click="showScheduleModal = false"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Cursos y Turnos en esta Aula -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                        Cursos Programados en este Ambiente (course_schedules)
                    </h4>

                    <div v-if="selectedRoomForSchedule.current_scheduled_courses.length > 0" class="space-y-2">
                        <div
                            v-for="(sched, idx) in selectedRoomForSchedule.current_scheduled_courses"
                            :key="idx"
                            class="flex items-center justify-between p-3.5 rounded-lg border border-emerald-100 bg-emerald-50/50"
                        >
                            <span class="text-sm font-bold text-slate-800">{{ sched }}</span>
                            <span class="rounded bg-emerald-100 text-[#2e8b57] text-xs font-bold px-2 py-0.5">En curso</span>
                        </div>
                    </div>
                    <div v-else class="p-6 text-center text-slate-400 text-sm font-medium bg-slate-50 rounded-lg border border-dashed border-slate-200">
                        No hay cursos activos ocupando este ambiente actualmente. Totalmente disponible.
                    </div>
                </div>

                <div class="flex items-center justify-end border-t border-slate-100 pt-4">
                    <button
                        @click="showScheduleModal = false"
                        class="rounded-lg bg-slate-800 hover:bg-slate-900 px-5 py-2 text-sm font-bold text-white transition"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL GENÉRICO DE CREACIÓN/EDICIÓN DE AULA -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showModal = false"
        >
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <h3 class="text-lg font-bold text-slate-900">{{ modalEntityTitle }}</h3>
                    <button
                        @click="showModal = false"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveModalData" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-800">Nombre del Aula</label>
                        <input
                            type="text"
                            required
                            placeholder="Ej. Aula 102 - Teoría"
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-800">Sede</label>
                            <select
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none font-semibold"
                            >
                                <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-800">Capacidad / Aforo</label>
                            <input
                                type="number"
                                placeholder="Ej. 30 vacantes"
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5 mt-6">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-[#2e8b57] hover:bg-[#257347] px-5 py-2 text-sm font-bold text-white shadow-xs transition"
                        >
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
