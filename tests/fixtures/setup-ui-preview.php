<?php

use App\Models\AccessRequest;
use App\Models\Actividad;
use App\Models\Finca;
use App\Models\Lote;
use App\Models\ProductionRecord;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = storage_path('app/private/ui-preview.sqlite');
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path, 'database.connections.sqlite.url' => null]);
if (is_file($path)) { echo "La base aislada de vista previa ya existe.\n"; exit(0); }
touch($path);
Artisan::call('migrate', ['--force' => true]);
$user = User::create(['name' => 'María del Campo', 'email' => 'productor@aggrio.test', 'password' => 'CampoDemo2026', 'role' => 'productor']);
User::create(['name' => 'Supervisión demo', 'email' => 'admin@aggrio.test', 'password' => 'AdminDemo2026', 'role' => 'superadmin']);
AccessRequest::create(['firstname' => 'María', 'lastname' => 'del Campo', 'id_type' => 'CC', 'id_number' => 'DEMO', 'landname' => 'El Porvenir', 'email' => $user->email, 'country' => 'Colombia', 'department' => 'Santander', 'city' => 'Socorro', 'status' => 'approved']);
AccessRequest::create(['firstname' => 'Pedro', 'lastname' => 'Demo', 'id_type' => 'CC', 'id_number' => 'DEMO2', 'landname' => 'El Recuerdo', 'email' => 'solicitud@aggrio.test', 'country' => 'Colombia', 'department' => 'Santander', 'city' => 'Socorro', 'status' => 'pending']);
$farm = Finca::create(['user_id' => $user->id, 'nombre' => 'El Porvenir', 'estado' => 'aprobado', 'hectareas_totales' => 12, 'latitud' => 6.46, 'longitud' => -73.26, 'tipo_suelo' => 'franco']);
Finca::create(['user_id' => $user->id, 'nombre' => 'La Esperanza', 'estado' => 'pendiente']);
$plot = Lote::create(['finca_id' => $farm->id, 'nombre' => 'El cafetal', 'hectareas' => 2, 'tipo_cultivo' => 'Café', 'estado' => 'en_uso']);
Lote::create(['finca_id' => $farm->id, 'nombre' => 'La huerta', 'hectareas' => 1, 'tipo_cultivo' => 'Sin cultivo', 'estado' => 'disponible']);
$base = ['finca_id' => $farm->id, 'lote_id' => $plot->id, 'estado' => 'activo'];
$crop = ProductionRecord::create($base + ['tipo' => 'cultivos', 'nombre' => 'Café Castillo', 'fecha' => '2023-04-10', 'datos' => ['tipo' => 'permanente', 'especie' => 'Café', 'variedad' => 'Castillo', 'area' => 2, 'numero_plantas' => 8000, 'sistema' => 'monocultivo', 'responsable' => $user->name]]);
$cycle = ProductionRecord::create($base + ['cultivo_id' => $crop->id, 'tipo' => 'ciclos', 'nombre' => 'Cosecha de fin de año', 'fecha' => '2026-09-01', 'datos' => ['area' => 2, 'unidad' => 'kg', 'rendimiento_esperado' => 500, 'fecha_cosecha_estimada' => '2026-12-20']]);
$context = $base + ['cultivo_id' => $crop->id, 'ciclo_id' => $cycle->id];
$day = now('America/Bogota')->toDateString();
$activity = Actividad::create(['lote_id' => $plot->id, 'tipo_actividad' => 'riego', 'fecha' => $day, 'costo' => 34000]);
$activity->cultivo_id = $crop->id; $activity->ciclo_id = $cycle->id; $activity->save();
ProductionRecord::create(array_merge($context, ['tipo' => 'labores', 'nombre' => 'Riego del cafetal', 'fecha' => $day, 'estado' => 'terminado', 'actividad_id' => $activity->id, 'datos' => ['tipo_actividad' => 'riego', 'responsable' => $user->name, 'fecha_real' => $day, 'costo_real' => 34000]]));
ProductionRecord::create(array_merge($context, ['tipo' => 'labores', 'nombre' => 'Revisar el suelo', 'fecha' => now('America/Bogota')->addDay()->toDateString(), 'estado' => 'pendiente', 'datos' => ['tipo_actividad' => 'analisis_suelo', 'responsable' => $user->name, 'presupuesto' => 60000]]));
ProductionRecord::create($context + ['tipo' => 'costos', 'nombre' => 'Abono para el café', 'fecha' => $day, 'total' => 80000, 'datos' => ['naturaleza' => 'produccion', 'categoria' => 'fertilizantes', 'recurso' => 'Abono', 'cantidad' => 2, 'unidad' => 'kg', 'precio_unitario' => 40000]]);
$harvest = ProductionRecord::create($context + ['tipo' => 'cosechas', 'nombre' => 'Café recogido esta semana', 'fecha' => $day, 'datos' => ['cantidad' => 120, 'perdida' => 4, 'unidad' => 'kg', 'calidad' => 'Primera', 'lote_producto' => 'CAFE-DEMO-01']]);
ProductionRecord::create($context + ['cosecha_id' => $harvest->id, 'tipo' => 'ventas', 'nombre' => 'Venta a la cooperativa', 'fecha' => $day, 'total' => 720000, 'datos' => ['cantidad' => 60, 'precio_unitario' => 12000, 'comprador' => 'Cooperativa demo', 'cobrado' => 450000, 'fecha_cobro' => $day, 'unidad' => 'kg']]);
echo "Vista previa aislada creada. No se modificó PostgreSQL ni se enviaron correos.\n";
