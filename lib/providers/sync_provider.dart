import 'package:flutter/foundation.dart';
import '../services/sync_service.dart';

class SyncProvider extends ChangeNotifier {
  final _sync = SyncService();
  int _pendingCount = 0;
  bool _isSyncing = false;
  int _syncedCount = 0;
  int _totalCount = 0;

  int get pendingCount => _pendingCount;
  bool get isSyncing => _isSyncing;
  int get syncedCount => _syncedCount;
  int get totalCount => _totalCount;

  Future<void> init() async {
    await _sync.updatePendingCount();
    _pendingCount = _sync.pendingCount;
    _isSyncing = _sync.isSyncing;
    _syncedCount = _sync.syncedCount;
    _totalCount = _sync.totalCount;
    notifyListeners();
  }

  Future<void> syncNow() async {
    await _sync.processQueue();
    _pendingCount = _sync.pendingCount;
    _isSyncing = _sync.isSyncing;
    _syncedCount = _sync.syncedCount;
    _totalCount = _sync.totalCount;
    notifyListeners();
  }

  Future<void> retryItem(int captureId) async {
    await _sync.retryItem(captureId);
    _pendingCount = _sync.pendingCount;
    notifyListeners();
  }

  Future<void> retryAll() async {
    await _sync.retryAll();
    _pendingCount = _sync.pendingCount;
    notifyListeners();
  }
}
