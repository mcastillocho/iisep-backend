import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Preferencia del usuario: seguir al dispositivo, o claro/oscuro fijo.
enum AppThemeMode {
  auto('Automático'),
  light('Claro'),
  dark('Oscuro');

  const AppThemeMode(this.label);
  final String label;
}

/// Almacén local (localStorage en web). Puede faltar si el navegador lo
/// bloquea: entonces la preferencia vale solo para la sesión.
final sharedPrefsProvider = Provider<SharedPreferences?>((_) => null);

const _kKey = 'iisep_theme_mode';

class ThemeModeController extends Notifier<AppThemeMode> {
  @override
  AppThemeMode build() {
    final saved = ref.read(sharedPrefsProvider)?.getString(_kKey);
    return AppThemeMode.values.where((m) => m.name == saved).firstOrNull ??
        AppThemeMode.auto;
  }

  Future<void> set(AppThemeMode mode) async {
    state = mode;
    try {
      await ref.read(sharedPrefsProvider)?.setString(_kKey, mode.name);
    } catch (_) {
      // sin almacenamiento: vale para esta sesión
    }
  }
}

final themeModeProvider =
    NotifierProvider<ThemeModeController, AppThemeMode>(ThemeModeController.new);
