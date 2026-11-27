import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/models.dart';
import '../../data/providers.dart';
import '../../data/repositories.dart';

class AuthState {
  const AuthState({this.user, this.loading = false, this.error});
  final AuthUser? user;
  final bool loading;
  final String? error;

  bool get isLoggedIn => user != null;
}

class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() => const AuthState();

  Future<void> login(String login, String password) async {
    state = const AuthState(loading: true);
    try {
      final user =
          await ref.read(authRepositoryProvider).login(login, password);
      state = AuthState(user: user);
    } on AuthException catch (e) {
      state = AuthState(error: e.message);
    } catch (_) {
      state = const AuthState(
          error: 'No se pudo iniciar sesión. Intenta nuevamente.');
    }
  }

  Future<void> logout() async {
    await ref.read(authRepositoryProvider).logout();
    state = const AuthState();
  }
}

final authProvider =
    NotifierProvider<AuthController, AuthState>(AuthController.new);
