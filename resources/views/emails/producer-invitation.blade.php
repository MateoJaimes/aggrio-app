<!doctype html>
<html lang="es"><body>
<p>Hola {{ $name }},</p>
<h1>Tu acceso a AGGRIO está aprobado</h1>
<p>Ya puedes entrar al panel del productor para consultar tus fincas y administrar tus registros de campo.</p>
<p><strong>Correo de acceso:</strong> {{ $email }}</p>
@if ($temporaryPassword)
<p><strong>Contraseña temporal:</strong> <code>{{ $temporaryPassword }}</code></p>
<p>Vence el {{ \Illuminate\Support\Carbon::parse($expiresAt)->timezone('America/Bogota')->format('d/m/Y H:i') }} (hora de Colombia). Al iniciar sesión tendrás que cambiarla por una propia antes de consultar tus datos.</p>
@else
<p>Ya tienes una cuenta: utiliza tu contraseña habitual. No la hemos cambiado.</p>
@endif
<p><a href="{{ $url }}">Entrar a mi panel de AGGRIO</a></p>
<p>Si tu contraseña temporal caducó, utiliza «Olvidé mi contraseña» en el inicio de sesión. No compartas este mensaje.</p>
</body></html>
