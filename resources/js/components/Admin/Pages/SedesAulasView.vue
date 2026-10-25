<script setup lang="ts">
import { computed, ref } from 'vue';

export interface Branch {
    id: number;
    name: string;
    code: string;
    address: string;
    phone: string;
}

export interface Classroom {
    id: number;
    branch_id: number;
    branch_name?: string;
    name: string;
    capacity: number | null;
    current_scheduled_courses: string[];
}

interface Props {
    branches: Branch[];
    classrooms: Classroom[];
    selectedBranchId?: number | 'all';
}

const props = withDefaults(defineProps<Props>(), {
    selectedBranchId: 'all',
});

const emit = defineEmits<{
    (e: 'update:selectedBranchId', branchId: number | 'all'): void;
    (e: 'notify', message: string): void;
}>();

// Filtro de búsqueda
const searchClassroomQuery = ref('');
const searchBranchQuery = ref('');

// --- MODO EDICIÓN DETALLADA DE SEDE (Similar al editor de carreras) ---
const editingBranch = ref<Branch | null>(null);

function startEditingBranch(branch: Branch) {
    editingBranch.value = JSON.parse(JSON.stringify(branch));
}

function cancelBranchEditing() {
    editingBranch.value = null;
}

function saveBranchChanges() {
    if (!editingBranch.value) return;
    const index = props.branches.findIndex((b) => b.id === editingBranch.value!.id);
    if (index !== -1) {
        props.branches[index] = JSON.parse(JSON.stringify(editingBranch.value));
    }
    emit('notify', `Sede "${editingBranch.value.name}" actualizada con éxito.`);
    editingBranch.value = null;
}

// Aulas pertenecientes a la sede en edición
const editingBranchClassrooms = computed(() => {
    if (!editingBranch.value) return [];
    return props.classrooms.filter((c) => c.branch_id === editingBranch.value!.id);
});

// Aforo total acumulado de la sede
const editingBranchTotalCapacity = computed(() => {
    return editingBranchClassrooms.value.reduce((sum, c) => sum + (c.capacity || 0), 0);
});

// --- MODAL DE NUEVA SEDE ---
const showNewBranchModal = ref(false);
const newBranchForm = ref({
    name: '',
    code: '',
    address: '',
    phone: '',
});

function openNewBranchModal() {
    newBranchForm.value = {
        name: '',
        code: '',
        address: '',
        phone: '',
    };
    showNewBranchModal.value = true;
}

function saveNewBranch() {
    if (!newBranchForm.value.name.trim()) return;
    const newId = props.branches.length > 0 ? Math.max(...props.branches.map((b) => b.id)) + 1 : 1;
    props.branches.push({
        id: newId,
        name: newBranchForm.value.name.trim(),
        code: newBranchForm.value.code.trim().toUpperCase() || `SED-${newId}`,
        address: newBranchForm.value.address.trim() || 'Dirección por registrar',
        phone: newBranchForm.value.phone.trim() || '999999999',
    });
    emit('notify', `Nueva sede "${newBranchForm.value.name}" creada con éxito.`);
    showNewBranchModal.value = false;
}

// --- MODAL DE AULA (CREAR / EDITAR) ---
const showClassroomModal = ref(false);
const editingClassroomId = ref<number | null>(null);
const classroomForm = ref({
    name: '',
    branch_id: 1,
    capacity: 30 as number | null,
});

function openNewClassroomModal(defaultBranchId?: number) {
    editingClassroomId.value = null;
    const initialBranchId = defaultBranchId || (props.selectedBranchId !== 'all' ? props.selectedBranchId : (props.branches[0]?.id || 1));
    classroomForm.value = {
        name: '',
        branch_id: initialBranchId,
        capacity: 30,
    };
    showClassroomModal.value = true;
}

function openEditClassroomModal(room: Classroom) {
    editingClassroomId.value = room.id;
    classroomForm.value = {
        name: room.name,
        branch_id: room.branch_id,
        capacity: room.capacity,
    };
    showClassroomModal.value = true;
}

function saveClassroom() {
    if (!classroomForm.value.name.trim()) return;
    const branchObj = props.branches.find((b) => b.id === classroomForm.value.branch_id);
    const branchName = branchObj ? branchObj.name : 'Sede Principal';

    if (editingClassroomId.value) {
        const index = props.classrooms.findIndex((c) => c.id === editingClassroomId.value);
        if (index !== -1) {
            props.classrooms[index].name = classroomForm.value.name.trim();
            props.classrooms[index].branch_id = classroomForm.value.branch_id;
            props.classrooms[index].branch_name = branchName;
            props.classrooms[index].capacity = classroomForm.value.capacity;
        }
        emit('notify', `Aula "${classroomForm.value.name}" actualizada con éxito.`);
    } else {
        const newId = props.classrooms.length > 0 ? Math.max(...props.classrooms.map((c) => c.id)) + 1 : 1;
        props.classrooms.push({
            id: newId,
            branch_id: classroomForm.value.branch_id,
            branch_name: branchName,
            name: classroomForm.value.name.trim(),
            capacity: classroomForm.value.capacity,
            current_scheduled_courses: [],
        });
        emit('notify', `Nueva aula "${classroomForm.value.name}" registrada con éxito.`);
    }
    showClassroomModal.value = false;
}

function deleteClassroom(roomId: number) {
    const index = props.classrooms.findIndex((c) => c.id === roomId);
    if (index !== -1) {
        const name = props.classrooms[index].name;
        props.classrooms.splice(index, 1);
        emit('notify', `Aula "${name}" eliminada.`);
    }
}

// --- MODAL DE DISPONIBILIDAD DE AULA ---
const selectedRoomForSchedule = ref<Classroom | null>(null);
const showScheduleModal = ref(false);

function viewRoomAvailability(room: Classroom) {
    selectedRoomForSchedule.value = room;
    showScheduleModal.value = true;
}

// Filtros
const filteredBranches = computed(() => {
    if (!searchBranchQuery.value.trim()) return props.branches;
    const q = searchBranchQuery.value.toLowerCase();
    return props.branches.filter((b) => b.name.toLowerCase().includes(q) || b.code.toLowerCase().includes(q) || b.address.toLowerCase().includes(q));
});

const filteredClassrooms = computed(() => {
    return props.classrooms.filter((room) => {
        if (props.selectedBranchId !== 'all' && room.branch_id !== props.selectedBranchId) {
            return false;
        }
        if (searchClassroomQuery.value.trim() !== '') {
            const q = searchClassroomQuery.value.toLowerCase();
            return (
                room.name.toLowerCase().includes(q) ||
                (room.branch_name && room.branch_name.toLowerCase().includes(q))
            );
        }
        return true;
    });
});
</script>

<template>
    <div class="space-y-6">
        <!-- 1. VISTA DETALLADA: EDITAR SEDE (Datos físicos y gestión de sus aulas) -->
        <div v-if="editingBranch" class="rounded-xl border border-slate-200 bg-white p-7 shadow-xs space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                    <button
                        @click="cancelBranchEditing"
                        class="inline-flex items-center gap-1 text-sm font-bold text-slate-600 hover:text-slate-900 bg-slate-100 px-3 py-1.5 rounded-lg transition cursor-pointer"
                    >
                        &larr; Volver a Sedes
                    </button>
                    <h2 class="text-xl font-black text-slate-900">
                        Editando Sede: <span class="text-[#2e8b57]">{{ editingBranch.name }}</span>
                    </h2>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="cancelBranchEditing"
                        class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="saveBranchChanges"
                        class="px-5 py-2 bg-[#2e8b57] hover:bg-[#257347] text-white text-sm font-bold rounded-lg shadow-sm transition cursor-pointer"
                    >
                        Guardar Cambios
                    </button>
                </div>
            </div>

            <!-- Formulario de Datos Registrados en BD para branches -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-800">Nombre de la Sede</label>
                    <input
                        v-model="editingBranch.name"
                        type="text"
                        placeholder="Ej. Sede Comas"
                        class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-800">Código Oficial</label>
                    <input
                        v-model="editingBranch.code"
                        type="text"
                        placeholder="Ej. COM"
                        class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-mono font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-800">Teléfono de Contacto</label>
                    <input
                        v-model="editingBranch.phone"
                        type="text"
                        placeholder="Ej. 944206945"
                        class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-800">Capacidad Total de Aulas</label>
                    <input
                        :value="`${editingBranchTotalCapacity} vacantes (${editingBranchClassrooms.length} aulas)`"
                        type="text"
                        readonly
                        disabled
                        class="mt-1.5 w-full rounded-lg border border-slate-200 bg-slate-100 px-3.5 py-2.5 text-sm font-mono font-bold text-slate-700 cursor-not-allowed"
                    />
                </div>

                <div class="md:col-span-2 lg:col-span-4">
                    <label class="block text-sm font-bold text-slate-800">Dirección Física (Ubicación)</label>
                    <input
                        v-model="editingBranch.address"
                        type="text"
                        placeholder="Ej. Av. Túpac Amaru 3845"
                        class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                </div>
            </div>

            <!-- Panel de Gestión de Aulas en esta Sede -->
            <div class="space-y-4 pt-4 border-t border-slate-100">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Aulas Asignadas a esta Sede</h3>
                        <p class="text-xs text-slate-500">
                            Gestiona los ambientes físicos (classrooms) habilitados para dictado en {{ editingBranch.name }}.
                        </p>
                    </div>

                    <button
                        @click="openNewClassroomModal(editingBranch.id)"
                        class="inline-flex items-center gap-2 rounded-lg bg-[#2e8b57] px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[#257347] transition cursor-pointer"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Nueva Aula en esta Sede</span>
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-left text-sm text-slate-700">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                            <tr>
                                <th class="py-3 px-4">Nombre del Aula</th>
                                <th class="py-3 px-4">Aforo / Capacidad</th>
                                <th class="py-3 px-4">Disponibilidad</th>
                                <th class="py-3 px-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="room in editingBranchClassrooms"
                                :key="room.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="py-3 px-4 font-bold text-slate-900">
                                    {{ room.name }}
                                </td>
                                <td class="py-3 px-4 font-mono font-bold text-slate-800">
                                    <span v-if="room.capacity">{{ room.capacity }} vacantes</span>
                                    <span v-else class="text-slate-400 font-normal italic">Sin límite</span>
                                </td>
                                <td class="py-3 px-4">
                                    <button
                                        @click="viewRoomAvailability(room)"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#2e8b57] bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-2.5 py-1 rounded transition cursor-pointer"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>Ver Disponibilidad</span>
                                    </button>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            @click="openEditClassroomModal(room)"
                                            class="inline-flex items-center gap-1 rounded border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 transition cursor-pointer"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            @click="deleteClassroom(room.id)"
                                            class="text-red-500 hover:text-red-700 text-xs font-bold cursor-pointer"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="editingBranchClassrooms.length === 0">
                                <td colspan="4" class="py-8 text-center text-slate-400 font-medium">
                                    Esta sede no tiene aulas registradas. Haz clic en "+ Nueva Aula en esta Sede" para agregar una.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2. VISTA REGULAR: TODAS LAS SEDES (ESTILO TARJETAS COMO CARRERAS) O FILTRADA -->
        <div v-else class="space-y-6">
            <!-- Barra Superior: Selector de Sedes, Buscador y Botones -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="relative flex-1 sm:max-w-md">
                    <input
                        v-if="selectedBranchId === 'all'"
                        v-model="searchBranchQuery"
                        type="text"
                        placeholder="Buscar sede por nombre, código o dirección..."
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pl-10 pr-4 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                    <input
                        v-else
                        v-model="searchClassroomQuery"
                        type="text"
                        placeholder="Buscar aula en esta sede..."
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pl-10 pr-4 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    />
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="openNewBranchModal"
                        class="inline-flex items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-[#257347] transition cursor-pointer"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Nueva Sede</span>
                    </button>

                    <button
                        @click="openNewClassroomModal()"
                        class="inline-flex items-center gap-2 rounded-lg border border-[#2e8b57] bg-emerald-50 px-4 py-2.5 text-sm font-bold text-[#2e8b57] hover:bg-emerald-100 transition cursor-pointer"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Nueva Aula</span>
                    </button>
                </div>
            </div>

            <!-- VISTA A: TODAS LAS SEDES (TARJETAS SIMILARES AL PANEL DE CARRERAS) -->
            <div v-if="selectedBranchId === 'all'" class="space-y-4">
                <div
                    v-for="branch in filteredBranches"
                    :key="branch.id"
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs hover:border-emerald-300 transition"
                >
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div class="space-y-2 flex-1">
                            <div class="flex items-center gap-3 flex-wrap">
                                <h3 class="text-lg font-black text-slate-900">{{ branch.name }}</h3>
                                <span class="rounded bg-emerald-50 px-2.5 py-0.5 text-xs font-mono font-bold text-[#2e8b57] border border-emerald-200">
                                    {{ branch.code }}
                                </span>
                                <span class="rounded bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-700">
                                    {{ classrooms.filter((c) => c.branch_id === branch.id).length }} aulas
                                </span>
                                <span class="rounded bg-slate-100 px-2.5 py-0.5 text-xs font-mono font-bold text-slate-700">
                                    {{ classrooms.filter((c) => c.branch_id === branch.id).reduce((s, c) => s + (c.capacity || 0), 0) }} aforo total
                                </span>
                            </div>

                            <!-- Ubicación / Dirección Física -->
                            <div class="flex items-center gap-2 text-sm text-slate-600 pt-1">
                                <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>{{ branch.address }}</span>
                            </div>

                            <!-- Teléfono -->
                            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                                <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                <span>Tel: {{ branch.phone }}</span>
                            </div>

                            <!-- Píldoras de Aulas registradas en esta sede -->
                            <div class="pt-3">
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1.5">
                                    Ambientes / Aulas Disponibles:
                                </span>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="room in classrooms.filter((c) => c.branch_id === branch.id)"
                                        :key="room.id"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-800"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full bg-[#2e8b57]"></span>
                                        <span>{{ room.name }}</span>
                                        <span class="text-slate-400 font-mono">({{ room.capacity }} vac)</span>
                                    </span>
                                    <span
                                        v-if="classrooms.filter((c) => c.branch_id === branch.id).length === 0"
                                        class="text-xs text-slate-400 italic"
                                    >
                                        Sin aulas asignadas aún
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Acciones por Sede -->
                        <div class="flex md:flex-col items-center md:items-end justify-between gap-3 border-t md:border-t-0 pt-4 md:pt-0 border-slate-100">
                            <button
                                @click="emit('update:selectedBranchId', branch.id)"
                                class="inline-flex items-center gap-1 text-xs font-bold text-slate-600 hover:text-slate-900 bg-slate-100 px-3 py-1.5 rounded-lg transition cursor-pointer"
                            >
                                Ver Solo Esta Sede &rarr;
                            </button>

                            <button
                                @click="startEditingBranch(branch)"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-600 bg-white px-4 py-2 text-xs font-bold text-[#2e8b57] hover:bg-emerald-50 transition shadow-2xs cursor-pointer"
                            >
                                <svg class="h-4 w-4 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <span>Editar Sede</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VISTA B: FILTRADA POR UNA SEDE ESPECÍFICA (TABLA DE SUS AULAS) -->
            <div v-else class="space-y-4">
                <div class="flex items-center justify-between bg-emerald-50/60 border border-emerald-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#2e8b57] text-white font-bold">
                            {{ branches.find((b) => b.id === selectedBranchId)?.code || 'SED' }}
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">
                                {{ branches.find((b) => b.id === selectedBranchId)?.name }}
                            </h3>
                            <p class="text-xs text-slate-600">
                                {{ branches.find((b) => b.id === selectedBranchId)?.address }} &bull; Tel: {{ branches.find((b) => b.id === selectedBranchId)?.phone }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="branches.find((b) => b.id === selectedBranchId)"
                            @click="startEditingBranch(branches.find((b) => b.id === selectedBranchId)!)"
                            class="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-bold text-[#2e8b57] hover:bg-emerald-50 transition cursor-pointer"
                        >
                            Editar esta Sede
                        </button>
                        <button
                            @click="emit('update:selectedBranchId', 'all')"
                            class="rounded-lg bg-white border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer"
                        >
                            Ver Todas las Sedes
                        </button>
                    </div>
                </div>

                <!-- Tabla de Aulas de la Sede -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <table class="w-full text-left text-sm text-slate-700">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-600 uppercase">
                            <tr>
                                <th class="py-4 px-6">Nombre del Aula</th>
                                <th class="py-4 px-4">Capacidad (Aforo)</th>
                                <th class="py-4 px-4">Horarios & Disponibilidad</th>
                                <th class="py-4 px-6 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="room in filteredClassrooms" :key="room.id" class="hover:bg-slate-50 transition">
                                <td class="py-4 px-6 font-bold text-slate-900 text-sm">
                                    {{ room.name }}
                                </td>
                                <td class="py-4 px-4 font-mono font-bold text-slate-800">
                                    <span v-if="room.capacity">{{ room.capacity }} vacantes</span>
                                    <span v-else class="text-slate-400 font-normal italic">Sin límite</span>
                                </td>
                                <td class="py-4 px-4">
                                    <button
                                        @click="viewRoomAvailability(room)"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#2e8b57] bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-3 py-1.5 rounded-md transition cursor-pointer"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>Ver Disponibilidad</span>
                                    </button>
                                </td>
                                <td class="py-4 px-6 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            @click="openEditClassroomModal(room)"
                                            class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-[#2e8b57] transition cursor-pointer"
                                        >
                                            Editar Aula
                                        </button>
                                        <button
                                            @click="deleteClassroom(room.id)"
                                            class="text-red-500 hover:text-red-700 text-xs font-bold cursor-pointer"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filteredClassrooms.length === 0">
                                <td colspan="4" class="py-12 text-center text-slate-500 font-medium">
                                    No se encontraron aulas en la sede seleccionada.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL 1: NUEVA SEDE -->
        <div
            v-if="showNewBranchModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showNewBranchModal = false"
        >
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <h3 class="text-lg font-bold text-slate-900">Registrar Nueva Sede</h3>
                    <button
                        @click="showNewBranchModal = false"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 cursor-pointer"
                    >
                        &times;
                    </button>
                </div>

                <form @submit.prevent="saveNewBranch" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-800">Nombre de la Sede</label>
                        <input
                            v-model="newBranchForm.name"
                            type="text"
                            required
                            placeholder="Ej. Sede Los Olivos"
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-800">Código</label>
                            <input
                                v-model="newBranchForm.code"
                                type="text"
                                placeholder="Ej. LSO"
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-mono font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-800">Teléfono</label>
                            <input
                                v-model="newBranchForm.phone"
                                type="text"
                                placeholder="Ej. 987654321"
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-800">Dirección Física</label>
                        <input
                            v-model="newBranchForm.address"
                            type="text"
                            placeholder="Ej. Av. Carlos Izaguirre 1234"
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5 mt-6">
                        <button
                            type="button"
                            @click="showNewBranchModal = false"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-[#2e8b57] hover:bg-[#257347] px-5 py-2 text-sm font-bold text-white shadow-xs transition cursor-pointer"
                        >
                            Crear Sede
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 2: AULA (CREAR O EDITAR) -->
        <div
            v-if="showClassroomModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showClassroomModal = false"
        >
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <h3 class="text-lg font-bold text-slate-900">
                        {{ editingClassroomId ? 'Editar Aula' : 'Registrar Nueva Aula' }}
                    </h3>
                    <button
                        @click="showClassroomModal = false"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 cursor-pointer"
                    >
                        &times;
                    </button>
                </div>

                <form @submit.prevent="saveClassroom" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-800">Nombre del Aula</label>
                        <input
                            v-model="classroomForm.name"
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
                                v-model="classroomForm.branch_id"
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none font-semibold cursor-pointer"
                            >
                                <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-800">Aforo / Vacantes</label>
                            <input
                                v-model.number="classroomForm.capacity"
                                type="number"
                                placeholder="Ej. 30 vacantes"
                                class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none font-mono"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5 mt-6">
                        <button
                            type="button"
                            @click="showClassroomModal = false"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-[#2e8b57] hover:bg-[#257347] px-5 py-2 text-sm font-bold text-white shadow-xs transition cursor-pointer"
                        >
                            Guardar Aula
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 3: DISPONIBILIDAD DE AULA -->
        <div
            v-if="showScheduleModal && selectedRoomForSchedule"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showScheduleModal = false"
        >
            <div class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
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
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 cursor-pointer"
                    >
                        &times;
                    </button>
                </div>

                <div class="space-y-3 mt-4">
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

                <div class="flex items-center justify-end border-t border-slate-100 pt-4 mt-5">
                    <button
                        @click="showScheduleModal = false"
                        class="rounded-lg bg-slate-800 hover:bg-slate-900 px-5 py-2 text-sm font-bold text-white transition cursor-pointer"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
