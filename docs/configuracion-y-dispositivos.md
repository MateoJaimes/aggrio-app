# Configuración y dispositivos del superadmin

La sección **Configuración** está en `/configuracion`, dentro de la barra lateral administrativa. Las rutas requieren una sesión de acceso del superadmin; los productores no pueden utilizarlas.

## Navegadores recordados

El inicio de sesión sigue comprobando la contraseña. Tras verificar un código de aplicación o recuperación se puede marcar **Recordar este navegador durante 30 días** y asignarle un nombre opcional. En los siguientes ingresos de ese navegador no se pide otro código mientras la autorización siga vigente.

La autorización utiliza una cookie cifrada, HttpOnly, SameSite Strict, sin dominio compartido y Secure en producción. En la base se almacena un hash del secreto aleatorio. La cookie está ligada a la cuenta, su contraseña, su versión de seguridad y el navegador; el nombre del equipo no es una credencial. La credencial se rota en cada ingreso reconocido sin prolongar el plazo original. Un cliente nativo no puede utilizarla para omitir el doble factor.

La contraseña incorrecta sigue rechazándose. Un navegador diferente, sin cookie, revocado o vencido vuelve a pedir código. Cambiar de perfil, borrar cookies o actualizar la identificación del navegador puede requerir otra verificación. La opción se recomienda para el equipo personal; no identifica físicamente el hardware.

**Olvidar** revoca la autorización para futuros ingresos, sin terminar una sesión ya abierta. El cierre de sesión ordinario conserva el navegador recordado; cerrar todas las sesiones y cambiar la contraseña revocan las autorizaciones. Las credenciales vencidas se retiran mediante el scheduler.

## Aplicaciones y teléfonos

1. Elegir **Añadir aplicación o teléfono** o **Cambiar aplicación principal**.
2. Introducir contraseña actual y un código de un autenticador confirmado, o un código de recuperación.
3. Asignar nombre, escanear el QR con la nueva aplicación e introducir su código.
4. La nueva aplicación se activa únicamente tras confirmar ese código. El registro pendiente vence a los diez minutos y admite hasta cinco intentos; también está ligado a la sesión que lo inició.

Cada aplicación adicional tiene un secreto distinto, cifrado en la base. Las aplicaciones confirmadas pueden usarse indistintamente al entrar. Los secretos existentes no se vuelven a exponer en Configuración.

Al reemplazar la aplicación principal, la anterior sigue funcionando hasta confirmar la nueva. **Retirar** exige contraseña y segundo factor. No permite eliminar el último autenticador; al retirar el principal se promueve una aplicación adicional confirmada. Se admiten hasta diez autenticadores confirmados.

Un cambio de autenticadores revoca los navegadores recordados, invalida retos y registros pendientes y cierra las otras sesiones. La sesión desde la que se realizó el cambio se conserva. Los eventos de seguridad registran acción, nombre del dispositivo, fecha e IP, sin contraseñas, códigos ni secretos.

## Recuperación

**Generar nuevos códigos** exige contraseña y segundo factor actual o un código de recuperación válido. Invalida los anteriores y muestra ocho códigos una sola vez. Se almacenan como hashes y cada uno se consume una sola vez. El cliente mantiene QR, contraseña y códigos únicamente en memoria y los descarta al salir de la sección.

## Limpieza local solicitada

`scripts/clean-local-database.php` inspecciona PostgreSQL local. Solo ejecuta cambios con `--execute`, en entorno `local`, y cuando existe exactamente un superadmin. Antes de limpiar genera un respaldo completo privado en `storage/app/private/backups/`.

La limpieza conserva la fila original del superadmin, incluida su contraseña y configuración del doble factor, aplica la migración de seguridad y vacía los otros usuarios, datos operativos, sesiones, retos, colas y registros auxiliares. Mantiene las tablas y el historial de migraciones. Verifica dentro de una transacción que la cuenta protegida esté intacta y las demás tablas vacías; genera un informe privado del resultado. No elimina archivos del almacenamiento ni respaldos.

Para el siguiente ingreso se requiere la verificación habitual una vez, ya que las sesiones se retiraron. Después se puede recordar el navegador.

## Validación

Las pruebas cubren contraseña obligatoria, cookie HttpOnly y rotación, caducidad, revocación, cambio de navegador y cuenta, clientes nativos, cifrado de secretos, confirmación de nuevas apps, cambio y promoción del autenticador principal, protección del último autenticador, límites de intentos, vinculación de la sesión, conservación de la sesión actual y códigos de recuperación de un solo uso.
