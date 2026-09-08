import 'dart:io';
import '../models/media_capture.dart';
import '../services/db_service.dart';
import '../services/minio_service.dart';
import '../services/connectivity_service.dart';

class SyncService {
  static final SyncService _instance = SyncService._internal();
  factory SyncService() => _instance;

  final _db = DbService();
  final _minio = MinioService();
  final _connectivity = ConnectivityService();
  bool _isSyncing = false;
  int _pendingCount = 0;
  int _syncedCount = 0;
  int _totalCount = 0;

  SyncService._internal() {
    _connectivity.onStatusChanged.listen((connected) {
      if (connected) {
        processQueue();
      }
    });
  }

  int get pendingCount => _pendingCount;
  bool get isSyncing => _isSyncing;
  int get syncedCount => _syncedCount;
  int get totalCount => _totalCount;

  Future<List<MediaCapture>> _getByStatus(SyncStatus status) async {
    final database = await _db.db;
    final maps = await database.query(
      'media',
      where: 'syncStatus = ?',
      whereArgs: [status.index],
    );
    return maps.map((m) => MediaCapture.fromMap(m)).toList();
  }

  Future<void> updatePendingCount() async {
    final pending = await _getByStatus(SyncStatus.pending);
    final failed = await _getByStatus(SyncStatus.failed);
    _pendingCount = pending.length + failed.length;
  }

  Future<void> processQueue() async {
    if (_isSyncing) return;

    final connected = await _connectivity.isConnected;
    if (!connected) return;

    _isSyncing = true;
    _syncedCount = 0;

    final pendingItems = await _getByStatus(SyncStatus.pending);
    final toProcess = pendingItems.take(5).toList();
    _totalCount = toProcess.length;

    for (final item in toProcess) {
      final success = await _processItem(item);
      if (success) {
        _syncedCount++;
      }
    }

    await updatePendingCount();
    _isSyncing = false;
  }

  Future<bool> _processItem(MediaCapture item) async {
    try {
      item.syncStatus = SyncStatus.uploading;
      await _updateMedia(item);

      if (item.tipo == MediaType.textNote) {
        return await _processTextNote(item);
      }

      if (item.localPath != null && File(item.localPath!).existsSync()) {
        final modeloTipo = _getModeloTipo(item);
        final modeloId = _getModeloId(item);

        if (modeloId == null) {
          await _markFailed(item, 'No se encontro la entidad asociada');
          return false;
        }

        final uploadResult = await _minio.uploadToMinio(
          file: File(item.localPath!),
          modeloTipo: modeloTipo,
          modeloId: modeloId,
          categoria: item.categoria,
        );

        if (uploadResult == null) {
          await _markFailed(item, 'Error al subir a MinIO');
          return false;
        }

        final registered = await _minio.registerInApi(
          modeloTipo: modeloTipo,
          modeloId: modeloId,
          uploadResult: uploadResult,
          texto: item.transcribedText,
          categoria: item.categoria,
        );

        if (!registered) {
          await _markFailed(item, 'Error al registrar en la API');
          return false;
        }

        item.syncStatus = SyncStatus.synced;
        item.remoteFileKey = uploadResult['file_key'];
        await _updateMedia(item);

        final localFile = File(item.localPath!);
        if (await localFile.exists()) {
          await localFile.delete();
        }
        return true;
      } else {
        await _markFailed(item, 'Archivo local no encontrado');
        return false;
      }
    } catch (e) {
      await _markFailed(item, e.toString());
      return false;
    }
  }

  Future<bool> _processTextNote(MediaCapture item) async {
    final modeloTipo = _getModeloTipo(item);
    final modeloId = _getModeloId(item);

    if (modeloId == null) {
      await _markFailed(item, 'No se encontro la entidad asociada');
      return false;
    }

    final registered = await _minio.registerTextNote(
      modeloTipo: modeloTipo,
      modeloId: modeloId,
      texto: item.transcribedText ?? '',
      categoria: item.categoria,
    );

    if (!registered) {
      await _markFailed(item, 'Error al registrar nota de texto en la API');
      return false;
    }

    item.syncStatus = SyncStatus.synced;
    await _updateMedia(item);
    return true;
  }

  Future<void> _markFailed(MediaCapture item, String error) async {
    item.syncStatus = SyncStatus.failed;
    item.errorMessage = error;
    await _updateMedia(item);
  }

  Future<void> _updateMedia(MediaCapture item) async {
    final database = await _db.db;
    await database.update('media', item.toMap(), where: 'id = ?', whereArgs: [item.id]);
  }

  Future<int> _insertMedia(MediaCapture item) async {
    final database = await _db.db;
    return await database.insert('media', item.toMap());
  }

  String _getModeloTipo(MediaCapture item) {
    if (item.actividadRemoteId != null) return 'actividad';
    if (item.loteRemoteId != null) return 'lote';
    return 'finca';
  }

  int? _getModeloId(MediaCapture item) {
    if (item.actividadRemoteId != null) return item.actividadRemoteId;
    if (item.loteRemoteId != null) return item.loteRemoteId;
    return item.fincaRemoteId;
  }

  Future<void> retryItem(int captureId) async {
    final database = await _db.db;
    final maps = await database.query('media', where: 'id = ?', whereArgs: [captureId]);
    if (maps.isEmpty) return;

    final item = MediaCapture.fromMap(maps.first);
    item.syncStatus = SyncStatus.pending;
    item.errorMessage = null;
    await _updateMedia(item);

    await processQueue();
  }

  Future<void> retryAll() async {
    final pending = await _getByStatus(SyncStatus.pending);
    final failed = await _getByStatus(SyncStatus.failed);
    final failedAndPending = [...pending, ...failed];

    for (final item in failedAndPending) {
      item.syncStatus = SyncStatus.pending;
      item.errorMessage = null;
      await _updateMedia(item);
    }

    await processQueue();
  }

  Future<int> saveMedia(MediaCapture item) async {
    final id = await _insertMedia(item);
    item.id = id;
    processQueue();
    return id;
  }

  Future<List<MediaCapture>> getAllMedia() async {
    final database = await _db.db;
    final maps = await database.query('media', orderBy: 'createdAt ASC');
    return maps.map((m) => MediaCapture.fromMap(m)).toList();
  }

  Future<void> deleteMedia(int captureId) async {
    final database = await _db.db;
    await database.delete('media', where: 'id = ?', whereArgs: [captureId]);
  }
}
