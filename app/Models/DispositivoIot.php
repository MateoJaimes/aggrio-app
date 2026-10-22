<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Sensor físico (ESP32) autorizado a enviar lecturas. RF002.
 *
 * El dispositivo se identifica con la cabecera X-Device-Key. La key en claro
 * sólo se entrega al registrarlo o al regenerarla; en la base queda su hash.
 */
class DispositivoIot extends Model
{
    public const KEY_HEADER = 'X-Device-Key';

    protected $table = 'dispositivos_iot';

    protected $fillable = [
        'lote_id',
        'nombre',
        'mac_dispositivo',
        'activo',
    ];

    protected $hidden = [
        'api_key_hash',
    ];

    // Igual al default de la columna, para que la respuesta del alta lo incluya.
    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultima_conexion' => 'datetime',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    /**
     * Genera una key nueva, guarda su hash y devuelve la key en claro.
     * La anterior deja de funcionar en cuanto se guarda el modelo.
     */
    public function asignarNuevaKey(): string
    {
        $plain = 'iot_'.Str::random(40);

        $this->api_key_hash = self::hashKey($plain);

        return $plain;
    }

    public static function buscarActivoPorKey(string $plain): ?self
    {
        return self::where('api_key_hash', self::hashKey($plain))
            ->where('activo', true)
            ->first();
    }

    private static function hashKey(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
