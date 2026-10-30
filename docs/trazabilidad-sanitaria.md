# Trazabilidad sanitaria por cultivo

La sección Sanidad del cultivo está disponible en el menú del productor y del superadmin. Usa la finca, lote y cultivo seleccionados; los datos de otra cuenta se excluyen con el mismo alcance de acceso que el resto de producción.

## Recorrido

1. Problema detectado: plaga, enfermedad, maleza u otro problema; sospecha o confirmación; síntomas, responsable y medición inicial.
2. Tratamiento o decisión: medida planificada, aplicada o no realizada; motivo, responsable, método y revisión prevista. Los datos de una aplicación real se guardan únicamente cuando se marca como aplicada.
3. Seguimiento: referencia al problema y opcionalmente a una medida; nueva medición, resultado observado, efectos adversos y siguiente acción.

Cada registro conserva UUID, versión, autor, fecha y auditoría. Las fotos, documentos y notas se adjuntan desde Ver detalle. La detección muestra sus medidas y revisiones relacionadas. Los gastos se registran una sola vez en Mi dinero con el cultivo; una medida sanitaria no crea gastos automáticamente.

## Mediciones y analítica

La incidencia se calcula en el servidor: unidades afectadas / unidades revisadas × 100. La muestra debe ser positiva y las afectadas no pueden superar las revisadas. La severidad se guarda por separado, de 0 a 100.

La vista de evolución compara incidencia inicial y última revisión solamente cuando coinciden la unidad y la descripción del método. Muestra una reducción relativa, que puede ser negativa. Una medición inicial de cero no permite calcular una reducción relativa. El resultado es descriptivo, no una prueba causal de eficacia. El enlace a las cosechas permite revisar el rendimiento registrado para el mismo cultivo; comparar rendimiento entre campañas requerirá controlar área, clima, variedad y otras intervenciones.

Los CSV incluyen identificadores de los registros y relaciones, UUID, fechas, datos estructurados, incidencia y versión, para unir los conjuntos de casos, tratamientos, revisiones y cosechas en análisis posteriores. Las observaciones anteriores sin datos suficientes no se convierten artificialmente en mediciones.

## Aplicaciones

Para aplicaciones químicas se exige producto, ingrediente activo, registro, lote del insumo, dosis y unidad, cantidad utilizada y unidad, periodos de carencia y reingreso. Son datos transcritos de la etiqueta y de la recomendación técnica: la app no prescribe productos, diagnósticos ni dosis. No reemplaza la revisión técnica ni calcula autorizaciones para cosechar.

Referencia de diseño: [FAO, cómo practicar el manejo integrado de plagas](https://www.fao.org/agriculture/crops/thematic-sitemap/theme/spi/scpi-home/managing-ecosystems/integrated-pest-management/ipm-how/en/), que conecta seguimiento, decisiones y evaluación de las medidas aplicadas.
