import 'dart:io';
import 'package:image_picker/image_picker.dart';
import 'package:camera/camera.dart';
import 'package:uuid/uuid.dart';
import 'package:path/path.dart' as path;

class MediaService {
  static final MediaService _instance = MediaService._internal();
  factory MediaService() => _instance;

  final ImagePicker _picker = ImagePicker();
  CameraController? _cameraController;
  List<CameraDescription>? _cameras;
  bool _isInitialized = false;

  MediaService._internal();

  Future<void> initCamera() async {
    if (_isInitialized) return;
    try {
      _cameras = await availableCameras();
      if (_cameras!.isNotEmpty) {
        _cameraController = CameraController(
          _cameras![0],
          ResolutionPreset.medium,
        );
        await _cameraController!.initialize();
        _isInitialized = true;
      }
    } catch (_) {
      _isInitialized = false;
    }
  }

  CameraController? get cameraController => _cameraController;
  bool get isCameraReady => _isInitialized && _cameraController != null;

  Future<File?> capturePhoto() async {
    try {
      final XFile? image = await _picker.pickImage(source: ImageSource.camera);
      if (image == null) return null;
      return File(image.path);
    } catch (_) {
      return null;
    }
  }

  Future<File?> pickPhotoFromGallery() async {
    try {
      final XFile? image = await _picker.pickImage(source: ImageSource.gallery);
      if (image == null) return null;
      return File(image.path);
    } catch (_) {
      return null;
    }
  }

  Future<File?> recordVideo() async {
    try {
      final XFile? video = await _picker.pickVideo(source: ImageSource.camera);
      if (video == null) return null;
      return File(video.path);
    } catch (_) {
      return null;
    }
  }

  Future<File?> capturePhotoWithCamera() async {
    if (!isCameraReady) return await capturePhoto();
    try {
      final XFile image = await _cameraController!.takePicture();
      return File(image.path);
    } catch (_) {
      return null;
    }
  }

  static String generateFilename(String extension) {
    const uuid = Uuid();
    return 'agrofield_${uuid.v4()}.$extension';
  }

  static String getMimeType(File file) {
    final ext = path.extension(file.path).toLowerCase();
    switch (ext) {
      case '.jpg':
      case '.jpeg':
        return 'image/jpeg';
      case '.png':
        return 'image/png';
      case '.mp4':
        return 'video/mp4';
      case '.m4a':
        return 'audio/mp4';
      case '.aac':
        return 'audio/aac';
      case '.wav':
        return 'audio/wav';
      default:
        return 'application/octet-stream';
    }
  }

  void disposeCamera() {
    _cameraController?.dispose();
    _isInitialized = false;
  }
}
