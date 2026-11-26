# Árbol Reducido de Endpoints — CETPRO IISEP

> **Leyenda:** 🟢 OK | 🔴 **CRÍTICO SEGURIDAD** | 🟠 **ROTO / RIESGO BD / ERROR 500** | 🟡 **INCONSISTENCIA / PARÁMETRO FALTANTE** | 🟣 **BOTÓN SIN ENDPOINT / MOCK** | 🔵 **RUTA PENDIENTE**

---

```text
🌐 CETPRO IISEP (routes/web.php + bootstrap/app.php)
│
├── 📁 / (Público y Salud)
│   ├── GET  /up                                     🟢 [Público] Healthcheck nativo de Laravel (Docker/Nginx).
│   └── GET  /                                       🟡 [Público] Landing pública (Welcome.vue).
│       └── ⚠️ Datos de carreras/talleres/sedes hardcodeados; muestra link "/admin" a usuarios no-staff logueados.
│
├── 📁 /login & /logout (Autenticación — AuthenticatedSessionController)
│   ├── GET  /login                                  🔴 [guest] Formulario de inicio de sesión (Auth/Login.vue).
│   │   └── ⚠️ Expone credenciales demo reales en pantalla (admin/admin123, storres/admin123).
│   ├── POST /login                                  🔴 [guest] Inicia sesión por email o username. SIN throttle/rate-limit.
│   │   ├── login            (body, string, req)     — Usuario (username) o correo electrónico.
│   │   ├── password         (body, string, req)     — Contraseña (alumnos/apoderados nacen con su DNI).
│   │   ├── remember         (body, bool, opc)       — Mantener sesión abierta.
│   │   └── ↳ Responde: 302 → /admin (si isStaff) | / (si Docente/Alumno/Apoderado).
│   └── POST /logout                                 🟢 [auth] Cierra sesión, invalida cookie y regenera token CSRF → /.
│
├── 📁 /admin (Dashboard Central — BranchController@dashboard)
│   └── GET  /admin                                  🔴🟠 [auth — FALTA isStaff()] Renderiza Admin/Index.vue con toda la BD.
│       ├── ↳ Recibe props: branches, classrooms, careers, allModulesPool, coursesList, teachers, scheduledCourses, enrollments, students.
│       ├── 🔴 Alumnos/Docentes entran con visibilidad GLOBAL (al ser staff=null se salta filtro por sede).
│       ├── 🟠 array_merge() sobreescribe careers (borra modules y available_branch_ids).
│       └── 🟣 Botón Sidebar "Panel Resumen & KPIs" lleva aquí, pero siempre abre pestaña Sedes & Aulas (falta ruta /admin/resumen).
│
├── 📁 /admin/sedes (Sedes — BranchController)
│   ├── GET  /admin/sedes                            🟢 [auth + Staff (Admin ve resumen; personal de sede redirige a /admin/sedes/{branch})] Resumen liviano de sedes.
│   │   └── ↳ Recibe props: branches[{id,name,code,address,phone,classrooms_count,total_capacity,classrooms[{id,branch_id,name,capacity}]}], inactiveBranches[{id,name,code,address,phone,deleted_at,restorable_classrooms_count}], selectedBranch:null, classrooms:[], initialEditing:false.
│   ├── GET  /admin/sedes/{branch}                   🟢 [auth + Admin o Staff de esa sede] Detalle de sede y gestión de sus aulas.
│   │   ├── {branch}         (path, int, req)        — ID de la sede (whereNumber).
│   │   ├── ?edit            (query, bool, opc)      — Si es true/1 abre el formulario de edición de la sede al cargar.
│   │   └── ↳ Recibe props: branches[{id,name,code,address,phone}], selectedBranch, classrooms[{id,branch_id,branch_name,name,capacity,schedules_count}], initialEditing.
│   ├── POST /admin/sedes                            🟢 [auth + Admin (branch_id=null)] Registra nueva sede física → redirige a /admin/sedes.
│   │   ├── name             (body, string, req)     — Nombre único entre sedes activas (max:255; índice único parcial WHERE deleted_at IS NULL).
│   │   ├── code             (body, string, opc)     — Código único entre sedes activas (max:50); si va vacío se autogenera de 3 letras.
│   │   ├── address          (body, string, opc)     — Dirección física (max:255).
│   │   └── phone            (body, string, opc)     — Teléfono de contacto (max:50).
│   ├── PUT  /admin/sedes/{branch}                   🟢 [auth + Admin o Coord de esa sede] Actualiza datos de una sede.
│   │   ├── {branch}         (path, int, req)        — ID de la sede a modificar (whereNumber).
│   │   ├── name             (body, string, Admin)   — Solo Admin puede cambiarlo (único entre activas; bloqueado en UI para Coord).
│   │   ├── code             (body, string, Admin)   — Solo Admin puede cambiarlo (único entre activas; bloqueado en UI para Coord).
│   │   ├── address          (body, string, opc)     — Dirección física (editable por Admin y Coord).
│   │   └── phone            (body, string, opc)     — Teléfono (editable por Admin y Coord).
│   ├── DELETE /admin/sedes/{branch}                 🟢 [auth + Admin (branch_id=null)] Da de baja lógica una sede (is_active=false + SoftDelete) y sus aulas activas en cascada lógica (deleted_with_branch=true).
│   │   ├── {branch}         (path, int, req)        — ID de la sede (whereNumber).
│   │   └── ↳ Bloqueos: última sede activa, personal staff asignado, alumnos en carrera (activo/en_reserva), cursos vigentes/programados (end_date ≥ hoy), matrículas en curso (activo/preinscrito). Redirige a /admin/sedes.
│   └── PATCH  /admin/sedes/{branch}/reactivar       🟢 [auth + Admin (branch_id=null)] Reactiva una sede dada de baja (withTrashed) y restaura únicamente las aulas borradas junto con ella (deleted_with_branch=true).
│       ├── {branch}         (path, int, req)        — ID de la sede dada de baja (whereNumber + withTrashed).
│       └── ↳ Validaciones: bloquea si ya está activa o si otra sede activa ya ocupa su mismo name o code. Redirige a /admin/sedes.
│
├── 📁 /admin/aulas (Aulas / Ambientes Físicos — ClassroomController)
│   ├── GET  /admin/aulas/{classroom}/horarios       🟢 [auth + Admin o Staff de esa sede] Consulta JSON bajo demanda de horarios del aula (modal "Ver Disponibilidad").
│   │   ├── {classroom}      (path, int, req)        — ID del aula (whereNumber). Ignora cursos programados eliminados.
│   │   └── ↳ Recibe JSON: { classroom_id, classroom_name, schedules[{id,course_name,teacher_name,day_of_week,start_time,end_time,start_date,end_date,status}] }.
│   ├── POST /admin/aulas                            🟢 [auth + Admin o Coord de esa sede] Crea nueva aula.
│   │   ├── name             (body, string, req)     — Nombre único por sede entre aulas activas (max:255; índice único parcial WHERE deleted_at IS NULL).
│   │   ├── branch_id        (body, int, req)        — ID de sede activa (exists whereNull deleted_at & is_active=true; forzado a sede del Coord).
│   │   └── capacity         (body, int, req)        — Aforo/vacantes (1..500).
│   ├── PUT  /admin/aulas/{classroom}                🟢 [auth + Admin / Coord sede / Secre sede] Edita un aula.
│   │   ├── {classroom}      (path, int, req)        — ID del aula (whereNumber).
│   │   ├── name             (body, string, Adm/Crd) — Nombre único por sede destino entre aulas activas (ignorado para Secre).
│   │   ├── capacity         (body, int, req)        — Aforo (1..500). Único campo editable por Secretaría.
│   │   └── branch_id        (body, int, solo Admin) — Transferir aula a otra sede activa (bloqueado si tiene horarios asociados).
│   └── DELETE /admin/aulas/{classroom}              🟢 [auth + Admin o Coord de esa sede] SoftDelete de aula.
│       └── {classroom}      (path, int, req)        — ID del aula (bloquea si existen courseSchedules; permite reutilizar nombre gracias al índice único parcial).
│
├── 📁 /admin/oferta-academica (Catálogo Curricular — AcademicOfferController)
│   ├── GET  /admin/oferta-academica                 🔴 [auth — FALTA isStaff()] Carga branches, careers, allModulesPool y coursesList.
│   │
│   ├── 📁 /admin/carreras (CareerController)
│   │   ├── POST /admin/carreras                     🟡 [auth + Admin o Coord (global)] Crea carrera técnica + pivotes.
│   │   │   ├── name                 (body, string, req)     — Nombre de la carrera (max:255, sin unique).
│   │   │   ├── description          (body, string, opc)     — Descripción (max:1000). 🟣 FALTA este campo en el formulario Vue!
│   │   │   ├── duration_in_weeks    (body, int, opc)        — Semanas totales (1..200; UI manda 48 si hay 0 módulos).
│   │   │   ├── modules[]            (body, array, opc)      — Lista de objetos { id (exists:modules), order (int) } → tabla career_module.
│   │   │   └── available_branch_ids (body, int[], opc)      — IDs de sedes habilitadas (también acepta alias branch_ids) → tabla branch_career.
│   │   ├── PUT  /admin/carreras/{career}            🟡 [auth + Admin o Coord (global)] Actualiza carrera y hace sync() de módulos/sedes.
│   │   │   ├── {career}             (path, int, req)        — ID de la carrera + mismos campos del body que POST.
│   │   │   └── 🔴 Un Coord de una sede puede alterar/quitar sedes de otra sede en una carrera global (doc dice Nivel 4).
│   │   └── DELETE /admin/carreras/{career}          🟡 [auth + Admin o Coord (global)] SoftDelete de carrera.
│   │       └── {career}             (path, int, req)        — Bloquea solo si hay student_careers (pero hoy nunca se crean student_careers).
│   │
│   ├── 📁 /admin/modulos (ModuleController)
│   │   ├── POST /admin/modulos                      🟡 [auth + Admin o Coord (global)] Crea módulo y vincula cursos.
│   │   │   ├── name                 (body, string, req)     — Nombre del módulo (max:255).
│   │   │   ├── description          (body, string, opc)     — Descripción/competencias (max:1000).
│   │   │   ├── duration_in_weeks    (body, int, req)        — Duración en semanas (1..52).
│   │   │   └── course_ids[]         (body, int[], opc)      — IDs de cursos (exists:courses). 🟠 Si el curso era de otro módulo, se lo roba sin aviso!
│   │   ├── PUT  /admin/modulos/{module}             🟡 [auth + Admin o Coord (global)] Actualiza módulo y sincroniza course_ids[].
│   │   │   ├── {module}             (path, int, req)        — ID del módulo + mismos campos del body que POST.
│   │   │   └── 🟡 No recalcula careers.duration_in_weeks; cursos desasignados quedan con module_id=null.
│   │   └── DELETE /admin/modulos/{module}           🟢 [auth + Admin o Coord] SoftDelete (bloquea si tiene courses, careers o enrollments).
│   │       └── {module}             (path, int, req)        — ID del módulo.
│   │
│   └── 📁 /admin/talleres (WorkshopController — gestiona tabla courses completa)
│       ├── POST /admin/talleres                     🟡 [auth + Admin o Coord (global)] Crea curso modular o taller libre.
│       │   ├── name                 (body, string, req)     — Nombre del curso o taller (max:255).
│       │   ├── description          (body, string, opc)     — Temario/descripción (max:1000).
│       │   ├── duration_in_weeks    (body, int, req)        — Duración en semanas (1..52).
│       │   ├── module_id            (body, int|null, opc)   — ID del módulo al que pertenece (exists:modules).
│       │   └── is_workshop          (body, bool, opc)       — true=Taller libre / false=Curso. 🟡 Permite is_workshop=true + module_id a la vez.
│       ├── PUT  /admin/talleres/{course}            🟢 [auth + Admin o Coord] Actualiza curso/taller ({course} + mismo body).
│       └── DELETE /admin/talleres/{course}          🟢 [auth + Admin o Coord] SoftDelete (bloquea si tiene scheduledCourses).
│
├── 📁 /admin/cursos-aperturados (Aperturas y Horarios — ScheduledCourseController)
│   ├── POST /admin/cursos-aperturados               🟠 [auth + Admin o Coord de esa sede] Crea apertura (ScheduledCourse + CourseSchedule).
│   │   ├── course_id        (body, int, req)        — ID del curso/taller del catálogo (exists:courses).
│   │   ├── branch_id        (body, int, req)        — ID de la sede (exists:branches; no valida branch_career).
│   │   ├── classroom_id     (body, int, req)        — ID del aula. 🟠 NO valida que pertenezca a branch_id ni choques de horario!
│   │   ├── teacher_id       (body, int|null, opc)   — ID del docente (exists:teachers). 🟠 NO valida choque de horario del docente!
│   │   ├── start_date       (body, date, req)       — Fecha de inicio del periodo (YYYY-MM-DD).
│   │   ├── end_date         (body, date, opc)       — 🟡 Se valida pero SE IGNORA siempre (se calcula con start_date + duration_in_weeks).
│   │   ├── days[]           (body, enum[], req)     — Exactamente 1 día (DayOfWeek, size:1). 🟡 Contradice cursos de 2 días del Seeder.
│   │   ├── start_time       (body, string, req)     — Hora inicio (HH:MM). 🟠 Sin date_format:H:i → texto inválido da ERROR 500 en Postgres!
│   │   └── end_time         (body, string, req)     — Hora fin (debe ser > start_time). Sin DB::transaction().
│   ├── PUT  /admin/cursos-aperturados/{scheduledCourse}  🟠 [auth + Admin o Coord sede] Actualiza apertura y recrea horarios.
│   │   ├── {scheduledCourse}(path, int, req)        — ID de la apertura + mismos campos del body que POST.
│   │   ├── 🟠 Ejecuta schedules()->forceDelete() sin DB::transaction().
│   │   ├── 🟠 UI recorta days a 1 solo día: editar cursos del seeder (2 días) BORRA permanentemente el 2° día!
│   │   └── 🟣 UI: Botón "Ingresar" bloquea a Secretaría impidiéndole ver el padrón o matricular desde ahí; modal showQuickDniModal es código muerto.
│   └── DELETE /admin/cursos-aperturados/{scheduledCourse} 🟡 [auth + Admin o Coord sede] SoftDelete (bloquea si tiene enrollments; no revisa attendances).
│
├── 📁 /admin/matriculas (Matrículas e Inscripciones — ScheduledCourseController)
│   ├── GET  /admin/matriculas                       🔴🟠 [auth — FALTA isStaff()] Vista principal de Alumnos, Matrículas y Aperturas.
│   │   ├── GET /admin/alumnos                       — Alias idéntico a /admin/matriculas (sin uso en Sidebar).
│   │   ├── ?tab             (query, string, opc)    — 'aperturas' abre AperturasView; cualquier otro valor abre MatriculasView.
│   │   ├── 🔴 FUGA MULTI-SEDE: devuelve TODOS los students y guardians de todas las sedes sin filtrar por sede en backend!
│   │   └── 🟠 N+1 query en classrooms->branch, sin paginación, y MatriculasView.vue:184 trata a usuarios sin staff como isAdmin=true.
│   ├── POST /admin/matriculas                       🔴🟠 [auth + Admin / Coord sede / Secre sede] Matricula alumno (existente o nuevo) en apertura.
│   │   ├── scheduled_course_id  (body, int, req)    — ID de apertura (findOrFail corre antes de validate → da 404 en vez de 422).
│   │   ├── student_document     (body, str, req)    — Documento del alumno (max:20; sin regex DNI; siempre fuerza DocumentType::DNI).
│   │   ├── first_name           (body, str, cond)   — Nombres (obligatorio si alumno es nuevo y no se manda student_full_name).
│   │   ├── paternal_surname     (body, str, cond)   — Apellido paterno (obligatorio si alumno nuevo).
│   │   ├── maternal_surname     (body, str, opc)    — Apellido materno.
│   │   ├── student_full_name    (body, str, cond)   — Alternativa en un campo ("Apellidos, Nombres" o "Nombres Apellidos").
│   │   ├── birth_date/phone/email (body, opc)       — Contacto del alumno (🟡 faltan en el Asistente de Matrícula de MatriculasView).
│   │   ├── has_guardian         (body, bool, opc)   — Indica si se registra/vincula apoderado.
│   │   ├── guardian_document    (body, str, opc)    — 🔴 Si coincide con cualquier User (Admin/Docente), SOBREESCRIBE sus datos y lo hace Student!
│   │   ├── guardian_first_name / paternal / maternal / phone / email (body, opc) — Datos del apoderado.
│   │   ├── enrollment_date      (body, date, opc)   — Fecha de matrícula (default: hoy Lima).
│   │   ├── status               (body, enum, opc)   — EnrollmentStatus (default: activo). 🟡 Secre puede crear directo como 'convalidado'.
│   │   ├── notes                (body, str, opc)    — 🟠 SE DESCARTA SILENCIOSAMENTE: la tabla enrollments NO tiene columna notes!
│   │   ├── 🔵 student_career_id (FALTANTE)          — Existe FK en BD pero jamás se envía ni vincula a student_careers.
│   │   ├── 🔵 document_type     (FALTANTE)          — Existe enum (dni/ce/pasaporte/ruc) pero siempre se graba 'dni'.
│   │   └── 🟠 Bugs BD: SIN check de aforo del aula; SIN DB::transaction; re-matricular alumno 'retirado'/'anulado' lanza ERROR 500 SQL!
│   ├── PUT  /admin/matriculas/{enrollment}/status   🔴🟠 [auth + Admin / Coord sede / Secre sede] Cambia estado de matrícula.
│   │   ├── {enrollment}         (path, int, req)    — ID de la matrícula.
│   │   ├── status               (body, enum, req)   — preinscrito | activo | completado | convalidado | retirado | anulado.
│   │   ├── notes                (body, str, opc)    — 🟠 VALIDADO PERO IGNORADO (columna inexistente en BD; se pierde al recargar).
│   │   └── 🔴 Bypass de sede si scheduledCourse fue soft-deleted ($branchId=null → isCoordinador(null)=true). Secre puede convalidar/anular.
│   └── DELETE /admin/matriculas/{enrollment}        🔴🟡 [auth + Admin / Coord sede / Secre sede] SoftDelete de matrícula.
│       └── {enrollment}         (path, int, req)    — Mismo bypass por $branchId=null; no verifica attendances; doc reserva anulación a Nivel 4.
│
├── 📁 /admin/alumnos & /admin/estudiantes/buscar-dni (Alumnos — ScheduledCourseController)
│   ├── POST /admin/alumnos                          🔴🟠 [auth + SOLO if(!$staff) — SIN check de cargo!] Registra alumno y apoderado.
│   │   ├── first_name           (body, str, req)    — Nombres del alumno (max:100).
│   │   ├── paternal_surname     (body, str, req)    — Apellido paterno (max:100).
│   │   ├── maternal_surname     (body, str, opc)    — Apellido materno (max:100).
│   │   ├── document             (body, str, req)    — DNI exacto de 8 dígitos (regex /^[0-9]{8}$/). No soporta CE/Pasaporte ni withTrashed (500).
│   │   ├── birth_date / phone / email (body, opc)   — Fecha nacimiento, teléfono (max:30), correo (email).
│   │   ├── has_guardian         (body, bool, opc)   — Activa bloque de apoderado.
│   │   ├── guardian_document    (body, str, opc)    — DNI 8 dígitos. 🔴 Sobreescribe datos de cualquier User existente con ese DNI!
│   │   ├── guardian_first_name / paternal / maternal / phone / email (body, opc) — Datos del apoderado.
│   │   ├── 🔵 document_type / branch_id (FALTANTES) — Siempre fija DNI; alumno queda sin sede formal en BD.
│   │   └── 🟠 SIN DB::transaction: crea User ANTES de validar apoderado → si falla apoderado deja User huérfano y bloquea el DNI!
│   ├── PUT  /admin/alumnos/{student}                🔴🟠 [auth + SOLO if(!$staff) — IDOR sin sede ni cargo!] Edita alumno y apoderado.
│   │   ├── {student}            (path, int, req)    — ID del registro Student + mismos campos del body que POST /admin/alumnos.
│   │   └── 🟠 Actualiza User antes de validar apoderado (sin transacción); cambiar DNI no actualiza username; sobreescribe User por guardian_document.
│   ├── GET  /admin/estudiantes/buscar-dni           🔴🟠 [auth — 0% VALIDACIÓN DE STAFF!] Búsqueda JSON rápida por DNI.
│   │   ├── ?document            (query, str, req)   — DNI a buscar (len ≥ 6). Sin rate-limit → fuga masiva de PII de todo el instituto!
│   │   ├── ?scheduled_course_id (query, int, opc)   — Verifica si ya está matriculado en esa apertura.
│   │   ├── ↳ Recibe JSON: { found, document, full_name, first_name, paternal_surname, maternal_surname, birth_date, phone, email, guardian:{...}|null, already_enrolled, enrolled_course_ids[] }.
│   │   └── 🟠 MUTA LA BD EN UN GET: ejecuta Student::firstOrCreate() → buscar el DNI del Director o Docente lo convierte en Alumno!
│   ├── 🔵 GET    /admin/alumnos/{student}           [PENDIENTE] Ficha individual (evitar cargar toda la BD en /admin/matriculas).
│   └── 🔵 DELETE /admin/alumnos/{student}           [PENDIENTE] Baja lógica de alumno.
│
└── 📁 /admin/asistencias (Control de Asistencia — 🟣 100% MOCK / SIN CONTROLADOR)
    ├── GET  /admin/asistencias                      🔴🟣 [auth — Route::inertia] Renderiza Admin/Asistencias.vue sin backend.
    │   ├── 🟣 Botón "Buscar Alumno" (DNI)            — Busca en array falso en memoria (sin endpoint).
    │   ├── 🟣 Botones "Presente / Tardanza / Autorizar Excepción" — Mutan array local, se pierde al recargar.
    │   ├── 🟣 Botón "Tomar Lista" + "Marcar Todos" + "Guardar Registro" — Sin endpoint.
    │   ├── 🟠 Link Sidebar "Apertura de Cursos" desde esta vista va a /admin/matriculas (falta ?tab=aperturas).
    │   └── 🟠 Desfase BD: tabla attendances solo tiene attended:boolean (faltan status de 4 valores y registered_at).
    ├── 🔵 GET  /admin/asistencias/buscar-dni        [PENDIENTE] Buscar alumno + sesiones activas/horarios del día.
    ├── 🔵 POST /admin/asistencias                   [PENDIENTE] Registrar asistencia individual / tardanza / cruzada (origin_scheduled_course_id) / fecha pasada.
    ├── 🔵 POST /admin/asistencias/nomina            [PENDIENTE] Guardar pase de lista completo en lote por sesión y fecha.
    └── 🔵 PUT  /admin/asistencias/{attendance}      [PENDIENTE] Editar / regularizar asistencia registrada.
```

---

## 📁 Módulos y Rutas Pendientes (Tablas ya existentes en BD sin CRUD)

```text
🚧 ENDPOINTS FALTANTES DETECTADOS EN ESQUEMA DE BD Y ROLES_Y_PERMISOS.md
│
├── 📁 /admin/docentes          (Tabla `teachers` — hoy solo existen los 5 del seeder)
│   ├── 🔵 GET    /admin/docentes                    [Coord / Admin] Listar docentes y especialidades.
│   ├── 🔵 POST   /admin/docentes                    [Coord / Admin] Registrar docente (User + Teacher).
│   ├── 🔵 PUT    /admin/docentes/{teacher}          [Coord / Admin] Editar datos/especialidad.
│   └── 🔵 DELETE /admin/docentes/{teacher}          [Admin] Baja lógica (si no tiene cursos activos).
│
├── 📁 /admin/staff             (Tabla `staff` — requerido para Nivel 4 Dirección General)
│   ├── 🔵 GET    /admin/staff                       [Admin] Listar personal, cargos y sedes.
│   ├── 🔵 POST   /admin/staff                       [Admin] Alta de Coordinación / Secretaría / Recepción.
│   ├── 🔵 PUT    /admin/staff/{staff}               [Admin] Cambiar cargo (position) o sede (branch_id).
│   └── 🔵 DELETE /admin/staff/{staff}               [Admin] Desactivar / dar de baja personal.
│
├── 📁 /admin/student-careers   (Tabla `student_careers` — admisión y egreso/titulación)
│   ├── 🔵 POST   /admin/alumnos/{student}/carreras  [Secre / Coord / Admin] Admitir alumno a una carrera y sede.
│   ├── 🔵 PUT    /admin/student-careers/{id}        [Coord / Admin] Cambiar estado (activo/egresado/titulado/en_reserva/retirado).
│   └── 🔵 DELETE /admin/student-careers/{id}        [Admin] Anular admisión a carrera.
│
├── 📁 /admin/resumen           (Panel KPIs reales — desacoplar de /admin)
│   └── 🔵 GET    /admin/resumen                     [Coord / Admin] Métricas reales por sede (matrículas, asistencia, aforo).
│
└── 📁 /perfil, /docente/* y /estudiante/* (Seguridad de cuentas y portales por rol)
    ├── 🔵 PUT    /perfil/password                   [auth] Cambio de contraseña (crítico: hoy alumnos nacen con password=DNI).
    ├── 🔵 GET    /docente/*                         [Docente] Mis cursos dictados y toma de asistencia propia.
    └── 🔵 GET    /estudiante/*                      [Estudiante/Apoderado] Consulta de horario, matrícula y asistencias.
```
