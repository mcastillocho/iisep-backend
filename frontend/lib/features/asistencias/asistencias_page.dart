import 'package:material_ui/material_ui.dart';

import '../../core/theme.dart';
import '../../shared/widgets.dart';

/// El backend aún no tiene controlador de asistencias (ARBOL_ENDPOINTS.md,
/// sección /admin/asistencias: 100 % maqueta). Se diseña cuando existan los
/// endpoints propuestos en docs/API_NECESARIA.md.
class AsistenciasPage extends StatelessWidget {
  const AsistenciasPage({super.key});

  @override
  Widget build(BuildContext context) => const PageFrame(
        title: 'Asistencias',
        subtitle: 'Control por DNI y pase de lista',
        child: Card(
          child: Padding(
            padding: EdgeInsets.all(Tk.s5),
            child: Column(
              children: [
                Icon(Icons.hourglass_empty, size: 40, color: Tk.inkMute),
                SizedBox(height: Tk.s3),
                Text('Pendiente del backend',
                    style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: Tk.ink)),
                SizedBox(height: Tk.s2),
                Text(
                  'El servidor todavía no guarda asistencias. Esta pantalla se '
                  'construye cuando existan los endpoints de registro y pase de lista.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Tk.inkMute),
                ),
              ],
            ),
          ),
        ),
      );
}
