<?php

namespace App\Filament\Widgets;

use App\Models\LecturaIot;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class ComportamientoIotChart extends ChartWidget
{
    protected ?string $heading = 'Comportamiento de Sensores (Últimos 7 Días)';
    protected static ?int $sort = 3;
    protected ?string $maxHeight = '300px';
    // Hacemos que ocupe todo el ancho de la pantalla debajo de la tabla
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isSuper = $user->isSuperAdmin();

        // 1. Definimos los últimos 7 días para el Eje X
        $fechas = [];
        $etiquetasFechas = [];
        for ($i = 6; $i >= 0; $i--) {
            $fecha = now()->subDays($i)->format('Y-m-d');
            $fechas[] = $fecha;
            // Para que se vea bonito en la gráfica (ej: 23/05)
            $etiquetasFechas[] = now()->subDays($i)->format('d/m'); 
        }

        // 2. Traemos todos los registros de los últimos 7 días con la seguridad de usuario
        $registros = LecturaIot::query()
            ->when(! $isSuper, fn ($query) => $query->whereHas('lote.finca', fn ($q) => $q->where('user_id', $user->id)))
            ->where('fecha_medicion', '>=', now()->subDays(6)->startOfDay())
            ->get()
            // Agrupamos usando colecciones de Laravel por fecha (Y-m-d)
            ->groupBy(fn ($item) => Carbon::parse($item->fecha_medicion)->format('Y-m-d'));

        // 3. Configuramos cómo se verá cada línea
        $configuracion = [
            'temperatura'      => ['label' => 'Temperatura (°C)',       'color' => '#f59e0b'], // Amarillo
            'humedad_suelo'    => ['label' => 'Humedad Suelo (%)',      'color' => '#ef4444'], // Rojo
            'humedad_ambiente' => ['label' => 'Humedad Ambiente (%)',   'color' => '#3b82f6'], // Azul
            'radiacion_solar'  => ['label' => 'Radiación Solar',        'color' => '#10b981'], // Verde
        ];

        $datasets = [];

        // 4. Calculamos el promedio diario para cada tipo de sensor
        foreach ($configuracion as $tipo => $config) {
            $datosLinea = [];

            foreach ($fechas as $fechaX) {
                // Si hay registros en este día, los filtramos por el tipo de sensor
                if ($registros->has($fechaX)) {
                    $medicionesDelDia = $registros[$fechaX]->where('tipo_medicion', $tipo);
                    
                    // Si hubo mediciones de este tipo ese día, sacamos el promedio
                    if ($medicionesDelDia->count() > 0) {
                        $datosLinea[] = round($medicionesDelDia->avg('valor'), 1);
                    } else {
                        $datosLinea[] = 0; // O null si prefieres que la línea se corte
                    }
                } else {
                    $datosLinea[] = 0;
                }
            }

            $datasets[] = [
                'label' => $config['label'],
                'data' => $datosLinea,
                'borderColor' => $config['color'],
                'backgroundColor' => $config['color'] . '33', // Color con transparencia
                'fill' => true,      // Relleno suave bajo la línea
                'tension' => 0.4,    // Hace que la línea sea curva y elegante
                'borderWidth' => 2,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $etiquetasFechas,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}