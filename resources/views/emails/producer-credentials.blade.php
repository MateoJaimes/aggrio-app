<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credenciales de acceso</title>
</head>
<body style="margin: 0; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; background-color: #ffffff; line-height: 1.6;">
    <div style="max-width: 520px; margin: 0 auto; padding: 24px 0;">
        <p style="font-size: 18px; font-weight: 700; color: #15803d; margin: 0 0 20px 0;">
            Aggrio
        </p>

        <p style="font-size: 15px; margin: 0 0 16px 0;">
            Hola {{ $user->name }},
        </p>

        <p style="font-size: 15px; margin: 0 0 16px 0;">
            Tu solicitud de acceso @if(!empty($farmName)) para la finca <strong>{{ $farmName }}</strong> @endif ha sido aprobada. Ya puedes iniciar sesión en la plataforma utilizando las siguientes credenciales:
        </p>

        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 18px; margin: 20px 0;">
            <p style="margin: 0 0 8px 0; font-size: 14px;">
                <strong>Usuario:</strong> {{ $user->email }}
            </p>
            <p style="margin: 0; font-size: 14px;">
                <strong>Contraseña:</strong> <code style="font-family: monospace; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 14px;">{{ $password }}</code>
            </p>
        </div>

        <p style="font-size: 14px; color: #64748b; margin: 0 0 20px 0;">
            <em>Por tu seguridad, recuerda cambiar esta contraseña temporal una vez inicies sesión.</em>
        </p>

        <p style="font-size: 14px; margin: 0; color: #334155;">
            Saludos,<br>
            <strong>Equipo de Aggrio</strong>
        </p>
    </div>
</body>
</html>
