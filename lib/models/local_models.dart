class FincaLocal {
  int? id;
  late int remoteId;
  late String nombre;
  String? latitud;
  String? longitud;
  String? hectareasTotales;
  String? tipoSuelo;
  String? estado;

  FincaLocal({
    this.id,
    required this.remoteId,
    required this.nombre,
    this.latitud,
    this.longitud,
    this.hectareasTotales,
    this.tipoSuelo,
    this.estado,
  });

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'remoteId': remoteId,
      'nombre': nombre,
      'latitud': latitud,
      'longitud': longitud,
      'hectareasTotales': hectareasTotales,
      'tipoSuelo': tipoSuelo,
      'estado': estado,
    };
  }

  factory FincaLocal.fromMap(Map<String, dynamic> map) {
    return FincaLocal(
      id: map['id'] as int?,
      remoteId: (map['remoteId'] as int?) ?? 0,
      nombre: map['nombre'] as String,
      latitud: map['latitud'] as String?,
      longitud: map['longitud'] as String?,
      hectareasTotales: map['hectareasTotales'] as String?,
      tipoSuelo: map['tipoSuelo'] as String?,
      estado: map['estado'] as String?,
    );
  }
}

class LoteLocal {
  int? id;
  late int remoteId;
  late int fincaRemoteId;
  late String nombre;
  String? hectareas;
  String? tipoCultivo;
  String? variedad;
  String? fechaSiembra;
  String? latitud;
  String? longitud;
  String? estado;

  LoteLocal({
    this.id,
    required this.remoteId,
    required this.fincaRemoteId,
    required this.nombre,
    this.hectareas,
    this.tipoCultivo,
    this.variedad,
    this.fechaSiembra,
    this.latitud,
    this.longitud,
    this.estado,
  });

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'remoteId': remoteId,
      'fincaRemoteId': fincaRemoteId,
      'nombre': nombre,
      'hectareas': hectareas,
      'tipoCultivo': tipoCultivo,
      'variedad': variedad,
      'fechaSiembra': fechaSiembra,
      'latitud': latitud,
      'longitud': longitud,
      'estado': estado,
    };
  }

  factory LoteLocal.fromMap(Map<String, dynamic> map) {
    return LoteLocal(
      id: map['id'] as int?,
      remoteId: (map['remoteId'] as int?) ?? 0,
      fincaRemoteId: (map['fincaRemoteId'] as int?) ?? 0,
      nombre: map['nombre'] as String,
      hectareas: map['hectareas'] as String?,
      tipoCultivo: map['tipoCultivo'] as String?,
      variedad: map['variedad'] as String?,
      fechaSiembra: map['fechaSiembra'] as String?,
      latitud: map['latitud'] as String?,
      longitud: map['longitud'] as String?,
      estado: map['estado'] as String?,
    );
  }
}
