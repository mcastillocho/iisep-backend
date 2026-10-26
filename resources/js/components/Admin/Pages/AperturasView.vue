<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

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
}

export interface CourseOption {
    id: number;
    name: string;
    duration_in_weeks: number;
    career_name?: string | null;
    module_name?: string | null;
    career_id?: number | null;
    is_workshop: boolean;
}

export interface TeacherItem {
    id: number;
    full_name: string;
    specialty: string;
}

export interface ScheduledCourseItem {
    id: number;
    course_id: number;
    course_name: string;
    branch_id: number;
    branch_name: string;
    classroom_id: number;
    classroom_name: string;
    teacher_id?: number | null;
    teacher_name: string;
    start_date: string;
    end_date: string;
    day_of_week: string;
    days?: string[];
    start_time?: string;
    end_time?: string;
    time_range: string;
    capacity: number;
    enrolled_count: number;
    status: 'en_curso' | 'programado' | 'finalizado' | 'cancelado';
}

export type EnrollmentStatus =
    | 'activo'
    | 'convalidado'
    | 'completado'
    | 'preinscrito'
    | 'retirado'
    | 'anulado';

export interface EnrollmentRecord {
    id: number;
    scheduled_course_id: number;
    student_full_name: string;
    student_document: string;
    course_name: string;
    module_name: string | null;
    career_name: string | null;
    branch_name: string;
    classroom_name?: string;
    enrollment_date: string;
    status: EnrollmentStatus;
    notes: string | null;
}

interface AuthUser {
    id: number;
    username: string;
    staff?: {
        id: number;
        branch_id: number | null;
        position: string | null;
    } | null;
}

interface Props {
    branches: Branch[];
    classrooms: Classroom[];
    courses: CourseOption[];
    teachers?: TeacherItem[];
    scheduledCourses?: ScheduledCourseItem[];
    enrollments?: EnrollmentRecord[];
    selectedBranchId?: number | 'all';
}

const props = withDefaults(defineProps<Props>(), {
    teachers: () => [],
    scheduledCourses: () => [],
    enrollments: () => [],
    selectedBranchId: 'all',
});

const emit = defineEmits<{
    (e: 'notify', message: string): void;
}>();

// Auth y Roles
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

const isSecretaria = computed(() => {
    const user = page.props.auth?.user;
    if (!user?.staff) return false;
    return user.staff.branch_id !== null && user.staff.position === 'secre';
});

const canManageAperturas = computed(() => {
    if (isAdmin.value) return true;
    if (isCoordinador.value) {
        const staffBranch = page.props.auth?.user?.staff?.branch_id;
        if (props.selectedBranchId === 'all') return true;
        return staffBranch === props.selectedBranchId;
    }
    return false;
});

const canEnroll = computed(() => {
    if (isAdmin.value) return true;
    const staffBranch = page.props.auth?.user?.staff?.branch_id;
    if (isCoordinador.value || isSecretaria.value) {
        if (props.selectedBranchId === 'all') return true;
        return staffBranch === props.selectedBranchId;
    }
    return false;
});

// Zona horaria de Perú
function getPeruToday(): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima' }).format(new Date());
}

function formatPeruDate(dateString: string): string {
    if (!dateString) return '---';
    const parts = dateString.split('-');
    if (parts.length < 3) return dateString;
    const year = Number(parts[0]);
    const month = Number(parts[1]);
    const day = Number(parts[2]);
    if (!year || !month || !day) return dateString;
    const date = new Date(year, month - 1, day);
    return new Intl.DateTimeFormat('es-PE', {
        timeZone: 'America/Lima',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

// ========================================================
// NAVEGACIÓN Y DRILL-DOWN JERÁRQUICO DE 3 NIVELES
// Nivel 1: Catálogo de Cursos & Talleres Registrados (por nombre)
// Nivel 2: Horarios aperturados del curso seleccionado en su respectiva sede
// Nivel 3: Edición de programación de horario y gestión de matrículas
// ========================================================
const selectedCourseForApertures = ref<CourseOption | null>(null);
const searchCourseQuery = ref('');
const courseTypeFilter = ref<'all' | 'workshops' | 'careers'>('all');

// Filtro de estado para la vista de horarios (Nivel 2)
const scheduledStatusFilter = ref<'all' | 'en_curso' | 'programado' | 'finalizado'>('all');

const workshopsCount = computed(() => props.courses.filter((c) => c.is_workshop).length);
const careerCoursesCount = computed(() => props.courses.filter((c) => !c.is_workshop).length);

const courseApertureCounts = computed(() => {
    const counts: Record<number, number> = {};
    for (const sc of props.scheduledCourses) {
        if (props.selectedBranchId !== 'all' && sc.branch_id !== props.selectedBranchId) {
            continue;
        }
        counts[sc.course_id] = (counts[sc.course_id] || 0) + 1;
    }
    return counts;
});

const filteredCoursesCatalog = computed(() => {
    return props.courses.filter((c) => {
        if (courseTypeFilter.value === 'workshops' && !c.is_workshop) return false;
        if (courseTypeFilter.value === 'careers' && c.is_workshop) return false;
        if (searchCourseQuery.value.trim() !== '') {
            const q = searchCourseQuery.value.toLowerCase();
            return (
                c.name.toLowerCase().includes(q) ||
                (c.career_name && c.career_name.toLowerCase().includes(q)) ||
                (c.module_name && c.module_name.toLowerCase().includes(q))
            );
        }
        return true;
    });
});

const schedulesForSelectedCourse = computed(() => {
    if (!selectedCourseForApertures.value) return [];
    return props.scheduledCourses.filter((sched) => {
        if (sched.course_id !== selectedCourseForApertures.value!.id) {
            return false;
        }
        if (props.selectedBranchId !== 'all' && sched.branch_id !== props.selectedBranchId) {
            return false;
        }
        if (scheduledStatusFilter.value !== 'all' && sched.status !== scheduledStatusFilter.value) {
            return false;
        }
        return true;
    });
});

function openCourseSchedulesView(course: CourseOption) {
    selectedCourseForApertures.value = course;
    isEditingOrCreating.value = false;
    currentEditingSched.value = null;
    editingScheduleCourseId.value = null;
}

function backToCoursesCatalog() {
    selectedCourseForApertures.value = null;
    isEditingOrCreating.value = false;
    currentEditingSched.value = null;
    editingScheduleCourseId.value = null;
}

// ========================================================
// 1. ESTADO DE EDICIÓN EN VENTANA DETALLADA (SIN POP-UP)
// ========================================================
const isEditingOrCreating = ref(false);
const editingScheduleCourseId = ref<number | null>(null);
const currentEditingSched = ref<ScheduledCourseItem | null>(null);

// Formulario de apertura: NO incluye end_date para evitar inyecciones
const scheduleForm = useForm({
    course_id: 1,
    branch_id: 1,
    classroom_id: 1,
    teacher_id: null as number | null,
    start_date: getPeruToday(),
    days: ['sabado'] as string[],
    start_time: '18:00',
    end_time: '20:00',
});

// Curso seleccionado actualmente en el formulario
const selectedCourse = computed(() => {
    return props.courses.find((c) => c.id === scheduleForm.course_id);
});

// Fecha de fin calculada automáticamente según la duración oficial del curso
const calculatedEndDate = computed(() => {
    if (!scheduleForm.start_date || !selectedCourse.value) return '';
    const parts = scheduleForm.start_date.split('-');
    if (parts.length < 3) return '';
    const y = Number(parts[0]);
    const m = Number(parts[1]) - 1;
    const d = Number(parts[2]);
    const date = new Date(y, m, d);
    const weeks = selectedCourse.value.duration_in_weeks || 4;
    date.setDate(date.getDate() + weeks * 7);
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima' }).format(date);
});

const weekDays = [
    { value: 'lunes', label: 'Lunes' },
    { value: 'martes', label: 'Martes' },
    { value: 'miercoles', label: 'Miércoles' },
    { value: 'jueves', label: 'Jueves' },
    { value: 'viernes', label: 'Viernes' },
    { value: 'sabado', label: 'Sábado' },
    { value: 'domingo', label: 'Domingo' },
];

function toggleDay(dayVal: string) {
    scheduleForm.days = [dayVal];
}

const availableClassroomsForBranch = computed(() => {
    return props.classrooms.filter(
        (c) => c.branch_id === scheduleForm.branch_id,
    );
});

watch(
    () => scheduleForm.branch_id,
    (newBranchId) => {
        const rooms = props.classrooms.filter((c) => c.branch_id === newBranchId);
        if (rooms.length > 0 && !rooms.some((r) => r.id === scheduleForm.classroom_id)) {
            scheduleForm.classroom_id = rooms[0].id;
        }
    },
);

function openNewScheduleCourseView(course?: CourseOption) {
    if (!canManageAperturas.value) {
        emit('notify', 'Solo el personal de Coordinación o Dirección General puede aperturar cursos.');
        return;
    }
    const targetCourse = course || selectedCourseForApertures.value || props.courses[0];
    if (targetCourse) {
        selectedCourseForApertures.value = targetCourse;
    }

    editingScheduleCourseId.value = null;
    currentEditingSched.value = null;
    scheduleForm.reset();
    scheduleForm.clearErrors();

    const initialBranchId =
        props.selectedBranchId !== 'all'
            ? props.selectedBranchId
            : props.branches[0]?.id || 1;

    scheduleForm.course_id = targetCourse?.id || props.courses[0]?.id || 1;
    scheduleForm.branch_id = initialBranchId;

    const initialRooms = props.classrooms.filter((c) => c.branch_id === initialBranchId);
    scheduleForm.classroom_id = initialRooms[0]?.id || props.classrooms[0]?.id || 1;

    scheduleForm.teacher_id = props.teachers[0]?.id || null;
    scheduleForm.start_date = getPeruToday();
    scheduleForm.days = ['sabado'];
    scheduleForm.start_time = '18:00';
    scheduleForm.end_time = '20:00';

    isEditingOrCreating.value = true;
}

function openEditScheduleCourseView(sched: ScheduledCourseItem) {
    if (!canManageAperturas.value) {
        emit('notify', 'Solo el personal de Coordinación o Dirección General puede modificar aperturas.');
        return;
    }
    const foundCourse = props.courses.find((c) => c.id === sched.course_id);
    if (foundCourse) {
        selectedCourseForApertures.value = foundCourse;
    }

    editingScheduleCourseId.value = sched.id;
    currentEditingSched.value = sched;
    scheduleForm.clearErrors();

    scheduleForm.course_id = sched.course_id;
    scheduleForm.branch_id = sched.branch_id;
    scheduleForm.classroom_id = sched.classroom_id;
    scheduleForm.teacher_id = sched.teacher_id ?? null;
    scheduleForm.start_date = sched.start_date;
    scheduleForm.days = sched.days && sched.days.length > 0 ? [sched.days[0]] : ['sabado'];
    scheduleForm.start_time = sched.start_time || '18:00';
    scheduleForm.end_time = sched.end_time || '20:00';

    isEditingOrCreating.value = true;
}

function closeScheduleCourseView() {
    isEditingOrCreating.value = false;
    editingScheduleCourseId.value = null;
    currentEditingSched.value = null;
    scheduleForm.clearErrors();
}

function saveScheduleCourse() {
    if (!canManageAperturas.value) return;

    // Al enviar el formulario, el backend calcula automáticamente la fecha de fin
    if (editingScheduleCourseId.value) {
        scheduleForm.put(`/admin/cursos-aperturados/${editingScheduleCourseId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeScheduleCourseView();
                emit('notify', 'Curso aperturado y horario actualizados con éxito.');
            },
        });
    } else {
        scheduleForm.post('/admin/cursos-aperturados', {
            preserveScroll: true,
            onSuccess: () => {
                closeScheduleCourseView();
                emit('notify', 'Curso aperturado exitosamente en la sede y aula asignada.');
            },
        });
    }
}

function deleteScheduleCourse(sched: ScheduledCourseItem) {
    if (!canManageAperturas.value) return;
    if (sched.enrolled_count > 0) {
        emit('notify', 'No se puede eliminar la apertura porque ya cuenta con estudiantes matriculados.');
        return;
    }

    if (confirm(`¿Está seguro de eliminar la apertura del curso "${sched.course_name}" en ${sched.branch_name}?`)) {
        router.delete(`/admin/cursos-aperturados/${sched.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('notify', 'Apertura de curso eliminada del sistema.');
            },
        });
    }
}

// ========================================================
// 2. LISTA DE ALUMNOS MATRICULADOS EN EL CURSO EDITADO
// ========================================================
const enrolledStudentsInEditingCourse = computed(() => {
    if (!editingScheduleCourseId.value) return [];
    return props.enrollments.filter(
        (e) => e.scheduled_course_id === editingScheduleCourseId.value,
    );
});

function removeStudentFromCourse(enrollmentId: number, studentName: string) {
    if (!confirm(`¿Desea retirar la matrícula de "${studentName}" de este curso aperturado?`)) return;
    router.delete(`/admin/matriculas/${enrollmentId}`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('notify', `Matrícula de ${studentName} retirada.`);
        },
    });
}

// ========================================================
// 3. BARRA FIJA DE BÚSQUEDA Y MATRÍCULA POR DNI
// ========================================================
const quickDni = ref('');
const quickSearching = ref(false);

// Estado del modal de estudiante no registrado
const showUnregisteredStudentModal = ref(false);
const unregisteredDni = ref('');
const unregisteredFirstName = ref('');
const unregisteredPaternalSurname = ref('');
const unregisteredMaternalSurname = ref('');
const unregisteredBirthDate = ref('');
const unregisteredPhone = ref('');
const unregisteredEmail = ref('');
const unregisteredHasGuardian = ref(false);
const unregisteredGuardianFirstName = ref('');
const unregisteredGuardianPaternalSurname = ref('');
const unregisteredGuardianMaternalSurname = ref('');
const unregisteredGuardianDocument = ref('');
const unregisteredGuardianPhone = ref('');
const unregisteredGuardianEmail = ref('');
const isSubmittingUnregistered = ref(false);
const unregisteredErrorMsg = ref('');

// Para el botón rápido "+ Matricular" de la tabla principal
const showQuickDniModal = ref(false);
const quickSelectedSched = ref<ScheduledCourseItem | null>(null);
const quickModalDni = ref('');
const quickModalFullName = ref('');
const quickModalFirstName = ref('');
const quickModalPaternalSurname = ref('');
const quickModalMaternalSurname = ref('');
const quickModalSearching = ref(false);
const quickModalStudentFound = ref<boolean | null>(null);
const quickModalAlreadyEnrolled = ref(false);
const quickModalSubmitting = ref(false);
const quickModalError = ref('');

// Búsqueda directa desde la barra fija del padrón
async function handleDniSearchAndEnroll() {
    const cleanDoc = quickDni.value.trim();
    if (cleanDoc.length !== 8) {
        emit('notify', 'Por favor ingrese un DNI válido de 8 dígitos.');
        return;
    }

    const sched = currentEditingSched.value;
    if (!sched) return;

    quickSearching.value = true;
    try {
        const res = await fetch(`/admin/estudiantes/buscar-dni?document=${cleanDoc}&scheduled_course_id=${sched.id}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        quickSearching.value = false;

        if (data.found) {
            if (data.already_enrolled) {
                emit('notify', `El estudiante ${data.full_name} ya está matriculado en este curso.`);
                return;
            }

            // Estudiante registrado: se añade de inmediato al curso
            router.post(
                '/admin/matriculas',
                {
                    scheduled_course_id: sched.id,
                    student_document: cleanDoc,
                    student_full_name: data.full_name,
                    enrollment_date: getPeruToday(),
                    status: 'activo',
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        quickDni.value = '';
                        emit('notify', `Estudiante ${data.full_name} matriculado exitosamente.`);
                    },
                    onError: (errs) => {
                        const first = Object.values(errs)[0];
                        if (first) emit('notify', String(first));
                    },
                },
            );
        } else {
            // Estudiante NO registrado: abrir pop-up con advertencia
            unregisteredDni.value = cleanDoc;
            unregisteredFirstName.value = '';
            unregisteredPaternalSurname.value = '';
            unregisteredMaternalSurname.value = '';
            unregisteredBirthDate.value = '';
            unregisteredPhone.value = '';
            unregisteredEmail.value = '';
            unregisteredHasGuardian.value = false;
            unregisteredGuardianFirstName.value = '';
            unregisteredGuardianPaternalSurname.value = '';
            unregisteredGuardianMaternalSurname.value = '';
            unregisteredGuardianDocument.value = '';
            unregisteredGuardianPhone.value = '';
            unregisteredGuardianEmail.value = '';
            unregisteredGuardianFoundName.value = '';
            unregisteredErrorMsg.value = '';
            showUnregisteredStudentModal.value = true;
        }
    } catch {
        quickSearching.value = false;
        emit('notify', 'Error al verificar el DNI en el servidor.');
    }
}

const unregisteredGuardianFoundName = ref('');
let unregisteredGuardianDniTimer: ReturnType<typeof setTimeout> | null = null;

function handleUnregisteredGuardianDniInput() {
    unregisteredGuardianFoundName.value = '';
    const doc = unregisteredGuardianDocument.value.trim();
    if (doc.length >= 8) {
        if (doc === unregisteredDni.value.trim()) {
            unregisteredErrorMsg.value = 'El estudiante no puede ser su propio apoderado.';
            return;
        }
        if (unregisteredGuardianDniTimer) clearTimeout(unregisteredGuardianDniTimer);
        unregisteredGuardianDniTimer = setTimeout(async () => {
            try {
                const res = await fetch(`/admin/estudiantes/buscar-dni?document=${encodeURIComponent(doc)}`);
                const data = await res.json();
                if (data.found) {
                    unregisteredGuardianFoundName.value = data.full_name;
                    unregisteredGuardianFirstName.value = data.first_name || '';
                    unregisteredGuardianPaternalSurname.value = data.paternal_surname || '';
                    unregisteredGuardianMaternalSurname.value = data.maternal_surname || '';
                    if (data.phone) unregisteredGuardianPhone.value = data.phone;
                    if (data.email) unregisteredGuardianEmail.value = data.email;
                    unregisteredErrorMsg.value = '';
                }
            } catch {
                // ignore
            }
        }, 300);
    }
}

function submitUnregisteredStudentEnrollment() {
    const sched = currentEditingSched.value || quickSelectedSched.value;
    if (!sched) return;

    const firstName = unregisteredFirstName.value.trim();
    const paternal = unregisteredPaternalSurname.value.trim();
    const maternal = unregisteredMaternalSurname.value.trim();

    if (!firstName) {
        unregisteredErrorMsg.value = 'Debe ingresar el nombre del estudiante.';
        return;
    }
    if (!paternal) {
        unregisteredErrorMsg.value = 'Debe ingresar el apellido paterno del estudiante.';
        return;
    }

    if (unregisteredHasGuardian.value) {
        if (!unregisteredGuardianDocument.value.trim()) {
            unregisteredErrorMsg.value = 'Debe ingresar el DNI del apoderado.';
            return;
        }
        if (!unregisteredGuardianFirstName.value.trim() || !unregisteredGuardianPaternalSurname.value.trim()) {
            unregisteredErrorMsg.value = 'Debe ingresar el nombre y apellido paterno del apoderado.';
            return;
        }
    }

    isSubmittingUnregistered.value = true;
    unregisteredErrorMsg.value = '';

    const fullNamePreview = `${firstName} ${paternal} ${maternal}`.trim();

    router.post(
        '/admin/matriculas',
        {
            scheduled_course_id: sched.id,
            student_document: unregisteredDni.value,
            first_name: firstName,
            paternal_surname: paternal,
            maternal_surname: maternal || undefined,
            birth_date: unregisteredBirthDate.value || undefined,
            phone: unregisteredPhone.value || undefined,
            email: unregisteredEmail.value || undefined,
            has_guardian: unregisteredHasGuardian.value,
            guardian_document: unregisteredGuardianDocument.value || undefined,
            guardian_first_name: unregisteredGuardianFirstName.value || undefined,
            guardian_paternal_surname: unregisteredGuardianPaternalSurname.value || undefined,
            guardian_maternal_surname: unregisteredGuardianMaternalSurname.value || undefined,
            guardian_phone: unregisteredGuardianPhone.value || undefined,
            guardian_email: unregisteredGuardianEmail.value || undefined,
            enrollment_date: getPeruToday(),
            status: 'activo',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showUnregisteredStudentModal.value = false;
                isSubmittingUnregistered.value = false;
                quickDni.value = '';
                emit('notify', `Estudiante ${fullNamePreview} registrado y matriculado con éxito.`);
            },
            onError: (errs) => {
                isSubmittingUnregistered.value = false;
                const first = Object.values(errs)[0];
                if (first) unregisteredErrorMsg.value = String(first);
            },
        },
    );
}

// Modal alternativo rápido desde la tabla
function openQuickDniEnrollModal(sched: ScheduledCourseItem) {
    quickSelectedSched.value = sched;
    quickModalDni.value = '';
    quickModalFullName.value = '';
    quickModalFirstName.value = '';
    quickModalPaternalSurname.value = '';
    quickModalMaternalSurname.value = '';
    quickModalStudentFound.value = null;
    quickModalAlreadyEnrolled.value = false;
    quickModalError.value = '';
    showQuickDniModal.value = true;
}

async function checkQuickModalDni() {
    const clean = quickModalDni.value.trim();
    quickModalError.value = '';
    if (clean.length < 8) {
        quickModalStudentFound.value = null;
        quickModalAlreadyEnrolled.value = false;
        return;
    }

    quickModalSearching.value = true;
    try {
        const schedId = quickSelectedSched.value?.id || '';
        const res = await fetch(`/admin/estudiantes/buscar-dni?document=${clean}&scheduled_course_id=${schedId}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (data.found) {
            quickModalStudentFound.value = true;
            quickModalFullName.value = data.full_name;
            quickModalAlreadyEnrolled.value = !!data.already_enrolled;
            if (data.already_enrolled) {
                quickModalError.value = 'Este estudiante ya se encuentra matriculado en este curso.';
            }
        } else {
            quickModalStudentFound.value = false;
            quickModalFullName.value = '';
            quickModalFirstName.value = '';
            quickModalPaternalSurname.value = '';
            quickModalMaternalSurname.value = '';
            quickModalAlreadyEnrolled.value = false;
        }
    } catch {
        quickModalStudentFound.value = null;
    } finally {
        quickModalSearching.value = false;
    }
}

watch(quickModalDni, (newVal) => {
    if (newVal.trim().length === 8) {
        checkQuickModalDni();
    } else {
        quickModalStudentFound.value = null;
        quickModalAlreadyEnrolled.value = false;
        quickModalError.value = '';
    }
});

function submitQuickModalEnrollment() {
    const sched = quickSelectedSched.value;
    if (!sched) return;

    const doc = quickModalDni.value.trim();
    if (!doc || doc.length < 8) return;

    if (quickModalStudentFound.value === false) {
        const firstName = quickModalFirstName.value.trim();
        const paternal = quickModalPaternalSurname.value.trim();
        const maternal = quickModalMaternalSurname.value.trim();

        if (!firstName) {
            quickModalError.value = 'Debe ingresar el nombre del estudiante.';
            return;
        }
        if (!paternal) {
            quickModalError.value = 'Debe ingresar el apellido paterno del estudiante.';
            return;
        }

        quickModalSubmitting.value = true;
        const fullNamePreview = `${firstName} ${paternal} ${maternal}`.trim();

        router.post(
            '/admin/matriculas',
            {
                scheduled_course_id: sched.id,
                student_document: doc,
                first_name: firstName,
                paternal_surname: paternal,
                maternal_surname: maternal || undefined,
                enrollment_date: getPeruToday(),
                status: 'activo',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showQuickDniModal.value = false;
                    quickModalSubmitting.value = false;
                    emit('notify', `Matrícula registrada exitosamente para ${fullNamePreview}.`);
                },
                onError: (errs) => {
                    quickModalSubmitting.value = false;
                    const first = Object.values(errs)[0];
                    if (first) quickModalError.value = String(first);
                },
            },
        );
    } else {
        quickModalSubmitting.value = true;
        router.post(
            '/admin/matriculas',
            {
                scheduled_course_id: sched.id,
                student_document: doc,
                enrollment_date: getPeruToday(),
                status: 'activo',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showQuickDniModal.value = false;
                    quickModalSubmitting.value = false;
                    emit('notify', `Matrícula registrada exitosamente en ${sched.course_name}.`);
                },
                onError: (errs) => {
                    quickModalSubmitting.value = false;
                    const first = Object.values(errs)[0];
                    if (first) quickModalError.value = String(first);
                },
            },
        );
    }
}

function openFullRegistrationFromQuickModal() {
    unregisteredDni.value = quickModalDni.value.trim();
    unregisteredFirstName.value = quickModalFirstName.value.trim();
    unregisteredPaternalSurname.value = quickModalPaternalSurname.value.trim();
    unregisteredMaternalSurname.value = quickModalMaternalSurname.value.trim();
    unregisteredBirthDate.value = '';
    unregisteredPhone.value = '';
    unregisteredEmail.value = '';
    unregisteredHasGuardian.value = false;
    unregisteredGuardianFirstName.value = '';
    unregisteredGuardianPaternalSurname.value = '';
    unregisteredGuardianMaternalSurname.value = '';
    unregisteredGuardianDocument.value = '';
    unregisteredGuardianPhone.value = '';
    unregisteredGuardianEmail.value = '';
    unregisteredErrorMsg.value = '';
    showQuickDniModal.value = false;
    showUnregisteredStudentModal.value = true;
}
</script>

<template>
    <div class="space-y-6">
        <!-- ======================================================== -->
        <!-- 1. VISTA DETALLADA: EDITAR O APERTURAR CURSO (VENTANA COMPLETA) -->
        <!-- ======================================================== -->
        <div
            v-if="isEditingOrCreating"
            class="space-y-6 rounded-xl border border-slate-200 bg-white p-7 shadow-xs"
        >
            <!-- Cabecera de la ventana detallada con Volver y Guardar -->
            <div
                class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="closeScheduleCourseView"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-100 px-3.5 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-200"
                    >
                        &larr; Volver a Horarios de {{ selectedCourseForApertures?.name || 'Curso' }}
                    </button>
                    <div>
                        <h2 class="text-xl font-black text-slate-900">
                            {{
                                editingScheduleCourseId
                                    ? 'Editando Programación de Curso:'
                                    : 'Aperturar Nuevo Horario en Sede:'
                            }}
                            <span v-if="selectedCourseForApertures" class="ml-1 text-[#2e8b57]">
                                {{ selectedCourseForApertures.name }}
                            </span>
                        </h2>
                        <p class="text-xs text-slate-500">
                            {{
                                editingScheduleCourseId
                                    ? `Ambiente asignado: ${currentEditingSched?.classroom_name} en ${currentEditingSched?.branch_name}`
                                    : 'Configura la asignación de aula física, día semanal de clase y periodo de vigencia formativa.'
                            }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="closeScheduleCourseView"
                        class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="saveScheduleCourse"
                        :disabled="scheduleForm.processing"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-[#257347] disabled:opacity-50"
                    >
                        <svg
                            v-if="scheduleForm.processing"
                            class="h-3.5 w-3.5 animate-spin text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ editingScheduleCourseId ? 'Guardar Cambios' : 'Aperturar Curso' }}</span>
                    </button>
                </div>
            </div>

            <!-- Formulario de Configuración de la Apertura -->
            <form @submit.prevent="saveScheduleCourse" class="space-y-5">
                <div
                    v-if="scheduleForm.hasErrors"
                    class="rounded-lg border border-rose-200 bg-rose-50 p-3.5 text-xs text-rose-700 space-y-1"
                >
                    <p class="font-bold">Por favor revise los siguientes campos:</p>
                    <p v-for="(err, key) in scheduleForm.errors" :key="key">&bull; {{ err }}</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-800 uppercase">
                            Curso o Taller
                        </label>
                        <select
                            v-model="scheduleForm.course_id"
                            required
                            class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        >
                            <option
                                v-for="c in courses"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }} ({{ c.duration_in_weeks }} semanas)
                                {{ c.is_workshop ? '&bull; Taller' : '' }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-800 uppercase">
                            Sede Presencial
                        </label>
                        <select
                            v-model="scheduleForm.branch_id"
                            required
                            class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        >
                            <option
                                v-for="b in branches"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.name }} ({{ b.code }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-800 uppercase">
                            Aula / Ambiente Físico
                        </label>
                        <select
                            v-model="scheduleForm.classroom_id"
                            required
                            class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        >
                            <option
                                v-for="room in availableClassroomsForBranch"
                                :key="room.id"
                                :value="room.id"
                            >
                                {{ room.name }} ({{ room.capacity ?? 'Sin aforo' }} vacantes)
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Fechas y Horas (Fecha de Fin Calculada Automáticamente, no editable) -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-800 uppercase">
                            Fecha de Inicio (Periodo)
                        </label>
                        <input
                            v-model="scheduleForm.start_date"
                            type="date"
                            required
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        />
                    </div>

                    <!-- FECHA DE FIN CALCULADA AUTOMÁTICAMENTE SEGÚN CURSO (NO EDITABLE, NO ENVIADA) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-800 uppercase">
                            Fecha de Fin (Calculada)
                        </label>
                        <input
                            :value="calculatedEndDate"
                            type="date"
                            readonly
                            disabled
                            class="mt-1.5 w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3.5 py-2 font-mono text-sm font-bold text-slate-600 select-none"
                        />
                        <p class="mt-1 text-[11px] text-slate-500">
                            Duración curricular: {{ selectedCourse?.duration_in_weeks ?? 4 }} semanas.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-800 uppercase">
                            Hora Inicio de Dictado
                        </label>
                        <input
                            v-model="scheduleForm.start_time"
                            type="time"
                            required
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-800 uppercase">
                            Hora Fin de Dictado
                        </label>
                        <input
                            v-model="scheduleForm.end_time"
                            type="time"
                            required
                            class="mt-1.5 w-full rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 font-mono text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Días de Dictado (Restringido a 1 día de clase por semana) -->
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                    <label class="block text-xs font-bold text-slate-800 uppercase">
                        Día de Clase Semanal (1 sesión por semana)
                    </label>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Selecciona el día de la semana en que se dictan las clases de esta apertura:
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            v-for="d in weekDays"
                            :key="d.value"
                            @click="toggleDay(d.value)"
                            :class="[
                                scheduleForm.days.includes(d.value)
                                    ? 'bg-[#2e8b57] text-white font-bold shadow-xs'
                                    : 'bg-white text-slate-700 hover:bg-slate-100 font-semibold border border-slate-300',
                                'cursor-pointer rounded-lg px-4 py-2 text-xs transition',
                            ]"
                        >
                            {{ d.label }}
                        </button>
                    </div>
                </div>

                <!-- Docente Asignado -->
                <div>
                    <label class="block text-xs font-bold text-slate-800 uppercase">
                        Docente Asignado a la Apertura
                    </label>
                    <select
                        v-model="scheduleForm.teacher_id"
                        class="mt-1.5 w-full cursor-pointer rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                    >
                        <option :value="null">-- Docente por asignar / Sin docente --</option>
                        <option
                            v-for="t in teachers"
                            :key="t.id"
                            :value="t.id"
                        >
                            {{ t.full_name }} ({{ t.specialty }})
                        </option>
                    </select>
                </div>
            </form>

            <!-- ======================================================== -->
            <!-- SECCIÓN: ALUMNOS MATRICULADOS EN ESTE CURSO APERTURADO   -->
            <!-- (Con barra fija de búsqueda e inscripción por DNI)       -->
            <!-- ======================================================== -->
            <div
                v-if="editingScheduleCourseId && currentEditingSched"
                class="space-y-4 rounded-xl border border-slate-200 bg-slate-50/70 p-6"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-slate-900">
                                Padrón de Estudiantes Matriculados
                            </h3>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 font-mono text-xs font-extrabold text-[#2e8b57]">
                                {{ enrolledStudentsInEditingCourse.length }} / {{ currentEditingSched.capacity }} Vacantes
                            </span>
                            <span class="font-mono text-xs font-bold text-slate-500">
                                ({{ Math.round((enrolledStudentsInEditingCourse.length / currentEditingSched.capacity) * 100) }}% ocupado)
                            </span>
                        </div>
                        <p class="text-xs text-slate-500">
                            Estudiantes oficialmente matriculados en este ambiente y horario.
                        </p>
                    </div>
                </div>

                <!-- BARRA FIJA DE BÚSQUEDA Y MATRÍCULA DIRECTA POR DNI (ESTILO CARRERAS/MÓDULOS) -->
                <div
                    v-if="canEnroll"
                    class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-2xs sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex flex-1 items-center gap-2">
                        <div class="relative flex-1 sm:max-w-xs">
                            <input
                                v-model="quickDni"
                                type="text"
                                maxlength="8"
                                placeholder="DNI del estudiante (8 dígitos)..."
                                @keyup.enter="handleDniSearchAndEnroll"
                                class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2 pr-9 pl-3.5 font-mono text-sm font-bold text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                            <span
                                v-if="quickSearching"
                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3"
                            >
                                <svg
                                    class="h-4 w-4 animate-spin text-slate-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                            </span>
                        </div>

                        <button
                            type="button"
                            @click="handleDniSearchAndEnroll"
                            :disabled="quickDni.trim().length !== 8 || quickSearching"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-[#2e8b57] px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-[#257347] disabled:opacity-50"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                            <span>+ Inscribir Estudiante</span>
                        </button>
                    </div>

                    <p class="text-xs text-slate-500">
                        Ingresa el DNI para matricular directamente al estudiante.
                    </p>
                </div>

                <!-- Tabla del Padrón de Estudiantes -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="border-b border-slate-200 bg-slate-50 font-bold text-slate-600 uppercase">
                            <tr>
                                <th class="w-12 px-4 py-3 text-center">N°</th>
                                <th class="px-4 py-3">Documento (DNI)</th>
                                <th class="px-4 py-3">Apellidos y Nombres</th>
                                <th class="px-4 py-3">Fecha Registro</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="w-20 px-4 py-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(st, idx) in enrolledStudentsInEditingCourse"
                                :key="st.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 text-center font-mono text-slate-400">
                                    {{ idx + 1 }}
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-slate-900">
                                    {{ st.student_document }}
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-800">
                                    {{ st.student_full_name }}
                                </td>
                                <td class="px-4 py-3 font-mono text-slate-500">
                                    {{ formatPeruDate(st.enrollment_date) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="st.status === 'activo'"
                                        class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800"
                                        >Activo</span
                                    >
                                    <span
                                        v-else-if="st.status === 'convalidado'"
                                        class="rounded bg-purple-100 px-2 py-0.5 text-[10px] font-bold text-purple-800"
                                        >Convalidado</span
                                    >
                                    <span
                                        v-else
                                        class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700"
                                        >{{ st.status }}</span
                                    >
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        v-if="canEnroll"
                                        type="button"
                                        @click="removeStudentFromCourse(st.id, st.student_full_name)"
                                        class="cursor-pointer rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                        title="Retirar matrícula"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="enrolledStudentsInEditingCourse.length === 0">
                                <td colspan="6" class="py-10 text-center text-slate-400 italic">
                                    Aún no hay estudiantes matriculados en este curso aperturado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Botones inferiores de Guardar y Cancelar -->
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <button
                    type="button"
                    @click="closeScheduleCourseView"
                    class="cursor-pointer rounded-lg border border-slate-300 px-5 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100"
                >
                    &larr; Volver a Horarios
                </button>
                <button
                    type="button"
                    @click="saveScheduleCourse"
                    :disabled="scheduleForm.processing"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-6 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-[#257347] disabled:opacity-50"
                >
                    {{ editingScheduleCourseId ? 'Guardar Cambios de Programación' : 'Aperturar Curso' }}
                </button>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- NIVEL 2: HORARIOS APERTURADOS DEL CURSO SELECCIONADO     -->
        <!-- ======================================================== -->
        <div v-else-if="selectedCourseForApertures !== null" class="space-y-6">
            <!-- Barra Superior de Navegación y Cabecera del Curso -->
            <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <button
                            type="button"
                            @click="backToCoursesCatalog"
                            class="mb-3 inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:border-[#2e8b57] hover:bg-white hover:text-[#2e8b57]"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            <span>Volver a Catálogo de Cursos &amp; Talleres</span>
                        </button>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                                {{ selectedCourseForApertures.name }}
                            </h2>
                            <span
                                v-if="selectedCourseForApertures.is_workshop"
                                class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-bold text-purple-800"
                            >
                                Taller Libre
                            </span>
                            <span
                                v-else
                                class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800"
                            >
                                Curso Carrera: {{ selectedCourseForApertures.career_name || 'Modular' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Duración: <strong class="text-slate-700">{{ selectedCourseForApertures.duration_in_weeks || 4 }} semanas</strong>
                            <span v-if="selectedCourseForApertures.module_name"> &bull; Módulo: {{ selectedCourseForApertures.module_name }}</span>
                            &bull; Mostrando horarios de la sede seleccionada.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            v-if="canManageAperturas"
                            type="button"
                            @click="openNewScheduleCourseView(selectedCourseForApertures)"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-4 py-2.5 text-xs sm:text-sm font-bold text-white shadow-xs transition hover:bg-[#257347]"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>+ Aperturar Nuevo Horario</span>
                        </button>
                    </div>
                </div>

                <!-- Filtro de Estado del Horario -->
                <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                    <span class="mr-1 text-xs font-bold tracking-wider text-slate-400 uppercase">Estado:</span>
                    <button
                        type="button"
                        @click="scheduledStatusFilter = 'all'"
                        :class="[
                            scheduledStatusFilter === 'all'
                                ? 'bg-slate-800 font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Todos ({{ schedulesForSelectedCourse.length }})
                    </button>
                    <button
                        type="button"
                        @click="scheduledStatusFilter = 'en_curso'"
                        :class="[
                            scheduledStatusFilter === 'en_curso'
                                ? 'bg-[#2e8b57] font-bold text-white'
                                : 'bg-emerald-50 font-semibold text-[#2e8b57] hover:bg-emerald-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        En Dictado
                    </button>
                    <button
                        type="button"
                        @click="scheduledStatusFilter = 'programado'"
                        :class="[
                            scheduledStatusFilter === 'programado'
                                ? 'bg-amber-600 font-bold text-white'
                                : 'bg-amber-50 font-semibold text-amber-700 hover:bg-amber-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Programados
                    </button>
                    <button
                        type="button"
                        @click="scheduledStatusFilter = 'finalizado'"
                        :class="[
                            scheduledStatusFilter === 'finalizado'
                                ? 'bg-slate-700 font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Finalizados
                    </button>
                </div>
            </div>

            <!-- Tabla de Horarios del Curso -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 uppercase">
                            <tr>
                                <th class="py-4 pr-3 pl-6">Sede &amp; Aula</th>
                                <th class="px-4 py-4">Día &amp; Horario</th>
                                <th class="px-4 py-4">Fechas Formativas</th>
                                <th class="px-4 py-4">Docente</th>
                                <th class="px-4 py-4">Aforo / Alumnos</th>
                                <th class="px-4 py-4">Estado</th>
                                <th class="py-4 pr-6 pl-4 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="sched in schedulesForSelectedCourse"
                                :key="sched.id"
                                class="transition hover:bg-slate-50/80"
                            >
                                <!-- Sede & Aula -->
                                <td class="py-4 pr-3 pl-6">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900">
                                            {{ sched.branch_name }}
                                        </span>
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600">
                                            #{{ sched.id }}
                                        </span>
                                    </div>
                                    <div class="mt-0.5 text-xs font-medium text-slate-500">
                                        Aula: <span class="font-bold text-slate-700">{{ sched.classroom_name }}</span>
                                    </div>
                                </td>

                                <!-- Día & Horario -->
                                <td class="px-4 py-4 text-xs whitespace-nowrap">
                                    <div class="font-bold text-[#2e8b57] capitalize">
                                        {{ sched.day_of_week }}
                                    </div>
                                    <div class="mt-0.5 font-mono text-slate-600">
                                        {{ sched.time_range }}
                                    </div>
                                </td>

                                <!-- Fechas Formativas -->
                                <td class="px-4 py-4 text-xs whitespace-nowrap">
                                    <div class="flex items-center gap-1.5 font-mono font-bold text-slate-800">
                                        <span>{{ formatPeruDate(sched.start_date) }}</span>
                                        <span class="font-normal text-slate-400">al</span>
                                        <span>{{ formatPeruDate(sched.end_date) }}</span>
                                    </div>
                                </td>

                                <!-- Docente -->
                                <td class="px-4 py-4 text-xs whitespace-nowrap">
                                    <div class="font-medium text-slate-800">
                                        {{ sched.teacher_name || 'Sin docente asignado' }}
                                    </div>
                                </td>

                                <!-- Aforo / Alumnos -->
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="mb-1 flex items-center justify-between gap-2 text-xs">
                                        <span class="font-mono font-bold text-slate-800">
                                            {{ sched.enrolled_count }} / {{ sched.capacity }}
                                        </span>
                                        <span class="font-mono text-[11px] font-bold text-slate-500">
                                            {{ Math.round((sched.enrolled_count / sched.capacity) * 100) }}%
                                        </span>
                                    </div>
                                    <div class="h-2 w-28 overflow-hidden rounded-full border border-slate-200 bg-slate-100">
                                        <div
                                            class="h-2 rounded-full bg-[#2e8b57] transition-all duration-300"
                                            :style="{
                                                width: `${Math.min(100, Math.round((sched.enrolled_count / sched.capacity) * 100))}%`,
                                            }"
                                        ></div>
                                    </div>
                                    <div class="mt-1 text-[11px] text-slate-400">
                                        {{ sched.capacity - sched.enrolled_count }} vacantes disp.
                                    </div>
                                </td>

                                <!-- Estado -->
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span
                                        v-if="sched.status === 'en_curso'"
                                        class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800"
                                    >
                                        En Dictado
                                    </span>
                                    <span
                                        v-else-if="sched.status === 'programado'"
                                        class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold text-amber-800"
                                    >
                                        Programado
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600"
                                    >
                                        Finalizado
                                    </span>
                                </td>

                                <!-- Acciones en Pila Vertical -->
                                <td class="py-3 pr-6 pl-4 text-right whitespace-nowrap">
                                    <div class="flex flex-col items-end gap-1.5">
                                        <!-- Ingresar al Horario / Editar -->
                                        <button
                                            type="button"
                                            @click="openEditScheduleCourseView(sched)"
                                            class="inline-flex w-32 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-[#2e8b57] px-3 py-1.5 text-xs font-bold text-white shadow-2xs transition hover:bg-[#257347]"
                                            title="Ingresar a ver matrículas y editar horario"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            <span>Ingresar</span>
                                        </button>

                                        <!-- Eliminar Horario si no tiene alumnos -->
                                        <button
                                            v-if="canManageAperturas"
                                            type="button"
                                            @click="deleteScheduleCourse(sched)"
                                            :disabled="sched.enrolled_count > 0"
                                            :class="[
                                                sched.enrolled_count > 0
                                                    ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'
                                                    : 'cursor-pointer border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100',
                                                'inline-flex w-32 items-center justify-center gap-1 rounded-md border px-2.5 py-1 text-xs font-semibold shadow-2xs transition',
                                            ]"
                                            :title="
                                                sched.enrolled_count > 0
                                                    ? 'No se puede eliminar: tiene alumnos matriculados'
                                                    : 'Eliminar horario aperturado'
                                            "
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            <span>Eliminar</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="schedulesForSelectedCourse.length === 0">
                                <td colspan="7" class="py-12 text-center">
                                    <div class="mx-auto max-w-sm text-center">
                                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <p class="font-bold text-slate-800">No hay horarios aperturados para este curso</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Aperture un nuevo horario para asignar aula, docente, fecha y recibir matrículas.
                                        </p>
                                        <button
                                            v-if="canManageAperturas"
                                            type="button"
                                            @click="openNewScheduleCourseView(selectedCourseForApertures)"
                                            class="mt-4 inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-[#2e8b57] px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-[#257347]"
                                        >
                                            + Aperturar Primer Horario
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- NIVEL 1: CATÁLOGO DE CURSOS Y TALLERES (SOLO POR NOMBRE) -->
        <!-- ======================================================== -->
        <div v-else class="space-y-6">
            <!-- Barra Superior: Búsqueda y Filtros de Cursos/Talleres -->
            <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-slate-900">
                            Apertura de Cursos &amp; Talleres
                        </h2>
                        <p class="text-xs text-slate-500">
                            Seleccione un curso o taller para ver sus horarios aperturados, gestionar aulas y registrar matrículas.
                        </p>
                    </div>

                    <!-- Buscador directo por nombre de curso o taller -->
                    <div class="relative flex-1 sm:max-w-md">
                        <svg
                            class="pointer-events-none absolute top-3 left-3.5 h-5 w-5 text-slate-400"
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
                        <input
                            v-model="searchCourseQuery"
                            type="text"
                            placeholder="Buscar curso o taller por nombre..."
                            class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-4 pl-11 text-sm text-slate-900 placeholder-slate-400 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Filtros por tipo de formación: Todos / Talleres Libres / Cursos Modulares -->
                <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                    <span class="mr-1 text-xs font-bold tracking-wider text-slate-400 uppercase">Catálogo:</span>
                    <button
                        type="button"
                        @click="courseTypeFilter = 'all'"
                        :class="[
                            courseTypeFilter === 'all'
                                ? 'bg-slate-800 font-bold text-white'
                                : 'bg-slate-100 font-semibold text-slate-600 hover:bg-slate-200',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Todos ({{ props.courses.length }})
                    </button>
                    <button
                        type="button"
                        @click="courseTypeFilter = 'workshops'"
                        :class="[
                            courseTypeFilter === 'workshops'
                                ? 'bg-purple-700 font-bold text-white'
                                : 'bg-purple-50 font-semibold text-purple-700 hover:bg-purple-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Talleres Libres ({{ workshopsCount }})
                    </button>
                    <button
                        type="button"
                        @click="courseTypeFilter = 'careers'"
                        :class="[
                            courseTypeFilter === 'careers'
                                ? 'bg-[#2e8b57] font-bold text-white'
                                : 'bg-emerald-50 font-semibold text-[#2e8b57] hover:bg-emerald-100',
                            'cursor-pointer rounded-lg px-3 py-1.5 text-xs transition',
                        ]"
                    >
                        Cursos de Carreras ({{ careerCoursesCount }})
                    </button>
                </div>
            </div>

            <!-- Cuadrícula de Cursos & Talleres Registrados (Solo por Nombre y Badges Clave) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div
                    v-for="course in filteredCoursesCatalog"
                    :key="course.id"
                    @click="openCourseSchedulesView(course)"
                    class="group relative flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-[#2e8b57] hover:shadow-md cursor-pointer"
                >
                    <div>
                        <!-- Fila superior: Tipo de curso y Horarios activos -->
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <span
                                v-if="course.is_workshop"
                                class="rounded-full bg-purple-100 px-2.5 py-0.5 text-[11px] font-bold text-purple-800"
                            >
                                Taller Libre
                            </span>
                            <span
                                v-else
                                class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800"
                            >
                                Carrera
                            </span>

                            <span
                                :class="[
                                    (courseApertureCounts[course.id] || 0) > 0
                                        ? 'bg-emerald-50 font-bold text-[#2e8b57] border border-emerald-200'
                                        : 'bg-slate-100 font-medium text-slate-500',
                                    'rounded-md px-2 py-0.5 text-[11px]',
                                ]"
                            >
                                {{ courseApertureCounts[course.id] || 0 }} {{ (courseApertureCounts[course.id] || 0) === 1 ? 'horario' : 'horarios' }}
                            </span>
                        </div>

                        <!-- Nombre del Curso (Elemento Principal) -->
                        <h3 class="text-base font-bold text-slate-800 transition-colors group-hover:text-[#2e8b57] line-clamp-2">
                            {{ course.name }}
                        </h3>

                        <!-- Info Secundaria Sutil -->
                        <div class="mt-2 text-xs text-slate-500">
                            <p v-if="course.career_name" class="truncate">
                                <span class="font-medium text-slate-600">{{ course.career_name }}</span>
                            </p>
                            <p class="mt-0.5 text-slate-400">
                                Duración: {{ course.duration_in_weeks || 4 }} semanas
                            </p>
                        </div>
                    </div>

                    <!-- Pie de la Tarjeta con Enlace Interactivo -->
                    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs font-semibold text-[#2e8b57]">
                        <span>Ver horarios aperturados</span>
                        <svg
                            class="h-4 w-4 transition-transform group-hover:translate-x-1"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Estado Vacío cuando no coincide la búsqueda -->
            <div
                v-if="filteredCoursesCatalog.length === 0"
                class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center"
            >
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="font-bold text-slate-800">No se encontraron cursos o talleres</p>
                <p class="mt-1 text-xs text-slate-500">
                    No hay resultados que coincidan con "{{ searchCourseQuery }}".
                </p>
                <button
                    type="button"
                    @click="searchCourseQuery = ''; courseTypeFilter = 'all'"
                    class="mt-4 inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50"
                >
                    Limpiar Filtros
                </button>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- POPUP: ADVERTENCIA Y REGISTRO DE ESTUDIANTE NO ENCONTRADO -->
        <!-- ======================================================== -->
        <div
            v-if="showUnregisteredStudentModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs overflow-y-auto"
            @click.self="showUnregisteredStudentModal = false"
        >
            <div class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl space-y-5 my-8">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900">
                            Registrar Estudiante & Matrícula
                        </h3>
                        <p v-if="currentEditingSched || quickSelectedSched" class="text-xs text-slate-500 font-medium mt-0.5">
                            {{ (currentEditingSched || quickSelectedSched)?.course_name }} &bull;
                            {{ (currentEditingSched || quickSelectedSched)?.branch_name }} ({{ (currentEditingSched || quickSelectedSched)?.classroom_name }})
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showUnregisteredStudentModal = false"
                        class="cursor-pointer rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    >
                        &times;
                    </button>
                </div>

                <!-- Advertencia solicitada por el usuario -->
                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/80 p-3.5 text-xs text-amber-900">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <p class="font-bold">Estudiante no registrado previamente</p>
                        <p class="mt-0.5 text-amber-800">
                            El DNI <span class="font-mono font-bold">{{ unregisteredDni }}</span> no existe en el sistema. Requiere registrarse la matrícula completando los datos del estudiante y su apoderado.
                        </p>
                    </div>
                </div>

                <form @submit.prevent="submitUnregisteredStudentEnrollment" class="space-y-4">
                    <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-1">
                        <!-- SECCIÓN 1: DATOS DEL ESTUDIANTE -->
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                1. Datos del Estudiante
                            </h4>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">DNI (8 dígitos) *</label>
                                    <input
                                        v-model="unregisteredDni"
                                        type="text"
                                        maxlength="8"
                                        placeholder="Ej. 74819203"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-mono font-bold text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Fecha de Nacimiento</label>
                                    <input
                                        v-model="unregisteredBirthDate"
                                        type="date"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-slate-700">Nombres *</label>
                                    <input
                                        v-model="unregisteredFirstName"
                                        type="text"
                                        required
                                        placeholder="Nombres completos"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Apellido Paterno *</label>
                                    <input
                                        v-model="unregisteredPaternalSurname"
                                        type="text"
                                        required
                                        placeholder="Primer apellido"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Apellido Materno</label>
                                    <input
                                        v-model="unregisteredMaternalSurname"
                                        type="text"
                                        placeholder="Segundo apellido"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Teléfono / Celular</label>
                                    <input
                                        v-model="unregisteredPhone"
                                        type="text"
                                        placeholder="Ej. 987654321"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Correo Electrónico</label>
                                    <input
                                        v-model="unregisteredEmail"
                                        type="email"
                                        placeholder="alumno@ejemplo.com"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 2: DATOS DEL APODERADO -->
                        <div class="border-t border-slate-100 pt-3 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    2. Datos del Apoderado
                                </h4>
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-[#2e8b57]">
                                    <input
                                        v-model="unregisteredHasGuardian"
                                        type="checkbox"
                                        class="rounded text-[#2e8b57] focus:ring-[#2e8b57]"
                                    />
                                    <span>{{ unregisteredHasGuardian ? 'Apoderado Habilitado' : 'Sin apoderado' }}</span>
                                </label>
                            </div>

                            <div v-if="unregisteredHasGuardian" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-3">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700">DNI Apoderado (8 dígitos) *</label>
                                        <input
                                            v-model="unregisteredGuardianDocument"
                                            type="text"
                                            maxlength="8"
                                            @input="handleUnregisteredGuardianDniInput"
                                            placeholder="Ej. 09876543"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-mono font-bold text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                        />
                                        <p v-if="unregisteredGuardianFoundName" class="mt-1 text-xs text-emerald-700 font-bold flex items-center gap-1">
                                            <span>✓ Apoderado encontrado: {{ unregisteredGuardianFoundName }}</span>
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700">Teléfono Apoderado</label>
                                        <input
                                            v-model="unregisteredGuardianPhone"
                                            type="text"
                                            placeholder="Ej. 912345678"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                        />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-bold text-slate-700">Nombres del Apoderado *</label>
                                        <input
                                            v-model="unregisteredGuardianFirstName"
                                            type="text"
                                            placeholder="Nombres"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700">Apellido Paterno Apoderado *</label>
                                        <input
                                            v-model="unregisteredGuardianPaternalSurname"
                                            type="text"
                                            placeholder="Paterno"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700">Apellido Materno Apoderado</label>
                                        <input
                                            v-model="unregisteredGuardianMaternalSurname"
                                            type="text"
                                            placeholder="Materno"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                        />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-bold text-slate-700">Correo Electrónico Apoderado</label>
                                        <input
                                            v-model="unregisteredGuardianEmail"
                                            type="email"
                                            placeholder="apoderado@ejemplo.com"
                                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="unregisteredErrorMsg"
                        class="rounded-lg border border-rose-200 bg-rose-50 p-2.5 text-xs font-semibold text-rose-700"
                    >
                        {{ unregisteredErrorMsg }}
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <button
                            type="button"
                            @click="showUnregisteredStudentModal = false"
                            class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="!unregisteredFirstName.trim() || !unregisteredPaternalSurname.trim() || isSubmittingUnregistered"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-[#2e8b57] px-5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-[#257347] disabled:opacity-50"
                        >
                            <svg
                                v-if="isSubmittingUnregistered"
                                class="h-3.5 w-3.5 animate-spin text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Registrar y Matricular</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL RÁPIDO: MATRICULAR POR DNI (DESDE TABLA PRINCIPAL) -->
        <!-- ======================================================== -->
        <div
            v-if="showQuickDniModal && quickSelectedSched"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            @click.self="showQuickDniModal = false"
        >
            <div
                class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">
                            Matricular Alumno por DNI
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ quickSelectedSched.course_name }} &bull;
                            {{ quickSelectedSched.branch_name }} ({{ quickSelectedSched.classroom_name }})
                        </p>
                    </div>
                    <button
                        @click="showQuickDniModal = false"
                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        &times;
                    </button>
                </div>

                <form @submit.prevent="submitQuickModalEnrollment" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-800">
                            Documento de Identidad (DNI)
                        </label>
                        <div class="relative mt-1.5">
                            <input
                                v-model="quickModalDni"
                                type="text"
                                maxlength="8"
                                required
                                placeholder="8 dígitos (Ej. 74819203)"
                                class="w-full rounded-lg border border-slate-300 bg-slate-50 py-2.5 pr-10 pl-3.5 font-mono text-base font-bold tracking-widest text-slate-900 focus:border-[#2e8b57] focus:bg-white focus:outline-none"
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                <svg
                                    v-if="quickModalSearching"
                                    class="h-4 w-4 animate-spin text-slate-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <svg
                                    v-else-if="quickModalStudentFound === true"
                                    class="h-5 w-5 text-emerald-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Estudiante Encontrado en Base de Datos -->
                    <div
                        v-if="quickModalStudentFound === true"
                        class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 text-xs"
                    >
                        <p class="font-bold text-emerald-800">Estudiante registrado:</p>
                        <p class="mt-0.5 text-sm font-extrabold text-slate-900">
                            {{ quickModalFullName }}
                        </p>
                    </div>

                    <!-- Estudiante Nuevo: Advertencia y Campos Separados de Nombre -->
                    <div
                        v-else-if="quickModalStudentFound === false && quickModalDni.length === 8"
                        class="space-y-3 rounded-lg border border-amber-200 bg-amber-50/80 p-3.5 text-xs"
                    >
                        <div class="flex items-start gap-2">
                            <svg class="h-4 w-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="font-bold text-amber-900">
                                DNI no registrado previamente en el sistema. Requiere registrarse la matrícula primero:
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700">
                                Nombres del Estudiante <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="quickModalFirstName"
                                type="text"
                                required
                                placeholder="Ej. Carlos Raúl"
                                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-bold text-slate-700">
                                    Apellido Paterno <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="quickModalPaternalSurname"
                                    type="text"
                                    required
                                    placeholder="Ej. Gómez"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700">
                                    Apellido Materno
                                </label>
                                <input
                                    v-model="quickModalMaternalSurname"
                                    type="text"
                                    placeholder="Ej. Morales"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#2e8b57] focus:outline-none"
                                />
                            </div>
                        </div>

                        <div class="pt-1">
                            <button
                                type="button"
                                @click="openFullRegistrationFromQuickModal"
                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
                            >
                                <svg class="h-3.5 w-3.5 text-[#2e8b57]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Completar Ficha Detallada (Apoderado / Contacto)</span>
                            </button>
                        </div>
                    </div>

                    <!-- Alerta de Errores -->
                    <div
                        v-if="quickModalError"
                        class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700"
                    >
                        {{ quickModalError }}
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <button
                            type="button"
                            @click="showQuickDniModal = false"
                            class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="quickModalSubmitting || quickModalAlreadyEnrolled || quickModalDni.length < 8"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-[#2e8b57] px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-[#257347] disabled:opacity-50"
                        >
                            <svg
                                v-if="quickModalSubmitting"
                                class="h-4 w-4 animate-spin text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Confirmar e Inscribir</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
