import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../config/app_config.dart';

typedef OnUnauthorized = void Function();

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;

  late final Dio _dio;
  final _storage = const FlutterSecureStorage();
  OnUnauthorized? onUnauthorized;

  ApiService._internal() {
    _dio = Dio(BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {'Accept': 'application/json'},
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _storage.read(key: 'auth_token');
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          await clearToken();
          onUnauthorized?.call();
          return handler.reject(
            DioException(
              requestOptions: error.requestOptions,
              response: error.response,
              error: 'Sesion expirada',
              type: DioExceptionType.badResponse,
            ),
          );
        }
        handler.next(error);
      },
    ));
  }

  Future<String?> get token async => await _storage.read(key: 'auth_token');

  Future<void> saveToken(String token) async {
    await _storage.write(key: 'auth_token', value: token);
  }

  Future<void> clearToken() async {
    await _storage.delete(key: 'auth_token');
  }

  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await _dio.post('/login', data: {
      'email': email,
      'password': password,
      'device_name': 'agrofield-mobile',
    });
    final data = response.data;
    if (data['success'] == true && data['data']?['token'] != null) {
      await saveToken(data['data']['token']);
    }
    return data;
  }

  Future<Map<String, dynamic>> getMe() async {
    final response = await _dio.get('/me');
    return response.data;
  }

  Future<List<dynamic>> getFincas() async {
    final response = await _dio.get('/mis-fincas');
    final data = response.data['data'];
    if (data == null) return [];
    return data as List;
  }

  Future<List<dynamic>> getLotes(int fincaId) async {
    final response = await _dio.get('/fincas/$fincaId/lotes');
    final data = response.data['data'];
    if (data == null) return [];
    return data as List;
  }

  Future<Map<String, dynamic>> updateLoteEstado({
    required int loteId,
    required String estado,
  }) async {
    final response = await _dio.patch('/lotes/$loteId/estado', data: {
      'estado': estado,
    });
    return response.data;
  }

  Future<Map<String, dynamic>> getPresignedUrl({
    required String modeloTipo,
    required int modeloId,
    required String filename,
    required String mimeType,
    String? categoria,
  }) async {
    final data = {
      'modelo_tipo': modeloTipo,
      'modelo_id': modeloId,
      'filename': filename,
      'mime_type': mimeType,
      if (categoria != null) 'categoria': categoria,
    };
    final response = await _dio.post('/minio/presigned-url', data: data);
    return response.data['data'];
  }

  Future<Map<String, dynamic>> registerMultimedia({
    required String modeloTipo,
    required int modeloId,
    required List<Map<String, dynamic>> archivosSubidos,
    String? texto,
    String? categoria,
  }) async {
    final data = {
      'modelo_tipo': modeloTipo,
      'modelo_id': modeloId,
      'archivos_subidos': archivosSubidos,
      if (texto != null) 'texto': texto,
      if (categoria != null) 'categoria': categoria,
    };
    final response = await _dio.post('/multimedia/subir', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> registerMultimediaTextNote({
    required String modeloTipo,
    required int modeloId,
    required String texto,
    String? categoria,
  }) async {
    final data = {
      'modelo_tipo': modeloTipo,
      'modelo_id': modeloId,
      'texto': texto,
      if (categoria != null) 'categoria': categoria,
    };
    final response = await _dio.post('/multimedia/subir', data: data);
    return response.data;
  }

  Future<void> logout() async {
    try {
      await _dio.post('/logout');
    } catch (_) {}
    await clearToken();
  }
}
