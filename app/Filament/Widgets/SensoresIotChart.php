<?php

namespace App\Filament\Widgets;

use App\Models\LecturaIot;
use Filament\Widgets\ChartWidget;

class SensoresIotChart extends ChartWidget
{
    // Cambiamos el título para reflejar que es una lectura en tiempo real
    protected ?string $heading = 'Estado Actual de Sensores IoT';
    
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isSuper = $user->isSuperAdmin();

        // 1. Configuramos los sensores y amarramos los colores fijos a cada uno
        $configuracion = [
            'humedad_suelo'    => ['nombre' => 'Humedad del Suelo %', 'color' => '#ef4449'], // Rojo
            'humedad_ambiente' => ['nombre' => 'Humedad Ambiente %',  'color' => '#3b82f6'], // Azul
            'temperatura'      => ['nombre' => 'Temperatura °C',       'color' => '#f59e0b'], // Amarillo
            'radiacion_solar'  => ['nombre' => 'Radiación Solar W/m²',   'color' => '#10b981'], // Verde
        ];

        $valores = [];
        $etiquetas = [];
        $coloresFondo = [];
        $coloresBorde = [];

        // 2. Buscamos el ÚLTIMO valor para cada tipo de sensor
        foreach ($configuracion as $tipo => $datos) {
            $ultimaLectura = LecturaIot::query()
                ->when(! $isSuper, fn ($query) => $query->whereHas('lote.finca', fn ($q) => $q->where('user_id', $user->id)))
                ->where('tipo_medicion', $tipo)
                ->orderBy('fecha_medicion', 'desc') // Ordenamos por el más reciente
                ->first(); // Tomamos solo 1 registro (el último)

            // Si el sensor tiene al menos una lectura, lo pintamos en la gráfica
            if ($ultimaLectura) {
                $valores[] = $ultimaLectura->valor; // Aquí inyectamos el valor numérico real
                $etiquetas[] = $datos['nombre'];
                $coloresFondo[] = $datos['color'] . '80'; // '80' al final aplica transparencia
                $coloresBorde[] = $datos['color']; // Color sólido para el borde
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Última Medición Registrada',
                    'data' => $valores,
                    'backgroundColor' => $coloresFondo,
                    'borderColor' => $coloresBorde,
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $etiquetas,
        ];
    }

    protected function getType(): string
    {
        return 'polarArea';
    }
}