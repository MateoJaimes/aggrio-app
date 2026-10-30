# Producción y rentabilidad

Alcance aprobado el 27 de octubre de 2026. El módulo `/produccion` está disponible desde la barra lateral de ambos roles, Mi campo y el dashboard administrativo.

## Recorrido de uso

1. Crear y habilitar la finca con el flujo existente; completar superficie y coordenadas.
2. En **Parametrización**, registrar tenencia, identificación del predio, asistencia técnica, autorización de datos, ubicación, áreas, agua, infraestructura y clima. Los lotes y las áreas reservadas no pueden superar la finca.
3. Crear y parametrizar lotes: estado agronómico, ambiente, suelo, pendiente, drenaje, riego y contorno. El contorno se captura con coordenadas de vértices y vista previa. Las estructuras protegidas habilitan cubierta, ventilación, sensores y demás datos específicos.
4. Registrar cada cultivo o plantación con duración, especie, variedad, material de siembra, proveedor, origen, número de plantas, área, densidad, sistema y responsable. La fecha permite calcular año de siembra y edad. Las plantaciones permanentes conservan su identidad durante los años.
5. Crear un ciclo por siembra transitoria, o ciclos/cortes para organizar semipermanentes y permanentes. Registrar cultivo anterior, cosecha estimada, producción esperada y unidad. Los ciclos cerrados conservan el historial y pueden reabrirse.
6. Generar la guía inicial desde el cultivo/ciclo: análisis de suelo, preparación, material, contratación, maquinaria, riego y siembra, sin duplicar tareas generadas. Ajustar responsables, fechas y presupuestos. Terminar una labor crea una actividad en el módulo existente.
7. Registrar partidas de costo con recurso, categoría, proveedor/contratista, modalidad, cantidad, unidad, tarifa y comprobante. Jornales y horas de maquinaria utilizan el mismo desglose. Separar producción de inversión.
8. Distribuir gastos generales mediante cultivo/ciclo destino, porcentaje y criterio. El importe no asignado permanece visible como gasto sin asignar.
9. Registrar cosecha, pérdidas, calidad y lote de producto. Su unidad también se utiliza en ventas y transportes asociados.
10. Registrar venta, comprador, cantidad, precio y cobro inicial. Registrar pagos posteriores en **Abonos y cobros** con fecha y comprobante. No se permite exceder producto disponible ni saldo.
11. Registrar transporte por lote de producto: origen, destino, transportista, placa, carga, remisión y entrega. Su costo se registra como partida de categoría transporte.
12. Consultar reportes por finca, lote, cultivo, ciclo y fechas. Exportar movimientos financieros o registros de cada sección en CSV.

**Certificaciones** conserva expedición y vencimiento. **Análisis de suelo** conserva fecha, laboratorio, pH y nutrientes por lote. El resumen muestra vencimientos y labores pendientes.

## Cálculos

- Partida o venta = cantidad × precio unitario, redondeado a dos decimales en COP.
- El desglose de una actividad reemplaza su costo general y actualiza los indicadores anteriores; no se suma dos veces.
- Los gastos generales se distribuyen una sola vez. La suma de porcentajes no supera 100%. El redondeo acumulado conserva el importe original, incluso para centavos.
- Resultado operativo = ventas − costos de producción. Infraestructura y equipos aparecen como inversión separada, sin depreciación automática.
- Cobrado = cobros iniciales y abonos por fecha de cada pago. El saldo corresponde a las ventas seleccionadas y sus pagos registrados.
- Rendimiento real = cosechas − pérdidas. Las unidades distintas no se suman. El costo por unidad requiere que todas las cosechas seleccionadas usen la misma unidad.
- El presupuesto utiliza la fecha programada; los costos utilizan la fecha de ejecución/gasto. Los rendimientos por ciclo muestran su acumulado completo.

## Desplegables y trazabilidad

Se utilizan opciones fijas para clasificaciones, listas dependientes para finca/lote/cultivo/ciclo y listas con búsqueda para catálogos. Hay opciones iniciales de especies, variedades, unidades, calidades y departamentos. Los valores nuevos escritos se conservan como opciones reutilizables de la finca. Variedades, municipios y veredas admiten un padre para filtrar las opciones. Cantidades, precios, fechas, nombres y observaciones mantienen controles apropiados.

Fincas, lotes y registros tienen UUID y QR. El QR abre el registro sujeto a sesión y permisos. La trazabilidad muestra datos, relaciones, evidencias e historial. Las evidencias admiten notas, archivos y GPS opcional; la descarga exige autorización. También aparecen en la galería administrativa y su filtro de propietario.

Cada modificación conserva versión, usuario, fecha y dispositivo/navegador. Una edición con versión antigua devuelve 409 y mantiene el formulario. Se archivan registros independientes con motivo, conservando historial. No se archivan registros con movimientos dependientes ni labores ejecutadas. Las escrituras aceptan `Idempotency-Key`; el formulario reutiliza la clave al reintentar el mismo envío tras un fallo de red.

## Integración y migración

`ProductionSchema` define campos y reglas compartidas por API y formularios. `production_records` conserva tipos y relaciones de cultivo, ciclo, cosecha, venta y gasto, vinculados con finca/lote. Los campos JSON se validan por tipo; identidad, contexto, importes y versión tienen columnas propias.

Las rutas `/api/produccion` requieren acceso y cuenta aprobada. El productor accede a sus fincas y el administrador conserva la consulta global. Las operaciones requieren finca habilitada y lote disponible. Las credenciales y los flujos existentes se conservan.

La migración `2026_10_28_000001_production_management.php` es aditiva. Asigna UUID a fincas y lotes e importa referencias de cultivo de los lotes existentes, conservando variedad, fecha, área y vínculo de actividades. La duración queda **por clasificar** para no inferirla del nombre. Los datos originales del lote se conservan.

`php scripts/apply-production.php` respalda PostgreSQL local en `storage/app/private/backups/`, aplica únicamente esta migración y verifica los recuentos anteriores. Se limita al entorno local. El respaldo permanece privado y fuera del control de versiones.

`php scripts/check-production.php` verifica en modo de solo lectura el inicio, propiedad, reportes y QR para los titulares de la base configurada.

La paleta compartida utiliza `#ec1c21` en temas claro y oscuro, paneles y portada pública; los colores de estado conservan su significado.

## Validación

Backend: actividad, desglose, cosecha, venta, abonos y resultado; reparto y redondeo; disponibilidad y saldo; permisos y evidencias; versiones; áreas y contornos; guía inicial; cierre de ciclos; exportación y conservación del historial.

Angular: contexto de listas, variedades dependientes, reintentos y conflictos de edición, junto con la suite existente de sesiones y paneles.
