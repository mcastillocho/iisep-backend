# Arquitectura y Hoja de Ruta - Panel Administrativo (CETPRO IISEP)

Este documento describe la estructura actual de directorios y componentes del módulo administrativo, las razones de diseño tras su desacoplamiento y la guía paso a paso de cómo evolucionará a futuro.

---

## 1. Estructura de Directorios Actual

```text
resources/js/
├── components/
│   └── Admin/
│       ├── Sidebar/
│       │   ├── Sidebar.vue                # Sidebar vertical institucional con orden por frecuencia de uso (Matrículas arriba)
│       │   └── OfertaAcademicaMenu.vue    # Menú colapsable específico para Carreras/Módulos/Talleres
│       └── Pages/
│           ├── AsistenciasView.vue        # Vista completa e independiente de Asistencias (lista priorizada y escaneo DNI)
│           ├── MatriculasView.vue         # Vista completa e independiente de Matrículas & Aperturas (2 modalidades)
│           ├── OfertaAcademicaView.vue    # Vista completa e independiente de Oferta Académica (Carreras/Módulos/Talleres)
│           └── SedesAulasView.vue         # Vista completa e independiente de Sedes y Aulas (estilo tarjetas y editor)
└── pages/
    └── Admin/
        ├── Index.vue                      # Dashboard central (/admin) modular y desacoplado
        ├── Asistencias.vue                # Página autónoma de Inertia (/admin/asistencias)
        ├── Matriculas.vue                 # Página autónoma de Inertia (/admin/matriculas)
        ├── OfertaAcademica.vue            # Página autónoma de Inertia (/admin/oferta-academica)
        └── SedesAulas.vue                 # Página autónoma de Inertia (/admin/sedes-aulas)
```

---

## 2. Decisiones de Diseño y Desacoplamiento

1. **Sidebar con Orden por Frecuencia de Uso (`components/Admin/Sidebar/Sidebar.vue`)**:
    - Centraliza la cabecera institucional oficial con los colores del IISEP (`#2e8b57`), navegación lateral y perfil del usuario.
    - **Orden de Frecuencia Operativa**:
        1. **Asistencias (Máxima frecuencia operativa diaria)**:
            - Control en vivo con indicador de sesiones activas.
        2. **Sección Matrículas** (Pestañas independientes en el sidebar):
            - **Matrículas**: Gestión de inscripciones, seguimiento y convalidaciones.
            - **Apertura de Cursos**: Programación temporal de ciclos formativos y ambientes físicos en formato lista.
        3. **Cursos (Oferta Académica)**: Con pestañas desplegables (Carreras, Módulos, Talleres).
        4. **Ambientes**: Infraestructura física (Sedes y Aulas) con baja frecuencia de cambio.
        5. **Dirección**: Panel Resumen & KPIs globales.
    - **Comportamiento Fijo e Independiente**: Posee altura fija de pantalla (`h-screen`, `md:sticky top-0`) con scroll interno autónomo (`overflow-y-auto flex-1`). Esto garantiza que al hacer scroll en la página principal, el sidebar se mantenga anclado a la izquierda y el pie con los datos del usuario permanezca siempre visible al fondo del viewport (`shrink-0`).

2. **Módulo de Matrículas y Aperturas ([MatriculasView.vue](file:///home/madgen/Documentos/Laravel/iisep-backend/resources/js/components/Admin/Pages/MatriculasView.vue))**:
    - **Pestañas Separadas e Independientes**:
        - **Pestaña 1: Matrículas (Inscripciones & Convalidaciones)**:
            - Filtros por estado formal (`activo`, `convalidado`, `completado`, `preinscrito`, `retirado`, `anulado`).
            - Búsqueda en tiempo real por DNI, estudiante o materia.
            - Modal de actualización de estado y observaciones de convalidación académica.
            - Modal de registro de nueva matrícula vinculando al alumno con el curso aperturado o taller.
        - **Pestaña 2: Apertura de Cursos (Vista en Lista / Tabla)**:
            - Presentación en **tabla estructurada / lista** para alta densidad informativa y legibilidad:
                - **Curso / Materia**: Nombre, tipo (modular / taller) y badge de estado (`En curso`, `Programado`).
                - **Sede & Aula Física**: Sede con indicador visual y ambiente asignado.
                - **Periodo de Vigencia**: Rango de fechas (`start_date` a `end_date`).
                - **Horarios & Docente**: Días de semana, rango de horas y nombre del profesor a cargo.
                - **Aforo & Vacantes**: Relación inscritos/capacidad, porcentaje y barra de progreso.
                - **Acciones**: Botón directo para matricular alumno al curso aperturado.
            - Filtros rápidos por estado de dictado (`Todos`, `En Dictado Activo`, `Programados`).
            - Modal "**+ Aperturar Nuevo Curso / Aula**" para registrar programación de fechas, sede, aula y turnos.

3. **Vista Integral de Sedes y Aulas (`components/Admin/Pages/SedesAulasView.vue`)**:
    - **Panel de Todas las Sedes (Estilo Carreras)**: Muestra cada sede como una tarjeta con su código institucional (`code`), dirección física (`address`), teléfono (`phone`), contador de ambientes y píldoras con las aulas existentes y sus vacantes.
    - **Botón "+ Nueva Sede"**: Modal con formulario para registrar una nueva sede física con los campos de la base de datos (`name`, `code`, `address`, `phone`).
    - **Editor Detallado de Sede**: Permite actualizar los datos físicos de la sede y gestionar directamente sus aulas (agregar nueva aula, editarla o eliminarla con aforo calculado en tiempo real).
    - **Modal de Disponibilidad**: Permite consultar los horarios y cursos programados (`course_schedules`) en cada ambiente.

4. **Menú Autónomo de Oferta Académica (`OfertaAcademicaMenu.vue`)**:
    - Opera como un submenú desplegable (acordeón) bajo un único título ("Oferta Académica"), similar al comportamiento de _Sedes & Aulas_.
    - Presenta las pestañas hijas: **Carreras**, **Módulos** y **Talleres** con sus contadores en tiempo real.
    - Está separado del resto de enlaces simples porque maneja su propio estado de apertura/cierre y eventos de navegación interna (`@select-curricular`).

5. **Vista Integral de Oferta Académica (`components/Admin/Pages/OfertaAcademicaView.vue`)**:
    - Encapsula toda la interactividad del catálogo educativo:
        - Buscadores específicos por pestaña.
        - **Editor de Carrera**: gestión de vacantes, asignación de sedes habilitadas, secuencia de módulos correlativos con orden cronológico (`order: 1, 2, ...`), controles de subir/bajar orden y cálculo automático de duración en semanas (de solo lectura).
    - Puede ser montado tanto dentro del dashboard `/admin` como en la página directa `/admin/oferta-academica`.

6. **Vista Integral de Asistencias & Sesiones ([AsistenciasView.vue](file:///home/madgen/Documentos/Laravel/iisep-backend/resources/js/components/Admin/Pages/AsistenciasView.vue))**:
    - **Modo Lista Priorizado Temporalmente**:
        - Las sesiones se ordenan cronológicamente con máxima prioridad para las que están **en dictado en estos momentos** (`is_currently_active: true`, badge verde animado y borde acentuado), seguidas de las programadas para hoy y el resto de la semana.
        - Filtros rápidos por estado (`En Dictado Ahora`, `Turno de Hoy`, `Todas las Sesiones`) y filtro por sede.
        - Columnas: Curso / Turno formativo, Sede & Aula física, Horario & Días, Docente a cargo, Barra de Asistencia Registrada (`X / Y presentes`, porcentaje) y botón de Tomar Lista.
    - **Módulo Administrativo de Marcación por DNI con Autoselección y Regularización**:
        - **Flujo de 3 Pasos**:
            1. **Ingreso de DNI**: Se digita el número del carnet del estudiante.
            2. **Autoselección de Curso Oficial**: El sistema autoselecciona de inmediato el curso en el que el estudiante está formalmente matriculado en ese horario.
            3. **Selector Modificable Dividido en 2 Secciones**:
                - **Sección 1: Cursos Activos en este Momento y Turnos de Hoy**.
                - **Sección 2: Fechas Anteriores** (para casos en los que por olvido no se haya actualizado la asistencia en días previos).
            4. **Validación Automática**:
                - Si se mantiene el curso autoseleccionado: Marcación directa como `Presente` o `Tardanza`.
                - Si el usuario modifica el curso (otra sede, horario o fecha anterior): Se solicita obligatoriamente el motivo de la excepción (_Recuperación de clase_, _Cambio temporal de grupo_, _Regularización por olvido en fecha anterior_, _Práctica libre_, _Autorización verbal_) y se registra en base de datos (`origin_scheduled_course_id` y `notes`).
    - **Modal de Pase de Lista Completo**:
        - Visualización de la nómina completa del curso con botones de un clic (`Presente`, `Tardanza`, `Falta`, `Justificado`), botón `Marcar Todos Presentes` y buscador interno de alumnos por DNI con registro de excepciones cruzadas.

---

## 3. Hoja de Ruta de Migración a Futuro

Cuando se proceda a conectar el backend y expandir el sistema, se recomienda seguir los siguientes pasos:

### Fase 1: Creación de un Layout Compartido (`AdminLayout.vue`)

Actualmente cada página incluye la envoltura y el `<Sidebar />`. El siguiente paso natural es crear:

```text
resources/js/layouts/
└── AdminLayout.vue
```

- **Responsabilidad**: Contendrá el `<Sidebar />`, la barra superior móvil, las notificaciones globales (toasts) y un `<slot />` para inyectar el contenido de cada página.
- **Uso en las páginas**:
    ```vue
    <script setup lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.vue';
    </script>

    <template>
        <AdminLayout title="Oferta Académica">
            <!-- Contenido específico de la página -->
        </AdminLayout>
    </template>
    ```

---

### Fase 2: Modularización Completa de Páginas de Inertia

Para evitar páginas monolíticas, cada sección del sidebar se convertirá en una página de Inertia dedicada con su propia URL limpia:

```text
resources/js/pages/Admin/
├── Index.vue                      # Redirección al módulo por defecto según rol
├── SedesAulas/                    # Ruta: /admin/sedes-aulas
│   ├── Index.vue
│   └── DisponibilidadModal.vue
├── OfertaAcademica/               # Ruta: /admin/oferta-academica
│   ├── Index.vue
│   ├── CarrerasTab.vue
│   ├── ModulosTab.vue
│   ├── TalleresTab.vue
│   └── CarreraEditor.vue
├── Matriculas/                    # Ruta: /admin/matriculas
│   └── Index.vue
├── Asistencias/                   # Ruta: /admin/asistencias
│   └── Index.vue
└── Resumen/                       # Ruta: /admin/resumen
    └── Index.vue
```

---

### Fase 3: Modularidad por Roles y Permisos (RBAC)

El sistema está pensado para adaptarse al cargo del usuario conectado:

- **Director / Coordinador General**: Acceso a _Resumen & KPIs_, _Oferta Académica_, _Sedes & Aulas_, _Matrículas_.
- **Coordinador de Sede**: Acceso limitado a su sede asignada, _Aulas_, _Sesiones & Asistencias_, consulta de _Matrículas_.
- **Secretaría Académica**: Gestión operativa de _Matrículas & Convalidaciones_.
- **Docente**: Marcación de _Asistencias_ y consulta de alumnos matriculados en sus cursos.

En el `Sidebar.vue`, cada bloque de navegación se condicionará con los permisos del usuario provistos por Inertia (`$page.props.auth.user.permissions` o directivas tipo `v-can`).

---

### Fase 4: Reemplazo de Mocks por Controladores de Laravel

Los datos actualmente simulados en frontend (`branches`, `classrooms`, `careers`, `allModulesPool`, etc.) se recibirán directamente desde controladores de Laravel:

```php
// app/Http/Controllers/Admin/OfertaAcademicaController.php
public function index(): Response
{
    return Inertia::render('Admin/OfertaAcademica/Index', [
        'branches' => Branch::select('id', 'name', 'code')->get(),
        'careers'  => Career::with(['modules'])->get(),
        'modules'  => Module::withCount('courses')->get(),
        'courses'  => Course::where('is_workshop', true)->get(),
    ]);
}
```

---

### Fase 5: Ajustes Pendientes en Base de Datos Identificados

Durante el desarrollo se detectaron los siguientes puntos que deberán implementarse en migraciones posteriores:

1. **Disponibilidad de Carreras por Sede (`branch_career`)**:
    - Actualmente la base de datos no tiene una tabla pivote entre `branches` y `careers`.
    - Se creará una migración `create_branch_career_table` (`branch_id`, `career_id`) para guardar formalmente en qué sedes físicas se apertura cada carrera técnica.
2. **Duración en Semanas**:
    - Se mantiene calculada por la sumatoria de las duraciones de los módulos vinculados en la tabla pivote `career_module` (`order`, `career_id`, `module_id`).
3. **Estado de Matrícula (`convalidado`)**:
    - Ya se encuentra integrado en el enum de PostgreSQL `enum_enrollment_status`.

---

## 4. Guía de Estilos y Paleta Institucional

- **Color Primario IISEP**: `#2e8b57` (SeaGreen).
- **Color Hover Primario**: `#257347`.
- **Fondos**:
    - Fondo de aplicación: `#f4f7f5` (Gris verdoso claro institucional).
    - Tarjetas y paneles: `#ffffff` con borde `border-slate-200` y sombra sutil `shadow-xs`.
    - Estados activos / Badge suave: `bg-emerald-50` con borde `border-emerald-200` y texto `#2e8b57`.
- **Tipografía y Legibilidad**:
    - Textos con alto contraste (`text-slate-900`, `text-slate-700`).
    - Evitar tamaños menores a `text-xs` (12px) y preferir `text-sm` (14px) en tablas y controles interactivos.
