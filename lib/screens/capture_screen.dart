import 'package:flutter/material.dart';
import '../models/media_capture.dart';
import '../services/sync_service.dart';
import '../services/media_service.dart';
import '../services/speech_service.dart';
import '../providers/sync_provider.dart';
import 'package:provider/provider.dart';
import 'package:video_player/video_player.dart';
import 'package:audioplayers/audioplayers.dart';
import 'dart:io';

class CaptureScreen extends StatefulWidget {
  final int fincaId;
  final String fincaNombre;
  final int? loteId;
  final String? loteNombre;

  const CaptureScreen({
    super.key,
    required this.fincaId,
    required this.fincaNombre,
    this.loteId,
    this.loteNombre,
  });

  @override
  State<CaptureScreen> createState() => _CaptureScreenState();
}

class _CaptureScreenState extends State<CaptureScreen> {
  final _sync = SyncService();
  final _media = MediaService();
  final _speech = SpeechService();

  MediaType _selectedType = MediaType.photo;
  File? _capturedFile;
  String? _transcribedText;
  String _categoria = 'seguimiento';
  bool _isProcessing = false;
  bool _isRecording = false;

  VideoPlayerController? _videoController;
  final AudioPlayer _audioPlayer = AudioPlayer();
  bool _isPlaying = false;

  @override
  void initState() {
    super.initState();
    _initCamera();
  }

  Future<void> _initCamera() async {
    try {
      await _media.initCamera();
    } catch (_) {}
  }

  @override
  void dispose() {
    _videoController?.dispose();
    _audioPlayer.dispose();
    super.dispose();
  }

  Future<void> _capture() async {
    setState(() => _isProcessing = true);

    File? file;

    switch (_selectedType) {
      case MediaType.photo:
        file = await _media.capturePhoto();
        break;
      case MediaType.video:
        file = await _media.recordVideo();
        break;
      case MediaType.audio:
        await _startRecording();
        return;
      case MediaType.textNote:
        _showTextNoteDialog();
        return;
    }

    if (file != null) {
      setState(() {
        _capturedFile = file;
        _isProcessing = false;
      });
    } else {
      setState(() => _isProcessing = false);
    }
  }

  Future<void> _startRecording() async {
    if (!_speech.isRecording) {
      final path = await _speech.startRecording();
      if (path != null) {
        setState(() {
          _isRecording = true;
          _isProcessing = false;
        });
      }
    } else {
      final path = await _speech.stopRecording();
      setState(() {
        _isRecording = false;
        _isProcessing = true;
      });

      if (path != null) {
        setState(() {
          _capturedFile = File(path);
          _isProcessing = false;
        });
      }
    }
  }

  void _showTextNoteDialog() {
    final controller = TextEditingController();
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Nota de Texto'),
        content: TextField(
          controller: controller,
          maxLines: 5,
          decoration: const InputDecoration(
            hintText: 'Escribe tu nota aqui...',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () async {
              final text = controller.text.trim();
              if (text.isNotEmpty) {
                await _saveTextNote(text);
              }
              Navigator.pop(ctx);
            },
            child: const Text('Guardar'),
          ),
        ],
      ),
    );
  }

  Future<void> _saveTextNote(String text) async {
    final capture = MediaCapture(
      tipo: MediaType.textNote,
      transcribedText: text,
      fincaRemoteId: widget.fincaId,
      loteRemoteId: widget.loteId,
      categoria: _categoria,
      pesoBytes: text.length,
      createdAt: DateTime.now(),
      syncStatus: SyncStatus.pending,
    );

    await _sync.saveMedia(capture);

    if (mounted) {
      context.read<SyncProvider>().init();
      Navigator.of(context).pop();
    }
  }

  Future<void> _saveCapture() async {
    if (_capturedFile == null) return;

    setState(() => _isProcessing = true);

    final capture = MediaCapture(
      localPath: _capturedFile!.path,
      tipo: _selectedType,
      transcribedText: _transcribedText,
      fincaRemoteId: widget.fincaId,
      loteRemoteId: widget.loteId,
      categoria: _categoria,
      mimeType: MediaService.getMimeType(_capturedFile!),
      pesoBytes: await _capturedFile!.length(),
      createdAt: DateTime.now(),
      syncStatus: SyncStatus.pending,
    );

    await _sync.saveMedia(capture);

    if (mounted) {
      context.read<SyncProvider>().init();
      Navigator.of(context).pop();
    }
  }

  Widget _buildPreview() {
    if (_capturedFile == null) {
      return const Center(
        child: Text('No hay captura aun'),
      );
    }

    switch (_selectedType) {
      case MediaType.photo:
        return Image.file(_capturedFile!, fit: BoxFit.contain);
      case MediaType.video:
        return _buildVideoPreview();
      case MediaType.audio:
        return _buildAudioPreview();
      case MediaType.textNote:
        return const SizedBox.shrink();
    }
  }

  Widget _buildVideoPreview() {
    if (_videoController == null || !_videoController!.value.isInitialized) {
      _videoController = VideoPlayerController.file(_capturedFile!);
      return FutureBuilder(
        future: _videoController!.initialize(),
        builder: (ctx, snapshot) {
          if (snapshot.connectionState == ConnectionState.done) {
            _videoController!.play();
            return AspectRatio(
              aspectRatio: _videoController!.value.aspectRatio,
              child: VideoPlayer(_videoController!),
            );
          }
          return const Center(child: CircularProgressIndicator());
        },
      );
    }
    return AspectRatio(
      aspectRatio: _videoController!.value.aspectRatio,
      child: VideoPlayer(_videoController!),
    );
  }

  Widget _buildAudioPreview() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.audio_file, size: 64, color: Colors.green),
        const SizedBox(height: 16),
        ElevatedButton.icon(
          onPressed: () async {
            if (_isPlaying) {
              await _audioPlayer.stop();
              setState(() => _isPlaying = false);
            } else {
              _audioPlayer.onPlayerComplete.listen((_) {
                if (mounted) setState(() => _isPlaying = false);
              });
              await _audioPlayer.play(DeviceFileSource(_capturedFile!.path));
              setState(() => _isPlaying = true);
            }
          },
          icon: Icon(_isPlaying ? Icons.stop : Icons.play_arrow),
          label: Text(_isPlaying ? 'Detener' : 'Reproducir'),
        ),
        if (_transcribedText != null && _transcribedText!.isNotEmpty) ...[
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Column(
              children: [
                const Text(
                  'Transcripción:',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                ),
                const SizedBox(height: 8),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.grey[100],
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.grey[300]!),
                  ),
                  child: Text(
                    _transcribedText!,
                    style: const TextStyle(fontSize: 14),
                  ),
                ),
                const SizedBox(height: 8),
                TextButton.icon(
                  onPressed: () {
                    final controller = TextEditingController(text: _transcribedText);
                    showDialog(
                      context: context,
                      builder: (ctx) => AlertDialog(
                        title: const Text('Editar Transcripción'),
                        content: TextField(
                          controller: controller,
                          maxLines: 8,
                          decoration: const InputDecoration(
                            hintText: 'Texto transcrito...',
                            border: OutlineInputBorder(),
                          ),
                        ),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(ctx),
                            child: const Text('Cancelar'),
                          ),
                          FilledButton(
                            onPressed: () {
                              setState(() {
                                _transcribedText = controller.text.trim();
                              });
                              Navigator.pop(ctx);
                            },
                            child: const Text('Guardar'),
                          ),
                        ],
                      ),
                    );
                  },
                  icon: const Icon(Icons.edit, size: 18),
                  label: const Text('Editar texto'),
                ),
              ],
            ),
          ),
        ] else ...[
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Column(
              children: [
                const Text(
                  'Sin transcripción',
                  style: TextStyle(fontSize: 12, color: Colors.grey, fontStyle: FontStyle.italic),
                ),
                TextButton.icon(
                  onPressed: () {
                    final controller = TextEditingController();
                    showDialog(
                      context: context,
                      builder: (ctx) => AlertDialog(
                        title: const Text('Agregar Nota'),
                        content: TextField(
                          controller: controller,
                          maxLines: 8,
                          decoration: const InputDecoration(
                            hintText: 'Escribe una nota para este audio...',
                            border: OutlineInputBorder(),
                          ),
                        ),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(ctx),
                            child: const Text('Cancelar'),
                          ),
                          FilledButton(
                            onPressed: () {
                              setState(() {
                                _transcribedText = controller.text.trim();
                              });
                              Navigator.pop(ctx);
                            },
                            child: const Text('Guardar'),
                          ),
                        ],
                      ),
                    );
                  },
                  icon: const Icon(Icons.add_comment, size: 18),
                  label: const Text('Agregar nota manualmente'),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.fincaNombre, style: const TextStyle(fontSize: 16)),
            if (widget.loteNombre != null)
              Text(
                widget.loteNombre!,
                style: TextStyle(fontSize: 12, color: Colors.white.withValues(alpha: 0.7)),
              ),
          ],
        ),
        actions: [
          DropdownButton<String>(
            value: _categoria,
            underline: const SizedBox(),
            items: const [
              DropdownMenuItem(value: 'seguimiento', child: Text('Seguimiento')),
              DropdownMenuItem(value: 'enfermedad', child: Text('Enfermedad')),
            ],
            onChanged: (v) => setState(() => _categoria = v!),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: Column(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            color: Colors.grey[100],
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                _typeButton(MediaType.photo, Icons.camera_alt, 'Foto'),
                _typeButton(MediaType.audio, Icons.mic, 'Audio'),
                _typeButton(MediaType.video, Icons.videocam, 'Video'),
                _typeButton(MediaType.textNote, Icons.note, 'Nota'),
              ],
            ),
          ),
          Expanded(
            child: _isRecording
                ? Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.mic, size: 80, color: Colors.red),
                        const SizedBox(height: 16),
                        const Text('Grabando...', style: TextStyle(fontSize: 20)),
                        const SizedBox(height: 24),
                        FilledButton.icon(
                          onPressed: _startRecording,
                          icon: const Icon(Icons.stop),
                          label: const Text('Detener Grabación'),
                          style: FilledButton.styleFrom(
                            backgroundColor: Colors.red,
                            padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 16),
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextButton.icon(
                          onPressed: () async {
                            await _speech.cancelRecording();
                            setState(() => _isRecording = false);
                          },
                          icon: const Icon(Icons.delete_outline),
                          label: const Text('Cancelar'),
                        ),
                      ],
                    ),
                  )
                : _buildPreview(),
          ),
          if (_capturedFile != null || _selectedType == MediaType.textNote)
            Padding(
              padding: const EdgeInsets.all(16),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: _isProcessing ? null : _saveCapture,
                  icon: _isProcessing
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.save),
                  label: Text(_isProcessing ? 'Guardando...' : 'Guardar Captura'),
                ),
              ),
            ),
          if (_capturedFile == null && !_isRecording)
            Padding(
              padding: const EdgeInsets.all(24),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: _isProcessing ? null : _capture,
                  icon: Icon(_selectedType == MediaType.photo
                      ? Icons.camera_alt
                      : _selectedType == MediaType.audio
                          ? Icons.mic
                          : _selectedType == MediaType.video
                              ? Icons.videocam
                              : Icons.note),
                  label: Text(
                    _selectedType == MediaType.photo
                        ? 'Tomar Foto'
                        : _selectedType == MediaType.audio
                            ? 'Grabar Audio'
                            : _selectedType == MediaType.video
                                ? 'Grabar Video'
                                : 'Crear Nota',
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _typeButton(MediaType type, IconData icon, String label) {
    final isSelected = _selectedType == type;
    return GestureDetector(
      onTap: () => setState(() => _selectedType = type),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            icon,
            color: isSelected ? Colors.green[800] : Colors.grey,
            size: 28,
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              color: isSelected ? Colors.green[800] : Colors.grey,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
            ),
          ),
        ],
      ),
    );
  }
}
