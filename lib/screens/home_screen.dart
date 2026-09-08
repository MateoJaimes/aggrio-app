import 'dart:async';
import 'package:flutter/material.dart';
import '../models/local_models.dart';
import 'finca_detail_screen.dart';
import 'sync_queue_screen.dart';
import 'package:provider/provider.dart';
import '../providers/fincas_provider.dart';
import '../providers/auth_provider.dart';
import '../providers/sync_provider.dart';
import '../services/connectivity_service.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _connectivity = ConnectivityService();
  bool _isConnected = true;
  late final StreamSubscription<bool> _connectivitySubscription;

  @override
  void initState() {
    super.initState();
    _loadData();
    _connectivitySubscription = _connectivity.onStatusChanged.listen((connected) {
      if (mounted) {
        setState(() => _isConnected = connected);
      }
    });
    _connectivity.isConnected.then((connected) {
      if (mounted) {
        setState(() => _isConnected = connected);
      }
    });
  }

  @override
  void dispose() {
    _connectivitySubscription.cancel();
    super.dispose();
  }

  Future<void> _loadData() async {
    await context.read<FincasProvider>().loadFincas();
    await context.read<SyncProvider>().init();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mis Fincas'),
        actions: [
          Consumer<SyncProvider>(
            builder: (context, sync, _) {
              if (sync.pendingCount > 0) {
                return Stack(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.sync),
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const SyncQueueScreen(),
                          ),
                        );
                      },
                    ),
                    Positioned(
                      right: 8,
                      top: 8,
                      child: Container(
                        padding: const EdgeInsets.all(2),
                        decoration: const BoxDecoration(
                          color: Colors.red,
                          shape: BoxShape.circle,
                        ),
                        constraints: const BoxConstraints(
                          minWidth: 16,
                          minHeight: 16,
                        ),
                        child: Text(
                          '${sync.pendingCount}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ),
                  ],
                );
              }
              return IconButton(
                icon: const Icon(Icons.sync),
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const SyncQueueScreen(),
                    ),
                  );
                },
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _confirmLogout,
          ),
        ],
      ),
      body: Column(
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 300),
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 16),
            color: _isConnected ? Colors.green[700] : Colors.red[700],
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  _isConnected ? Icons.wifi : Icons.wifi_off,
                  size: 16,
                  color: Colors.white,
                ),
                const SizedBox(width: 8),
                Text(
                  _isConnected ? 'En linea' : 'Sin conexion - Los datos se guardan localmente',
                  style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _loadData,
              child: Consumer<FincasProvider>(
                builder: (context, provider, _) {
                  if (provider.isLoading && provider.fincas.isEmpty) {
                    return const Center(child: CircularProgressIndicator());
                  }

                  if (provider.fincas.isEmpty) {
                    return const Center(
                      child: Text('No tienes fincas aprobadas aun'),
                    );
                  }

                  return ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: provider.fincas.length,
                    itemBuilder: (context, index) {
                      final finca = provider.fincas[index];
                      return _FincaCard(finca: finca);
                    },
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _confirmLogout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cerrar sesion'),
        content: const Text('¿Estas seguro de que deseas cerrar sesion?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Cerrar sesion'),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      context.read<AuthProvider>().logout();
    }
  }
}

class _FincaCard extends StatelessWidget {
  final FincaLocal finca;

  const _FincaCard({required this.finca});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        leading: const Icon(Icons.landscape, size: 40, color: Colors.green),
        title: Text(finca.nombre),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (finca.hectareasTotales != null)
              Text('${finca.hectareasTotales} hectareas'),
            if (finca.tipoSuelo != null)
              Text('Suelo: ${finca.tipoSuelo}'),
          ],
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => FincaDetailScreen(
                finca: finca,
              ),
            ),
          );
        },
      ),
    );
  }
}
