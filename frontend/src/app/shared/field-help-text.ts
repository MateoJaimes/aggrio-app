/** Plain-language explanations shared by all form labels. */
export function fieldHelpText(
  caption: string,
  control: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement,
): string {
  const title = caption
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
  const rules: [RegExp, string][] = [
    [
      /dosis utilizada|unidad de la dosis/,
      'Copia la dosis realmente utilizada y su unidad según la etiqueta y la recomendación técnica. No confundas la dosis con la cantidad total de producto. La app no recomienda dosis.',
    ],
    [
      /carencia/,
      'Días que indica la etiqueta entre la última aplicación y la cosecha. Copia el valor del producto; no lo estimes.',
    ],
    [
      /reingreso/,
      'Horas de espera que indica la etiqueta antes de volver a entrar al área tratada. Copia el valor del producto.',
    ],
    [
      /unidades revisadas/,
      'Número total de plantas, hojas, frutos o trampas que inspeccionaste. Debe ser mayor que cero.',
    ],
    [
      /unidades afectadas/,
      'De las unidades revisadas, cuántas presentaron el problema. No puede superar el total inspeccionado.',
    ],
    [
      /severidad/,
      'Porcentaje de daño observado, de 0 a 100, usando el mismo criterio en las revisiones. Es diferente de cuántas plantas están afectadas.',
    ],
    [
      /muestreo/,
      'Describe el recorrido, ubicación y forma de inspección. Repite el método y la unidad para que las revisiones puedan compararse.',
    ],
    [
      /diagnostico/,
      'Indica si es una sospecha del productor o si un técnico o laboratorio confirmó el problema.',
    ],
    [
      /lote del producto aplicado/,
      'Identificador del lote de fabricación que aparece en el envase del producto utilizado.',
    ],
    [
      /ingrediente activo|registro del producto/,
      'Copia exactamente el dato que aparece en la etiqueta del producto utilizado.',
    ],
    [
      /producto aplicado/,
      'Nombre comercial del producto realmente utilizado. Conserva una foto de la etiqueta en las evidencias.',
    ],
    [
      /tipo de control/,
      'Distingue observación, prácticas del cultivo, control mecánico, biológico, químico o una combinación de métodos.',
    ],

    [
      /^nombres?$|primer nombre|segundo nombre/,
      'Escribe tus nombres tal como aparecen en tu documento de identidad.',
    ],
    [/apellido/, 'Escribe tus apellidos tal como aparecen en tu documento de identidad.'],
    [
      /tipo de documento/,
      'Selecciona el documento de identidad que vas a registrar, por ejemplo cédula de ciudadanía o pasaporte.',
    ],
    [
      /como llamas|nombre.*(finca|lote|cultivo)|nombre o referencia/,
      'Escribe un nombre corto que reconozcas después. Ejemplo: El Porvenir para una finca o La huerta para un lote.',
    ],
    [
      /latitud/,
      'Coordenada norte o sur de la finca. Ejemplo: 6.46. Puedes obtenerla con el botón de ubicación o copiarla de un mapa.',
    ],
    [
      /longitud/,
      'Coordenada este u oeste de la finca. En Colombia suele ser negativa; por ejemplo, -73.26.',
    ],
    [
      /precision.*gps/,
      'Margen de error de la ubicación, en metros, informado por el dispositivo. Un número menor indica mayor precisión.',
    ],
    [
      /altitud/,
      'Altura sobre el nivel del mar, en metros. Ejemplo: 1250. Consulta un mapa o la ficha técnica de tu finca.',
    ],
    [
      /cuanto dura|duracion del cultivo/,
      'Elige por temporada si se siembra y cosecha en un ciclo; varios años para una plantación permanente; varios cortes si vuelve a producir antes de renovarla.',
    ],
    [
      /plantas|plantulas/,
      'Cantidad total de plantas sembradas en esta siembra o lote. Ejemplo: 1000 plantas; no escribas aquí kilos ni hectáreas.',
    ],
    [
      /densidad/,
      'Cantidad de plantas por hectárea. Divide el número de plantas entre las hectáreas sembradas.',
    ],
    [
      /cosecha estimada|esperas cosechar/,
      'Fecha aproximada en la que esperas empezar a recoger esta siembra. Puedes ajustarla después.',
    ],
    [
      /cantidad que esperas|produccion esperada|rendimiento esperado/,
      'Cantidad de producto que esperas cosechar. Usa la unidad indicada, por ejemplo 500 kg.',
    ],
    [/fecha de siembra/, 'Día en que sembraste o comenzaste este ciclo o corte.'],
    [
      /cuanto te pagaron|cobrado acumulado/,
      'Total que ya recibiste por esta venta. Si solo pagaron una parte, escribe ese abono; no el valor que todavía deben.',
    ],
    [
      /valor total|valor del gasto|cuanto gastaste/,
      'Dinero pagado por este gasto o trabajo, en pesos colombianos. Ejemplo: 45000. Regístralo una sola vez para evitar duplicarlo.',
    ],
    [
      /costo real|costo.*sin desglose/,
      'Costo total de este trabajo en pesos colombianos. Si lo registraste como gastos relacionados, utiliza ese desglose para evitar contarlo dos veces.',
    ],
    [
      /presupuesto/,
      'Dinero que planeas gastar en el trabajo. Es una estimación, diferente del gasto que finalmente pagaste.',
    ],
    [
      /precio.*unidad/,
      'Valor de una sola unidad en pesos colombianos. Ejemplo: si cada kilo vale $12000, escribe 12000. El total se calcula con la cantidad.',
    ],
    [
      /importe|valor del abono/,
      'Dinero que recibiste ahora para pagar esta venta, en pesos colombianos. No repitas abonos ya registrados.',
    ],
    [
      /porcentaje/,
      'Parte del gasto que corresponde a este cultivo, entre 0 y 100. Ejemplo: 25 significa una cuarta parte.',
    ],
    [
      /criterio de distribucion/,
      'Elige cómo calculaste el reparto: superficie, horas, plantas, consumo o un porcentaje definido manualmente.',
    ],
    [
      /gasto general.*distribuir/,
      'Selecciona el gasto ya registrado que usarán varios cultivos. Aquí lo repartes; no creas otro gasto.',
    ],
    [
      /naturaleza/,
      'Producción: insumos y trabajo del cultivo. Inversión: equipos, construcciones o infraestructura que usarás durante más tiempo.',
    ],
    [
      /categoria/,
      'Elige el grupo que mejor describe el gasto, como fertilizantes, mano de obra, maquinaria o transporte.',
    ],
    [
      /en que gastaste|insumo, persona o maquina/,
      'Nombre del insumo, servicio, persona o máquina que pagaste. Ejemplo: abono, jornal de Pedro o alquiler de tractor.',
    ],
    [/modalidad/, 'Indica si el recurso es propio, contratado, alquilado o comprado.'],
    [
      /cantidad vendida/,
      'Cantidad entregada al comprador, usando la misma unidad de la cosecha. No puede superar el producto disponible.',
    ],
    [
      /cantidad cosechada|cuanto recogiste/,
      'Cantidad total recogida antes de descontar la pérdida o descarte. Usa la unidad seleccionada.',
    ],
    [
      /perdida|descarte/,
      'Cantidad que no se puede vender o aprovechar. Usa la misma unidad de la cosecha.',
    ],
    [
      /cantidad transportada/,
      'Cantidad de producto que lleva este viaje, en la unidad de la cosecha seleccionada.',
    ],
    [
      /medio de pago/,
      'Selecciona cómo recibiste el dinero: efectivo, transferencia, consignación u otro medio.',
    ],
    [
      /forma de pago|como.*pag|pago de la venta/,
      'Indica si recibiste todo el dinero, un abono parcial o si la venta quedó pendiente de pago.',
    ],
    [/comprador/, 'Nombre de la persona, negocio o cooperativa que compró el producto.'],
    [/proveedor|contratista/, 'Nombre de quien te vendió el material o prestó el servicio.'],
    [/responsable/, 'Nombre de la persona que realiza o coordina este cultivo o trabajo.'],
    [
      /variedad|material vegetal/,
      'Variedad o material del cultivo. Ejemplo: Castillo para café o Hass para aguacate. Si no la conoces y es opcional, puedes completarla después.',
    ],
    [
      /que cultivo es|especie/,
      'Nombre del cultivo sembrado, por ejemplo café, cacao, maíz o tomate.',
    ],
    [
      /cultivo anterior|uso anterior/,
      'Lo que había antes en este lote. Sirve para conservar el historial y planear la rotación.',
    ],
    [
      /monocultivo|^sistema$/,
      'Monocultivo: una especie. Asociado: varios cultivos juntos. Agroforestal: cultivos combinados con árboles.',
    ],
    [
      /ambiente de produccion/,
      'Indica si este lote está a cielo abierto, en invernadero, bajo malla, en vivero o en semillero.',
    ],
    [
      /area cubierta/,
      'Superficie que cubre el invernadero o estructura, en metros cuadrados. Una hectárea equivale a 10000 m².',
    ],
    [
      /hectarea|superficie|area.*\(ha\)/,
      'Superficie en hectáreas. Una hectárea equivale a 10000 m². Puedes ingresar decimales: 0.5 significa media hectárea.',
    ],
    [
      /pendiente.*%/,
      'Inclinación del terreno expresada en porcentaje. Usa el dato medido o el de la ficha técnica del lote.',
    ],
    [
      /contorno|poligono/,
      'Límite del lote en el mapa. Añade los puntos del borde en orden; el sistema cierra el contorno.',
    ],
    [
      /tenencia/,
      'Relación con el predio: propietario, arrendatario, aparcero o préstamo de uso (comodato).',
    ],
    [
      /cedula catastral/,
      'Identificador del predio que aparece en el recibo predial o documento catastral.',
    ],
    [
      /matricula inmobiliaria/,
      'Número de identificación del predio en el certificado de tradición y libertad.',
    ],
    [/registro ica/, 'Número del registro del predio ante el ICA, si lo tiene.'],
    [/concesion/, 'Referencia del permiso de uso de agua, si la finca cuenta con uno.'],
    [
      /fuente de agua/,
      'Origen del agua usada en la finca, como quebrada, río, pozo, acueducto o lluvia.',
    ],
    [/riego/, 'Selecciona cómo llega el agua al cultivo: manual, goteo, aspersión u otro sistema.'],
    [
      /drenaje/,
      'Indica cómo sale el exceso de agua del lote: naturalmente, mediante obras o sin drenaje.',
    ],
    [
      /suelo/,
      'Tipo de suelo observado o indicado en el análisis. Si no lo sabes, no adivines: complétalo cuando tengas el dato.',
    ],
    [
      /^ph$/,
      'Copia el pH del informe del laboratorio. Es una medida de acidez del suelo; conserva los decimales del resultado.',
    ],
    [
      /nitrogeno|fosforo|potasio|materia organica/,
      'Copia el resultado del análisis de laboratorio en la unidad indicada en el título. No cambies la unidad ni inventes el valor.',
    ],
    [/laboratorio/, 'Nombre del laboratorio que realizó el análisis de suelo.'],
    [/calidad/, 'Clasificación del producto recogido, como Primera, Segunda o Industrial.'],
    [
      /referencia de esta cosecha|codigo del lote de producto/,
      'Código para reconocer y rastrear esta cosecha. El sistema puede generarlo automáticamente.',
    ],
    [
      /lote de semilla|vivero/,
      'Código del lote que aparece en el empaque de la semilla o en la entrega del vivero.',
    ],
    [
      /siembra o corte|ciclo/,
      'Selecciona la temporada, siembra o corte al que pertenece este registro. Así conservas sus costos y resultados por separado.',
    ],
    [/cosecha|lote de producto/, 'Elige la cosecha ya registrada de la que sale este producto.'],
    [/venta/, 'Selecciona la venta a la que corresponde este pago.'],
    [
      /lote/,
      'Selecciona la parte de la finca donde ocurre el registro. Para un gasto general puedes elegir Toda la finca cuando esté disponible.',
    ],
    [
      /finca/,
      'Elige la finca a la que pertenece el registro. Los lotes y cultivos disponibles dependen de esta elección.',
    ],
    [
      /cultivo/,
      'Selecciona lo que sembraste en este lote para asociar su trabajo, gastos o cosechas.',
    ],
    [
      /que trabajo|actividad/,
      'Elige el trabajo realizado o que vas a programar, por ejemplo riego, poda, siembra o fertilización.',
    ],
    [/cuando lo hiciste|fecha de ejecucion/, 'Día en que realmente realizaste el trabajo.'],
    [/para que dia/, 'Día en que planeas realizar este trabajo.'],
    [
      /estado/,
      'Selecciona la situación actual del registro. Para un trabajo, indica si está pendiente, en proceso, terminado o cancelado.',
    ],
    [
      /observacion|nota|descripcion|motivo/,
      'Escribe una explicación breve que ayude a entender el registro. Ejemplo: qué ocurrió, qué hiciste y cualquier novedad importante.',
    ],
    [
      /comprobante|factura|remision/,
      'Número o referencia del documento que respalda la compra, venta, pago o entrega.',
    ],
    [
      /correo|email/,
      'Escribe la dirección de correo completa, por ejemplo nombre@correo.com. Revisa que no tenga espacios.',
    ],
    [
      /confirm.*contrasena|repet.*contrasena/,
      'Escribe exactamente la misma contraseña que ingresaste en el campo anterior.',
    ],
    [
      /contrasena/,
      'Escribe tu contraseña respetando las mayúsculas y minúsculas. Si estás creando una nueva, sigue los requisitos que muestra el formulario.',
    ],
    [
      /codigo de 6|codigo.*autentic|codigo.*verificacion/,
      'Escribe el código vigente de seis números que muestra tu aplicación autenticadora. Cambia aproximadamente cada 30 segundos.',
    ],
    [
      /recuperacion/,
      'Usa uno de tus códigos de recuperación guardados. Cada código puede utilizarse una sola vez.',
    ],
    [
      /recordar.*navegador/,
      'Marca esta opción únicamente en un dispositivo de confianza. Durante 30 días no se pedirá el segundo factor en este navegador.',
    ],
    [
      /telefono|celular|contacto/,
      'Número de teléfono donde se puede contactar a la persona. Incluye el indicativo si corresponde.',
    ],
    [
      /documento|identificacion|cedula/,
      'Número del documento de identidad de la persona, tal como aparece en el documento.',
    ],
    [
      /departamento|municipio|ciudad|vereda|pais/,
      'Selecciona o escribe la ubicación real de la finca o de la persona, según este formulario.',
    ],
    [
      /unidad/,
      'Elige en qué se mide la cantidad: kg para kilos, t para toneladas, litro, unidad, hora o jornal, según corresponda.',
    ],
    [/origen/, 'Lugar de donde sale el producto o de donde proviene el material.'],
    [/destino|bodega/, 'Lugar donde se entrega o guarda el producto.'],
    [/transportista/, 'Nombre de la persona o empresa que realiza el transporte.'],
    [/placa/, 'Placa del vehículo que lleva el producto.'],
    [
      /precipitacion/,
      'Cantidad de lluvia anual de referencia en milímetros. Usa un registro meteorológico o la ficha técnica de la finca.',
    ],
    [/temperatura/, 'Temperatura medida o de referencia en grados Celsius, según el registro.'],
    [/humedad/, 'Valor de humedad informado por el sensor en la unidad indicada.'],
    [/vencimiento/, 'Último día de validez del certificado o documento.'],
    [/certific|entidad/, 'Nombre, número o entidad que aparece en el certificado correspondiente.'],
    [
      /nombre|como llamas|referencia/,
      'Escribe un nombre corto que puedas reconocer después. Ejemplo: La huerta para un lote.',
    ],
    [
      /buscar/,
      'Escribe parte del nombre o dato que quieres encontrar. La lista mostrará los registros que coincidan.',
    ],
  ];
  let explanation =
    (control.type === 'checkbox' || control.type === 'radio') && !/recordar.*navegador/.test(title)
      ? 'Marca esta opción si corresponde a tu caso; déjala sin marcar si no corresponde.'
      : rules.find(([pattern]) => pattern.test(title))?.[1];
  if (!explanation) {
    if (control.type === 'checkbox' || control.type === 'radio')
      explanation =
        'Marca esta opción si corresponde a tu caso; déjala sin marcar si no corresponde.';
    else if (control.type === 'file')
      explanation =
        'Selecciona el archivo que corresponde a este registro. Revisa los formatos y el tamaño permitidos en el formulario.';
    else if (control.tagName === 'SELECT')
      explanation = `Elige la opción que corresponde a «${caption}».`;
    else if (control.type === 'date' || control.type === 'datetime-local')
      explanation = `Selecciona la fecha correspondiente a «${caption}».`;
    else if (control.type === 'number')
      explanation = `Escribe el valor numérico de «${caption}», en la unidad indicada.`;
    else explanation = `Escribe el dato correspondiente a «${caption}».`;
    const example = control.getAttribute('placeholder');
    if (example) explanation += ` Referencia: ${example}.`;
  }
  if (control.required) explanation += ' Este dato es obligatorio.';
  return explanation;
}
