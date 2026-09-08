import 'package:flutter/foundation.dart';
import '../models/local_models.dart';
import '../services/api_service.dart';
import '../services/db_service.dart';

class FincasProvider extends ChangeNotifier {
  final _api = ApiService();
  final _db = DbService();

  List<FincaLocal> _fincas = [];
  List<LoteLocal> _lotes = [];
  bool _isLoading = false;
  int _selectedFincaId = 0;

  List<FincaLocal> get fincas => _fincas;
  List<LoteLocal> get lotes => _lotes;
  bool get isLoading => _isLoading;
  int get selectedFincaId => _selectedFincaId;

  Future<void> loadFincas() async {
    _isLoading = true;
    notifyListeners();

    try {
      final fincasData = await _api.getFincas();
      final database = await _db.db;
      final newFincas = <FincaLocal>[];
      for (final f in fincasData) {
        newFincas.add(FincaLocal(
          remoteId: f['id'],
          nombre: f['nombre'] ?? '',
          latitud: f['latitud']?.toString(),
          longitud: f['longitud']?.toString(),
          hectareasTotales: f['hectareas_totales']?.toString(),
          tipoSuelo: f['tipo_suelo'],
          estado: f['estado'],
        ));
      }
      await database.delete('fincas');
      for (final finca in newFincas) {
        await database.insert('fincas', finca.toMap());
      }
      _fincas = await _loadFincasFromDb();
    } catch (_) {
      _fincas = await _loadFincasFromDb();
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<List<FincaLocal>> _loadFincasFromDb() async {
    final database = await _db.db;
    final maps = await database.query('fincas');
    return maps.map((m) => FincaLocal.fromMap(m)).toList();
  }

  Future<void> loadLotes(int fincaId) async {
    _selectedFincaId = fincaId;
    _isLoading = true;
    notifyListeners();

    try {
      final lotesData = await _api.getLotes(fincaId);
      final database = await _db.db;
      await database.delete('lotes', where: 'fincaRemoteId = ?', whereArgs: [fincaId]);

      for (final l in lotesData) {
        final lote = LoteLocal(
          remoteId: l['id'],
          fincaRemoteId: fincaId,
          nombre: l['nombre'] ?? '',
          hectareas: l['hectareas']?.toString(),
          tipoCultivo: l['tipo_cultivo'],
          variedad: l['variedad'],
          fechaSiembra: l['fecha_siembra'],
          latitud: l['latitud']?.toString(),
          longitud: l['longitud']?.toString(),
          estado: l['estado'] ?? 'disponible',
        );
        await database.insert('lotes', lote.toMap());
      }
      _lotes = await _loadLotesFromDb(fincaId);
    } catch (_) {
      _lotes = await _loadLotesFromDb(fincaId);
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<List<LoteLocal>> _loadLotesFromDb(int fincaId) async {
    final database = await _db.db;
    final maps = await database.query(
      'lotes',
      where: 'fincaRemoteId = ?',
      whereArgs: [fincaId],
    );
    return maps.map((m) => LoteLocal.fromMap(m)).toList();
  }

  List<LoteLocal> getLotesForFinca(int fincaId) {
    return _lotes.where((l) => l.fincaRemoteId == fincaId).toList();
  }
}
