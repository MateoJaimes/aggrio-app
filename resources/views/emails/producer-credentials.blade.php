<x-emails.layout
    title="Credenciales de acceso"
    preheader="Tu solicitud de acceso a Aggrio fue aprobada."
>
    <p style="margin: 0 0 20px; color: #1e293b; font-size: 16px; line-height: 24px;">
        Hola {{ $user->name }},
    </p>

    <h1 style="margin: 0 0 16px; color: #166534; font-size: 24px; line-height: 32px;">
        ¡Tu acceso fue aprobado!
    </h1>

    <p style="margin: 0 0 20px; color: #475569; font-size: 15px; line-height: 24px;">
        Tu solicitud de acceso
        @if (! empty($farmName))
            para la finca <strong>{{ $farmName }}</strong>
        @endif
        fue aprobada. Usa estas credenciales para iniciar sesión en Aggrio.
    </p>

    <div style="margin: 24px 0; padding: 18px; border: 1px solid #bbf7d0; border-radius: 8px; background-color: #f0fdf4;">
        <p style="margin: 0 0 10px; color: #334155; font-size: 14px; line-height: 20px;">
            <strong>Correo:</strong> {{ $user->email }}
        </p>
        <p style="margin: 0; color: #334155; font-size: 14px; line-height: 20px;">
            <strong>Contraseña temporal:</strong>
            <code style="padding: 3px 6px; border-radius: 4px; background-color: #dcfce7; color: #166534; font-family: monospace; font-size: 14px;">{{ $password }}</code>
        </p>
    </div>

    <p style="margin: 0; color: #64748b; font-size: 14px; line-height: 22px;">
        Por seguridad, cambia esta contraseña temporal después de iniciar sesión.
    </p>
</x-emails.layout>
