import 'dart:io';
import 'package:http/http.dart' as http;
import 'api_service.dart';
import 'media_service.dart';

class MinioService {
  static final MinioService _instance = MinioService._internal();
  factory MinioService() => _instance;

  final _api = ApiService();

  MinioService._internal();

  Future<Map<String, dynamic>?> uploadToMinio({
    required File file,
    required String modeloTipo,
    required int modeloId,
    String? categoria,
  }) async {
    final filename = MediaService.generateFilename(
      file.path.split('.').last,
    );
    final mimeType = MediaService.getMimeType(file);

    final presignedData = await _api.getPresignedUrl(
      modeloTipo: modeloTipo,
      modeloId: modeloId,
      filename: filename,
      mimeType: mimeType,
      categoria: categoria,
    );

    final uploadUrl = presignedData['upload_url'] as String;
    final fileKey = presignedData['file_key'] as String;
    final fullUrl = presignedData['full_url'] as String;

    final fileBytes = await file.readAsBytes();
    final uploadResponse = await http.put(
      Uri.parse(uploadUrl),
      headers: {'Content-Type': mimeType},
      body: fileBytes,
    );

    if (uploadResponse.statusCode >= 200 && uploadResponse.statusCode < 300) {
      return {
        'file_key': fileKey,
        'full_url': fullUrl,
        'mime_type': mimeType,
        'peso_bytes': fileBytes.length,
      };
    }

    return null;
  }

  Future<bool> registerInApi({
    required String modeloTipo,
    required int modeloId,
    required Map<String, dynamic> uploadResult,
    String? texto,
    String? categoria,
  }) async {
    try {
      await _api.registerMultimedia(
        modeloTipo: modeloTipo,
        modeloId: modeloId,
        archivosSubidos: [
          {
            'ruta_archivo': uploadResult['file_key'],
            'tipo_archivo': _guessTipoArchivo(uploadResult['mime_type']),
            'peso_bytes': uploadResult['peso_bytes'],
          },
        ],
        texto: texto,
        categoria: categoria,
      );
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> registerTextNote({
    required String modeloTipo,
    required int modeloId,
    required String texto,
    String? categoria,
  }) async {
    try {
      await _api.registerMultimediaTextNote(
        modeloTipo: modeloTipo,
        modeloId: modeloId,
        texto: texto,
        categoria: categoria,
      );
      return true;
    } catch (_) {
      return false;
    }
  }

  String _guessTipoArchivo(String mimeType) {
    if (mimeType.startsWith('image/')) return 'foto';
    if (mimeType.startsWith('video/')) return 'video';
    if (mimeType.startsWith('audio/')) return 'audio';
    return 'archivo';
  }
}
