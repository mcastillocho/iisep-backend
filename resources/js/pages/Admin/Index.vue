<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Sede {
    id: number;
    name: string;
    code: string;
    address: string;
    classroomsCount: number;
    activeStudents: number;
    headCoordinator: string;
}

interface Carrera {
    id: number;
    name: string;
    modulesCount: number;
    duration: string;
    category: string;
    code: string;
}

interface CourseItem {
    id: number;
    name: string;
    moduleName?: string;
    durationInWeeks: number;
    isWorkshop: boolean;
    activeStudents: number;
}

interface EnrollmentRecord {
    id: number;
    studentName: string;
    studentDni: string;
    courseName: string;
    moduleName?: string;
    careerName?: string;
    branchName: string;
    enrollmentDate: string;
    status: 'activo' | 'convalidado' | 'completado' | 'preinscrito' | 'retirado' | 'anulado';
    resolutionOrNote?: string;
}

interface AttendanceSession {
    id: number;
    courseName: string;
    classroom: string;
    branchName: string;
    teacherName: string;
    schedule: string;
    presentCount: number;
    totalCount: number;
}

// Roles / Permisos Mockeados
type UserRole = 'coordinador' | 'secretaria' | 'director' | 'docente';
const currentRole = ref<UserRole>('coordinador');

// Navigation Tabs
type TabType = 'matriculas' | 'resumen' | 'academico' | 'sedes' | 'asistencia';
const activeTab = ref<TabType>('matriculas');
const isMobileSidebarOpen = ref(false);

// Sede Selector Filter
const selectedBranchId = ref<number | 'all'>('all');

// Mocked Sedes
const sedes = ref<Sede[]>([
    {
        id: 1,
        name: 'Sede Comas',
        code: 'CMS',
        address: 'Av. Túpac Amaru 1234, Comas - Lima',
        classroomsCount: 6,
        activeStudents: 142,
        headCoordinator: 'Sofia Torres Castro',
    },
    {
        id: 2,
        name: 'Sede Puente Piedra',
        code: 'PPD',
        address: 'Av. Panamericana Norte 567, Puente Piedra - Lima',
        classroomsCount: 4,
        activeStudents: 106,
        headCoordinator: 'Jorge Miranda Ramos',
    },
]);

// Mocked Carreras
const carreras = ref<Carrera[]>([
    {
        id: 1,
        code: 'CET-01',
        name: 'Computación e Informática',
        modulesCount: 4,
        duration: '2 Años (4 Ciclos)',
        category: 'Tecnología',
    },
    {
        id: 2,
        code: 'CET-02',
        name: 'Logística y Gestión de Almacén',
        modulesCount: 2,
        duration: '1 Año (2 Ciclos)',
        category: 'Gestión',
    },
    {
        id: 3,
        code: 'CET-03',
        name: 'Gastronomía y Arte Culinario',
        modulesCount: 2,
        duration: '1 Año (2 Ciclos)',
        category: 'Servicios',
    },
]);

// Mocked Cursos y Talleres
const coursesList = ref<CourseItem[]>([
    {
        id: 1,
        name: 'Excel Avanzado y Macros',
        moduleName: 'Ofimática Empresarial',
        durationInWeeks: 4,
        isWorkshop: false,
        activeStudents: 32,
    },
    {
        id: 2,
        name: 'Arquitectura de Hardware y Diagnóstico',
        moduleName: 'Soporte Técnico y Ensamblaje',
        durationInWeeks: 4,
        isWorkshop: false,
        activeStudents: 28,
    },
    {
        id: 3,
        name: 'Taller de Barber Shop y Cortes Urbanos',
        durationInWeeks: 6,
        isWorkshop: true,
        activeStudents: 24,
    },
    {
        id: 4,
        name: 'Taller de Uñas Acrílicas y Polygel',
        durationInWeeks: 4,
        isWorkshop: true,
        activeStudents: 18,
    },
    {
        id: 5,
        name: 'Taller de Reparación de Celulares y Tablets',
        durationInWeeks: 8,
        isWorkshop: true,
        activeStudents: 22,
    },
]);

// Mocked Enrollments
const enrollments = ref<EnrollmentRecord[]>([
    {
        id: 101,
        studentName: 'Valeria Ríos Flores',
        studentDni: '78901234',
        courseName: 'Excel Avanzado y Macros',
        moduleName: 'Ofimática Empresarial',
        careerName: 'Computación e Informática',
        branchName: 'Sede Comas',
        enrollmentDate: '2026-03-05',
        status: 'convalidado',
        resolutionOrNote: 'Convalidado por estudios previos de Instituto Superior (Res. D-042)',
    },
    {
        id: 102,
        studentName: 'Diego Mendoza Salazar',
        studentDni: '76543210',
        courseName: 'Arquitectura de Hardware y Diagnóstico',
        moduleName: 'Soporte Técnico y Ensamblaje',
        careerName: 'Computación e Informática',
        branchName: 'Sede Comas',
        enrollmentDate: '2026-10-01',
        status: 'activo',
        resolutionOrNote: 'Regular - Apoderado: Juan Carlos Mendoza',
    },
    {
        id: 103,
        studentName: 'Carlos Alberto Gómez Paredes',
        studentDni: '34567890',
        courseName: 'Excel Avanzado y Macros',
        moduleName: 'Ofimática Empresarial',
        branchName: 'Sede Comas',
        enrollmentDate: '2026-10-02',
        status: 'activo',
        resolutionOrNote: 'Módulo independiente libre (Docente IISEP)',
    },
    {
        id: 104,
        studentName: 'Rosa Elena Huamán Quispe',
        studentDni: '45123987',
        courseName: 'Taller de Barber Shop y Cortes Urbanos',
        branchName: 'Sede Puente Piedra',
        enrollmentDate: '2026-10-01',
        status: 'activo',
        resolutionOrNote: 'Taller libre práctico presencial',
    },
    {
        id: 105,
        studentName: 'Lucía Fernández Castro',
        studentDni: '73491820',
        courseName: 'Arquitectura de Hardware y Diagnóstico',
        moduleName: 'Soporte Técnico y Ensamblaje',
        careerName: 'Computación e Informática',
        branchName: 'Sede Comas',
        enrollmentDate: '2026-08-20',
        status: 'convalidado',
        resolutionOrNote: 'Experiencia laboral demostrada y examen de suficiencia',
    },
    {
        id: 106,
        studentName: 'Juan Pablo Quispe Silva',
        studentDni: '71209345',
        courseName: 'Excel Avanzado y Macros',
        moduleName: 'Ofimática Empresarial',
        branchName: 'Sede Puente Piedra',
        enrollmentDate: '2026-09-01',
        status: 'completado',
        resolutionOrNote: 'Certificación modular emitida',
    },
    {
        id: 107,
        studentName: 'Brayan Alexis Sánchez Soto',
        studentDni: '72918471',
        courseName: 'Taller de Reparación de Celulares y Tablets',
        branchName: 'Sede Comas',
        enrollmentDate: '2026-10-24',
        status: 'preinscrito',
        resolutionOrNote: 'Pendiente de pago de matrícula y cupo',
    },
]);

// Attendance Sessions
const attendanceSessions = ref<AttendanceSession[]>([
    {
        id: 1,
        courseName: 'Arquitectura de Hardware y Diagnóstico',
        classroom: 'Laboratorio 1 Cómputo',
        branchName: 'Sede Comas',
        teacherName: 'Prof. Marco Antonio Rojas',
        schedule: 'Lun / Mié 08:00 - 10:00',
        presentCount: 14,
        totalCount: 15,
    },
    {
        id: 2,
        courseName: 'Excel Avanzado y Macros',
        classroom: 'Aula 101 Teoría y Práctica',
        branchName: 'Sede Comas',
        teacherName: 'Prof. Marco Antonio Rojas',
        schedule: 'Mar / Jue 18:00 - 20:00',
        presentCount: 18,
        totalCount: 20,
    },
    {
        id: 3,
        courseName: 'Taller de Barber Shop y Cortes Urbanos',
        classroom: 'Aula 201 Taller de Estilismo',
        branchName: 'Sede Puente Piedra',
        teacherName: 'Prof. Carlos Alberto Gómez',
        schedule: 'Sábados 14:00 - 18:00',
        presentCount: 12,
        totalCount: 12,
    },
]);

// Search & Filter state for enrollments
const searchQuery = ref('');
const statusFilter = ref<'all' | 'activo' | 'convalidado' | 'completado' | 'preinscrito'>('all');

// Filtered Enrollments
const filteredEnrollments = computed(() => {
    return enrollments.value.filter((record) => {
        // Sede filter
        if (selectedBranchId.value !== 'all') {
            const currentSede = sedes.value.find((s) => s.id === selectedBranchId.value);
            if (currentSede && record.branchName !== currentSede.name) {
                return false;
            }
        }
        // Status filter
        if (statusFilter.value !== 'all' && record.status !== statusFilter.value) {
            return false;
        }
        // Search text
        if (searchQuery.value.trim() !== '') {
            const q = searchQuery.value.toLowerCase();
            return (
                record.studentName.toLowerCase().includes(q) ||
                record.studentDni.includes(q) ||
                record.courseName.toLowerCase().includes(q) ||
                (record.resolutionOrNote && record.resolutionOrNote.toLowerCase().includes(q))
            );
        }
        return true;
    });
});

// Modal state: Nueva Matrícula o Convalidación
const showModal = ref(false);
const modalType = ref<'matricula' | 'convalidacion'>('convalidacion');
const formStudentName = ref('');
const formStudentDni = ref('');
const formCourseId = ref<number>(1);
const formBranchId = ref<number>(1);
const formResolution = ref('');
const toastMessage = ref('');
const showToast = ref(false);

function triggerToast(msg: string) {
    toastMessage.value = msg;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 4000);
}

function openModal(type: 'matricula' | 'convalidacion') {
    modalType.value = type;
    formStudentName.value = '';
    formStudentDni.value = '';
    formResolution.value = '';
    showModal.value = true;
}

function submitForm() {
    if (!formStudentName.value || !formStudentDni.value) {
        alert('Por favor complete el nombre y el DNI del alumno');
        return;
    }

    const course = coursesList.value.find((c) => c.id === Number(formCourseId.value));
    const branch = sedes.value.find((s) => s.id === Number(formBranchId.value));

    const newRecord: EnrollmentRecord = {
        id: Date.now(),
        studentName: formStudentName.value.trim(),
        studentDni: formStudentDni.value.trim(),
        courseName: course ? course.name : 'Curso General',
        moduleName: course?.moduleName,
        branchName: branch ? branch.name : 'Sede Principal',
        enrollmentDate: new Date().toISOString().split('T')[0],
        status: modalType.value === 'convalidacion' ? 'convalidado' : 'activo',
        resolutionOrNote:
            formResolution.value.trim() ||
            (modalType.value === 'convalidacion'
                ? 'Convalidado por resolución de secretaría académica'
                : 'Matrícula regular en periodo lectivo'),
    };

    enrollments.value.unshift(newRecord);
    showModal.value = false;
    triggerToast(
        modalType.value === 'convalidacion'
            ? `¡Convalidación registrada con éxito para ${newRecord.studentName}!`
            : `¡Matrícula activa registrada con éxito para ${newRecord.studentName}!`
    );
}

// Helpers para títulos dinámicos
const currentTabTitle = computed(() => {
    switch (activeTab.value) {
        case 'matriculas':
            return {
                title: 'Secretaría: Matrículas & Convalidaciones',
                description: 'Registro de estudiantes, convalidaciones atómicas y emisión modular',
                roleBadge: 'Secretaría Académica',
            };
        case 'resumen':
            return {
                title: 'Dirección: Panel Ejecutivo & Métricas',
                description: 'Métricas de rendimiento institucional, retención y certificaciones',
                roleBadge: 'Dirección General',
            };
        case 'academico':
            return {
                title: 'Gestión Curricular: Planes & Cursos',
                description: 'Carreras técnicas, módulos CETPRO y catálogo de talleres libres',
                roleBadge: 'Coordinación Académica',
            };
        case 'sedes':
            return {
                title: 'Infraestructura: Sedes & Aulas',
                description: 'Administración de sedes presenciales, laboratorios y aforos',
                roleBadge: 'Coordinación de Sede',
            };
        case 'asistencia':
            return {
                title: 'Control Docente: Asistencias & Horarios',
                description: 'Registro de asistencia por sesión y ambientes de clase en tiempo real',
                roleBadge: 'Docencia & Coordinación',
            };
    }
});
</script>

<template>
    <Head title="Panel de Administración - IISEP" />

    <div class="min-h-screen bg-[#f4f7f5] font-sans text-slate-800 antialiased selection:bg-[#2e8b57] selection:text-white flex flex-col md:flex-row">
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
                class="fixed top-5 right-5 z-50 flex items-center gap-3 rounded-xl border border-[#2e8b57]/30 bg-white px-4 py-3 text-slate-800 shadow-xl"
            >
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-[#2e8b57]">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div class="text-sm font-semibold text-slate-800">{{ toastMessage }}</div>
            </div>
        </Transition>

        <!-- Mobile Top Bar with Hamburger -->
        <div class="md:hidden flex items-center justify-between bg-white border-b border-slate-200 px-4 py-3 sticky top-0 z-40">
            <div class="flex items-center gap-2">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#2e8b57] font-black text-white">
                    II
                </div>
                <span class="text-base font-black text-[#2e8b57]">IISEP</span>
            </div>
            <button
                @click="isMobileSidebarOpen = !isMobileSidebarOpen"
                class="rounded-lg p-2 text-slate-600 hover:bg-slate-100"
                aria-label="Toggle Menu"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <!-- SIDEBAR VERTICAL DE NAVEGACIÓN -->
        <aside
            :class="[
                isMobileSidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
                'fixed md:static inset-y-0 left-0 z-50 w-64 md:w-72 bg-white border-r border-slate-200 flex flex-col justify-between transition-transform duration-200 ease-in-out shadow-sm'
            ]"
        >
            <div>
                <!-- Brand Header -->
                <div class="p-5 border-b border-slate-100 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#2e8b57] font-black text-white shadow-md shadow-emerald-700/20">
                        <span class="text-lg tracking-wider">II</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-lg font-black tracking-tight text-[#2e8b57]">IISEP</span>
                            <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-[#2e8b57] border border-emerald-200">
                                CETPRO
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 font-medium">Gestión Académica Modular</p>
                    </div>
                </div>

                <!-- Role / Permission Selector (Boceto para simular perfiles) -->
                <div class="p-4 mx-3 my-3 rounded-xl bg-slate-50 border border-slate-200/80">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-500 uppercase">
                        <span>Perfil Activo</span>
                        <span class="text-[10px] text-emerald-700 font-bold bg-emerald-100/60 px-1.5 py-0.2 rounded">Simulación</span>
                    </div>
                    <select
                        v-model="currentRole"
                        class="mt-1.5 w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:border-[#2e8b57]"
                    >
                        <option value="coordinador">Coordinador de Sede</option>
                        <option value="secretaria">Secretaría Académica</option>
                        <option value="director">Dirección General</option>
                        <option value="docente">Docente / Instructor</option>
                    </select>
                </div>

                <!-- Navigation Links Grouped Modularly -->
                <nav class="px-3 py-2 space-y-1.5">
                    <div class="px-3 pt-2 pb-1 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                        Operaciones & Secretaría
                    </div>

                    <button
                        id="tab-btn-matriculas"
                        @click="activeTab = 'matriculas'; isMobileSidebarOpen = false"
                        :class="[
                            activeTab === 'matriculas'
                                ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium',
                            'w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-xs transition'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Matrículas & Conval.</span>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-[#2e8b57]">
                            {{ enrollments.length }}
                        </span>
                    </button>

                    <button
                        id="tab-btn-asistencia"
                        @click="activeTab = 'asistencia'; isMobileSidebarOpen = false"
                        :class="[
                            activeTab === 'asistencia'
                                ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium',
                            'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs transition'
                        ]"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>Sesiones & Asistencias</span>
                    </button>

                    <div class="px-3 pt-4 pb-1 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                        Gestión Curricular
                    </div>

                    <button
                        id="tab-btn-academico"
                        @click="activeTab = 'academico'; isMobileSidebarOpen = false"
                        :class="[
                            activeTab === 'academico'
                                ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium',
                            'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs transition'
                        ]"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span>Planes, Cursos & Talleres</span>
                    </button>

                    <button
                        id="tab-btn-sedes"
                        @click="activeTab = 'sedes'; isMobileSidebarOpen = false"
                        :class="[
                            activeTab === 'sedes'
                                ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium',
                            'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs transition'
                        ]"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Sedes Presenciales</span>
                    </button>

                    <div class="px-3 pt-4 pb-1 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                        Dirección & Auditoría
                    </div>

                    <button
                        id="tab-btn-resumen"
                        @click="activeTab = 'resumen'; isMobileSidebarOpen = false"
                        :class="[
                            activeTab === 'resumen'
                                ? 'bg-emerald-50 text-[#2e8b57] font-bold border-l-4 border-[#2e8b57]'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 font-medium',
                            'w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs transition'
                        ]"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                        </svg>
                        <span>Panel Resumen & KPIs</span>
                    </button>
                </nav>
            </div>

            <!-- User Info at Bottom of Sidebar -->
            <div class="p-4 border-t border-slate-100 bg-slate-50/60">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 border border-emerald-300 text-xs font-bold text-[#2e8b57]">
                        ST
                    </div>
                    <div class="leading-tight overflow-hidden">
                        <p class="text-xs font-bold text-slate-900 truncate">Sofia Torres Castro</p>
                        <p class="text-[11px] text-slate-500 truncate">Sede Comas</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- CONTENIDO PRINCIPAL A LA DERECHA -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top bar with Sede selector and Context info -->
            <header class="bg-white border-b border-slate-200 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-2xs">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-emerald-100/70 px-2 py-0.5 text-[10px] font-bold text-[#2e8b57]">
                            {{ currentTabTitle.roleBadge }}
                        </span>
                        <h1 class="text-lg font-black text-slate-900">{{ currentTabTitle.title }}</h1>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">{{ currentTabTitle.description }}</p>
                </div>

                <!-- Sede Filter Selector -->
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 font-medium hidden sm:inline">Filtrar Sede:</span>
                    <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">
                        <svg class="h-4 w-4 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <select
                            id="branch-selector"
                            v-model="selectedBranchId"
                            class="bg-transparent text-xs font-bold text-slate-800 focus:outline-none cursor-pointer"
                        >
                            <option :value="'all'">Todas las Sedes</option>
                            <option v-for="sede in sedes" :key="sede.id" :value="sede.id">
                                {{ sede.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </header>

            <!-- MAIN CONTENT AREA SWITCHED BY TAB -->
            <main class="flex-1 p-6 max-w-7xl w-full mx-auto space-y-6">

                <!-- 1. PESTAÑA: MATRÍCULAS Y CONVALIDACIONES (SECRETARÍA ACADÉMICA) -->
                <div v-if="activeTab === 'matriculas'" class="space-y-5">
                    <!-- KPIs ESPECÍFICOS DE ESTA PESTAÑA (No fijos globalmente) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-slate-500 uppercase">Matrículas Totales</span>
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-2xl font-black text-slate-900">{{ filteredEnrollments.length }}</span>
                                <span class="text-xs font-semibold text-emerald-600">Alumnos inscritos</span>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-purple-700 uppercase">Materias Convalidadas</span>
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-2xl font-black text-purple-700">
                                    {{ filteredEnrollments.filter(e => e.status === 'convalidado').length }}
                                </span>
                                <span class="text-xs font-semibold text-purple-600">Convalidación atómica</span>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-amber-700 uppercase">Preinscritos pendientes</span>
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-2xl font-black text-amber-700">
                                    {{ filteredEnrollments.filter(e => e.status === 'preinscrito').length }}
                                </span>
                                <span class="text-xs font-semibold text-amber-600">Pendiente de pago</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions & Filters -->
                    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                        <!-- Search input -->
                        <div class="relative flex-1 sm:max-w-md">
                            <svg class="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                id="search-enrollment-input"
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar por DNI, alumno, curso o resolución..."
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pr-4 pl-9 text-xs text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:ring-1 focus:ring-[#2e8b57] focus:outline-none"
                            />
                        </div>

                        <!-- Status Filter Badges -->
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button
                                @click="statusFilter = 'all'"
                                :class="[
                                    statusFilter === 'all'
                                        ? 'bg-slate-800 text-white font-bold'
                                        : 'bg-slate-100 text-slate-600 hover:text-slate-900',
                                    'rounded-md px-3 py-1 text-xs transition font-semibold'
                                ]"
                            >
                                Todos ({{ enrollments.length }})
                            </button>
                            <button
                                @click="statusFilter = 'convalidado'"
                                :class="[
                                    statusFilter === 'convalidado'
                                        ? 'bg-purple-600 text-white font-bold'
                                        : 'bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200',
                                    'rounded-md px-3 py-1 text-xs transition font-semibold'
                                ]"
                            >
                                Convalidados ({{ enrollments.filter(e => e.status === 'convalidado').length }})
                            </button>
                            <button
                                @click="statusFilter = 'activo'"
                                :class="[
                                    statusFilter === 'activo'
                                        ? 'bg-[#2e8b57] text-white font-bold'
                                        : 'bg-emerald-50 text-[#2e8b57] hover:bg-emerald-100 border border-emerald-200',
                                    'rounded-md px-3 py-1 text-xs transition font-semibold'
                                ]"
                            >
                                Activos ({{ enrollments.filter(e => e.status === 'activo').length }})
                            </button>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex items-center gap-2">
                            <button
                                id="btn-nueva-convalidacion"
                                @click="openModal('convalidacion')"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-purple-300 bg-purple-50 px-3.5 py-2 text-xs font-bold text-purple-700 transition hover:bg-purple-100 shadow-2xs"
                            >
                                <svg class="h-4 w-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <span>+ Convalidar Curso</span>
                            </button>

                            <button
                                id="btn-nueva-matricula"
                                @click="openModal('matricula')"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#2e8b57] px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#257347]"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>+ Matrícula Regular</span>
                            </button>
                        </div>
                    </div>

                    <!-- Table Container -->
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-700">
                                <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-bold tracking-wider text-slate-600 uppercase">
                                    <tr>
                                        <th scope="col" class="py-3.5 pr-3 pl-4 sm:pl-6">Estudiante / DNI</th>
                                        <th scope="col" class="px-3 py-3.5">Materia / Taller</th>
                                        <th scope="col" class="px-3 py-3.5">Módulo & Carrera</th>
                                        <th scope="col" class="px-3 py-3.5">Sede</th>
                                        <th scope="col" class="px-3 py-3.5">Fecha</th>
                                        <th scope="col" class="px-3 py-3.5">Estado</th>
                                        <th scope="col" class="py-3.5 pr-4 pl-3 sm:pr-6">Observación / Sustento</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="item in filteredEnrollments"
                                        :key="item.id"
                                        class="transition hover:bg-emerald-50/40"
                                    >
                                        <td class="py-3 pr-3 pl-4 sm:pl-6">
                                            <div class="font-bold text-slate-900">{{ item.studentName }}</div>
                                            <div class="font-mono text-[11px] text-slate-500">DNI: {{ item.studentDni }}</div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="font-bold text-slate-800">{{ item.courseName }}</div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div v-if="item.moduleName" class="font-medium text-slate-700">
                                                {{ item.moduleName }}
                                            </div>
                                            <div v-if="item.careerName" class="text-[11px] text-slate-500 font-medium">
                                                {{ item.careerName }}
                                            </div>
                                            <span
                                                v-if="!item.moduleName && !item.careerName"
                                                class="inline-block rounded-sm bg-amber-50 border border-amber-200 px-1.5 py-0.5 text-[10px] font-bold text-amber-800"
                                            >
                                                Taller Independiente
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                                                <span class="h-2 w-2 rounded-full" :class="item.branchName.includes('Comas') ? 'bg-[#2e8b57]' : 'bg-teal-600'"></span>
                                                {{ item.branchName }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                            {{ item.enrollmentDate }}
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <span
                                                v-if="item.status === 'convalidado'"
                                                class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-2.5 py-0.5 text-[11px] font-bold text-purple-700 shadow-2xs"
                                            >
                                                <svg class="h-3.5 w-3.5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                                Convalidado
                                            </span>
                                            <span
                                                v-else-if="item.status === 'activo'"
                                                class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-[#2e8b57]"
                                            >
                                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#2e8b57]"></span>
                                                Activo
                                            </span>
                                            <span
                                                v-else-if="item.status === 'completado'"
                                                class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-700"
                                            >
                                                Completado
                                            </span>
                                            <span
                                                v-else-if="item.status === 'preinscrito'"
                                                class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-bold text-amber-700"
                                            >
                                                Preinscrito
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600"
                                            >
                                                {{ item.status }}
                                            </span>
                                        </td>
                                        <td class="py-3 pr-4 pl-3 text-slate-500 sm:pr-6">
                                            <div class="line-clamp-2 max-w-xs text-[11px] leading-relaxed">
                                                {{ item.resolutionOrNote || '—' }}
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 2. PESTAÑA: PANEL RESUMEN & AUDITORÍA DE CONVALIDACIONES (DIRECCIÓN) -->
                <div v-if="activeTab === 'resumen'" class="space-y-6">
                    <!-- KPIs ESPECÍFICOS DE RESUMEN EJECUTIVO -->
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-slate-500 uppercase">Aforo Global</span>
                            <div class="mt-1 text-2xl font-black text-slate-900">248 / 320</div>
                            <span class="text-[11px] text-emerald-600 font-semibold">77.5% de ocupación</span>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-slate-500 uppercase">Sedes Activas</span>
                            <div class="mt-1 text-2xl font-black text-[#2e8b57]">2 Sedes</div>
                            <span class="text-[11px] text-slate-500">100% presencial</span>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-slate-500 uppercase">Tasa Convalidación</span>
                            <div class="mt-1 text-2xl font-black text-purple-700">12.5%</div>
                            <span class="text-[11px] text-purple-600">Alumnos por traslado</span>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-slate-500 uppercase">Asistencia Promedio</span>
                            <div class="mt-1 text-2xl font-black text-emerald-600">93.8%</div>
                            <span class="text-[11px] text-slate-500">Laboratorios prácticos</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Convalidation Rule Card -->
                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-purple-50 text-purple-700">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">Lógica de Convalidación Atómica</h3>
                            </div>
                            <p class="mt-3 text-xs leading-relaxed text-slate-600">
                                En el CETPRO IISEP, cada curso/taller es evaluado de forma individual. Un estudiante que convalida solo materias específicas conserva su progreso oficial:
                            </p>
                            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                                <h4 class="text-xs font-bold text-[#2e8b57]">Condición de Certificación Modular Oficial</h4>
                                <code class="mt-2 block rounded-lg bg-white border border-emerald-200 p-2.5 font-mono text-xs font-bold text-[#2e8b57] shadow-2xs">
                                    status IN ('completado', 'convalidado')
                                </code>
                                <p class="mt-2 text-[11px] text-slate-600">
                                    Cuando la totalidad de los cursos de un módulo cumplen esta condición, el sistema habilita el botón de emisión de certificado oficial.
                                </p>
                            </div>
                        </div>

                        <!-- Branches Overview -->
                        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-[#2e8b57]">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">Capacidad Operativa por Sede</h3>
                            </div>
                            <div class="mt-4 space-y-3">
                                <div v-for="sede in sedes" :key="sede.id" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-900">{{ sede.name }}</span>
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-[#2e8b57]">{{ sede.activeStudents }} Estudiantes</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ sede.address }}</p>
                                    <div class="mt-2 flex items-center justify-between text-xs text-slate-600">
                                        <span>🏛️ {{ sede.classroomsCount }} Ambientes y Labs</span>
                                        <span class="font-semibold text-slate-700">Coord: {{ sede.headCoordinator }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. PESTAÑA: PLANES, CURSOS & TALLERES (GESTIÓN CURRICULAR) -->
                <div v-if="activeTab === 'academico'" class="space-y-6">
                    <!-- KPIs ESPECÍFICOS DE GESTIÓN CURRICULAR -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-slate-500 uppercase">Carreras Técnicas</span>
                            <div class="mt-1 text-2xl font-black text-slate-900">{{ carreras.length }} Programas</div>
                            <span class="text-xs text-emerald-600 font-semibold">Titulaciones modulares</span>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-slate-500 uppercase">Cursos Modulares</span>
                            <div class="mt-1 text-2xl font-black text-[#2e8b57]">
                                {{ coursesList.filter(c => !c.isWorkshop).length }} Unidades
                            </div>
                            <span class="text-xs text-slate-500">Forman parte de módulos</span>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-amber-700 uppercase">Talleres Libres</span>
                            <div class="mt-1 text-2xl font-black text-amber-700">
                                {{ coursesList.filter(c => c.isWorkshop).length }} Talleres
                            </div>
                            <span class="text-xs text-amber-600 font-semibold">Cursos rápidos independientes</span>
                        </div>
                    </div>

                    <!-- Carreras -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Carreras Técnicas Formales</h3>
                                <p class="text-xs text-slate-500">Estructuradas en módulos independientes y acumulativos</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div v-for="carrera in carreras" :key="carrera.id" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-mono font-bold text-slate-500">{{ carrera.code }}</span>
                                    <span class="text-[10px] font-extrabold text-[#2e8b57] uppercase tracking-wide">{{ carrera.category }}</span>
                                </div>
                                <h4 class="mt-1.5 text-sm font-bold text-slate-900">{{ carrera.name }}</h4>
                                <div class="mt-3 flex items-center justify-between text-xs font-medium text-slate-600 border-t border-slate-200/60 pt-2">
                                    <span>{{ carrera.modulesCount }} Módulos</span>
                                    <span>{{ carrera.duration }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cursos y Talleres Libres -->
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                        <div class="mb-4">
                            <h3 class="text-sm font-bold text-slate-900">Catálogo de Cursos y Talleres Cortos</h3>
                            <p class="text-xs text-slate-500">Unidades atómicas con asignación de duración en semanas</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-700">
                                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-600 uppercase">
                                    <tr>
                                        <th class="py-3 px-4">Nombre del Curso / Taller</th>
                                        <th class="py-3 px-4">Tipo</th>
                                        <th class="py-3 px-4">Módulo Pertenece</th>
                                        <th class="py-3 px-4">Duración</th>
                                        <th class="py-3 px-4">Alumnos Inscritos</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="curso in coursesList" :key="curso.id" class="hover:bg-slate-50">
                                        <td class="py-3 px-4 font-bold text-slate-900">{{ curso.name }}</td>
                                        <td class="py-3 px-4">
                                            <span
                                                v-if="curso.isWorkshop"
                                                class="rounded bg-amber-50 border border-amber-200 px-2 py-0.5 text-[10px] font-bold text-amber-800"
                                            >
                                                Taller Libre
                                            </span>
                                            <span
                                                v-else
                                                class="rounded bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold text-[#2e8b57]"
                                            >
                                                Curso Modular
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-slate-600 font-medium">{{ curso.moduleName || 'Ninguno (Independiente)' }}</td>
                                        <td class="py-3 px-4 font-mono font-bold text-slate-700">{{ curso.durationInWeeks }} semanas</td>
                                        <td class="py-3 px-4 font-bold text-[#2e8b57]">{{ curso.activeStudents }} activos</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 4. PESTAÑA: SEDES & AMBIENTES (INFRAESTRUCTURA) -->
                <div v-if="activeTab === 'sedes'" class="space-y-6">
                    <!-- KPIs DE SEDES -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-slate-500 uppercase">Total Ambientes Físicos</span>
                            <div class="mt-1 text-2xl font-black text-slate-900">
                                {{ sedes.reduce((acc, s) => acc + s.classroomsCount, 0) }} Aulas & Labs
                            </div>
                            <span class="text-xs text-emerald-600 font-semibold">100% operativos</span>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-slate-500 uppercase">Capacidad Máxima Simultánea</span>
                            <div class="mt-1 text-2xl font-black text-[#2e8b57]">320 Estudiantes</div>
                            <span class="text-xs text-slate-500">Distribución en turnos mañana y noche</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="sede in sedes" :key="sede.id" class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-[#2e8b57] font-extrabold">
                                        {{ sede.code }}
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-900">{{ sede.name }}</h3>
                                        <p class="text-xs text-slate-500">{{ sede.address }}</p>
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-xs font-bold text-[#2e8b57]">Operativa</span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
                                <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-100">
                                    <span class="text-[11px] font-bold text-slate-500 uppercase">Aulas Físicas</span>
                                    <p class="text-lg font-black text-slate-900">{{ sede.classroomsCount }} Ambientes</p>
                                </div>
                                <div class="rounded-xl bg-emerald-50/70 p-3.5 border border-emerald-100">
                                    <span class="text-[11px] font-bold text-emerald-700 uppercase">Estudiantes</span>
                                    <p class="text-lg font-black text-[#2e8b57]">{{ sede.activeStudents }} Matriculados</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. PESTAÑA: SESIONES & ASISTENCIAS (CONTROL DOCENTE) -->
                <div v-if="activeTab === 'asistencia'" class="space-y-6">
                    <!-- KPIs DE ASISTENCIAS -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-slate-500 uppercase">Sesiones Programadas Hoy</span>
                            <div class="mt-1 text-2xl font-black text-slate-900">{{ attendanceSessions.length }} Grupos</div>
                            <span class="text-xs text-slate-500">Turnos mañana y tarde</span>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-emerald-700 uppercase">Asistencia en Sala</span>
                            <div class="mt-1 text-2xl font-black text-[#2e8b57]">
                                {{ attendanceSessions.reduce((a, s) => a + s.presentCount, 0) }} /
                                {{ attendanceSessions.reduce((a, s) => a + s.totalCount, 0) }}
                            </div>
                            <span class="text-xs text-emerald-600 font-semibold">93.6% puntualidad</span>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs">
                            <span class="text-[11px] font-bold text-amber-700 uppercase">Faltas Justificadas</span>
                            <div class="mt-1 text-2xl font-black text-amber-700">3 Faltas</div>
                            <span class="text-xs text-amber-600 font-semibold">Sustentadas por apoderado</span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Sesiones en Dictado Actual</h3>
                                <p class="text-xs text-slate-500">Marcación de asistencia por ambiente físico</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <div
                                v-for="session in attendanceSessions"
                                :key="session.id"
                                class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 shadow-2xs"
                            >
                                <div class="flex items-start justify-between">
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-[#2e8b57]">
                                        {{ session.branchName }}
                                    </span>
                                    <span class="font-mono text-xs font-bold text-[#2e8b57]">
                                        {{ session.presentCount }}/{{ session.totalCount }} presentes
                                    </span>
                                </div>
                                <h4 class="mt-2 text-xs font-bold text-slate-900">{{ session.courseName }}</h4>
                                <p class="mt-1 text-[11px] text-slate-600 font-medium">🏫 {{ session.classroom }}</p>
                                <p class="text-[11px] text-slate-600 font-medium">👨‍🏫 {{ session.teacherName }}</p>
                                <div class="mt-3 flex items-center justify-between border-t border-slate-200/80 pt-2 text-[11px]">
                                    <span class="text-slate-500 font-medium">⏰ {{ session.schedule }}</span>
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
            </main>
        </div>

        <!-- Modal: Registrar Matrícula o Convalidación -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showModal = false"
        >
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl"
                            :class="modalType === 'convalidacion' ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-[#2e8b57]'"
                        >
                            <svg v-if="modalType === 'convalidacion'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">
                                {{ modalType === 'convalidacion' ? 'Registrar Convalidación de Materia' : 'Nueva Matrícula Regular' }}
                            </h3>
                            <p class="text-xs text-slate-500">
                                {{ modalType === 'convalidacion' ? 'Acreditar curso por estudios previos o traslado' : 'Inscribir alumno en grupo regular' }}
                            </p>
                        </div>
                    </div>
                    <button
                        id="btn-close-modal"
                        @click="showModal = false"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Form -->
                <form @submit.prevent="submitForm" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700">Nombres y Apellidos del Estudiante</label>
                        <input
                            id="input-student-name"
                            v-model="formStudentName"
                            type="text"
                            required
                            placeholder="Ej. Valeria Ríos Flores"
                            class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700">Documento de Identidad (DNI)</label>
                            <input
                                id="input-student-dni"
                                v-model="formStudentDni"
                                type="text"
                                maxlength="8"
                                required
                                placeholder="8 dígitos"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700">Sede</label>
                            <select
                                id="select-modal-branch"
                                v-model="formBranchId"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none font-medium"
                            >
                                <option v-for="sede in sedes" :key="sede.id" :value="sede.id">
                                    {{ sede.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700">Curso o Taller a Registrar</label>
                        <select
                            id="select-modal-course"
                            v-model="formCourseId"
                            class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none font-medium"
                        >
                            <option v-for="c in coursesList" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.isWorkshop ? 'Taller Libre' : 'Modular - ' + c.moduleName }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700">
                            {{ modalType === 'convalidacion' ? 'Resolución / Documento de Sustento' : 'Notas u Observación' }}
                        </label>
                        <textarea
                            id="textarea-modal-notes"
                            v-model="formResolution"
                            rows="2"
                            :placeholder="modalType === 'convalidacion' ? 'Ej: Certificado oficial de notas Instituto X, Res. Directoral N° 045-2026' : 'Observaciones de matrícula o datos del apoderado...'"
                            class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        ></textarea>
                    </div>

                    <!-- Footer actions -->
                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            id="btn-submit-enrollment"
                            type="submit"
                            :class="modalType === 'convalidacion' ? 'bg-purple-700 hover:bg-purple-800' : 'bg-[#2e8b57] hover:bg-[#257347]'"
                            class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                        >
                            {{ modalType === 'convalidacion' ? 'Confirmar Convalidación' : 'Registrar Matrícula' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
