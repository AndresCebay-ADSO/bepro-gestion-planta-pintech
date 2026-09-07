<?php

declare(strict_types=1);

namespace App\Http\Requests\PaintDevelopmentRequests;

trait PaintDevelopmentRequestRules
{
    /**
     * @return array<string, mixed>
     */
    public function sharedRules(): array
    {
        return [
            'client_name' => ['required', 'string', 'max:255'],
            'project_name' => ['required', 'string', 'max:255'],
            'responsible' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'sample_due_date' => ['required', 'date'],
            'current_product' => ['nullable', 'string', 'max:255'],

            // Contexto
            'context_payload' => ['required', 'array'],
            'context_payload.sustrato' => ['required', 'string', 'max:255'],
            'context_payload.estado_superficie' => ['required', 'string', 'max:255'],
            'context_payload.otro_sustrato' => ['nullable', 'string', 'max:255', 'required_if:context_payload.sustrato,Otro'],
            'context_payload.pintura_existente' => ['required', 'string', 'in:Sí,No,No se sabe'],
            'context_payload.sistema_existente' => ['nullable', 'string', 'max:5000', 'required_if:context_payload.pintura_existente,Sí'],
            'context_payload.preparacion' => ['required', 'array'],
            'context_payload.preparacion.*' => ['string', 'max:255'],
            'context_payload.exposicion' => ['required', 'array'],
            'context_payload.exposicion.*' => ['string', 'max:255'],
            'context_payload.agua_humedad' => ['required', 'string', 'max:255'],
            'context_payload.corrosividad' => ['nullable', 'string', 'max:255'],
            'context_payload.temp_min' => ['nullable', 'numeric'],
            'context_payload.temp_max' => ['nullable', 'numeric', function (string $attribute, mixed $value, \Closure $fail): void {
                $min = $this->input('context_payload.temp_min');
                if ($min !== null && $value !== null && (float) $min > (float) $value) {
                    $fail(__('La temperatura mínima no puede ser mayor que la máxima.'));
                }
            }],
            'context_payload.ciclos_termicos' => ['required', 'string', 'max:255'],
            'context_payload.quimicos' => ['nullable', 'string', 'max:5000'],

            // Desempeño
            'performance_payload' => ['required', 'array'],
            'performance_payload.funcion' => ['required', 'array'],
            'performance_payload.funcion.*' => ['string', 'max:255'],
            'performance_payload.vida_util' => ['required', 'string', 'max:255'],
            'performance_payload.prioridad' => ['required', 'string', 'max:255'],
            'performance_payload.pruebas' => ['nullable', 'array'],
            'performance_payload.pruebas.*' => ['string', 'max:255'],
            'performance_payload.adherencia_obj' => ['nullable', 'numeric'],
            'performance_payload.camara_obj' => ['nullable', 'numeric'],
            'performance_payload.criterios_tecnicos' => ['nullable', 'string', 'max:5000'],
            'performance_payload.indispensable' => ['required', 'string', 'max:5000'],
            'performance_payload.negociable' => ['nullable', 'string', 'max:5000'],
            'performance_payload.color' => ['required', 'string', 'max:255'],
            'performance_payload.brillo' => ['required', 'string', 'max:255'],
            'performance_payload.textura' => ['required', 'string', 'max:255'],
            'performance_payload.cubricion' => ['required', 'string', 'max:255'],
            'performance_payload.delta_e' => ['nullable', 'numeric'],
            'performance_payload.retencion' => ['required', 'string', 'max:255'],
            'performance_payload.acabado_ref' => ['nullable', 'string', 'max:5000'],

            // Aplicación
            'application_payload' => ['required', 'array'],
            'application_payload.metodo' => ['required', 'array'],
            'application_payload.metodo.*' => ['string', 'max:255'],
            'application_payload.temp_aplicacion' => ['nullable', 'numeric'],
            'application_payload.humedad_aplicacion' => ['nullable', 'numeric'],
            'application_payload.geometria' => ['required', 'string', 'max:255'],
            'application_payload.dft_min' => ['nullable', 'numeric'],
            'application_payload.dft_max' => ['nullable', 'numeric', function (string $attribute, mixed $value, \Closure $fail): void {
                $min = $this->input('application_payload.dft_min');
                if ($min !== null && $value !== null && (float) $min > (float) $value) {
                    $fail(__('El espesor mínimo no puede ser mayor que el máximo.'));
                }
            }],
            'application_payload.manos' => ['required', 'integer', 'min:1'],
            'application_payload.repinte' => ['required', 'string', 'max:255'],
            'application_payload.servicio' => ['required', 'string', 'max:255'],
            'application_payload.antidescuelgue' => ['required', 'string', 'max:255'],
            'application_payload.equipo_detalle' => ['nullable', 'string', 'max:5000'],
            'application_payload.vehiculo' => ['required', 'string', 'max:255'],
            'application_payload.tecnologia' => ['required', 'string', 'max:255'],
            'application_payload.otra_tecnologia' => ['nullable', 'string', 'max:255', 'required_if:application_payload.tecnologia,Otra'],
            'application_payload.componentes' => ['required', 'string', 'max:255'],
            'application_payload.relacion_mezcla' => ['nullable', 'string', 'max:255'],
            'application_payload.pot_life' => ['nullable', 'integer', 'min:1'],
            'application_payload.solidos_vol' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'application_payload.ajustador' => ['required', 'string', 'max:255'],
            'application_payload.voc' => ['nullable', 'numeric', 'min:0'],
            'application_payload.restricciones' => ['nullable', 'array'],
            'application_payload.restricciones.*' => ['string', 'max:255'],
            'application_payload.sistema_capas' => ['required', 'string', 'max:5000'],

            // Especificaciones
            'specifications_payload' => ['required', 'array'],
            'specifications_payload.viscosidad_metodo' => ['nullable', 'string', 'max:255'],
            'specifications_payload.densidad' => ['nullable', 'string', 'max:255'],
            'specifications_payload.solidos_peso' => ['nullable', 'string', 'max:255'],
            'specifications_payload.finura' => ['nullable', 'string', 'max:255'],
            'specifications_payload.secados' => ['required', 'string', 'max:5000'],
            'specifications_payload.estabilidad' => ['required', 'string', 'max:5000'],
            'specifications_payload.presentacion' => ['required', 'array'],
            'specifications_payload.presentacion.*' => ['string', 'max:255'],
            'specifications_payload.consumo' => ['required', 'string', 'max:255'],
            'specifications_payload.frecuencia' => ['required', 'string', 'max:255'],
            'specifications_payload.almacenamiento' => ['nullable', 'string', 'max:5000'],
            'specifications_payload.kit_ab' => ['nullable', 'string', 'max:5000'],
            'specifications_payload.competidor' => ['nullable', 'string', 'max:255'],
            'specifications_payload.meta_competidor' => ['required', 'string', 'max:255'],
            'specifications_payload.costo_objetivo' => ['required', 'string', 'max:255'],
            'specifications_payload.cantidad_prueba' => ['required', 'string', 'max:255'],
            'specifications_payload.aprobador' => ['required', 'string', 'max:255'],
            'specifications_payload.forma_aprobacion' => ['required', 'string', 'max:255'],
            'specifications_payload.criterios_aprobacion' => ['required', 'string', 'max:5000'],
            'specifications_payload.observaciones' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function sharedAttributes(): array
    {
        return [
            'client_name' => 'nombre del cliente',
            'project_name' => 'nombre del proyecto',
            'responsible' => 'responsable',
            'city' => 'ciudad',
            'sample_due_date' => 'fecha de entrega de muestra',
            'current_product' => 'producto actual',

            // Contexto
            'context_payload' => 'contexto de la solicitud',
            'context_payload.sustrato' => 'sustrato',
            'context_payload.estado_superficie' => 'estado de la superficie',
            'context_payload.otro_sustrato' => 'otro sustrato',
            'context_payload.pintura_existente' => 'pintura existente',
            'context_payload.sistema_existente' => 'sistema existente',
            'context_payload.preparacion' => 'preparación de superficie',
            'context_payload.preparacion.*' => 'método de preparación',
            'context_payload.exposicion' => 'condiciones de exposición',
            'context_payload.exposicion.*' => 'condición de exposición',
            'context_payload.agua_humedad' => 'agua y humedad',
            'context_payload.corrosividad' => 'nivel de corrosividad',
            'context_payload.temp_min' => 'temperatura mínima',
            'context_payload.temp_max' => 'temperatura máxima',
            'context_payload.ciclos_termicos' => 'ciclos térmicos',
            'context_payload.quimicos' => 'exposición a químicos',

            // Desempeño
            'performance_payload' => 'desempeño requerido',
            'performance_payload.funcion' => 'funciones principales',
            'performance_payload.funcion.*' => 'función principal',
            'performance_payload.vida_util' => 'vida útil esperada',
            'performance_payload.prioridad' => 'prioridad de desempeño',
            'performance_payload.pruebas' => 'pruebas requeridas',
            'performance_payload.pruebas.*' => 'prueba requerida',
            'performance_payload.adherencia_obj' => 'adherencia objetivo',
            'performance_payload.camara_obj' => 'cámara salina objetivo',
            'performance_payload.criterios_tecnicos' => 'criterios técnicos',
            'performance_payload.indispensable' => 'requisitos indispensables',
            'performance_payload.negociable' => 'requisitos negociables',
            'performance_payload.color' => 'color',
            'performance_payload.brillo' => 'nivel de brillo',
            'performance_payload.textura' => 'textura',
            'performance_payload.cubricion' => 'cubrición o poder cubriente',
            'performance_payload.delta_e' => 'tolerancia Delta E',
            'performance_payload.retencion' => 'retención de color y brillo',
            'performance_payload.acabado_ref' => 'referencia de acabado',

            // Aplicación
            'application_payload' => 'condiciones de aplicación',
            'application_payload.metodo' => 'métodos de aplicación',
            'application_payload.metodo.*' => 'método de aplicación',
            'application_payload.temp_aplicacion' => 'temperatura de aplicación',
            'application_payload.humedad_aplicacion' => 'humedad de aplicación',
            'application_payload.geometria' => 'geometría de la pieza',
            'application_payload.dft_min' => 'espesor mínimo (DFT)',
            'application_payload.dft_max' => 'espesor máximo (DFT)',
            'application_payload.manos' => 'número de manos',
            'application_payload.repinte' => 'tiempo de repinte',
            'application_payload.servicio' => 'puesta en servicio',
            'application_payload.antidescuelgue' => 'resistencia al descuelgue',
            'application_payload.equipo_detalle' => 'detalle del equipo',
            'application_payload.vehiculo' => 'tipo de vehículo',
            'application_payload.tecnologia' => 'tecnología requerida',
            'application_payload.otra_tecnologia' => 'otra tecnología',
            'application_payload.componentes' => 'número de componentes',
            'application_payload.relacion_mezcla' => 'relación de mezcla',
            'application_payload.pot_life' => 'vida útil de la mezcla (pot life)',
            'application_payload.solidos_vol' => 'sólidos por volumen',
            'application_payload.ajustador' => 'tipo de ajustador o solvente',
            'application_payload.voc' => 'límite de COV (VOC)',
            'application_payload.restricciones' => 'restricciones de aplicación',
            'application_payload.restricciones.*' => 'restricción',
            'application_payload.sistema_capas' => 'sistema de capas',

            // Especificaciones
            'specifications_payload' => 'especificaciones técnicas',
            'specifications_payload.viscosidad_metodo' => 'viscosidad y método',
            'specifications_payload.densidad' => 'densidad',
            'specifications_payload.solidos_peso' => 'sólidos por peso',
            'specifications_payload.finura' => 'finura de molienda',
            'specifications_payload.secados' => 'tiempos de secado',
            'specifications_payload.estabilidad' => 'estabilidad en envase',
            'specifications_payload.presentacion' => 'presentaciones',
            'specifications_payload.presentacion.*' => 'presentación',
            'specifications_payload.consumo' => 'consumo proyectado',
            'specifications_payload.frecuencia' => 'frecuencia de pedido',
            'specifications_payload.almacenamiento' => 'condiciones de almacenamiento',
            'specifications_payload.kit_ab' => 'especificación kit A/B',
            'specifications_payload.competidor' => 'producto competidor',
            'specifications_payload.meta_competidor' => 'meta frente al competidor',
            'specifications_payload.costo_objetivo' => 'costo objetivo',
            'specifications_payload.cantidad_prueba' => 'cantidad para prueba',
            'specifications_payload.aprobador' => 'aprobador de la muestra',
            'specifications_payload.forma_aprobacion' => 'forma de aprobación',
            'specifications_payload.criterios_aprobacion' => 'criterios de aprobación',
            'specifications_payload.observaciones' => 'observaciones adicionales',
        ];
    }
}
