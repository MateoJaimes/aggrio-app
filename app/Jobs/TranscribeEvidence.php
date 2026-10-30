<?php

namespace App\Jobs;

use App\Models\ArchivoMultimedia;
use App\Services\TranscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TranscribeEvidence implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $evidenceId) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(TranscriptionService $service): void
    {
        $a = ArchivoMultimedia::find($this->evidenceId);
        if (! $a || $a->transcription_status === 'completed') {
            return;
        }
        $a->update(['transcription_status' => 'processing']);
        $text = $service->transcribeAudio($a->ruta_archivo, $a->storage_disk);
        if (! $text) {
            $a->update(['transcription_status' => 'pending']);
            throw new \RuntimeException('Transcripción no disponible.');
        }
        // Original notes are never overwritten by a background worker.
        $a->update(['transcription_text' => $text, 'transcription_status' => 'completed']);
    }

    public function failed(?\Throwable $exception): void
    {
        ArchivoMultimedia::find($this->evidenceId)?->update(['transcription_status' => 'failed']);
    }
}
