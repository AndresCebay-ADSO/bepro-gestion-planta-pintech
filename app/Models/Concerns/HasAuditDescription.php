<?php

namespace App\Models\Concerns;

/**
 * Provides standardized Spanish audit descriptions for models using Spatie's LogsActivity.
 *
 * Models using this trait MUST define:
 * - `string $auditLabel` — Human-readable name for the model (e.g., 'Producto', 'Bodega')
 * - `string $auditIdentifierAttribute` — The attribute used as identifier (e.g., 'name', 'code', 'id')
 *
 * Optionally define:
 * - `bool $auditFeminine` — Set to true for feminine nouns (default: false)
 *
 * @example
 * ```php
 * class Product extends Model
 * {
 *     use LogsActivity, HasAuditDescription;
 *
 *     protected string $auditLabel = 'Producto';
 *     protected string $auditIdentifierAttribute = 'name';
 * }
 * ```
 */
trait HasAuditDescription
{
    /**
     * @var array<string, array{masculine: string, feminine: string}>
     */
    private static array $eventTranslations = [
        'created' => ['masculine' => 'creado', 'feminine' => 'creada'],
        'updated' => ['masculine' => 'actualizado', 'feminine' => 'actualizada'],
        'deleted' => ['masculine' => 'eliminado', 'feminine' => 'eliminada'],
    ];

    /**
     * Generate a standardized Spanish audit description.
     *
     * Output format: `{Label} "{identifier}" {translated_event}`
     * Examples:
     *   - `Producto "Pintura Blanca" creado`
     *   - `Bodega "Fábrica Principal" creada`
     *   - `Orden de producción "OP-00042" eliminada`
     */
    public function getAuditDescription(string $eventName): string
    {
        $label = property_exists($this, 'auditLabel') ? $this->auditLabel : class_basename($this);
        $identifier = $this->resolveAuditIdentifier();
        $translatedEvent = $this->translateEvent($eventName);

        if ($identifier !== null && $identifier !== '') {
            return "{$label} \"{$identifier}\" {$translatedEvent}";
        }

        return "{$label} {$translatedEvent}";
    }

    /**
     * Resolve the identifier value for the audit description.
     */
    private function resolveAuditIdentifier(): ?string
    {
        if (! property_exists($this, 'auditIdentifierAttribute')) {
            return null;
        }

        $attribute = $this->auditIdentifierAttribute;
        $value = $this->getAttribute($attribute);

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Translate the Spatie event name to Spanish with correct grammatical gender.
     */
    private function translateEvent(string $eventName): string
    {
        $isFeminine = property_exists($this, 'auditFeminine') && $this->auditFeminine === true;
        $gender = $isFeminine ? 'feminine' : 'masculine';

        return self::$eventTranslations[$eventName][$gender] ?? $eventName;
    }
}
