enum MediaType { photo, audio, video, textNote }

enum SyncStatus { pending, uploading, synced, failed }

class MediaCapture {
  int? id;
  String? localPath;
  late MediaType tipo;
  late SyncStatus syncStatus;
  String? transcribedText;
  int? fincaRemoteId;
  int? loteRemoteId;
  int? actividadRemoteId;
  String categoria;
  String? mimeType;
  int? pesoBytes;
  String? remoteFileKey;
  String? errorMessage;
  late DateTime createdAt;

  MediaCapture({
    this.id,
    this.localPath,
    required this.tipo,
    this.syncStatus = SyncStatus.pending,
    this.transcribedText,
    this.fincaRemoteId,
    this.loteRemoteId,
    this.actividadRemoteId,
    this.categoria = 'seguimiento',
    this.mimeType,
    this.pesoBytes,
    this.remoteFileKey,
    this.errorMessage,
    required this.createdAt,
  });

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'localPath': localPath,
      'tipo': tipo.index,
      'syncStatus': syncStatus.index,
      'transcribedText': transcribedText,
      'fincaRemoteId': fincaRemoteId,
      'loteRemoteId': loteRemoteId,
      'actividadRemoteId': actividadRemoteId,
      'categoria': categoria,
      'mimeType': mimeType,
      'pesoBytes': pesoBytes,
      'remoteFileKey': remoteFileKey,
      'errorMessage': errorMessage,
      'createdAt': createdAt.millisecondsSinceEpoch,
    };
  }

  factory MediaCapture.fromMap(Map<String, dynamic> map) {
    return MediaCapture(
      id: map['id'] as int?,
      localPath: map['localPath'] as String?,
      tipo: MediaType.values[map['tipo'] as int],
      syncStatus: SyncStatus.values[map['syncStatus'] as int],
      transcribedText: map['transcribedText'] as String?,
      fincaRemoteId: map['fincaRemoteId'] as int?,
      loteRemoteId: map['loteRemoteId'] as int?,
      actividadRemoteId: map['actividadRemoteId'] as int?,
      categoria: map['categoria'] as String? ?? 'seguimiento',
      mimeType: map['mimeType'] as String?,
      pesoBytes: map['pesoBytes'] as int?,
      remoteFileKey: map['remoteFileKey'] as String?,
      errorMessage: map['errorMessage'] as String?,
      createdAt: DateTime.fromMillisecondsSinceEpoch(map['createdAt'] as int),
    );
  }
}
