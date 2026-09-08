import 'package:flutter/foundation.dart';
import '../services/api_service.dart';
import '../services/db_service.dart';
import '../services/connectivity_service.dart';

class AuthProvider extends ChangeNotifier {
  final _api = ApiService();
  bool _isLoading = false;
  bool _isAuthenticated = false;
  String? _errorMessage;
  Map<String, dynamic>? _user;

  bool get isLoading => _isLoading;
  bool get isAuthenticated => _isAuthenticated;
  String? get errorMessage => _errorMessage;
  Map<String, dynamic>? get user => _user;

  AuthProvider() {
    _api.onUnauthorized = _handleUnauthorized;
  }

  void _handleUnauthorized() {
    _isAuthenticated = false;
    _user = null;
    notifyListeners();
  }

  Future<void> checkAuth() async {
    final token = await _api.token;
    if (token != null) {
      try {
        final response = await _api.getMe();
        _user = response['data']['user'];
        _isAuthenticated = true;
      } catch (_) {
        final connected = await ConnectivityService().isConnected;
        if (!connected) {
          _isAuthenticated = true;
        } else {
          _isAuthenticated = false;
          _user = null;
          await _api.clearToken();
        }
      }
    }
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.login(email, password);
      if (response['success'] == true) {
        _user = response['data']['user'];
        _isAuthenticated = true;
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _errorMessage = response['message'] ?? 'Error al iniciar sesion';
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _errorMessage = 'Error de conexion. Verifica la URL del servidor.';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    try {
      await _api.logout();
    } catch (_) {}
    _isAuthenticated = false;
    _user = null;
    _errorMessage = null;
    await DbService().clearAll();
    notifyListeners();
  }
}
