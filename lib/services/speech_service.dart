import 'dart:io';
import 'package:record/record.dart';
import 'package:path_provider/path_provider.dart';
import 'package:uuid/uuid.dart';
import 'package:path/path.dart' as p;

class SpeechService {
  static final SpeechService _instance = SpeechService._internal();
  factory SpeechService() => _instance;

  final _recorder = Record();
  bool _isRecording = false;
  String? _currentPath;

  SpeechService._internal();

  Future<String?> startRecording() async {
    try {
      if (await _recorder.hasPermission()) {
        final dir = await getApplicationDocumentsDirectory();
        final recordingsDir = Directory('${dir.path}/agrofield_recordings');
        if (!await recordingsDir.exists()) {
          await recordingsDir.create(recursive: true);
        }
        final filename = 'recording_${const Uuid().v4()}.m4a';
        _currentPath = p.join(recordingsDir.path, filename);

        await _recorder.start(
          path: _currentPath!,
          encoder: AudioEncoder.aacLc,
        );
        _isRecording = true;
        return _currentPath;
      }
    } catch (_) {
      return null;
    }
    return null;
  }

  Future<String?> stopRecording() async {
    try {
      if (_isRecording) {
        final path = await _recorder.stop();
        _isRecording = false;
        return path ?? _currentPath;
      }
    } catch (_) {
      _isRecording = false;
    }
    return null;
  }

  bool get isRecording => _isRecording;

  Future<void> cancelRecording() async {
    try {
      if (_isRecording) {
        await _recorder.stop();
        _isRecording = false;
      }
      if (_currentPath != null) {
        final file = File(_currentPath!);
        if (await file.exists()) {
          await file.delete();
        }
      }
    } catch (_) {}
  }

  void dispose() {
    _recorder.dispose();
  }
}
