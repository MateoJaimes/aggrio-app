<?php

namespace App\Services;

use Illuminate\Validation\Rule;

class ProductionSchema
{
    public static function field(string $key, string $label, string $type = 'text', bool $required = false, array|string $options = [], ?string $show = null): array
    {
        return compact('key', 'label', 'type', 'required', 'options', 'show');
    }

    public static function definitions(): array
    {
        $f = self::field(...);
        $context = [$f('lote_id', 'Lote', 'relation', true, 'lotes'), $f('cultivo_id', 'Cultivo', 'relation', false, 'cultivos'), $f('ciclo_id', 'Ciclo o corte', 'relation', false, 'ciclos')];

        return [
            'sanidad_casos' => ['label' => 'Problemas detectados', 'states' => ['abierto', 'en_observacion', 'controlado', 'cerrado'], 'fields' => [
                $f('lote_id', 'Lote', 'relation', true, 'lotes'), $f('cultivo_id', 'Cultivo', 'relation', true, 'cultivos'), $f('ciclo_id', 'Siembra asociada', 'relation', false, 'ciclos'),
                $f('clase_problema', 'Tipo de problema', 'select', true, ['plaga', 'enfermedad', 'maleza', 'otro', 'por_identificar']),
                $f('problema', 'Plaga, enfermedad o problema observado', 'catalog', true, 'problemas_sanitarios'),
                $f('diagnostico', 'Confirmación del diagnóstico', 'select', true, ['sospecha', 'tecnico', 'laboratorio']),
                $f('sintomas', 'Síntomas y parte del cultivo afectada', 'text', true), $f('responsable', 'Persona que inspeccionó', 'catalog', true, 'responsables'),
                $f('etapa_cultivo', 'Etapa del cultivo', 'select', false, ['establecimiento', 'vegetativo', 'floracion', 'fructificacion', 'cosecha', 'poscosecha']),
                $f('metodo_muestreo', 'Cómo y dónde tomaste la muestra', 'text', true), $f('unidad_muestreo', 'Unidad observada', 'select', true, ['planta', 'hoja', 'fruto', 'trampa']),
                $f('muestra_total', 'Unidades revisadas', 'integer', true), $f('muestra_afectada', 'Unidades afectadas', 'integer', true),
                $f('severidad', 'Severidad: daño observado (%)', 'number'), $f('tecnico', 'Técnico o laboratorio que confirmó'),
                $f('fecha_revision', 'Próxima revisión', 'date'),
            ]],
            'sanidad_tratamientos' => ['label' => 'Tratamientos y decisiones', 'states' => ['planificado', 'aplicado', 'no_realizado'], 'fields' => [
                $f('lote_id', 'Lote', 'relation', true, 'lotes'), $f('cultivo_id', 'Cultivo', 'relation', true, 'cultivos'), $f('ciclo_id', 'Siembra asociada', 'relation', false, 'ciclos'),
                $f('caso_id', 'Problema que estás atendiendo', 'relation', true, 'sanidad_casos'),
                $f('metodo', 'Tipo de control', 'select', true, ['monitoreo', 'cultural', 'mecanico', 'biologico', 'quimico', 'integrado']),
                $f('accion', 'Qué se hizo o qué se planeó hacer', 'text', true), $f('motivo_decision', 'Por qué se decidió actuar o no actuar', 'text', true),
                $f('responsable', 'Responsable del tratamiento', 'catalog', true, 'responsables'), $f('recomendado_por', 'Técnico que recomendó la medida'),
                $f('producto', 'Producto aplicado', 'catalog', false, 'productos_fitosanitarios'), $f('ingrediente_activo', 'Ingrediente activo'),
                $f('registro_producto', 'Registro del producto'), $f('lote_insumo', 'Lote del producto aplicado'),
                $f('dosis', 'Dosis utilizada', 'number'), $f('unidad_dosis', 'Unidad de la dosis', 'select', false, ['ml/L', 'g/L', 'L/ha', 'kg/ha', 'ml/planta', 'g/planta']),
                $f('cantidad_aplicada', 'Cantidad total de producto utilizada', 'number'), $f('unidad_aplicada', 'Unidad del producto utilizado', 'select', false, ['ml', 'L', 'g', 'kg', 'unidad']),
                $f('area_tratada', 'Superficie tratada (ha)', 'number'), $f('volumen_mezcla', 'Volumen de mezcla aplicado (L)', 'number'),
                $f('carencia_dias', 'Periodo de carencia de la etiqueta (días)', 'integer'), $f('reingreso_horas', 'Tiempo de reingreso de la etiqueta (horas)', 'integer'),
                $f('equipo', 'Equipo y método de aplicación'), $f('condiciones_clima', 'Clima durante la aplicación'),
                $f('fecha_revision', 'Cuándo revisar el resultado', 'date'),
            ]],
            'sanidad_seguimientos' => ['label' => 'Revisiones y resultados', 'states' => ['registrado'], 'fields' => [
                $f('lote_id', 'Lote', 'relation', true, 'lotes'), $f('cultivo_id', 'Cultivo', 'relation', true, 'cultivos'), $f('ciclo_id', 'Siembra asociada', 'relation', false, 'ciclos'),
                $f('caso_id', 'Problema que estás revisando', 'relation', true, 'sanidad_casos'), $f('tratamiento_id', 'Tratamiento evaluado (opcional)', 'relation', false, 'sanidad_tratamientos'),
                $f('resultado_observado', 'Qué resultado observaste', 'select', true, ['sin_evaluar', 'mejoro', 'sin_cambio', 'empeoro', 'sin_sintomas']),
                $f('metodo_muestreo', 'Cómo y dónde tomaste la muestra', 'text', true), $f('unidad_muestreo', 'Unidad observada', 'select', true, ['planta', 'hoja', 'fruto', 'trampa']),
                $f('muestra_total', 'Unidades revisadas', 'integer', true), $f('muestra_afectada', 'Unidades afectadas', 'integer', true), $f('severidad', 'Severidad: daño observado (%)', 'number'),
                $f('responsable', 'Persona que revisó', 'catalog', true, 'responsables'), $f('efectos_adversos', 'Daños no esperados o efectos adversos'),
                $f('siguiente_accion', 'Qué se hará después', 'text', true), $f('fecha_revision', 'Próxima revisión', 'date'),
            ]],
            'cultivos' => ['label' => 'Cultivos y plantaciones', 'fields' => [
                $f('lote_id', 'Lote', 'relation', true, 'lotes'),
                $f('tipo', 'Duración del cultivo', 'select', true, ['transitorio', 'permanente', 'semipermanente']),
                $f('especie', 'Especie', 'catalog', true, 'especies'), $f('variedad', 'Variedad o material', 'catalog', false, 'variedades'),
                $f('proveedor', 'Proveedor', 'catalog', false, 'proveedores'), $f('origen', 'Origen del material'), $f('lote_semilla', 'Lote de semilla o vivero'),
                $f('numero_plantas', 'Número de plantas o plántulas', 'integer'), $f('area', 'Área sembrada (ha)', 'number', true),
                $f('densidad', 'Densidad (plantas/ha)', 'number'), $f('sistema', 'Sistema', 'select', true, ['monocultivo', 'asociado', 'agroforestal']),
                $f('responsable', 'Responsable', 'catalog', true, 'responsables'),
                $f('estado_plantacion', 'Estado de la plantación', 'select', false, ['establecimiento', 'produccion', 'renovacion', 'finalizada']),
                $f('fecha_poda', 'Última poda', 'date'), $f('fecha_zoca', 'Última zoca', 'date'), $f('fecha_renovacion', 'Última renovación', 'date'),
            ]],
            'ciclos' => ['label' => 'Ciclos y cortes', 'fields' => [
                $f('lote_id', 'Lote', 'relation', true, 'lotes'), $f('cultivo_id', 'Cultivo o plantación', 'relation', true, 'cultivos'),
                $f('fecha_cosecha_estimada', 'Cosecha estimada', 'date', true), $f('cultivo_anterior', 'Cultivo anterior para rotación', 'catalog', false, 'especies'),
                $f('rendimiento_esperado', 'Producción esperada', 'number'), $f('unidad', 'Unidad de producción', 'select', true, ['kg', 't', 'unidad', 'litro']),
                $f('area', 'Área del ciclo (ha)', 'number', true), $f('numero_plantas', 'Plántulas del ciclo', 'integer'),
            ]],
            'labores' => ['label' => 'Plan de trabajo y actividades', 'fields' => [
                ...$context, $f('tipo_actividad', 'Actividad', 'select', true, ['analisis_suelo', 'preparacion', 'compra_material', 'contratacion', 'maquinaria', 'instalacion_riego', 'siembra', 'fertilizacion', 'riego', 'control_plagas', 'poda', 'zoca', 'renovacion', 'cosecha', 'mantenimiento', 'transporte']),
                $f('responsable', 'Responsable', 'catalog', true, 'responsables'), $f('fecha_real', 'Fecha de ejecución', 'date', false, [], 'estado=terminado'),
                $f('presupuesto', 'Presupuesto previsto (COP)', 'money'), $f('costo_real', 'Costo real sin desglose (COP)', 'money', false, [], 'estado=terminado'),
                $f('cantidad', 'Cantidad de trabajo', 'number'), $f('unidad', 'Unidad', 'catalog', false, 'unidades'),
            ], 'states' => ['pendiente', 'en_proceso', 'terminado', 'cancelado']],
            'costos' => ['label' => 'Costos e inversiones', 'fields' => [
                $f('lote_id', 'Lote (opcional para gasto general)', 'relation', false, 'lotes'), $f('cultivo_id', 'Cultivo', 'relation', false, 'cultivos'), $f('ciclo_id', 'Ciclo o corte', 'relation', false, 'ciclos'),
                $f('actividad_id', 'Actividad ejecutada', 'relation', false, 'actividades'),
                $f('naturaleza', 'Naturaleza', 'select', true, ['produccion', 'inversion']),
                $f('categoria', 'Categoría', 'select', true, ['semillas_plantulas', 'fertilizantes', 'control_sanitario', 'mano_obra', 'maquinaria', 'combustible', 'agua', 'energia', 'transporte', 'infraestructura', 'equipos', 'otros']),
                $f('recurso', 'Insumo, persona o máquina', 'catalog', true, 'recursos'), $f('proveedor', 'Proveedor o contratista', 'catalog', false, 'proveedores'),
                $f('modalidad', 'Modalidad', 'select', false, ['propio', 'contratado', 'alquilado', 'comprado']),
                $f('cantidad', 'Cantidad', 'number', true), $f('unidad', 'Unidad', 'catalog', true, 'unidades'), $f('precio_unitario', 'Precio por unidad (COP)', 'money', true),
                $f('comprobante', 'Referencia de factura o comprobante'),
            ]],
            'repartos' => ['label' => 'Distribución de gastos compartidos', 'fields' => [
                $f('costo_id', 'Gasto general a distribuir', 'relation', true, 'costos'),
                $f('lote_id', 'Lote destino', 'relation', true, 'lotes'), $f('cultivo_id', 'Cultivo destino', 'relation', true, 'cultivos'), $f('ciclo_id', 'Ciclo destino', 'relation', false, 'ciclos'),
                $f('porcentaje', 'Porcentaje asignado', 'number', true), $f('criterio', 'Criterio de distribución', 'select', true, ['area', 'horas', 'plantas', 'consumo', 'manual']),
            ]],
            'cosechas' => ['label' => 'Cosechas y lotes de producto', 'fields' => [
                ...$context, $f('cantidad', 'Cantidad cosechada', 'number', true), $f('perdida', 'Pérdida o descarte', 'number'),
                $f('unidad', 'Unidad', 'select', true, ['kg', 't', 'unidad', 'litro']), $f('calidad', 'Calidad', 'catalog', true, 'calidades'),
                $f('lote_producto', 'Código del lote de producto', 'text', true), $f('destino', 'Destino o bodega', 'catalog', false, 'destinos'),
            ]],
            'ventas' => ['label' => 'Ventas y cobros', 'fields' => [
                $f('cosecha_id', 'Cosecha o lote de producto', 'relation', true, 'cosechas'),
                $f('cantidad', 'Cantidad vendida (unidad de la cosecha)', 'number', true), $f('precio_unitario', 'Precio por unidad (COP)', 'money', true),
                $f('comprador', 'Comprador', 'catalog', true, 'compradores'), $f('cobrado', 'Valor cobrado acumulado (COP)', 'money', true),
                $f('fecha_cobro', 'Último cobro', 'date'), $f('comprobante', 'Factura o comprobante'),
            ]],
            'cobros' => ['label' => 'Abonos y cobros', 'fields' => [
                $f('venta_id', 'Venta', 'relation', true, 'ventas'), $f('importe', 'Importe del abono (COP)', 'money', true),
                $f('medio_pago', 'Medio de pago', 'select', true, ['efectivo', 'transferencia', 'consignacion', 'otro']), $f('comprobante', 'Referencia del comprobante'),
            ]],
            'transportes' => ['label' => 'Transporte', 'fields' => [
                $f('cosecha_id', 'Lote de producto', 'relation', true, 'cosechas'), $f('cantidad', 'Cantidad transportada', 'number', true),
                $f('origen', 'Origen', 'text', true), $f('destino', 'Destino', 'catalog', true, 'destinos'), $f('transportista', 'Transportista', 'catalog', true, 'proveedores'),
                $f('placa', 'Placa del vehículo'), $f('remision', 'Remisión'), $f('fecha_entrega', 'Fecha de entrega', 'date'),
            ]],
            'certificaciones' => ['label' => 'Certificaciones', 'fields' => [
                $f('certificacion', 'Certificación', 'select', true, ['BPA ICA', 'Organico', 'Rainforest', 'Global G.A.P.', 'Otra']),
                $f('entidad', 'Entidad certificadora'), $f('numero', 'Número de certificado'), $f('vencimiento', 'Vencimiento', 'date', true),
            ]],
            'analisis' => ['label' => 'Análisis de suelo', 'fields' => [
                $f('lote_id', 'Lote', 'relation', true, 'lotes'), $f('laboratorio', 'Laboratorio', 'catalog', true, 'laboratorios'),
                $f('ph', 'pH', 'number', true), $f('nitrogeno', 'Nitrógeno (mg/kg)', 'number'), $f('fosforo', 'Fósforo (mg/kg)', 'number'),
                $f('potasio', 'Potasio (cmol/kg)', 'number'), $f('materia_organica', 'Materia orgánica (%)', 'number'),
            ]],
            'catalogos' => ['label' => 'Opciones de los desplegables', 'fields' => [
                $f('catalogo', 'Catálogo', 'select', true, ['especies', 'variedades', 'proveedores', 'responsables', 'recursos', 'unidades', 'calidades', 'destinos', 'compradores', 'laboratorios', 'departamentos', 'municipios', 'veredas']),
                $f('padre', 'Especie, departamento o municipio del que depende'),
            ]],
        ];
    }

    public static function parameters(string $type): array
    {
        $f = self::field(...);
        if ($type === 'finca') {
            return [
                $f('tenencia', 'Tipo de tenencia', 'select', false, ['propietario', 'arrendatario', 'aparcero', 'comodato']),
                $f('documento_productor', 'Documento del productor'), $f('contacto', 'Contacto'), $f('cedula_catastral', 'Cédula catastral'), $f('matricula', 'Matrícula inmobiliaria'), $f('registro_ica', 'Registro ICA'),
                $f('asistencia_tecnica', 'Técnico o entidad de asistencia', 'catalog', false, 'proveedores'),
                $f('autoriza_datos', 'Autorización de tratamiento de datos registrada', 'boolean'), $f('fecha_autorizacion', 'Fecha de autorización', 'date'),
                $f('departamento', 'Departamento', 'catalog', false, 'departamentos'), $f('municipio', 'Municipio', 'catalog', false, 'municipios'), $f('vereda', 'Vereda', 'catalog', false, 'veredas'),
                $f('altitud', 'Altitud (msnm)', 'number'), $f('area_productiva', 'Área productiva (ha)', 'number'), $f('area_conservacion', 'Conservación o bosque (ha)', 'number'),
                $f('area_infraestructura', 'Infraestructura (ha)', 'number'), $f('area_sin_uso', 'Área sin uso (ha)', 'number'),
                $f('fuente_agua', 'Fuente de agua', 'select', false, ['quebrada', 'rio', 'pozo', 'acueducto', 'lluvia', 'reservorio', 'otra']), $f('concesion', 'Concesión de agua'),
                $f('riego', 'Sistema de riego', 'select', false, ['sin_riego', 'manual', 'goteo', 'aspersion', 'fertirriego']),
                $f('beneficiadero', 'Beneficiadero', 'boolean'), $f('bodega', 'Bodega de agroquímicos', 'boolean'), $f('secadero', 'Secadero', 'boolean'), $f('vias', 'Vías de acceso'),
                $f('precipitacion', 'Precipitación de referencia (mm/año)', 'number'), $f('temperatura', 'Temperatura de referencia (°C)', 'temperature'),
                $f('tipo_suelo', 'Suelo predominante', 'select', false, ['franco', 'arcilloso', 'arenoso', 'limoso', 'franco_arcilloso', 'franco_arenoso', 'otro']),
            ];
        }

        return [
            $f('estado_agronomico', 'Estado agronómico', 'select', false, ['produccion', 'descanso', 'renovacion']),
            $f('ambiente', 'Ambiente de producción', 'select', false, ['cielo_abierto', 'invernadero', 'casa_malla', 'vivero', 'semillero']),
            $f('poligono', 'Contorno del lote', 'geojson'), $f('pendiente', 'Pendiente (%)', 'number'),
            $f('tipo_suelo', 'Tipo de suelo', 'select', false, ['franco', 'arcilloso', 'arenoso', 'limoso', 'franco_arcilloso', 'franco_arenoso', 'otro']),
            $f('riego', 'Riego', 'select', false, ['sin_riego', 'manual', 'goteo', 'aspersion', 'fertirriego']), $f('drenaje', 'Drenaje', 'select', false, ['natural', 'artificial', 'sin_drenaje']),
            $f('uso_anterior', 'Uso anterior'), $f('area_cubierta', 'Área cubierta (m²)', 'number', false, [], 'protegido'),
            $f('ano_construccion', 'Año de construcción', 'integer', false, [], 'protegido'),
            $f('cubierta', 'Material de cubierta', 'select', false, ['plastico', 'vidrio', 'policarbonato', 'malla'], 'protegido'),
            $f('ultimo_cambio', 'Último cambio de cubierta', 'date', false, [], 'protegido'),
            $f('ventilacion', 'Ventilación', 'select', false, ['natural', 'forzada'], 'protegido'),
            $f('control_climatico', 'Control climático', 'boolean', false, [], 'protegido'),
            $f('sensor_temperatura', 'Sensor de temperatura', 'boolean', false, [], 'protegido'), $f('sensor_humedad', 'Sensor de humedad', 'boolean', false, [], 'protegido'), $f('sensor_co2', 'Sensor CO₂', 'boolean', false, [], 'protegido'),
            $f('medio', 'Medio de cultivo', 'select', false, ['suelo', 'fibra_coco', 'cascarilla', 'turba', 'otro_sustrato', 'hidroponia']),
            $f('control_riego', 'Control de riego', 'select', false, ['manual', 'automatico']), $f('fertirriego', 'Fertirriego', 'boolean'),
        ];
    }

    public static function rules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $f) {
            $rule = [$f['required'] ? 'required' : 'nullable'];
            $rule[] = match ($f['type']) {
                'number', 'money' => 'numeric', 'integer', 'relation' => 'integer', 'date' => 'date', 'boolean' => 'boolean', default => 'string',
            };
            if (in_array($f['type'], ['number', 'money', 'integer'])) {
                $rule[] = 'min:0';
                $rule[] = 'max:999999999';
                if ($f['type'] === 'money') {
                    $rule[] = 'decimal:0,2';
                }
                if ($f['type'] === 'number') {
                    $rule[] = 'decimal:0,4';
                }
            } elseif ($f['type'] === 'temperature') {
                $rule = ['nullable', 'numeric', 'between:-50,65'];
            } elseif ($f['type'] === 'select') {
                $rule[] = Rule::in($f['options']);
            } elseif (in_array($f['type'], ['text', 'catalog'])) {
                $rule[] = 'max:255';
            } elseif ($f['type'] === 'geojson') {
                $rule[] = 'json';
                $rule[] = 'max:100000';
            }
            $rules[$f['key']] = $rule;
        }

        return $rules;
    }

    public static function catalogDefaults(): array
    {
        return [
            'especies' => ['Café', 'Cacao', 'Maíz', 'Papa', 'Frijol', 'Tomate', 'Plátano', 'Caña', 'Yuca', 'Piña', 'Aguacate'],
            'variedades' => ['Café' => ['Castillo', 'Caturra', 'Colombia'], 'Cacao' => ['CCN51', 'ICS95'], 'Aguacate' => ['Hass', 'Lorena']],
            'unidades' => ['kg', 't', 'g', 'litro', 'ml', 'unidad', 'hora', 'jornal', 'hectarea', 'viaje', 'kWh', 'm3'],
            'calidades' => ['Primera', 'Segunda', 'Industrial'],
            'departamentos' => ['Amazonas', 'Antioquia', 'Arauca', 'Atlántico', 'Bogotá D.C.', 'Bolívar', 'Boyacá', 'Caldas', 'Caquetá', 'Casanare', 'Cauca', 'Cesar', 'Chocó', 'Córdoba', 'Cundinamarca', 'Guainía', 'Guaviare', 'Huila', 'La Guajira', 'Magdalena', 'Meta', 'Nariño', 'Norte de Santander', 'Putumayo', 'Quindío', 'Risaralda', 'San Andrés y Providencia', 'Santander', 'Sucre', 'Tolima', 'Valle del Cauca', 'Vaupés', 'Vichada'],
        ];
    }
}
