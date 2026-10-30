# Recorrido sencillo de AGGRIO

## Quién hace cada paso

1. La persona solicita acceso e indica el nombre de su primera finca.
2. El superadmin aprueba el acceso y esa finca en una sola acción. Las solicitudes rechazadas o las fincas adicionales conservan su revisión separada.
3. El productor entra, cambia su contraseña temporal y completa nombre, hectáreas y ubicación de la finca.
4. El productor crea sus lotes indicando nombre y superficie. El cultivo se registra después, sin repetirlo al crear el lote.
5. En Mis cultivos registra qué sembró. Para un cultivo transitorio, el mismo formulario guarda el cultivo y su primera siembra de forma atómica.
6. En Trabajo del día registra labores y cosechas. En Mi dinero registra gastos, ventas y pagos posteriores.

El superadmin acompaña y corrige datos desde sus herramientas de supervisión; el productor puede llevar su operación cotidiana sin depender de él.

## Organización de las pantallas

- Inicio del productor: siguiente paso pendiente y accesos directos a seis tareas frecuentes.
- Mi finca: preparación básica por pasos, lista de lotes y datos complementarios plegados.
- Mis cultivos: cultivos y siembras/cortes.
- Trabajo del día: labores, cosechas y transportes.
- Mi dinero: resumen, gastos, ventas y pagos. El detalle financiero se despliega cuando se necesita.
- Mi cuenta y Ayuda paso a paso: contacto, seguridad y explicación de responsabilidades.
- Inicio de administración: solicitudes, fincas y acompañamiento. Las gráficas técnicas están plegadas.

La finca y el lote seleccionados se conservan entre las áreas durante la sesión. Los formularios muestran pasos cortos, nombres cotidianos y opciones avanzadas al final. Los datos históricos permanecen consultables; las actividades anteriores aparecen como historial y las nuevas se registran desde Trabajo del día.

## Cuentas sin duplicaciones

Un gasto sencillo requiere un valor total; el desglose por cantidad y precio es opcional. El costo de una labor se registra en esa labor o se desglosa en gastos relacionados, sin sumar dos veces ambos importes. Las ventas permiten pago completo, parcial o pendiente. Los equipos e infraestructura se separan del gasto de producción.

## Verificación

Los datos de demostración de `tests/fixtures/setup-ui-preview.php` y el servidor de vista previa utilizan una base SQLite privada y cookies separadas. No modifican la base PostgreSQL real. El tema usa el rojo #ec1c21 y colores semánticos de superficie, texto, controles y foco para los modos claro y oscuro.
