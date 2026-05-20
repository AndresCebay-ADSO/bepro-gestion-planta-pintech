# Actualizaciones de costos y materias primas (2026-04-23)

## 1) Comando de recálculo de costos en producción

Se dejó disponible el comando `costs:recalculate-product` para poder recalcular costos cuando:
- cambia el precio de una materia prima,
- se ajustan variantes,
- se necesita corregir históricos sin tocar manualmente base de datos.

### ¿Se debe borrar en producción?
No. La recomendación es **mantenerlo** y usarlo como herramienta de mantenimiento controlado.

### Recomendación de uso
- Ejecutarlo manualmente por soporte/admin cuando haya cambios de costo.
- Registrar en bitácora interna cuándo se ejecutó y para qué producto.
- No exponerlo a usuarios finales ni automatizarlo sin necesidad.

## 2) Cambios aplicados recientemente

### Costos de producción
- Cierre de órdenes con lógica FIFO real para consumo de ingredientes.
- Inclusión de costo de empaque por variante en el costo final.
- Validación de consistencia entre rendimiento real y envasado equivalente.
- Vista de orden con más desglose de costos (granel, terminado y estimaciones).

### Recalculo de costos comerciales
- Al cambiar costos base relevantes, se puede disparar recálculo de precio.
- Se añadió soporte para recalcular en bloque por comando para casos operativos.

### Materias primas (creación)
Se ajustó la política para que al crear materia prima:
- **Obligatorios**: `code`, `category_id`, `unit_of_measure_id`, `minimum_stock`, `alert_days_before_expiry`.
- **Opcional**: `current_price`.
- Si `current_price` no se envía, se inicializa en `0` hasta que existan lotes.

Esto evita exigir precio cuando todavía no hay lotes registrados.

## 3) Criterio de operación recomendado

- El costo contable/operativo de consumo en producción debe venir de lotes (FIFO).
- El precio de referencia general de materia prima puede mantenerse editable para planeación, pero no debe contradecir la realidad de lotes por largos periodos.
- Si más adelante cambian la política (ponderado, último lote, techo al más caro), se debe implementar como estrategia configurable en un servicio de costos.

## 4) Pendiente opcional para futuro

- Parametrizar política de costo de referencia de materia prima:
  - `weighted_average`
  - `last_lot`
  - `max(last_lot, previous_reference)`

Así se puede cambiar regla sin reescribir módulos.
