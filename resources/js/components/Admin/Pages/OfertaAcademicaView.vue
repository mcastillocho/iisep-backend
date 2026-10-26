<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface AuthStaff {
    id?: number;
    branch_id?: number | null;
    position?: string | null;
}

interface AuthUser {
    id: number;
    username: string;
    full_name?: string;
    staff?: AuthStaff | null;
}

export interface Branch {
    id: number;
    name: string;
    code: string;
    address: string;
    phone: string;
}

export interface CareerModuleItem {
    id: number;
    name: string;
    duration_in_weeks: number;
    order: number;
}

export interface Career {
    id: number;
    name: string;
    description: string;
    estimated_capacity: number;
    available_branch_ids: number[];
    modules: CareerModuleItem[];
}

export interface ModuleItem {
    id: number;
    name: string;
    description: string;
    duration_in_weeks: number;
    careers_associated: string[];
    courses_count: number;
    has_dependencies?: boolean;
}

export interface CourseItem {
    id: number;
    module_id: number | null;
    module_name?: string;
    name: string;
    description: string;
    duration_in_weeks: number;
    is_workshop: boolean;
    has_scheduled_courses?: boolean;
    scheduled_courses_count?: number;
}

type CurricularTab = 'carreras' | 'modulos' | 'talleres';

interface Props {
    activeTab?: CurricularTab;
    branches: Branch[];
    careers: Career[];
    allModulesPool: ModuleItem[];
    coursesList: CourseItem[];
}

const props = withDefaults(defineProps<Props>(), {
    activeTab: 'carreras',
});

const emit = defineEmits<{
    (e: 'update:activeTab', tab: CurricularTab): void;
    (e: 'notify', message: string): void;
}>();

// Control de Perfiles y Autorizaciones para Talleres
const page = usePage<{ auth?: { user?: AuthUser } }>();

const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    if (!user?.staff) return false;
    return user.staff.branch_id === null;
});

const isCoordinador = computed(() => {
    const user = page.props.auth?.user;
    if (!user?.staff) return false;
    if (isAdmin.value) return true;
    return user.staff.branch_id !== null && user.staff.position === 'coord';
});

// Los talleres y módulos los puede modificar el personal de Coordinación de Sede para arriba (Coordinador y Director General)
const canManageWorkshops = computed(() => isCoordinador.value || isAdmin.value);
const canManageModules = computed(() => isCoordinador.value || isAdmin.value);

// --- MODAL DE TALLER (CREAR / EDITAR) ---
const showWorkshopModal = ref(false);
const editingWorkshopId = ref<number | null>(null);
const workshopForm = useForm({
    name: '',
    description: '',
    duration_in_weeks: 4,
});

function openNewWorkshopModal() {
    editingWorkshopId.value = null;
    workshopForm.reset();
    workshopForm.clearErrors();
    workshopForm.duration_in_weeks = 4;
    showWorkshopModal.value = true;
}

function openEditWorkshopModal(curso: CourseItem) {
    editingWorkshopId.value = curso.id;
    workshopForm.clearErrors();
    workshopForm.name = curso.name;
    workshopForm.description = curso.description || '';
    workshopForm.duration_in_weeks = curso.duration_in_weeks || 4;
    showWorkshopModal.value = true;
}

function saveWorkshop() {
    if (!workshopForm.name.trim()) return;

    if (editingWorkshopId.value) {
        workshopForm.put(`/admin/talleres/${editingWorkshopId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit(
                    'notify',
                    `Taller "${workshopForm.name}" actualizado con éxito.`,
                );
                showWorkshopModal.value = false;
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) emit('notify', String(firstError));
            },
        });
    } else {
        workshopForm.post('/admin/talleres', {
            preserveScroll: true,
            onSuccess: () => {
                emit(
                    'notify',
                    `Nuevo taller "${workshopForm.name}" registrado con éxito.`,
                );
                showWorkshopModal.value = false;
                workshopForm.reset();
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) emit('notify', String(firstError));
            },
        });
    }
}

function deleteWorkshop(workshopId: number) {
    const workshop = props.coursesList.find((c) => c.id === workshopId);
    if (!workshop) return;
    if (workshop.has_scheduled_courses || (workshop.scheduled_courses_count && workshop.scheduled_courses_count > 0)) {
        alert('No se puede eliminar este taller porque tiene clases programadas actualmente.');
        return;
    }
    if (!confirm(`¿Estás seguro de eliminar el taller "${workshop.name}"?`))
        return;

    router.delete(`/admin/talleres/${workshopId}`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('notify', `Taller "${workshop.name}" eliminado.`);
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            if (firstError) emit('notify', String(firstError));
        },
    });
}

// --- MODAL DE MÓDULO FORMATIVO (CREAR / EDITAR) ---
const showModuleModal = ref(false);
const editingModuleId = ref<number | null>(null);
const moduleForm = useForm({
    name: '',
    description: '',
    duration_in_weeks: 8,
});

function openNewModuleModal() {
    editingModuleId.value = null;
    moduleForm.reset();
    moduleForm.clearErrors();
    moduleForm.duration_in_weeks = 8;
    showModuleModal.value = true;
}

function openEditModuleModal(modulo: ModuleItem) {
    editingModuleId.value = modulo.id;
    moduleForm.clearErrors();
    moduleForm.name = modulo.name;
    moduleForm.description = modulo.description || '';
    moduleForm.duration_in_weeks = modulo.duration_in_weeks || 8;
    showModuleModal.value = true;
}

function saveModule() {
    if (!moduleForm.name.trim()) return;

    if (editingModuleId.value) {
        moduleForm.put(`/admin/modulos/${editingModuleId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit(
                    'notify',
                    `Módulo "${moduleForm.name}" actualizado con éxito.`,
                );
                showModuleModal.value = false;
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) emit('notify', String(firstError));
            },
        });
    } else {
        moduleForm.post('/admin/modulos', {
            preserveScroll: true,
            onSuccess: () => {
                emit(
                    'notify',
                    `Nuevo módulo "${moduleForm.name}" registrado con éxito.`,
                );
                showModuleModal.value = false;
                moduleForm.reset();
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) emit('notify', String(firstError));
            },
        });
    }
}

function deleteModule(moduleId: number) {
    const mod = props.allModulesPool.find((m) => m.id === moduleId);
    if (!mod) return;
    if (
        mod.has_dependencies ||
        mod.courses_count > 0 ||
        (mod.careers_associated && mod.careers_associated.length > 0)
    ) {
        alert(
            'No se puede eliminar este módulo formativo porque tiene cursos o carreras asociadas.',
        );
        return;
    }
    if (!confirm(`¿Estás seguro de eliminar el módulo "${mod.name}"?`)) return;

    router.delete(`/admin/modulos/${moduleId}`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('notify', `Módulo "${mod.name}" eliminado.`);
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            if (firstError) emit('notify', String(firstError));
        },
    });
}

// Pestaña activa interna (sincronizada con v-model si se pasa)
const currentTab = computed({
    get: () => props.activeTab,
    set: (val: CurricularTab) => emit('update:activeTab', val),
});

// Filtros de búsqueda
const searchCareerQuery = ref('');
const searchModuleQuery = ref('');
const searchCourseQuery = ref('');

// --- EDICIÓN DETALLADA DE CARRERA ---
const editingCareer = ref<Career | null>(null);
const selectedModuleToAddId = ref<number | ''>('');

// Cantidad de semanas calculada automáticamente según los módulos (solo lectura)
const calculatedWeeks = computed(() => {
    if (!editingCareer.value || !editingCareer.value.modules) return 0;
    return editingCareer.value.modules.reduce(
        (sum, m) => sum + m.duration_in_weeks,
        0,
    );
});

// Módulos del catálogo aún no asignados a esta carrera
const availableModulesToAdd = computed(() => {
    if (!editingCareer.value) return [];
    const assignedIds = new Set(editingCareer.value.modules.map((m) => m.id));
    return props.allModulesPool.filter((m) => !assignedIds.has(m.id));
});

function startEditingCareer(carrera: Career) {
    editingCareer.value = JSON.parse(JSON.stringify(carrera));
}

function cancelCareerEditing() {
    editingCareer.value = null;
}

function saveCareerChanges() {
    if (!editingCareer.value) return;
    const index = props.careers.findIndex(
        (c) => c.id === editingCareer.value!.id,
    );
    if (index !== -1) {
        props.careers[index] = JSON.parse(JSON.stringify(editingCareer.value));
    }
    emit(
        'notify',
        `Carrera "${editingCareer.value.name}" actualizada con éxito.`,
    );
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
    if (!editingCareer.value || index >= editingCareer.value.modules.length - 1)
        return;
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
    const mod = props.allModulesPool.find(
        (m) => m.id === Number(selectedModuleToAddId.value),
    );
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

// Filtros calculados
const filteredCareers = computed(() => {
    if (!searchCareerQuery.value.trim()) return props.careers;
    const q = searchCareerQuery.value.toLowerCase();
    return props.careers.filter(
        (c) =>
            c.name.toLowerCase().includes(q) ||
            c.description.toLowerCase().includes(q),
    );
});

const filteredModules = computed(() => {
    if (!searchModuleQuery.value.trim()) return props.allModulesPool;
    const q = searchModuleQuery.value.toLowerCase();
    return props.allModulesPool.filter(
        (m) =>
            m.name.toLowerCase().includes(q) ||
            m.description.toLowerCase().includes(q),
    );
});

const filteredCourses = computed(() => {
    if (!searchCourseQuery.value.trim()) return props.coursesList;
    const q = searchCourseQuery.value.toLowerCase();
    return props.coursesList.filter(
        (c) =>
            c.name.toLowerCase().includes(q) ||
            c.description.toLowerCase().includes(q),
    );
});
</script>

<template>
    <div class="space-y-6">
        <!-- 1. VISTA DETALLADA: EDITAR CARRERA -->
        <div
            v-if="editingCareer"
            class="space-y-6 rounded-xl border border-slate-200 bg-white p-7 shadow-xs"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 pb-4"
            >
                <div class="flex items-center gap-3">
                    <button
                        @click="cancelCareerEditing"
                        class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-bold text-slate-600 transition hover:text-slate-900"
                    >
                        &larr; Volver a Carreras
                    </button>
                    <h2 class="text-xl font-black text-slate-900">
                        Editando Carrera:
                        <span class="text-[#2e8b57]">{{
                            editingCareer.name
                        }}</span>
                    </h2>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="cancelCareerEditing"
                        class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="saveCareerChanges"
                        class="cursor-pointer rounded-lg bg-[#2e8b57] px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#257347]"
                    >
                        Guardar Cambios
                    </button>
                </div>
            </div>

            <!-- Formulario de Datos Básicos -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div>
                    <label class="block text-sm font-bold text-slate-800"
                        >Nombre de la Carrera</label
                    >
                    <input
                        v-model="editingCareer.name"
                        type="text"
                        class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-800"
                        >Aforo / Vacantes Estimadas</label
                    >
                    <input
                        v-model="editingCareer.estimated_capacity"
                        type="number"
                        placeholder="Ej. 40"
                        class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-bold text-slate-800"
                            >Duración Total</label
                        >
                        <span
                            class="text-xs font-semibold text-slate-400 italic"
                            >Calculada automáticamente</span
                        >
                    </div>
                    <div class="mt-1.5 flex items-center gap-2">
                        <input
                            :value="`${calculatedWeeks} semanas (${Math.round((calculatedWeeks / 4) * 10) / 10} meses)`"
                            type="text"
                            readonly
                            disabled
                            class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3.5 py-2.5 font-mono text-sm font-bold text-slate-700"
                        />
                    </div>
                </div>
            </div>

            <!-- Sedes Disponibles -->
            <div
                class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-5"
            >
                <label class="block text-sm font-bold text-slate-800">
                    Sedes Disponibles para esta Carrera
                </label>
                <p class="mb-3 text-xs text-slate-500">
                    Selecciona en qué sedes físicas estará aperturada esta
                    carrera (a futuro se persistirá mediante tabla de asignación
                    por sede):
                </p>
                <div class="flex flex-wrap items-center gap-6">
                    <label
                        v-for="branch in branches"
                        :key="branch.id"
                        class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-300 bg-white px-4 py-2 hover:border-[#2e8b57]"
                    >
                        <input
                            type="checkbox"
                            :value="branch.id"
                            v-model="editingCareer.available_branch_ids"
                            class="h-4 w-4 rounded text-[#2e8b57] focus:ring-[#2e8b57]"
                        />
                        <span class="text-sm font-bold text-slate-800">{{
                            branch.name
                        }}</span>
                        <span class="text-xs text-slate-400"
                            >({{ branch.code }})</span
                        >
                    </label>
                </div>
            </div>

            <!-- Panel de Módulos Ordenables -->
            <div class="space-y-4 pt-2">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Módulos Componentes de la Carrera
                        </h3>
                        <p class="text-xs text-slate-500">
                            Organiza los módulos en orden correlativo y
                            cronológico de dictado (career_module).
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <select
                            v-model="selectedModuleToAddId"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 focus:border-[#2e8b57] focus:outline-none"
                        >
                            <option value="">
                                -- Seleccionar Módulo Existente --
                            </option>
                            <option
                                v-for="mod in availableModulesToAdd"
                                :key="mod.id"
                                :value="mod.id"
                            >
                                {{ mod.name }} ({{ mod.duration_in_weeks }}
                                semanas)
                            </option>
                        </select>
                        <button
                            @click="addModuleToCareer"
                            :disabled="selectedModuleToAddId === ''"
                            class="cursor-pointer rounded-lg bg-[#2e8b57] px-3.5 py-2 text-xs font-bold text-white transition hover:bg-[#257347] disabled:bg-slate-300"
                        >
                            + Asignar a Carrera
                        </button>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white"
                >
                    <table class="w-full text-left text-sm text-slate-700">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600 uppercase"
                        >
                            <tr>
                                <th class="w-16 px-4 py-3 text-center">
                                    Orden
                                </th>
                                <th class="px-4 py-3">
                                    Nombre del Módulo Formativo
                                </th>
                                <th class="px-4 py-3">Duración</th>
                                <th class="w-36 px-4 py-3 text-center">
                                    Cambiar Orden
                                </th>
                                <th class="w-24 px-4 py-3 text-right">
                                    Quitar
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(mod, index) in editingCareer.modules"
                                :key="mod.id"
                                class="hover:bg-slate-50"
                            >
                                <td
                                    class="bg-slate-50/50 px-4 py-3 text-center font-mono font-bold text-slate-900"
                                >
                                    {{ mod.order }}
                                </td>
                                <td class="px-4 py-3 font-bold text-slate-900">
                                    {{ mod.name }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono font-semibold text-[#2e8b57]"
                                >
                                    {{ mod.duration_in_weeks }} semanas
                                </td>
                                <td
                                    class="px-4 py-3 text-center whitespace-nowrap"
                                >
                                    <div class="inline-flex items-center gap-1">
                                        <button
                                            @click="moveModuleUp(index)"
                                            :disabled="index === 0"
                                            title="Mover hacia arriba"
                                            class="cursor-pointer rounded border border-slate-300 p-1 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30"
                                        >
                                            <svg
                                                class="h-4 w-4 text-slate-700"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M5 15l7-7 7 7"
                                                />
                                            </svg>
                                        </button>
                                        <button
                                            @click="moveModuleDown(index)"
                                            :disabled="
                                                index ===
                                                editingCareer.modules.length - 1
                                            "
                                            title="Mover hacia abajo"
                                            class="cursor-pointer rounded border border-slate-300 p-1 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30"
                                        >
                                            <svg
                                                class="h-4 w-4 text-slate-700"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        @click="removeModuleFromCareer(index)"
                                        title="Quitar módulo"
                                        class="cursor-pointer text-xs font-bold text-red-500 hover:text-red-700"
                                    >
                                        ✕ Quitar
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="editingCareer.modules.length === 0">
                                <td
                                    colspan="5"
                                    class="py-8 text-center font-medium text-slate-400"
                                >
                                    Esta carrera aún no cuenta con módulos
                                    asignados. Usa el selector arriba para
                                    asignar el primer módulo.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2. VISTAS REGULARES: CARRERAS / MÓDULOS / TALLERES -->
        <div v-else class="space-y-6">
            <!-- Pestañas Superiores -->
            <div class="border-b border-slate-200">
                <nav class="flex space-x-8" aria-label="Tabs">
                    <button
                        @click="currentTab = 'carreras'"
                        :class="[
                            currentTab === 'carreras'
                                ? 'border-[#2e8b57] font-bold text-[#2e8b57]'
                                : 'border-transparent font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700',
                            'flex cursor-pointer items-center gap-2 border-b-2 px-1 py-3 text-sm whitespace-nowrap',
                        ]"
                    >
                        <span>Carreras</span>
                        <span
                            class="rounded-full bg-slate-100 px-2.5 py-0.5 font-mono text-xs text-slate-600"
                        >
                            {{ careers.length }}
                        </span>
                    </button>

                    <button
                        @click="currentTab = 'modulos'"
                        :class="[
                            currentTab === 'modulos'
                                ? 'border-[#2e8b57] font-bold text-[#2e8b57]'
                                : 'border-transparent font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700',
                            'flex cursor-pointer items-center gap-2 border-b-2 px-1 py-3 text-sm whitespace-nowrap',
                        ]"
                    >
                        <span>Módulos</span>
                        <span
                            class="rounded-full bg-slate-100 px-2.5 py-0.5 font-mono text-xs text-slate-600"
                        >
                            {{ allModulesPool.length }}
                        </span>
                    </button>

                    <button
                        @click="currentTab = 'talleres'"
                        :class="[
                            currentTab === 'talleres'
                                ? 'border-[#2e8b57] font-bold text-[#2e8b57]'
                                : 'border-transparent font-semibold text-slate-500 hover:border-slate-300 hover:text-slate-700',
                            'flex cursor-pointer items-center gap-2 border-b-2 px-1 py-3 text-sm whitespace-nowrap',
                        ]"
                    >
                        <span>Talleres</span>
                        <span
                            class="rounded-full bg-slate-100 px-2.5 py-0.5 font-mono text-xs text-slate-600"
                        >
                            {{ coursesList.length }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Panel de Búsqueda y Botón de Creación -->
            <div
                class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="relative flex-1 sm:max-w-md">
                    <input
                        v-if="currentTab === 'carreras'"
                        v-model="searchCareerQuery"
                        type="text"
                        placeholder="Buscar carrera técnica..."
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-10 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                    <input
                        v-else-if="currentTab === 'modulos'"
                        v-model="searchModuleQuery"
                        type="text"
                        placeholder="Buscar módulo formativo..."
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-10 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                    <input
                        v-else
                        v-model="searchCourseQuery"
                        type="text"
                        placeholder="Buscar taller práctico..."
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-10 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                    >
                        <svg
                            class="h-5 w-5 text-slate-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        v-if="currentTab === 'carreras'"
                        @click="emit('notify', 'Crear nueva carrera técnica')"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347]"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        <span>Nueva Carrera</span>
                    </button>
                    <button
                        v-else-if="currentTab === 'modulos' && canManageModules"
                        @click="openNewModuleModal"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347]"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        <span>Nuevo Módulo</span>
                    </button>
                    <button
                        v-else-if="currentTab === 'talleres' && canManageWorkshops"
                        @click="openNewWorkshopModal"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347]"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        <span>+ Nuevo Taller</span>
                    </button>
                </div>
            </div>

            <!-- CONTENIDO DE SUB-PESTAÑA 1: CARRERAS -->
            <div v-if="currentTab === 'carreras'" class="space-y-4">
                <div
                    v-for="carrera in filteredCareers"
                    :key="carrera.id"
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs transition hover:border-emerald-300"
                >
                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                    >
                        <div class="flex-1 space-y-2">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-lg font-black text-slate-900">
                                    {{ carrera.name }}
                                </h3>
                                <span
                                    class="rounded border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-[#2e8b57]"
                                >
                                    {{ carrera.modules.length }} módulos
                                </span>
                                <span
                                    class="rounded bg-slate-100 px-2.5 py-0.5 font-mono text-xs font-bold text-slate-700"
                                >
                                    {{
                                        carrera.modules.reduce(
                                            (s, m) => s + m.duration_in_weeks,
                                            0,
                                        )
                                    }}
                                    semanas totales
                                </span>
                            </div>
                            <p class="text-sm leading-relaxed text-slate-600">
                                {{ carrera.description }}
                            </p>

                            <!-- Sedes disponibles -->
                            <div
                                class="flex flex-wrap items-center gap-2 pt-2 text-xs"
                            >
                                <span class="font-bold text-slate-500"
                                    >Sedes Habilitadas:</span
                                >
                                <span
                                    v-for="branchId in carrera.available_branch_ids"
                                    :key="branchId"
                                    class="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-0.5 font-bold text-slate-700"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-[#2e8b57]"
                                    ></span>
                                    {{
                                        branches.find((b) => b.id === branchId)
                                            ?.name || 'Sede'
                                    }}
                                </span>
                            </div>

                            <!-- Módulos incluidos ordenados -->
                            <div class="pt-3">
                                <span
                                    class="mb-1.5 block text-xs font-bold tracking-wider text-slate-400 uppercase"
                                >
                                    Módulos Curriculares (Secuencia):
                                </span>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="mod in carrera.modules"
                                        :key="mod.id"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-800"
                                    >
                                        <span
                                            class="flex h-4 w-4 items-center justify-center rounded-full bg-[#2e8b57] text-[10px] font-bold text-white"
                                        >
                                            {{ mod.order }}
                                        </span>
                                        <span>{{ mod.name }}</span>
                                        <span class="font-mono text-slate-400"
                                            >({{
                                                mod.duration_in_weeks
                                            }}
                                            sem)</span
                                        >
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Botón Editar Carrera -->
                        <div
                            class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4 md:flex-col md:items-end md:border-t-0 md:pt-0"
                        >
                            <div class="text-right">
                                <p
                                    class="text-xs font-bold text-slate-400 uppercase"
                                >
                                    Aforo Estimado
                                </p>
                                <p
                                    class="font-mono text-base font-black text-slate-900"
                                >
                                    {{ carrera.estimated_capacity }} vacantes
                                </p>
                            </div>
                            <button
                                @click="startEditingCareer(carrera)"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-emerald-600 bg-white px-4 py-2 text-xs font-bold text-[#2e8b57] shadow-2xs transition hover:bg-emerald-50"
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
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                    />
                                </svg>
                                <span>Editar Carrera</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTENIDO DE SUB-PESTAÑA 2: MÓDULOS -->
            <div v-if="currentTab === 'modulos'" class="space-y-4">
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs"
                >
                    <table class="w-full text-left text-sm text-slate-700">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600 uppercase"
                        >
                            <tr>
                                <th class="px-6 py-3.5">Módulo Formativo</th>
                                <th class="px-4 py-3.5">Duración</th>
                                <th class="px-4 py-3.5">
                                    Carreras que lo incluyen
                                </th>
                                <th class="px-4 py-3.5">Cursos asignados</th>
                                <th class="px-6 py-3.5 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="modulo in filteredModules"
                                :key="modulo.id"
                                class="transition hover:bg-slate-50"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="text-sm font-bold text-slate-900"
                                    >
                                        {{ modulo.name }}
                                    </div>
                                    <div
                                        class="mt-0.5 line-clamp-1 max-w-md text-xs text-slate-500"
                                    >
                                        {{ modulo.description }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-4 font-mono font-bold text-[#2e8b57]"
                                >
                                    {{ modulo.duration_in_weeks }} semanas
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="car in modulo.careers_associated"
                                            :key="car"
                                            class="inline-block rounded border border-emerald-100 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-[#2e8b57]"
                                        >
                                            {{ car }}
                                        </span>
                                        <span
                                            v-if="
                                                modulo.careers_associated
                                                    .length === 0
                                            "
                                            class="text-xs text-slate-400 italic"
                                        >
                                            Módulo libre / No asociado
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-4 font-mono font-bold text-slate-700"
                                >
                                    {{ modulo.courses_count }} cursos
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <div
                                        v-if="canManageModules"
                                        class="inline-flex items-center gap-2"
                                    >
                                        <button
                                            @click="openEditModuleModal(modulo)"
                                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-[#2e8b57]"
                                        >
                                            <svg
                                                class="h-4 w-4 text-slate-500"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                />
                                            </svg>
                                            <span>Editar</span>
                                        </button>
                                        <button
                                            @click="deleteModule(modulo.id)"
                                            :disabled="Boolean(modulo.has_dependencies || modulo.courses_count > 0 || (modulo.careers_associated && modulo.careers_associated.length > 0))"
                                            :title="Boolean(modulo.has_dependencies || modulo.courses_count > 0 || (modulo.careers_associated && modulo.careers_associated.length > 0)) ? 'No se puede eliminar: tiene cursos o carreras asociadas' : 'Eliminar módulo'"
                                            class="text-xs font-bold transition"
                                            :class="Boolean(modulo.has_dependencies || modulo.courses_count > 0 || (modulo.careers_associated && modulo.careers_associated.length > 0)) ? 'cursor-not-allowed text-slate-500 hover:text-slate-500' : 'cursor-pointer text-red-500 hover:text-red-700'"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs text-slate-400 italic"
                                    >
                                        Solo lectura
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CONTENIDO DE SUB-PESTAÑA 3: TALLERES -->
            <div v-if="currentTab === 'talleres'" class="space-y-4">
                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs"
                >
                    <table class="w-full text-left text-sm text-slate-700">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600 uppercase"
                        >
                            <tr>
                                <th class="px-6 py-3.5">Taller / Curso</th>
                                <th class="px-4 py-3.5">
                                    Módulo de Pertenencia
                                </th>
                                <th class="px-4 py-3.5">Duración</th>
                                <th class="px-6 py-3.5 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="curso in filteredCourses"
                                :key="curso.id"
                                class="transition hover:bg-slate-50"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-sm font-bold text-slate-900"
                                            >{{ curso.name }}</span
                                        >
                                        <span
                                            v-if="curso.is_workshop"
                                            class="rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-700"
                                        >
                                            Taller Libre
                                        </span>
                                    </div>
                                    <div
                                        class="mt-0.5 line-clamp-1 max-w-md text-xs text-slate-500"
                                    >
                                        {{ curso.description }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-4 font-semibold text-slate-700"
                                >
                                    {{
                                        curso.module_name ||
                                        'Ninguno (Taller Independiente)'
                                    }}
                                </td>
                                <td
                                    class="px-4 py-4 font-mono font-bold text-slate-800"
                                >
                                    {{ curso.duration_in_weeks }} semanas
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <div
                                        v-if="canManageWorkshops"
                                        class="inline-flex items-center gap-2"
                                    >
                                        <button
                                            @click="openEditWorkshopModal(curso)"
                                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-[#2e8b57]"
                                        >
                                            <svg
                                                class="h-4 w-4 text-slate-500"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                />
                                            </svg>
                                            <span>Editar</span>
                                        </button>
                                        <button
                                            @click="deleteWorkshop(curso.id)"
                                            :disabled="Boolean(curso.has_scheduled_courses || (curso.scheduled_courses_count && curso.scheduled_courses_count > 0))"
                                            :title="curso.has_scheduled_courses || (curso.scheduled_courses_count && curso.scheduled_courses_count > 0) ? 'No se puede eliminar: tiene clases programadas' : (curso.is_workshop ? 'Eliminar taller' : 'Eliminar curso')"
                                            class="text-xs font-bold transition"
                                            :class="Boolean(curso.has_scheduled_courses || (curso.scheduled_courses_count && curso.scheduled_courses_count > 0)) ? 'cursor-not-allowed text-slate-500 hover:text-slate-500' : 'cursor-pointer text-red-500 hover:text-red-700'"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs text-slate-400 italic"
                                        >Solo lectura</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL DE TALLER (CREAR / EDITAR) -->
        <div
            v-if="showWorkshopModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showWorkshopModal = false"
        >
            <div
                class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 pb-4"
                >
                    <h3 class="text-lg font-bold text-slate-900">
                        {{
                            editingWorkshopId
                                ? 'Editar Taller Práctico'
                                : 'Registrar Nuevo Taller Práctico'
                        }}
                    </h3>
                    <button
                        @click="showWorkshopModal = false"
                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        &times;
                    </button>
                </div>

                <form @submit.prevent="saveWorkshop" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Nombre del Taller</label
                        >
                        <input
                            v-model="workshopForm.name"
                            type="text"
                            required
                            placeholder="Ej. Taller de Barber Shop y Cortes Urbanos"
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            :class="{
                                'border-rose-500 bg-rose-50':
                                    workshopForm.errors.name,
                            }"
                        />
                        <p
                            v-if="workshopForm.errors.name"
                            class="mt-1 text-xs font-semibold text-rose-600"
                        >
                            {{ workshopForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Duración (en semanas)</label
                        >
                        <input
                            v-model.number="workshopForm.duration_in_weeks"
                            type="number"
                            required
                            min="1"
                            max="52"
                            placeholder="Ej. 6 semanas"
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            :class="{
                                'border-rose-500 bg-rose-50':
                                    workshopForm.errors.duration_in_weeks,
                            }"
                        />
                        <p
                            v-if="workshopForm.errors.duration_in_weeks"
                            class="mt-1 text-xs font-semibold text-rose-600"
                        >
                            {{ workshopForm.errors.duration_in_weeks }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Descripción / Habilidades Aprendidas</label
                        >
                        <textarea
                            v-model="workshopForm.description"
                            rows="3"
                            placeholder="Describe brevemente las técnicas y contenidos del taller..."
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            :class="{
                                'border-rose-500 bg-rose-50':
                                    workshopForm.errors.description,
                            }"
                        ></textarea>
                        <p
                            v-if="workshopForm.errors.description"
                            class="mt-1 text-xs font-semibold text-rose-600"
                        >
                            {{ workshopForm.errors.description }}
                        </p>
                    </div>

                    <div
                        class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-5"
                    >
                        <button
                            type="button"
                            @click="showWorkshopModal = false"
                            class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="workshopForm.processing"
                            class="cursor-pointer rounded-lg bg-[#2e8b57] px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347] disabled:opacity-50"
                        >
                            {{
                                workshopForm.processing
                                    ? 'Guardando...'
                                    : 'Guardar Taller'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL DE MÓDULO FORMATIVO (CREAR / EDITAR) -->
        <div
            v-if="showModuleModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showModuleModal = false"
        >
            <div
                class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 pb-4"
                >
                    <h3 class="text-lg font-bold text-slate-900">
                        {{
                            editingModuleId
                                ? 'Editar Módulo Formativo'
                                : 'Registrar Nuevo Módulo Formativo'
                        }}
                    </h3>
                    <button
                        @click="showModuleModal = false"
                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        &times;
                    </button>
                </div>

                <form @submit.prevent="saveModule" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Nombre del Módulo</label
                        >
                        <input
                            v-model="moduleForm.name"
                            type="text"
                            required
                            placeholder="Ej. Ofimática Empresarial"
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            :class="{
                                'border-rose-500 bg-rose-50':
                                    moduleForm.errors.name,
                            }"
                        />
                        <p
                            v-if="moduleForm.errors.name"
                            class="mt-1 text-xs font-semibold text-rose-600"
                        >
                            {{ moduleForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Duración (en semanas)</label
                        >
                        <input
                            v-model.number="moduleForm.duration_in_weeks"
                            type="number"
                            min="1"
                            max="52"
                            required
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            :class="{
                                'border-rose-500 bg-rose-50':
                                    moduleForm.errors.duration_in_weeks,
                            }"
                        />
                        <p
                            v-if="moduleForm.errors.duration_in_weeks"
                            class="mt-1 text-xs font-semibold text-rose-600"
                        >
                            {{ moduleForm.errors.duration_in_weeks }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800"
                            >Descripción / Competencias</label
                        >
                        <textarea
                            v-model="moduleForm.description"
                            rows="3"
                            placeholder="Describe el alcance y competencias desarrolladas en este módulo formativo..."
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            :class="{
                                'border-rose-500 bg-rose-50':
                                    moduleForm.errors.description,
                            }"
                        ></textarea>
                        <p
                            v-if="moduleForm.errors.description"
                            class="mt-1 text-xs font-semibold text-rose-600"
                        >
                            {{ moduleForm.errors.description }}
                        </p>
                    </div>

                    <div
                        class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-5"
                    >
                        <button
                            type="button"
                            @click="showModuleModal = false"
                            class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="moduleForm.processing"
                            class="cursor-pointer rounded-lg bg-[#2e8b57] px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347] disabled:opacity-50"
                        >
                            {{
                                moduleForm.processing
                                    ? 'Guardando...'
                                    : 'Guardar Módulo'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
