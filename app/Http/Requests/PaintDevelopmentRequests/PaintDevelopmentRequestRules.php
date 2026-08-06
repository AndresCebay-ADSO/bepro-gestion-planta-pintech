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
}
