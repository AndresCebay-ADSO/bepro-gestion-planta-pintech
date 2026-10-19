<?php

declare(strict_types=1);

namespace App\Http\Requests\PaintDevelopmentRequests;

use App\Models\PaintDevelopmentRequest;
use App\Rules\PlantDate;

trait PaintDevelopmentRequestRules
{
    /**
     * La fecha de muestra guardada, al editar; null al crear.
     */
    private function savedSampleDueDate(): ?string
    {
        $request = $this->route('paintDevelopmentRequest');

        return $request instanceof PaintDevelopmentRequest ? $request->sample_due_date?->toDateString() : null;
    }

    /**
     * Un número técnico de la solicitud, con 2 decimales y su rango físico (auditoría 3.6, PDR-02). Los rangos aceptan
     * cualquier producto real, aunque Pintech no lo fabrique hoy, y rechazan lo absurdo: un error de digitación sale tal
     * cual en el PDF que recibe I+D. Revisados contra fichas técnicas el 2026-10-19 (nota del plan del PR 5, §4.2).
     *
     * @param  string  $minRule  `min:N` (el valor N vale) o `gt:N` (debe ser mayor, como un espesor).
     * @return list<string>
     */
    private function technicalNumber(string $minRule, string $max): array
    {
        return ['nullable', 'numeric', 'decimal:0,2', $minRule, 'max:'.$max];
    }

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
            // No antes de hoy en planta; un borrador viejo conserva la fecha que ya tenía (D-PDR1).
            'sample_due_date' => ['bail', 'required', PlantDate::make()->notPast()->allowing($this->savedSampleDueDate())],
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
            // De GNL y criogénicos (−196 °C) a siliconados y cerámicos de alta temperatura (650 a 1.000 °C).
            'context_payload.temp_min' => $this->technicalNumber('min:-200', '1000'),
            'context_payload.temp_max' => [...$this->technicalNumber('min:-200', '1000'), function (string $attribute, mixed $value, \Closure $fail): void {
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
            // Pull-off en psi (ASTM D4541): los equipos llegan a unos 3.500 psi con el dado de 20 mm.
            'performance_payload.adherencia_obj' => $this->technicalNumber('gt:0', '10000'),
            // Horas de cámara salina (ASTM B117 no fija duración; lo usual va de 24 a 5.000 h).
            'performance_payload.camara_obj' => $this->technicalNumber('gt:0', '20000'),
            'performance_payload.criterios_tecnicos' => ['nullable', 'string', 'max:5000'],
            'performance_payload.indispensable' => ['required', 'string', 'max:5000'],
            'performance_payload.negociable' => ['nullable', 'string', 'max:5000'],
            'performance_payload.color' => ['required', 'string', 'max:255'],
            'performance_payload.brillo' => ['required', 'string', 'max:255'],
            'performance_payload.textura' => ['required', 'string', 'max:255'],
            'performance_payload.cubricion' => ['required', 'string', 'max:255'],
            // Tolerancia de color: de negro a blanco hay ΔE = 100. 0 no es una tolerancia que se pueda medir.
            'performance_payload.delta_e' => $this->technicalNumber('gt:0', '100'),
            'performance_payload.retencion' => ['required', 'string', 'max:255'],
            'performance_payload.acabado_ref' => ['nullable', 'string', 'max:5000'],

            // Aplicación
            'application_payload' => ['required', 'array'],
            'application_payload.metodo' => ['required', 'array'],
            'application_payload.metodo.*' => ['string', 'max:255'],
            // Hay productos que se aplican sobre superficies calientes, hasta 200 a 250 °C.
            'application_payload.temp_aplicacion' => $this->technicalNumber('min:-30', '250'),
            'application_payload.humedad_aplicacion' => $this->technicalNumber('min:0', '100'),
            'application_payload.geometria' => ['required', 'string', 'max:255'],
            // Mils por mano: un intumescente epóxico llega a unos 790 mils (20 mm) en una sola mano.
            'application_payload.dft_min' => $this->technicalNumber('gt:0', '1200'),
            'application_payload.dft_max' => [...$this->technicalNumber('gt:0', '1200'), function (string $attribute, mixed $value, \Closure $fail): void {
                $min = $this->input('application_payload.dft_min');
                if ($min !== null && $value !== null && (float) $min > (float) $value) {
                    $fail(__('El espesor mínimo no puede ser mayor que el máximo.'));
                }
            }],
            'application_payload.manos' => ['required', 'integer', 'min:1', 'max:20'],
            'application_payload.repinte' => ['required', 'string', 'max:255'],
            'application_payload.servicio' => ['required', 'string', 'max:255'],
            'application_payload.antidescuelgue' => ['required', 'string', 'max:255'],
            'application_payload.equipo_detalle' => ['nullable', 'string', 'max:5000'],
            'application_payload.vehiculo' => ['required', 'string', 'max:255'],
            'application_payload.tecnologia' => ['required', 'string', 'max:255'],
            'application_payload.otra_tecnologia' => ['nullable', 'string', 'max:255', 'required_if:application_payload.tecnologia,Otra'],
            'application_payload.componentes' => ['required', 'string', 'max:255'],
            'application_payload.relacion_mezcla' => ['nullable', 'string', 'max:255'],
            // Minutos: el de un bicomponente va de minutos a unas 8 horas. Un monocomponente lo deja vacío.
            'application_payload.pot_life' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'application_payload.solidos_vol' => $this->technicalNumber('min:0', '100'),
            'application_payload.ajustador' => ['required', 'string', 'max:255'],
            // g/L: 0 es un producto sin VOC; un solvente puro ronda 880.
            'application_payload.voc' => $this->technicalNumber('min:0', '1000'),
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
