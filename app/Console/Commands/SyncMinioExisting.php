<?php

namespace App\Console\Commands;

use App\Models\ArchivoMultimedia;
use App\Models\Finca;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncMinioExisting extends Command
{
    protected $signature = 'minio:sync-existing {--dry-run : Solo mostrar lo que se haria sin insertar}';
    protected $description = 'Sincroniza archivos existentes en MinIO con la tabla archivos_multimedia';

    public function handle(): int
    {
        $this->info('Iniciando sincronizacion de archivos MinIO -> Laravel...');
        $this->newLine();

        $disk = Storage::disk('s3');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: No se insertaran registros');
            $this->newLine();
        }

        $allFiles = $disk->allFiles();

        if (empty($allFiles)) {
            $this->error('No se encontraron archivos en el bucket de MinIO');
            return Command::FAILURE;
        }

        $this->info("Encontrados " . count($allFiles) . " archivos en MinIO");
        $this->newLine();

        $bar = $this->output->createProgressBar(count($allFiles));
        $bar->start();

        $created = 0;
        $skipped = 0;
        $noFinca = 0;
        $errors = [];

        foreach ($allFiles as $fileKey) {
            try {
                $result = $this->processFile($disk, $fileKey, $dryRun);

                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } elseif ($result === 'no_finca') {
                    $noFinca++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'file' => $fileKey,
                    'error' => $e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->newLine();

        $this->info('=== REPORTE FINAL ===');
        $this->line("Total archivos en MinIO: " . count($allFiles));
        $this->info("Registros creados: $created");
        $this->warn("Registros saltados (ya existian): $skipped");
        if ($noFinca > 0) {
            $this->error("Sin finca aprobada: $noFinca");
        }

        if (!empty($errors)) {
            $this->error("Errores: " . count($errors));
            $this->newLine();
            foreach ($errors as $err) {
                $this->error("  - {$err['file']}: {$err['error']}");
            }
        }

        return Command::SUCCESS;
    }

    private function processFile($disk, string $fileKey, bool $dryRun): string
    {
        if (ArchivoMultimedia::where('ruta_archivo', $fileKey)->exists()) {
            return 'skipped';
        }

        $parsed = $this->parseFileKey($fileKey);

        if (!$parsed) {
            return 'skipped';
        }

        $userId = $parsed['user_id'];
        $categoria = $parsed['categoria'];
        $tipoCarpeta = $parsed['tipo_carpeta'];
        $extension = $parsed['extension'];

        $finca = Finca::where('user_id', $userId)
            ->where('estado', 'aprobado')
            ->first();

        if (!$finca) {
            return 'no_finca';
        }

        $tipoArchivo = $this->guessTipoArchivo($tipoCarpeta, $extension);
        $pesoBytes = $disk->size($fileKey) ?? 0;

        if ($dryRun) {
            $this->line("  [DRY-RUN] {$fileKey} -> Finca #{$finca->id} ({$finca->nombre})");
            return 'created';
        }

        ArchivoMultimedia::create([
            'fileable_type' => Finca::class,
            'fileable_id'   => $finca->id,
            'ruta_archivo'  => $fileKey,
            'tipo_archivo'  => $tipoArchivo,
            'peso_bytes'    => $pesoBytes,
            'categoria'     => $categoria,
        ]);

        return 'created';
    }

    private function parseFileKey(string $fileKey): ?array
    {
        $pattern = '/^documentos\/usuario_(\d+)\/(seguimiento|enfermedad)\/(imagenes|audios|videos|documentos)\/[^\/]+\.(.+)$/';

        if (!preg_match($pattern, $fileKey, $matches)) {
            return null;
        }

        return [
            'user_id'     => (int) $matches[1],
            'categoria'   => $matches[2],
            'tipo_carpeta' => $matches[3],
            'extension'   => $matches[4],
        ];
    }

    private function guessTipoArchivo(string $tipoCarpeta, string $extension): string
    {
        return match ($tipoCarpeta) {
            'imagenes' => 'foto_campo',
            'audios'   => 'nota_audio',
            'videos'   => 'video_campo',
            default    => 'archivo_campo',
        };
    }
}
