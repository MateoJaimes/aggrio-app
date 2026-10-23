<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; color: #1e293b; font-family: Arial, Helvetica, sans-serif;">
    @if (! empty($preheader))
        <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; color: transparent;">
            {{ $preheader }}
        </div>
    @endif

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; background-color: #f1f5f9;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 560px; width: 100%;">
                    <tr>
                        <td style="padding: 0 8px 16px; color: #15803d; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">
                            Aggrio
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 32px;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 8px 0; color: #64748b; font-size: 12px; line-height: 18px; text-align: center;">
                            © {{ date('Y') }} Aggrio · Plataforma de trazabilidad agrícola
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
