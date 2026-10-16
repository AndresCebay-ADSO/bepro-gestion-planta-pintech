<?php

declare(strict_types=1);

namespace App\Enums;

use App\Casts\WholeUnits;

/**
 * Catálogo de tipos de número del esquema (auditoría 3.6, PR 4). Cada columna numérica pertenece a un tipo, y el tipo
 * decide su tamaño en la base, el cast del modelo y la regla que la protege en los FormRequests. Es la única fuente de
 * esos límites: las migraciones repiten los tamaños escritos (no dependen de la aplicación) y un test compara las
 * columnas con este catálogo.
 *
 * Los topes de negocio (margen menor que 100, Hegman hasta 8, mínimo 1 unidad…) van en cada request, encima de estos.
 */
enum NumberType
{
    /** Kilos, litros, galones, derrame: 4 decimales porque las fórmulas pesan en gramos. */
    case Quantity;

    /** Envases, etiquetas, unidades planificadas y vendidas: se cuentan enteros. */
    case Units;

    /** Costos, precios y totales en pesos. 12 enteros: una OP o un pedido pasan con holgura de los $100 M. */
    case Money;

    /** Porcentajes que se digitan: margen, CIF, IVA, umbrales, ajustes de precio. */
    case Percentage;

    /** Porcentajes que calcula el sistema (variaciones de costo): pueden pasar de 999,99 % sin que sea un error. */
    case ComputedPercentage;

    /** Equivalencias entre unidades de medida: 6 decimales para que mL → galón no pierda precisión. */
    case Factor;

    /** Densidad en kg por galón. */
    case Density;

    /** Mediciones de calidad (KU, Hegman, % de sólidos), con un decimal. */
    case Quality;

    /** Dígitos totales de la columna; null para las columnas enteras. */
    public function precision(): ?int
    {
        return match ($this) {
            self::Quantity => 12,
            self::Units => null,
            self::Money => 16,
            self::Percentage => 5,
            self::ComputedPercentage => 12,
            self::Factor => 14,
            self::Density => 10,
            self::Quality => 4,
        };
    }

    public function scale(): int
    {
        return match ($this) {
            self::Units => 0,
            self::Percentage => 2,
            self::Factor => 6,
            self::Quality => 1,
            self::Quantity, self::Money, self::ComputedPercentage, self::Density => 4,
        };
    }

    /** El mayor valor que cabe en la columna, como texto para no pasar por un float. */
    public function max(): string
    {
        $precision = $this->precision();

        if ($precision === null) {
            return '2147483647';
        }

        $integerDigits = $precision - $this->scale();
        $integerPart = $integerDigits > 0 ? str_repeat('9', $integerDigits) : '0';

        return $this->scale() > 0 ? $integerPart.'.'.str_repeat('9', $this->scale()) : $integerPart;
    }

    /**
     * Reglas que garantizan que el valor cabe en la columna: tipo, decimales y máximo. Cada request agrega `required`
     * o `nullable` y el mínimo. Un tope de negocio más bajo que el de la columna se pasa en `$max`, para que el campo
     * tenga un solo `max` y un solo mensaje.
     *
     * @return list<string>
     */
    public function rules(?string $max = null): array
    {
        $max ??= $this->max();

        if ($this === self::Units) {
            return ['integer', 'max:'.$max];
        }

        return ['numeric', 'decimal:0,'.$this->scale(), 'max:'.$max];
    }

    /** Cast de Eloquent para las columnas de este tipo. */
    public function cast(): string
    {
        return $this === self::Units ? WholeUnits::class : 'decimal:'.$this->scale();
    }
}
