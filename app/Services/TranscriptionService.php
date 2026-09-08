<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TranscriptionService
{
    /**
     * Transcribe audio file from MinIO
     * 
     * @param string $fileKey The MinIO file key (e.g., 'documentos/usuario_1/seguimiento/audios/uuid.m4a')
     * @return string|null The transcribed text or null if failed
     */
    public function transcribeAudio(string $fileKey): ?string
    {
        try {
            $disk = Storage::disk('s3');
            
            if (!$disk->exists($fileKey)) {
                Log::error("Audio file not found in MinIO: $fileKey");
                return null;
            }

            $audioContent = $disk->get($fileKey);
            $tempFile = tempnam(sys_get_temp_dir(), 'transcribe_');
            file_put_contents($tempFile, $audioContent);

            $result = $this->transcribeFile($tempFile);

            unlink($tempFile);

            return $result;
        } catch (\Exception $e) {
            Log::error("Transcription failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Transcribe audio file using configured service
     */
    private function transcribeFile(string $filePath): ?string
    {
        $provider = config('services.transcription.provider', 'openai');

        return match ($provider) {
            'openai' => $this->transcribeWithOpenAI($filePath),
            'google' => $this->transcribeWithGoogle($filePath),
            'assembly' => $this->transcribeWithAssembly($filePath),
            default => null,
        };
    }

    /**
     * Transcribe using OpenAI Whisper API
     */
    private function transcribeWithOpenAI(string $filePath): ?string
    {
        $apiKey = config('services.transcription.openai_api_key');
        if (!$apiKey) {
            Log::warning('OpenAI API key not configured for transcription');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $apiKey",
            ])->attach(
                'file',
                file_get_contents($filePath),
                basename($filePath)
            )->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => 'es',
                'response_format' => 'text',
            ]);

            if ($response->successful()) {
                return trim($response->body());
            }

            Log::error("OpenAI transcription failed: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("OpenAI transcription error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Transcribe using Google Cloud Speech-to-Text API
     */
    private function transcribeWithGoogle(string $filePath): ?string
    {
        $apiKey = config('services.transcription.google_api_key');
        if (!$apiKey) {
            Log::warning('Google API key not configured for transcription');
            return null;
        }

        try {
            $audioContent = base64_encode(file_get_contents($filePath));

            $response = Http::post(
                "https://speech.googleapis.com/v1/speech:recognize?key=$apiKey",
                [
                    'config' => [
                        'encoding' => 'MP4',
                        'sampleRateHertz' => 16000,
                        'languageCode' => 'es-ES',
                        'model' => 'default',
                    ],
                    'audio' => [
                        'content' => $audioContent,
                    ],
                ]
            );

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['results'][0]['alternatives'][0]['transcript'])) {
                    return $data['results'][0]['alternatives'][0]['transcript'];
                }
            }

            Log::error("Google transcription failed: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Google transcription error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Transcribe using AssemblyAI API
     */
    private function transcribeWithAssembly(string $filePath): ?string
    {
        $apiKey = config('services.transcription.assembly_api_key');
        if (!$apiKey) {
            Log::warning('AssemblyAI API key not configured for transcription');
            return null;
        }

        try {
            $uploadResponse = Http::withHeaders([
                'authorization' => $apiKey,
            ])->attach(
                'file',
                file_get_contents($filePath),
                basename($filePath)
            )->post('https://api.assemblyai.com/v2/upload');

            if (!$uploadResponse->successful()) {
                Log::error("AssemblyAI upload failed: " . $uploadResponse->body());
                return null;
            }

            $uploadUrl = $uploadResponse->json()['upload_url'];

            $transcriptResponse = Http::withHeaders([
                'authorization' => $apiKey,
                'content-type' => 'application/json',
            ])->post('https://api.assemblyai.com/v2/transcript', [
                'audio_url' => $uploadUrl,
                'language_code' => 'es',
            ]);

            if (!$transcriptResponse->successful()) {
                Log::error("AssemblyAI transcription failed: " . $transcriptResponse->body());
                return null;
            }

            $transcriptId = $transcriptResponse->json()['id'];

            // Poll for completion
            $maxAttempts = 30;
            $attempt = 0;
            while ($attempt < $maxAttempts) {
                sleep(2);
                $statusResponse = Http::withHeaders([
                    'authorization' => $apiKey,
                ])->get("https://api.assemblyai.com/v2/transcript/$transcriptId");

                $status = $statusResponse->json()['status'];
                if ($status === 'completed') {
                    return $statusResponse->json()['text'];
                }
                if ($status === 'error') {
                    Log::error("AssemblyAI transcription error: " . $statusResponse->body());
                    return null;
                }
                $attempt++;
            }

            Log::error("AssemblyAI transcription timeout");
            return null;
        } catch (\Exception $e) {
            Log::error("AssemblyAI transcription error: " . $e->getMessage());
            return null;
        }
    }
}
