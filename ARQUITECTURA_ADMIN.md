# Arquitectura y Hoja de Ruta - Panel Administrativo (CETPRO IISEP)

Este documento describe la estructura actual de directorios y componentes del módulo administrativo, las razones de diseño tras su desacoplamiento y la guía paso a paso de cómo evolucionará a futuro.

---

## 1. Estructura de Directorios Actual

```text
resources/js/
├── components/
│   └── Admin/
│       ├── Sidebar/
│       │   ├── Sidebar.vue                # Sidebar vertical institucional completo
│       │   └── OfertaAcademicaMenu.vue    # Menú colapsable específico para Carreras/Módulos/Talleres
│       └── Pages/
│           └── OfertaAcademicaView.vue    # Vista completa e independiente de Oferta Académica
└── pages/
    └── Admin/
        ├── Index.vue                      # Dashboard central (/admin) con Sedes, Aulas, etc.
        └── OfertaAcademica.vue            # Página autónoma de Inertia (/admin/oferta-academica)
```

---

## 2. Decisiones de Diseño y Desacoplamiento

1. **Sidebar Independiente (`components/Admin/Sidebar/Sidebar.vue`)**:
   - Centraliza la cabecera institucional oficial con los colores del IISEP (`#2e8b57`), navegación lateral por grupos lógicos, selector de ambientes por sede y perfil del usuario.
   - **Comportamiento Fijo e Independiente**: Posee altura fija de pantalla (`h-screen`, `md:sticky top-0`) con scroll interno autónomo (`overflow-y-auto flex-1`). Esto garantiza que al hacer scroll en la página principal, el sidebar se mantenga anclado a la izquierda y el pie con los datos del usuario permanezca siempre visible al fondo del viewport (`shrink-0`).
   - Es reutilizable en cualquier página del panel administrativo.

2. **Menú Autónomo de Oferta Académica (`OfertaAcademicaMenu.vue`)**:
   - Opera como un submenú desplegable (acordeón) bajo un único título ("Oferta Académica"), similar al comportamiento de *Sedes & Aulas*.
   - Presenta las pestañas hijas: **Carreras**, **Módulos** y **Talleres** con sus contadores en tiempo real.
   - Está separado del resto de enlaces simples porque maneja su propio estado de apertura/cierre y eventos de navegación interna (`@select-curricular`).

3. **Vista Integral de Oferta Académica (`components/Admin/Pages/OfertaAcademicaView.vue`)**:
   - Encapsula toda la interactividad del catálogo educativo:
     - Buscadores específicos por pestaña.
     - **Editor de Carrera**: gestión de vacantes, asignación de sedes habilitadas, secuencia de módulos correlativos con orden cronológico (`order: 1, 2, ...`), controles de subir/bajar orden y cálculo automático de duración en semanas (de solo lectura).
   - Puede ser montado tanto dentro del dashboard `/admin` como en la página directa `/admin/oferta-academica`.

---

## 3. Hoja de Ruta de Migración a Futuro

Cuando se proceda a conectar el backend y expandir el sistema, se recomienda seguir los siguientes pasos:

### Fase 1: Creación de un Layout Compartido (`AdminLayout.vue`)
Actualmente cada página incluye la envoltura y el `<Sidebar />`. El siguiente paso natural es crear:
```text
resources/js/layouts/
└── AdminLayout.vue
```
* **Responsabilidad**: Contendrá el `<Sidebar />`, la barra superior móvil, las notificaciones globales (toasts) y un `<slot />` para inyectar el contenido de cada página.
* **Uso en las páginas**:
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
* **Director / Coordinador General**: Acceso a *Resumen & KPIs*, *Oferta Académica*, *Sedes & Aulas*, *Matrículas*.
* **Coordinador de Sede**: Acceso limitado a su sede asignada, *Aulas*, *Sesiones & Asistencias*, consulta de *Matrículas*.
* **Secretaría Académica**: Gestión operativa de *Matrículas & Convalidaciones*.
* **Docente**: Marcación de *Asistencias* y consulta de alumnos matriculados en sus cursos.

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

* **Color Primario IISEP**: `#2e8b57` (SeaGreen).
* **Color Hover Primario**: `#257347`.
* **Fondos**:
  * Fondo de aplicación: `#f4f7f5` (Gris verdoso claro institucional).
  * Tarjetas y paneles: `#ffffff` con borde `border-slate-200` y sombra sutil `shadow-xs`.
  * Estados activos / Badge suave: `bg-emerald-50` con borde `border-emerald-200` y texto `#2e8b57`.
* **Tipografía y Legibilidad**:
  * Textos con alto contraste (`text-slate-900`, `text-slate-700`).
  * Evitar tamaños menores a `text-xs` (12px) y preferir `text-sm` (14px) en tablas y controles interactivos.
