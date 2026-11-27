# API que necesita el frontend Flutter (propuesta para el backend)

El backend `iisep-backend` es Laravel + Inertia: responde HTML/props de Vue con
sesión por cookie. No tiene rutas `/api` ni Sanctum, así que una app Flutter
(móvil o web) no puede consumirlo tal cual. Este documento lista lo mínimo que
hace falta. Los controladores y validaciones **ya existen**; solo falta
exponerlos como JSON.

## 1. Base

- `routes/api.php` con prefijo `/api/v1`, middleware `auth:sanctum`.
- Laravel Sanctum con **tokens personales** (sirve igual para app y web).
- CORS (`config/cors.php`) para el origen donde se publique el Flutter web.
- Errores en JSON: 401 sin sesión, 403 sin permiso, 422 con `{message, errors}`.
- Paginación (`?page=`) en listas grandes (alumnos, matrículas).

## 2. Autenticación

| Método | Ruta | Cuerpo / respuesta |
|---|---|---|
| POST | `/api/v1/login` | `{login, password}` → `{token, user:{id, full_name, username, position, branch_id, branch_name}}` |
| POST | `/api/v1/logout` | revoca el token |
| GET | `/api/v1/me` | usuario actual |

Pedir **rate-limit** en login (hoy no tiene). Responder `403` si el usuario no es staff.

## 3. Lectura (lo que hoy van como props de Inertia)

| Ruta | Datos (campos usados por el frontend) |
|---|---|
| GET `/branches` | `id, name, code, address, phone, classrooms[{id, branch_id, name, capacity}]` |
| GET `/careers` | `id, name, description, duration_in_weeks, modules[{id,name,order}], branches[{id,name}]` |
| GET `/modules` | `id, name, description, duration_in_weeks, courses[{id,name}]` |
| GET `/courses` | `id, name, description, duration_in_weeks, is_workshop, module{id,name}\|null` |
| GET `/scheduled-courses` | `id, course{id,name,is_workshop}, branch, classroom, teacher\|null, start_date, end_date, days[], start_time, end_time, capacity, enrolled_count` |
| GET `/enrollments` | `id, student{full_name, document}, course_name, branch_name, status, enrollment_date` (+ filtro `?status=&q=`) |

El **alcance por sede** debe aplicarse en el servidor: personal con
`branch_id` solo recibe datos de su sede (hoy `/admin/matriculas` devuelve todo).

## 4. Escritura (ya existen como POST/PUT/DELETE de Inertia)

Mismos cuerpos que hoy, ver `ARBOL_ENDPOINTS.md`: sedes, aulas, carreras, módulos,
talleres, aperturas, alumnos, matrículas y cambio de estado. Solo cambia que
respondan JSON (`201`/`200` + recurso, o `422`) en lugar de redirecciones.

## 5. Pendientes que bloquean pantallas

- **Asistencias**: no existe controlador. Hacen falta `GET buscar-dni`,
  `POST /attendances`, `POST /attendances/roster`, `PUT /attendances/{id}`.
- **Resumen/KPIs**: `GET /summary` (matrículas activas, cursos en dictado, ocupación).
- `GET /students/search?document=` no debe **crear** alumnos (hoy hace `firstOrCreate`)
  ni estar abierto a cualquier usuario autenticado.

## 6. Cómo se conecta en el frontend

Todo pasa por `lib/data/repositories.dart`. Se escribe `ApiRepository`
(con `dio` o `http`) que implemente `AuthRepository` y `AcademicRepository`, y
en `lib/data/providers.dart` se cambia `MockRepository()` por `ApiRepository()`.
Las pantallas no se modifican.

## 7. Matrículas: lo que el frontend ya hace y el backend debería respetar

Contrastado con `ScheduledCourseController` (rama `main`, 2026-11-25).

| Tema | Backend hoy | Lo que hace el frontend (y conviene en el servidor) |
|---|---|---|
| Quién cambia estados | Secretaría, Coordinación y Dirección pueden poner cualquier estado | Según `ROLES_Y_PERMISOS.md`: Secretaría → preinscrito/activo/completado; Coordinación → + retirado/convalidado; Dirección → + anulado. Aplicar lo mismo en el servidor |
| Borrar matrícula | Las tres jerarquías | Solo Dirección (el resto usa «Retirado») |
| Aforo | No se comprueba | Se bloquea si no hay vacantes (ocupan `preinscrito` y `activo`). Validar en el servidor |
| Re-matricular a un alumno `retirado`/`anulado` | Error 500 por índice único | Se muestra «cambia su estado desde la lista». Mejor: permitirlo reactivando la fila |
| Campo `notes` | Se valida y se descarta (no hay columna) | La pantalla no lo ofrece. Crear la columna o quitar el campo |
| `GET /estudiantes/buscar-dni` | Crea al alumno si no existe (`firstOrCreate`) y cualquier usuario autenticado puede llamarlo | Debe ser solo lectura y exigir cargo de staff |
| Apoderado con DNI de otro usuario (docente/admin) | Sobrescribe sus datos | Rechazar si el DNI pertenece a un usuario que no sea apoderado |
| Respuesta de `GET /enrollments` | — | Incluir `scheduled_course_id` (el frontend lo usa para el aforo y los duplicados) |

Al matricular a un alumno ya registrado, el servidor solo completa teléfono, correo y
fecha de nacimiento si llegan con valor (no borra); el frontend se comporta igual.

## 8. Aperturas de cursos

Contrastado con `ScheduledCourseController::store/update/destroy` (main, commit 547eac9 «Patch /cursos-aperturados», 2026-11-26).
El parche del otro desarrollador ya cubre lo que antes hacía solo el frontend: aula ∈ sede, choque de aula y
de docente (en todas las sedes), formato de hora estricto (`HH:MM[:SS]`), de 1 a 7 días distintos por
semana, transacciones al crear/editar/eliminar, y al editar con matrículas no deja cambiar curso/sede ni
pasar a un aula con menos aforo. No se elimina con matrículas, asistencias ni asistencias de origen.
Fecha de fin = inicio + semanas del curso (se ignora la enviada).

Lo que el frontend sigue haciendo por su cuenta (para que la demo se comporte igual):

| Regla | Backend (547eac9) | Frontend |
|---|---|---|
| Choque de aula/docente | Sí | Mismo criterio: basta un día en común y solape de fechas y horas |
| Varios días por apertura | Sí (1–7) | Selector de días con chips |
| Eliminar con asistencias | Bloquea | Sin asistencias en la demo (no aplica) |

Endpoints que necesita el frontend: `GET /teachers` (id, nombre, especialidad) y `GET /courses`
completo (hoy solo van como props de Inertia).

## 9. Oferta académica (carreras, módulos, cursos y talleres)

Contrastado con `CareerController`, `ModuleController` y `WorkshopController` (main, 2026-11-25).
Todo es exclusivo de la Dirección General y el frontend lo respeta.

Ya valida el backend (el frontend lo replica): nombre único; duración de carrera 1–200
(vacía = suma de módulos); módulos de una carrera sin repetir y con orden; módulo con duración
1–52; solo cursos modulares libres dentro de un módulo; un taller libre queda sin módulo; no se
cambia módulo ni tipo con aperturas vigentes o matrículas en curso; no se elimina un curso con
aperturas ni un módulo con cursos, carreras o matrículas.

Para el frontend faltan datos que hoy solo viajan como props de Inertia:

| Ruta | Campos que usa el frontend |
|---|---|
| `GET /careers` | `id, name, description, duration_in_weeks, modules[{id, order}], branch_ids[]` (ids, no solo nombres) |
| `GET /modules` | `id, name, description, duration_in_weeks, course_ids[]` |
| `GET /courses` | `id, name, description, duration_in_weeks, is_workshop, module_id` |

Detalles a corregir en el servidor:
- `description` de carrera no está en el formulario Vue (ya lo marcó su `ARBOL_ENDPOINTS.md`); aquí sí.
- Al editar un módulo no se recalcula `careers.duration_in_weeks`.
- Eliminar una carrera debería indicar claramente que los módulos se conservan (el frontend lo avisa).
