# Implementación de las semanas 3 y 4

Revisión de implementación local del 24 de octubre de 2026. Se toma como alcance el plan histórico de AGGRIO APP: cuenta del productor, fincas, lotes, labores genéricas, monitoreo, evidencia, dashboard propio y formulario público. La hoja mantiene responsables humanos y tareas de aceptación desde la semana 5. Implementación local y pruebas automatizadas no equivalen a aprobación de producción.

## Comportamiento implementado

- El productor entra a `/campo`, con navegación propia. No se envía al menú administrativo ni se genera un bucle de login. El Superadmin conserva su panel y segundo factor.
- El perfil permite modificar nombre y teléfono opcional; correo y rol no son editables desde esa superficie.
- Las fincas propias muestran pendientes, aprobadas y rechazadas. La vinculación se aprueba primero; después el productor completa superficie y GPS. Solo una finca aprobada y completa permite operación nueva.
- Los lotes propios pueden crearse, editarse y archivarse. Los cambios de superficie bloquean la fila de finca y computan en centésimas de hectárea; la administración cumple la misma regla. Archivar conserva historia y superficie, desactiva sus sensores y permite consulta histórica.
- Las actividades genéricas incluyen riego, aplicaciones y labores. Fecha, tipo y costo se validan; no se inventan inventario, dosis, consumo de agua ni planificación futura. Las correcciones administrativas requieren motivo y quedan auditadas. Las anulaciones no borran historia.
- El productor consulta telemetría paginada por variable y período. La medición manual tiene origen y motivo propios. Hardware usa identidad individual, `event_id` y hora de medición/recepción; el mismo evento no duplica lecturas ni modifica su valor. La producción exige clave incluso si se configura accidentalmente el modo legado.
- La evidencia admite notas, imágenes, audio, PDF y MP4, categorías y GPS opcional solicitado por el usuario. Los archivos se verifican por contenido y tamaño reales, se vinculan a usuario y recurso y se copian a una clave final privada distinta a la URL de subida. La descarga exige autorización; el interceptor no filtra tokens a almacenamiento externo.
- Las transcripciones trabajan en cola con estado y reintentos. El texto original no se sobrescribe. Los resultados reales del proveedor dependen de su configuración y se validan en el cierre de integración.
- El dashboard propio filtra cada agregado por propietario, conserva costos e historia de lotes archivados y separa variables/unidades. Los promedios diarios utilizan días de Colombia; reporte reciente no significa conexión en tiempo real.
- El formulario `/solicitar-acceso` captura nombre, documento, correo, finca y consentimiento, deduplica correos normalizados y aplica límite de frecuencia. Aprobar una solicitud genera siempre una contraseña temporal de 24 horas, incluso para una cuenta existente, y cierra sus sesiones anteriores. El correo incluye las credenciales y el enlace al login; el cambio de contraseña es obligatorio antes de consultar el panel. La invitación usa una cola dedicada, conserva el secreto cifrado hasta el envío y admite reenvío desde administración. Una vista previa local nunca se presenta como correo entregado.
- Las escrituras del espacio propio aceptan `Idempotency-Key`. El cliente conserva el identificador para reintentar tras fallos de red y recupera borradores de texto del mismo usuario/contexto. No se promete sincronización offline integral.

## Contrato del productor

Todas las rutas `/api/campo/*` requieren token de acceso y una solicitud aprobada. Un token de renovación no sirve para datos. Las rutas de negocio legadas siguen disponibles, con las mismas validaciones de estado en creación de lote, actividad y evidencia.

| Método | Ruta | Uso |
| --- | --- | --- |
| GET y PUT | `/api/campo/perfil` | Perfil propio y campos permitidos |
| GET | `/api/campo/fincas` | Todos los estados y superficie disponible |
| GET y POST | `/api/campo/fincas/{finca}/lotes` | Listado y creación; `archivados=1` incluye historia |
| PUT | `/api/campo/fincas/{finca}/lotes/{id}` | Editar sin trasladar el lote |
| DELETE | `/api/campo/lotes/{id}` | Archivar con `motivo` |
| GET y POST | `/api/campo/lotes/{lote}/actividades` | Historial paginado y registro |
| GET y POST | `/api/campo/lotes/{lote}/lecturas` | Historial paginado y medición manual |
| GET | `/api/campo/evidencia/{tipo}/{id}` | Evidencia del recurso autorizado |
| GET | `/api/campo/archivos/{id}/descargar` | Descarga privada autenticada |
| POST | `/api/campo/multimedia/archivo` | Carga multipart privada con verificación |
| GET | `/api/campo/dashboard` | Agregados propios |

Los historiales aceptan `page`; actividades admiten `desde`, `hasta` y `tipo_actividad`; lecturas admiten `desde`, `hasta` y `tipo_medicion`. La respuesta conserva `success` y `data`; los listados paginados contienen `data.data`, `current_page`, `last_page` y `total`.

Para carga prefirmada, `POST /api/minio/presigned-url` devuelve `upload_id`, `file_key`, `upload_url`, `upload_headers` y vencimiento. En almacenamiento local utiliza una ruta PUT firmada y acotada por tamaño; en S3 utiliza el SDK. `POST /api/multimedia/subir` finaliza la carga tras verificar el objeto y el ticket. Las claves finales y los bytes reales se obtienen del servidor. La descarga requiere autorización y no depende de enlaces públicos permanentes.

## Migraciones y ejecución local

Las migraciones del 24 de octubre añaden archivo lógico, auditoría, tickets de carga, idempotencia, identidad de evento IoT, estados de transcripción y teléfono opcional. Se aplicaron a PostgreSQL local después de crear un respaldo en `storage/app/private/backups`. No se ejecutó `migrate:fresh` sobre esa base.

```bash
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
cd frontend
npm start -- --host 127.0.0.1 --port 4200
```

Los trabajadores de correo/transcripción y el scheduler se gestionan por separado. No se procesaron mensajes reales pendientes durante esta implementación. Configurar y supervisar los workers antes del piloto; `DB_QUEUE_RETRY_AFTER` debe superar el timeout de transcripción. No exponer `APP_DEBUG`, credenciales ni buckets públicos en producción.

## Verificación y pendientes de cierre

El código de las semanas 3 y 4 está implementado. La documentación formal de resultados, casos, incidencias y aceptación se preparará en la fase posterior; este documento describe funcionalidad y pendientes operativos.

El refresh web HttpOnly con CSRF/origen y la administración de suspensión, reactivación y recuperación de cuentas también están implementados; consultar `cuentas-sesiones-y-permisos.md`. El cierre de integración mantiene la consola de dispositivos, CI, OpenAPI web, QA independiente, firmware real, entrega de correo, transcripción real, política de buckets, revisión de objetos históricos, restauración y despliegue. El producto vigente utiliza Angular para ambos roles. El almacenamiento local privado no valida por sí mismo una instalación de MinIO ni la disponibilidad del proveedor de transcripción.

```bash
php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml
cd frontend
npm test -- --watch=false
npm run build
```

Los módulos marcados ROADMAP permanecen fuera del cierre de esta versión.
