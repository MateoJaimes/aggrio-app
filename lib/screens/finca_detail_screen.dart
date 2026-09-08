import 'package:flutter/material.dart';
import '../models/local_models.dart';
import '../screens/capture_screen.dart';
import '../services/api_service.dart';
import '../services/db_service.dart';
import 'package:provider/provider.dart';
import '../providers/fincas_provider.dart';

class FincaDetailScreen extends StatefulWidget {
  final FincaLocal finca;

  const FincaDetailScreen({super.key, required this.finca});

  @override
  State<FincaDetailScreen> createState() => _FincaDetailScreenState();
}

class _FincaDetailScreenState extends State<FincaDetailScreen> {
  @override
  void initState() {
    super.initState();
    context.read<FincasProvider>().loadLotes(widget.finca.remoteId);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.finca.nombre),
      ),
      body: Consumer<FincasProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading && provider.lotes.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }

          final lotes = provider.getLotesForFinca(widget.finca.remoteId);

          if (lotes.isEmpty) {
            return const Center(
              child: Text('Esta finca no tiene lotes registrados'),
            );
          }

          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: lotes.length,
            itemBuilder: (context, index) {
              final lote = lotes[index];
              return _LoteCard(lote: lote, finca: widget.finca);
            },
          );
        },
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => CaptureScreen(
                fincaId: widget.finca.remoteId,
                fincaNombre: widget.finca.nombre,
              ),
            ),
          );
        },
        icon: const Icon(Icons.add_a_photo),
        label: const Text('Capturar'),
      ),
    );
  }
}

class _LoteCard extends StatefulWidget {
  final LoteLocal lote;
  final FincaLocal finca;

  const _LoteCard({required this.lote, required this.finca});

  @override
  State<_LoteCard> createState() => _LoteCardState();
}

class _LoteCardState extends State<_LoteCard> {
  Color _getEstadoColor(String? estado) {
    switch (estado) {
      case 'disponible':
        return Colors.green;
      case 'en_uso':
        return Colors.blue;
      case 'no_disponible':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  String _getEstadoLabel(String? estado) {
    switch (estado) {
      case 'disponible':
        return 'Disponible';
      case 'en_uso':
        return 'En uso';
      case 'no_disponible':
        return 'No disponible';
      default:
        return 'Desconocido';
    }
  }

  Future<void> _cambiarEstado() async {
    final nuevoEstado = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cambiar estado del lote'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.check_circle, color: Colors.green),
              title: const Text('Disponible'),
              subtitle: const Text('Se pueden registrar actividades'),
              onTap: () => Navigator.pop(context, 'disponible'),
            ),
            ListTile(
              leading: const Icon(Icons.work, color: Colors.blue),
              title: const Text('En uso'),
              subtitle: const Text('Actualmente en trabajo'),
              onTap: () => Navigator.pop(context, 'en_uso'),
            ),
            ListTile(
              leading: const Icon(Icons.block, color: Colors.red),
              title: const Text('No disponible'),
              subtitle: const Text('Temporalmente no usable'),
              onTap: () => Navigator.pop(context, 'no_disponible'),
            ),
          ],
        ),
      ),
    );

    if (nuevoEstado == null || nuevoEstado == widget.lote.estado) return;

    try {
      await ApiService().updateLoteEstado(
        loteId: widget.lote.remoteId,
        estado: nuevoEstado,
      );

      final db = await DbService().db;
      await db.update(
        'lotes',
        {'estado': nuevoEstado},
        where: 'remoteId = ?',
        whereArgs: [widget.lote.remoteId],
      );

      if (mounted) {
        setState(() {});
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Estado actualizado correctamente')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al actualizar: $e')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        leading: const Icon(Icons.grid_on, size: 40, color: Colors.blue),
        title: Text(widget.lote.nombre),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (widget.lote.tipoCultivo != null)
              Text('Cultivo: ${widget.lote.tipoCultivo}'),
            if (widget.lote.variedad != null)
              Text('Variedad: ${widget.lote.variedad}'),
            if (widget.lote.hectareas != null)
              Text('${widget.lote.hectareas} hectareas'),
            const SizedBox(height: 4),
            Row(
              children: [
                Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: _getEstadoColor(widget.lote.estado),
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  _getEstadoLabel(widget.lote.estado),
                  style: TextStyle(
                    color: _getEstadoColor(widget.lote.estado),
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ],
        ),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconButton(
              icon: const Icon(Icons.edit, size: 20),
              tooltip: 'Cambiar estado',
              onPressed: _cambiarEstado,
            ),
            const Icon(Icons.chevron_right),
          ],
        ),
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => CaptureScreen(
                fincaId: widget.finca.remoteId,
                fincaNombre: widget.finca.nombre,
                loteId: widget.lote.remoteId,
                loteNombre: widget.lote.nombre,
              ),
            ),
          );
        },
      ),
    );
  }
}
