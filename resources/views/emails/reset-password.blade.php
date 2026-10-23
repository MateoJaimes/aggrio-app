<x-emails.layout
    title="Restablece tu contraseña"
    preheader="Recibimos una solicitud para restablecer tu contraseña de Aggrio."
>
    <p style="margin: 0 0 20px; color: #1e293b; font-size: 16px; line-height: 24px;">
        Hola {{ $user->name }},
    </p>

    <h1 style="margin: 0 0 16px; color: #166534; font-size: 24px; line-height: 32px;">
        Restablece tu contraseña
    </h1>

    <p style="margin: 0 0 24px; color: #475569; font-size: 15px; line-height: 24px;">
        Recibimos una solicitud para restablecer la contraseña de tu cuenta. Selecciona el botón para crear una nueva.
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 0 24px;">
        <tr>
            <td style="border-radius: 7px; background-color: #15803d;">
                <a href="{{ $resetUrl }}" style="display: inline-block; padding: 12px 20px; color: #ffffff; font-size: 14px; font-weight: 700; line-height: 20px; text-decoration: none;">
                    Restablecer contraseña
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 12px; color: #64748b; font-size: 14px; line-height: 22px;">
        Este enlace caduca en {{ $minutes }} minutos.
    </p>
    <p style="margin: 0; color: #64748b; font-size: 14px; line-height: 22px;">
        Si no solicitaste este cambio, puedes ignorar este correo: tu contraseña no se modificará.
    </p>
</x-emails.layout>
