import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';

class ConnectivityService {
  static final ConnectivityService _instance = ConnectivityService._internal();
  factory ConnectivityService() => _instance;

  final Connectivity _connectivity = Connectivity();
  final _controller = StreamController<bool>.broadcast();

  ConnectivityService._internal() {
    _connectivity.onConnectivityChanged.listen(_updateStatus);
  }

  Stream<bool> get onStatusChanged => _controller.stream;

  Future<bool> get isConnected async {
    final results = await _connectivity.checkConnectivity();
    return _evaluateResults(results);
  }

  bool _evaluateResults(List<ConnectivityResult> results) {
    for (final result in results) {
      if (result != ConnectivityResult.none) {
        return true;
      }
    }
    return false;
  }

  Future<void> _updateStatus(List<ConnectivityResult> results) async {
    _controller.add(_evaluateResults(results));
  }

  void dispose() {
    _controller.close();
  }
}
