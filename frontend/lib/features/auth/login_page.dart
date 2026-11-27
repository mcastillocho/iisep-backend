import 'package:material_ui/material_ui.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme.dart';
import '../../shared/widgets.dart';
import 'auth_controller.dart';

/// Login a pantalla completa con tarjeta blanca centrada, campos y botón en
/// forma de píldora (patrón del login de Blackboard), con colores del IISEP.
class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final _form = GlobalKey<FormState>();
  final _login = TextEditingController();
  final _password = TextEditingController();
  bool _hide = true;

  @override
  void dispose() {
    _login.dispose();
    _password.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_form.currentState?.validate() ?? false)) return;
    ref.read(authProvider.notifier).login(_login.text, _password.text);
  }

  InputDecoration _pill(String label, {Widget? suffix}) {
    OutlineInputBorder b(Color c, double w) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(999),
          borderSide: BorderSide(color: c, width: w),
        );
    return InputDecoration(
      labelText: label,
      suffixIcon: suffix,
      filled: false,
      floatingLabelBehavior: FloatingLabelBehavior.auto,
      labelStyle: const TextStyle(
          color: Tk.ink, fontWeight: FontWeight.w600, fontSize: 14),
      contentPadding: const EdgeInsets.symmetric(horizontal: 22, vertical: 16),
      border: b(Tk.ink, 1.2),
      enabledBorder: b(Tk.ink, 1.2),
      focusedBorder: b(Tk.primary, 2),
      errorBorder: b(Tk.danger, 1.2),
      focusedErrorBorder: b(Tk.danger, 2),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);

    return Scaffold(
      body: Stack(
        fit: StackFit.expand,
        children: [
          const _Backdrop(),
          const SafeArea(
            child: Align(
              alignment: Alignment.topRight,
              child: Padding(
                padding: EdgeInsets.all(Tk.s2),
                child: ThemeModeButton(onDark: true),
              ),
            ),
          ),
          SafeArea(
            child: Column(
              children: [
                Expanded(
                  child: Center(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.all(Tk.s4),
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 360),
                        child: Container(
                          padding: const EdgeInsets.all(Tk.s5),
                          decoration: BoxDecoration(
                            color: Tk.surface,
                            borderRadius: BorderRadius.circular(6),
                            boxShadow: const [
                              BoxShadow(
                                  color: Color(0x33000000),
                                  blurRadius: 32,
                                  offset: Offset(0, 12)),
                            ],
                          ),
                          child: Form(
                            key: _form,
                            child: AutofillGroup(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  const Center(child: LogoMark(size: 150)),
                                  const SizedBox(height: Tk.s2),
                                  const Text(
                                    'Panel administrativo',
                                    textAlign: TextAlign.center,
                                    style: TextStyle(
                                        fontSize: 13, color: Tk.inkMute),
                                  ),
                                  const SizedBox(height: Tk.s5),
                                  TextFormField(
                                    controller: _login,
                                    autofillHints: const [
                                      AutofillHints.username
                                    ],
                                    textInputAction: TextInputAction.next,
                                    decoration: _pill('Nombre de usuario'),
                                    validator: (v) => (v ?? '').trim().isEmpty
                                        ? 'Ingresa tu usuario'
                                        : null,
                                  ),
                                  const SizedBox(height: Tk.s4),
                                  TextFormField(
                                    controller: _password,
                                    obscureText: _hide,
                                    autofillHints: const [
                                      AutofillHints.password
                                    ],
                                    onFieldSubmitted: (_) => _submit(),
                                    decoration: _pill(
                                      'Contraseña',
                                      suffix: IconButton(
                                        tooltip: _hide ? 'Mostrar' : 'Ocultar',
                                        icon: Icon(
                                            _hide
                                                ? Icons.visibility_outlined
                                                : Icons.visibility_off_outlined,
                                            size: 20),
                                        onPressed: () =>
                                            setState(() => _hide = !_hide),
                                      ),
                                    ),
                                    validator: (v) => (v ?? '').isEmpty
                                        ? 'Ingresa tu contraseña'
                                        : null,
                                  ),
                                  if (auth.error != null) ...[
                                    const SizedBox(height: Tk.s3),
                                    Container(
                                      padding: const EdgeInsets.all(Tk.s3),
                                      decoration: BoxDecoration(
                                        color: Tk.dangerSoft,
                                        borderRadius:
                                            BorderRadius.circular(Tk.radiusSm),
                                      ),
                                      child: Text(auth.error!,
                                          style: const TextStyle(
                                              color: Tk.danger, fontSize: 13)),
                                    ),
                                  ],
                                  const SizedBox(height: Tk.s5),
                                  FilledButton(
                                    onPressed: auth.loading ? null : _submit,
                                    style: FilledButton.styleFrom(
                                      shape: const StadiumBorder(),
                                      minimumSize: const Size(0, 48),
                                    ),
                                    child: auth.loading
                                        ? const SizedBox(
                                            width: 20,
                                            height: 20,
                                            child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                                color: Colors.white),
                                          )
                                        : const Text('Iniciar sesión'),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                const Padding(
                  padding: EdgeInsets.fromLTRB(Tk.s4, 0, Tk.s4, Tk.s4),
                  child: Text(
                    '© 2026 CETPRO IISEP · Instituto Internacional de Sistemas Empresariales\nModo demostración · datos de ejemplo',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                        fontSize: 12, color: Color(0xB3FFFFFF), height: 1.5),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Fondo con el azul y el verde del logo. Se puede cambiar por una foto del
/// instituto: poner la imagen con `DecorationImage` y mantener el tinte.
class _Backdrop extends StatelessWidget {
  const _Backdrop();

  @override
  Widget build(BuildContext context) => Stack(
        fit: StackFit.expand,
        children: [
          // Degradado de respaldo: se ve si la foto no carga.
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xFF0B2F5B), Color(0xFF1F7A55)],
              ),
            ),
          ),
          Image.asset(
            'assets/images/login_fondo.jpg',
            fit: BoxFit.cover,
            alignment: Alignment.center,
            errorBuilder: (_, _, _) => const SizedBox.shrink(),
          ),
          // Tinte azul → verde del logo: da legibilidad y marca la identidad.
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xD90B2F5B), Color(0xCC0E4A6E), Color(0xB31F7A55)],
              ),
            ),
          ),
        ],
      );
}
