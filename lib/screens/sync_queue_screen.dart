import 'package:flutter/material.dart';
import 'dart:io';
import '../models/media_capture.dart';
import '../services/sync_service.dart';
import '../providers/sync_provider.dart';
import 'package:provider/provider.dart';
import 'package:audioplayers/audioplayers.dart';

class SyncQueueScreen extends StatefulWidget {
  const SyncQueueScreen({super.key});

  @override
  State<SyncQueueScreen> createState() => _SyncQueueScreenState();
}

class _SyncQueueScreenState extends State<SyncQueueScreen> {
  final _sync = SyncService();
  List<MediaCapture> _items = [];
  bool _isLoading = true;
  final AudioPlayer _audioPlayer = AudioPlayer();

  @override
  void initState() {
    super.initState();
    _loadItems();
  }

  @override
  void dispose() {
    _audioPlayer.dispose();
    super.dispose();
  }

  Future<void> _loadItems() async {
    setState(() => _isLoading = true);
    final all = await _sync.getAllMedia();
    _items = all
        .where((e) =>
            e.syncStatus == SyncStatus.pending ||
            e.syncStatus == SyncStatus.uploading ||
            e.syncStatus == SyncStatus.failed)
        .toList()
      ..sort((a, b) => a.createdAt.compareTo(b.createdAt));
    setState(() => _isLoading = false);
  }

  Future<void> _deleteItem(MediaCapture item) async {
    await _sync.deleteMedia(item.id!);
    if (item.localPath != null) {
      final file = File(item.localPath!);
      if (await file.exists()) {
        await file.delete();
      }
    }
    await _loadItems();
    await context.read<SyncProvider>().init();
  }

  String _statusLabel(SyncStatus status) {
    switch (status) {
      case SyncStatus.pending:
        return 'Pendiente';
      case SyncStatus.uploading:
        return 'Subiendo...';
      case SyncStatus.synced:
        return 'Sincronizado';
      case SyncStatus.failed:
        return 'Error';
    }
  }

  Color _statusColor(SyncStatus status) {
    switch (status) {
      case SyncStatus.pending:
        return Colors.orange;
      case SyncStatus.uploading:
        return Colors.blue;
      case SyncStatus.synced:
        return Colors.green;
      case SyncStatus.failed:
        return Colors.red;
    }
  }

  IconData _typeIcon(MediaType type) {
    switch (type) {
      case MediaType.photo:
        return Icons.photo;
      case MediaType.audio:
        return Icons.audiotrack;
      case MediaType.video:
        return Icons.videocam;
      case MediaType.textNote:
        return Icons.note;
    }
  }

  Future<void> _playAudio(String path) async {
    await _audioPlayer.play(DeviceFileSource(path));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Cola de Sincronizacion'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadItems,
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Expanded(
                  child: Consumer<SyncProvider>(
                    builder: (context, sync, _) {
                      if (sync.isSyncing && sync.totalCount > 0) {
                        return Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            FilledButton.icon(
                              onPressed: null,
                              icon: const SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                              ),
                              label: const Text('Sincronizando...'),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${sync.syncedCount} de ${sync.totalCount}',
                              style: const TextStyle(fontSize: 12, color: Colors.grey),
                            ),
                          ],
                        );
                      }
                      return FilledButton.icon(
                        onPressed: () async {
                          await context.read<SyncProvider>().syncNow();
                          await _loadItems();
                        },
                        icon: const Icon(Icons.sync),
                        label: const Text('Sincronizar Ahora'),
                      );
                    },
                  ),
                ),
                const SizedBox(width: 12),
                OutlinedButton.icon(
                  onPressed: () async {
                    await context.read<SyncProvider>().retryAll();
                    await _loadItems();
                  },
                  icon: const Icon(Icons.replay),
                  label: const Text('Reintentar Todo'),
                ),
              ],
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _items.isEmpty
                    ? const Center(
                        child: Text('No hay items pendientes'),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        itemCount: _items.length,
                        itemBuilder: (context, index) {
                          final item = _items[index];
                          return Dismissible(
                            key: ValueKey(item.id),
                            direction: DismissDirection.endToStart,
                            background: Container(
                              alignment: Alignment.centerRight,
                              padding: const EdgeInsets.only(right: 20),
                              color: Colors.red,
                              child: const Icon(Icons.delete, color: Colors.white),
                            ),
                            confirmDismiss: (direction) async {
                              return await showDialog<bool>(
                                context: context,
                                builder: (ctx) => AlertDialog(
                                  title: const Text('Eliminar captura'),
                                  content: const Text('¿Estas seguro de que deseas eliminar esta captura?'),
                                  actions: [
                                    TextButton(
                                      onPressed: () => Navigator.pop(ctx, false),
                                      child: const Text('Cancelar'),
                                    ),
                                    FilledButton(
                                      onPressed: () => Navigator.pop(ctx, true),
                                      child: const Text('Eliminar'),
                                    ),
                                  ],
                                ),
                              );
                            },
                            onDismissed: (_) => _deleteItem(item),
                            child: Card(
                              margin: const EdgeInsets.only(bottom: 8),
                              child: ListTile(
                                leading: Icon(
                                  _typeIcon(item.tipo),
                                  color: _statusColor(item.syncStatus),
                                ),
                                title: Text(
                                  '${item.tipo.name.toUpperCase()} - ${_statusLabel(item.syncStatus)}',
                                ),
                                subtitle: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      item.createdAt.toString().substring(0, 19),
                                    ),
                                    if (item.errorMessage != null)
                                      Text(
                                        item.errorMessage!,
                                        style: const TextStyle(color: Colors.red),
                                      ),
                                    if (item.transcribedText != null &&
                                        item.transcribedText!.isNotEmpty)
                                      Text(
                                        'Transcripcion: ${item.transcribedText!.substring(0, item.transcribedText!.length > 50 ? 50 : item.transcribedText!.length)}...',
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                  ],
                                ),
                                trailing: item.tipo == MediaType.audio &&
                                        item.localPath != null
                                    ? IconButton(
                                        icon: const Icon(Icons.play_arrow),
                                        onPressed: () =>
                                            _playAudio(item.localPath!),
                                      )
                                    : null,
                              ),
                            ),
                          );
                        },
                      ),
          ),
        ],
      ),
    );
  }
}
