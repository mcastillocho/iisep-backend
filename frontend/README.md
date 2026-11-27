# IISEP App (Flutter)

Frontend del panel administrativo de CETPRO IISEP. Una sola base de código para
**web** y **Android**. Estilo: verde institucional `#2e8b57`, sobrio, inspirado en
[styles.refero.design](https://styles.refero.design/).

Vive en la carpeta `frontend/` del repo del backend
(<https://github.com/mcastillocho/iisep-backend>), que aún no expone API JSON
(ver `docs/API_NECESARIA.md`). Por ahora la app corre con **datos de demostración**
(`MockRepository`); toda la interfaz depende solo de `lib/data/repositories.dart`, así que
conectar el backend real es cambiar `MockRepository` por un `ApiRepository` en
`lib/data/providers.dart`.

Demo en línea: <https://jhon719.github.io/iisep-app/>

## Ejecutar

Requiere Flutter 3.50 o superior (Dart `^3.14`).

```bash
cd frontend
flutter pub get
flutter analyze
flutter test                 # reglas de sedes, aulas, matrículas, aperturas, oferta y tema
flutter run -d chrome        # web
flutter run                  # Android (con emulador o teléfono)
flutter build web --release  # sale en build/web
```

Usuarios de demostración (contraseña `admin123`): `admin` (dirección, todas las
sedes), `storres` (coordinación Comas), `mcastro` (secretaría Comas),
`lflores` (coordinación Puente Piedra).

## Estructura

```
lib/
  core/      tema (tokens) y rutas con permisos por cargo
  data/      modelos, contratos (repositories.dart) y datos de demostración
  features/  auth, shell (menú), sedes, oferta, matrículas, aperturas, asistencias, resumen
  shared/    widgets comunes
```

## Estado

| Pantalla | Estado |
|---|---|
| Login, menú por cargo, alcance por sede | listo (demo) |
| Sedes y aulas: crear, editar, dar de baja y reactivar sedes; crear, editar, transferir y eliminar aulas; ver disponibilidad | listo (demo), con las reglas del backend |
| Matrículas: nueva matrícula (con búsqueda de alumno por DNI y apoderado), cambiar estado, eliminar; botón Matricular en cada apertura | listo (demo), con reglas de cupos y cargos |
| Aperturas: aperturar, editar y eliminar curso (aula por sede, docente, de 1 a 7 días por semana, fin calculado, choques de aula y docente) | listo (demo), alineado con el commit 547eac9 del backend |
| Oferta académica: crear, editar y eliminar carreras (módulos en orden, sedes), módulos (cursos) y cursos/talleres; solo Dirección General | listo (demo) |
| Resumen | solo lectura |
| Asistencias | pendiente: el backend aún no tiene controlador |
| Alumnos (ficha, edición) | pendiente del backend |

Nota: Flutter 3.50 movió Material a `material_ui`; por eso se importa
`package:material_ui/material_ui.dart`.

## Créditos de imágenes

- `assets/images/logo_iisep.png`: logo oficial del IISEP (foto de perfil de su página de Facebook). Pendiente reemplazar por el original en alta calidad.
- `assets/images/login_fondo.jpg`: foto de Mikhail Nilov en Pexels (licencia Pexels, uso libre, sin atribución obligatoria): <https://www.pexels.com/photo/woman-and-man-by-table-with-electronics-9242810/>

## Modo móvil

Es el mismo código: por debajo de 900 px de ancho el menú pasa a ser un cajón lateral,
las tarjetas se apilan, las acciones principales ocupan todo el ancho y los formularios
se vuelven una columna desplazable. Verificado a 390 px (tamaño de teléfono).

- Probarlo sin teléfono: servir `build/web` (p. ej. puerto 8780) y abrir `tools/vista_movil/index.html`
  con otro servidor (p. ej. `python -m http.server 8781` dentro de `tools/vista_movil`), que
  muestra la app en un marco de 390 px de ancho.
- Web en el celular: publicar `build/web` y abrir la dirección; se puede «Agregar a pantalla de inicio».
- APK de Android: `flutter build apk --release` → `build/app/outputs/flutter-apk/app-release.apk` (~54 MB).
  Va firmado con la clave de depuración (sirve para evaluar; para Play Store hay que crear un keystore
  propio y quitar `signingConfig = debug` de `android/app/build.gradle.kts`). Nombre «CETPRO IISEP»,
  ícono con el logo, permiso de internet (descarga la fuente Inter). **Aún no se ha probado en un
  teléfono ni en emulador**; la versión web móvil sí se verificó a 390 px.
- Requiere el SDK de Android con plataforma 36, build-tools 36.0.0 y NDK 28.2 (lo exige Flutter).

## Publicación de la demo web

La demo (solo la compilación) está en GitHub Pages: <https://jhon719.github.io/iisep-app/>.
Se compila con `flutter build web --release --base-href /iisep-app/`.

## Tema claro / oscuro

- Interruptor «Tema» (Automático · Claro · Oscuro) abajo en el menú; en el ingreso, un botón arriba a la derecha. Se guarda en el navegador (`shared_preferences`) y «Automático» sigue al dispositivo.
- Los colores están en `lib/core/theme.dart`: cada token `Tk.*` es un `DynColor` (un `Color` que lee `Tk.dark` al pintar), así que las pantallas siguen usando `Tk.ink`, `Tk.surface`… dentro de `const` sin cambios. `Tk.fill` y `Tk.dangerFill` son rellenos con texto blanco y no cambian de modo.
- **Trampa:** al cambiar de tema hay que reconstruir todo el árbol, y `go_router` conserva su navegador (con todas las pantallas) mientras exista. Por eso `main.dart` invalida `routerProvider` y se crea un `GoRouter` nuevo (la sesión y los datos viven en providers; `_lastLocation` mantiene la página). Solo cambiar una `key` NO bastaba: los textos quedaban con el color anterior.
- `test/tema_test.dart` comprueba contraste mínimo (WCAG) en ambos modos y que la preferencia se recuerde.
