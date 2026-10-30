# Pruebas del panel del productor

El panel se abre en http://localhost:4200/campo. La solicitud pública está en http://localhost:4200/solicitar-acceso y la administración de solicitudes en http://localhost:4200/access-requests.

## Preparación

La migración de invitaciones y primer acceso se aplicó sobre PostgreSQL local con respaldo previo. No se borraron los datos existentes.

Mantener API y Angular activos. El trabajador de invitaciones usa una cola dedicada:

```bash
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:listen --queue=invitations --sleep=2 --tries=3 --timeout=60
# En otra terminal, dentro de frontend:
npm start -- --host 127.0.0.1 --port 4200
```

Para correo real, configurar `.env` con el servidor, puerto, esquema, usuario, contraseña y remitente del proveedor. No incluir credenciales en Git:

Durante las pruebas locales se puede usar `MAIL_MAILER=log` con `INVITATION_CONSOLE_ENABLED=true`. Al aprobar una solicitud nueva, la consola de `queue:listen --queue=invitations` muestra correo, contraseña temporal y enlace de acceso después de procesar la invitación. Para una solicitud aprobada que ya fue procesada, utilizar **Reenviar acceso**; si aún utilizaba contraseña temporal, se genera una nueva y la anterior queda invalidada. No se muestran contraseñas personales de cuentas existentes. La salida por consola se desactiva automáticamente con SMTP y nunca está disponible en producción.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=servidor-del-proveedor
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=usuario-del-proveedor
MAIL_PASSWORD=secreto-del-proveedor
MAIL_FROM_ADDRESS=remitente-verificado@tu-dominio
MAIL_FROM_NAME=AGGRIO
FRONTEND_URL=http://localhost:4200
```

Usar los valores y el esquema indicados por el proveedor. Después:

```bash
php artisan config:clear
php artisan mail:check-smtp --connect
```

La comprobación autentica contra SMTP sin enviar mensajes. Un transporte `log` no entrega correos a bandejas reales. En desarrollo local produce una vista privada de prueba accesible al Superadmin, marcada expresamente como prueba. En producción ese transporte no puede marcar una invitación como enviada. `sent` significa aceptado por el servidor de correo, no confirma lectura ni llegada a la bandeja principal.

## Recorrido de aceptación

Si la consola indica «Cuenta existente», utiliza **Generar contraseña temporal** en la solicitud aprobada. Esta acción está disponible únicamente para el Superadmin en pruebas locales con salida por consola habilitada; reemplaza expresamente la contraseña actual y cierra sus sesiones. El reenvío normal preserva una contraseña personal existente. No es posible recuperar en texto una contraseña almacenada como hash.

1. Enviar una solicitud con nombre, tipo y número de documento, correo real propio de prueba, finca y consentimiento. No se crea todavía una cuenta. Comprobar validación de campos vacíos y formatos inválidos.
2. Entrar como Superadmin, abrir Solicitudes de acceso y pulsar Permitir. Debe aparecer aprobada y el correo en cola. La aprobación genera siempre una contraseña temporal; si la cuenta ya existía, reemplaza la anterior y cierra sus sesiones. Actualizar para consultar el resultado del envío.
3. Comprobar la recepción real del correo con el usuario y contraseña temporal. Revisar spam si el proveedor aceptó el mensaje. Si hay un error, corregir la configuración y utilizar Reenviar acceso. El reenvío invalida una contraseña temporal anterior; preserva contraseñas personales de cuentas existentes.
4. Entrar con la contraseña temporal. La aplicación debe pedir únicamente una contraseña personal de al menos 12 caracteres con letras y números y su confirmación; no se vuelve a pedir la contraseña temporal, porque la sesión ya fue autenticada. Al guardar se actualiza la sesión y se abre el panel. Antes del cambio la API no permite consultar datos del campo. La contraseña temporal caduca en 24 horas; la recuperación por correo permite crear una nueva. Los cambios voluntarios posteriores sí requieren la contraseña personal actual.
5. Tras guardar, comprobar acceso al panel y consultar Mi cuenta: correo, documento registrado, ubicación y contacto. El productor puede editar nombre y teléfono, pero no rol, documento ni correo.
6. En Mis fincas, comprobar que se muestran todas las fincas propias y sus estados, superficie y GPS. La cuenta aprobada y la finca aprobada son decisiones separadas. El Superadmin debe aprobar la finca en Fincas antes de completar su perfil técnico.
7. Completar superficie, coordenadas y suelo. Crear lotes, editar sus datos y probar exceso de superficie. Archivar conserva la historia y la superficie asignada.
8. Registrar riego, aplicación y otras actividades con fecha y costo. Revisar el historial paginado y los totales del resumen.
9. Consultar lecturas por variable y período. Registrar una medición manual con unidad, fecha y motivo. Probar unidades y fechas inválidas. La prueba del firmware y sus claves requiere un dispositivo real.
10. Guardar una nota y archivos de imagen, PDF, audio y MP4. Comprobar descarga privada, rechazo de formatos/tamaños inválidos y GPS solo con permiso. Audio requiere el proveedor de transcripción y el trabajador de la cola general para producir texto.
11. Crear otro productor y comprobar que no puede abrir fincas, lotes, actividades, documentos ni archivos ajenos. Tampoco debe entrar al panel administrativo.
12. Cerrar sesión, probar recuperación de contraseña y comprobar que la contraseña anterior y las sesiones revocadas dejan de funcionar.

Para transcripción, configurar el proveedor y ejecutar un trabajador independiente con `php artisan queue:work --queue=default --timeout=180 --tries=3`. Para recuperación de la cola y limpieza de credenciales vencidas, el despliegue debe ejecutar `php artisan schedule:work` o el scheduler habitual. Las integraciones externas necesitan validación real; las pruebas simuladas de SMTP y almacenamiento no la sustituyen.

## Evidencia automatizada

La suite backend cubre aprobación, contraseña temporal, vencimiento, reenvío, secreto cifrado, correo simulado, fallo SMTP, vista previa privada, cambio de contraseña y acceso propio. Angular cubre el panel, sus pantallas, borradores y redirección de primer acceso. La comprobación adicional en PostgreSQL recorre solicitud, aprobación, login, cambio, finca, lote, actividad, lectura, nota y dashboard dentro de una transacción revertida; no deja usuarios temporales ni envía mensajes reales.
