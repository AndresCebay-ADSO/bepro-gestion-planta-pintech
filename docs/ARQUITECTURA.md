# Guía de Arquitectura - Pintech OS

> Documento de decisiones arquitectónicas y patrones recomendados para el desarrollo del sistema.

### 📜 Nota de Privacidad - Materias Primas
Debido a una política de privacidad y seguridad industrial de Pintech Colombia S.A.S, el modelo **RawMaterial** NO incluye un campo `name`. Todas las materias primas se gestionan exclusivamente mediante sus **códigos internos** para proteger la propiedad intelectual de las formulaciones. No se debe intentar añadir el campo `name` a esta tabla.

## 1. Filosofía

**Mantenerlo simple**. Este es un proyecto de 3 meses con 1 desarrollador. La arquitectura actual (Laravel + Inertia + React) es suficiente. No agregar complejidad innecesaria.

### Principios

1. **Code First**: Escribir código funcional antes de abstracciones
2. **YAGNI**: No implementar algo hasta que sea necesario
3. **Skinny Controllers, Fat Services**: La lógica de negocio va en Services/Actions, no en controllers
4. **Type Safety**: Usar tipos estrictos donde sea posible (PHP 8.3+)

---

## 2. Estructura de Carpetas

```
app/
├── Http/
│   ├── Controllers/          ← Agrupados por dominio
│   │   ├── Inventory/
│   │   │   └── InventoryMovementController.php
│   │   ├── Production/
│   │   └── Products/
│   └── Requests/
│       └── [Mismo patrón que Controllers]
├── Services/                 ← Lógica reutilizable entre controllers
│   └── Inventory/
│       ├── PEPSStrategy.php
│       └── StockCalculator.php
├── Actions/                  ← Operaciones de una sola responsabilidad
│   ├── Inventory/
│   └── Production/
├── ValueObjects/             ← Datos inmutables con lógica
├── Policies/                 ← Autorización (ya existen)
└── Models/                   ← Eloquent (ya existen)
```

---

## 3. Cuándo usar cada patrón

### 3.1 Services

**Usar cuando**: Hay lógica de negocio reutilizable entre controllers o que involucra múltiples modelos.

**Ejemplo**: `InventoryService` ya existe ✅

```php
// ✅ Bien: Lógica compleja de inventario
class InventoryService
{
    public function storeMovement(array $data, int $userId): InventoryMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            // ... lógica compleja
        });
    }
}
```

### 3.2 Action Classes

**Usar cuando**: Una operación específica requiere múltiples pasos coordinados.

**Crear cuando**:
- La operación tiene más de 3 pasos lógicos
- Se necesita desde múltiples lugares (CLI, Jobs, Controllers)
- Se quiere testear la operación aislada

**Ejemplos a crear**:

```php
// app/Actions/Production/CreateProductionOrderAction.php
namespace App\Actions\Production;

class CreateProductionOrderAction
{
    public function __construct(
        private PEPSStrategy $peps,
        private CalculateProductionCostAction $costCalculator,
    ) {}
    
    public function execute(ProductionOrderData $data): ProductionOrder
    {
        return DB::transaction(function () use ($data) {
            // 1. Crear orden
            // 2. Consumir materias primas (PEPS)
            // 3. Calcular costo real
            // 4. Generar QR
            return $order;
        });
    }
}
```

### 3.3 Value Objects

**Usar cuando**: Se necesitan datos con comportamiento inmutable.\n
**Crear cuando**:
- Hay cálculos derivados de los datos
- Se necesitan validaciones específicas del dominio
- Los datos son inmutables (ej: costos calculados)

**Ejemplo**:

```php
// app/ValueObjects/FormulaCost.php
namespace App\ValueObjects;

readonly class FormulaCost
{
    public function __construct(
        public float $rawMaterialsCost,
        public float $overheadCost,
        public float $totalCost,
        public float $costPerLiter,
    ) {}
    
    public function withVariation(float $percentage): self
    {
        return new self(
            $this->rawMaterialsCost * (1 + $percentage),
            $this->overheadCost,
            $this->totalCost * (1 + $percentage),
            $this->costPerLiter * (1 + $percentage),
        );
    }
}
```

### 3.4 Repositories

**Usar cuando**: Los queries de Eloquent se vuelven demasiado complejos o repetidos.

**NO crear aún**: Mientras los queries sean manejables en los modelos, no es necesario.

**Crear cuando**:
- Un query se repite en más de 3 lugares
- Se necesitan queries dinámicos complejos
- Se quiere cambiar la fuente de datos (cache, API, etc.)

---

## 4. Patrones Específicos por Dominio

### 4.1 Inventario - PEPS (CU13)

El PEPS es el algoritmo más crítico del sistema. Implementar en una clase dedicada:

```php
// app/Services/Inventory/PEPSStrategy.php
namespace App\Services\Inventory;

class PEPSStrategy
{
    /**
     * Asigna lotes a una orden siguiendo PEPS.
     * Lanza excepción si no hay stock suficiente.
     *
     * @param int $rawMaterialId ID de la materia prima
     * @param float $requiredQuantity Cantidad requerida
     * @return array Lotes asignados con cantidades
     * @throws InsufficientStockException
     */
    public function assignLots(int $rawMaterialId, float $requiredQuantity): array
    {
        $batches = InventoryBatch::where('raw_material_id', $rawMaterialId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->lockForUpdate()  // Bloquear para concurrencia
            ->get();
        
        $assigned = [];
        $remaining = $requiredQuantity;
        
        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            
            $take = min($batch->remaining_quantity, $remaining);
            $assigned[] = [
                'batch_id' => $batch->id,
                'quantity' => $take,
                'unit_cost' => $batch->unit_price,
                'batch' => $batch,  // Referencia para actualizar después
            ];
            $remaining -= $take;
        }
        
        if ($remaining > 0) {
            throw new InsufficientStockException(
                "Stock insuficiente para materia prima ID: {$rawMaterialId}"
            );
        }
        
        return $assigned;
    }
}
```

### 4.2 Órdenes de Producción (CU39)

Operación compleja que debe ser una Action:

```php
// app/Actions/Production/CreateProductionOrderAction.php
class CreateProductionOrderAction
{
    public function __construct(
        private PEPSStrategy $peps,
        private CalculateProductionCostAction $costCalculator,
        private GenerateQRCodeAction $qrGenerator,
    ) {}
    
    public function execute(CreateOrderData $data): ProductionOrder
    {
        return DB::transaction(function () use ($data) {
            // 1. Crear orden base
            $order = ProductionOrder::create([
                'order_number' => $this->generateOrderNumber(),
                'product_id' => $data->productId,
                'formula_id' => $data->formulaId,
                'planned_quantity' => $data->quantity,
                'status' => 'pendiente',
                'created_by' => $data->userId,
            ]);
            
            // 2. Consumir materias primas según fórmula (PEPS)
            foreach ($data->formula->items as $item) {
                $requiredQty = $item->quantity * $data->quantity;
                $lots = $this->peps->assignLots($item->raw_material_id, $requiredQty);
                
                foreach ($lots as $lot) {
                    $order->consumptions()->create([
                        'raw_material_id' => $item->raw_material_id,
                        'inventory_batch_id' => $lot['batch_id'],
                        'quantity' => $lot['quantity'],
                        'unit_cost' => $lot['unit_cost'],
                    ]);
                    
                    // Actualizar stock del lote
                    $lot['batch']->decrement('remaining_quantity', $lot['quantity']);
                }
            }
            
            // 3. Calcular costo esperado
            $cost = $this->costCalculator->forOrder($order);
            $order->update(['estimated_cost' => $cost->totalCost]);
            
            // 4. Generar QR
            $qr = $this->qrGenerator->execute($order);
            $order->update(['qr_code_path' => $qr->path()]);
            
            return $order->refresh();
        });
    }
}
```

---

## 5. Convenciones de Código

### 5.1 Nomenclatura

| Elemento | Convención | Ejemplo |
|----------|-----------|---------|
| Controllers | PascalCase + Controller | `InventoryMovementController` |
| Services | PascalCase + Service | `InventoryService` |
| Actions | PascalCase + Action | `CreateProductionOrderAction` |
| ValueObjects | PascalCase | `FormulaCost` |
| Métodos | camelCase + verbo | `calculateTotal`, `assignLots` |
| Variables | camelCase | `$remainingQuantity` |

### 5.2 Type Hints

```php
// ✅ Usar tipos estrictos
public function assignLots(int $rawMaterialId, float $requiredQuantity): array

// ✅ DTOs para datos complejos
public function execute(ProductionOrderData $data): ProductionOrder

// ✅ Nullable explícito
public function findById(int $id): ?ProductionOrder
```

### 5.3 Inyección de Dependencias

```php
// ✅ Inyección por constructor (preferido)
class InventoryMovementController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}
}

// ✅ En Actions, inyectar dependencias necesarias
class CreateProductionOrderAction
{
    public function __construct(
        private PEPSStrategy $peps,
        private CalculateCostAction $costCalculator,
    ) {}
}
```

---

## 6. Testing

### 6.1 Prioridad de Tests

1. **PEPS Strategy** - Crítico, testear con múltiples escenarios
2. **Actions** - Testear operaciones complejas
3. **Form Requests** - Validaciones
4. **Controllers** - Flujos de integración

### 6.2 Ejemplo de Test para PEPS

```php
// tests/Unit/Services/Inventory/PEPSStrategyTest.php
class PEPSStrategyTest extends TestCase
{
    #[Test]
    public function it_assigns_lots_in_fifo_order()
    {
        $rawMaterial = RawMaterial::factory()->create();
        
        // Crear lotes: primero 100, segundo 50
        $batch1 = InventoryBatch::factory()->create([
            'raw_material_id' => $rawMaterial->id,
            'remaining_quantity' => 100,
            'entry_date' => now()->subDays(10),
        ]);
        
        $batch2 = InventoryBatch::factory()->create([
            'raw_material_id' => $rawMaterial->id,
            'remaining_quantity' => 50,
            'entry_date' => now()->subDays(5),
        ]);
        
        $strategy = new PEPSStrategy();
        $result = $strategy->assignLots($rawMaterial->id, 120);
        
        // Debe tomar 100 del primero, 20 del segundo
        $this->assertCount(2, $result);
        $this->assertEquals(100, $result[0]['quantity']);
        $this->assertEquals(20, $result[1]['quantity']);
    }
    
    #[Test]
    public function it_throws_exception_when_insufficient_stock()
    {
        $this->expectException(InsufficientStockException::class);
        
        $rawMaterial = RawMaterial::factory()->create();
        InventoryBatch::factory()->create([
            'raw_material_id' => $rawMaterial->id,
            'remaining_quantity' => 10,
        ]);
        
        $strategy = new PEPSStrategy();
        $strategy->assignLots($rawMaterial->id, 100);
    }
}
```

---

## 7. Checklist por Fase

### Semana 3 (Checkpoint Alfa)
- [ ] CRUD Materias Primas funcional
- [ ] Auth + roles operativos
- [ ] Validaciones en Form Requests
- [ ] Mensajes de éxito/error con toast

### Semana 4-5 (Inventario PEPS)
- [ ] `PEPSStrategy` implementado y testeado
- [ ] CRUD de entradas de inventario
- [ ] Cálculo de costo promedio ponderado

### Semana 7 (Formulaciones)
- [ ] `CalculateFormulaCostAction` implementado
- [ ] CRUD de formulaciones
- [ ] Cálculo automático de costos

### Semana 8 (Órdenes Producción)
- [ ] `CreateProductionOrderAction` implementado
- [ ] Consumo automático PEPS funcionando
- [ ] Estados de orden operativos

### Semana 10+ (Refactorización opcional)
- [ ] Revisar si necesitas Repositories
- [ ] Extraer lógica de Modelos a Traits si crecieron
- [ ] Optimizar queries con eager loading

---

## 8. Anti-patrones a Evitar

### ❌ No hagas esto

```php
// ❌ Controller gordo con lógica de negocio
class InventoryController extends Controller
{
    public function store(Request $request)
    {
        // 50 líneas de lógica aquí...
        // Validaciones manuales...
        // Transacciones...
        // Cálculos...
    }
}

// ❌ Modelo con lógica de otros modelos
class Product extends Model
{
    public function createOrder($data)  // Debería estar en Action
    {
        // Lógica de órdenes en modelo Product...
    }
}

// ❌ Services vacíos o con un solo método
class CalculatorService  // Innecesario
{
    public function add($a, $b) { return $a + $b; }
}

// ❌ Interfaces por cada clase (YAGNI)
interface UserRepositoryInterface { }
class UserRepository implements UserRepositoryInterface { }  // Solo hay 1 implementación
```

### ✅ Haz esto en su lugar

```php
// ✅ Controller delega a Service/Action
class InventoryMovementController extends Controller
{
    public function store(StoreRequest $request)
    {
        $movement = $this->service->storeMovement(
            $request->validated(), 
            $request->user()->id
        );
        
        return redirect()->route('inventory.index')
            ->with('success', 'Movimiento registrado');
    }
}

// ✅ Action para operaciones complejas
class CreateProductionOrderAction
{
    public function execute(CreateOrderData $data): ProductionOrder
    {
        // Lógica aquí...
    }
}
```

---

## 9. Recursos

- [Laravel Pint](https://github.com/laravel/pint) - Formateo de código
- [PHPStan](https://phpstan.org/) - Análisis estático (opcional)
- [Laravel IDE Helper](https://github.com/barryvdh/laravel-ide-helper) - Autocompletado en IDE

---

**Última actualización**: Abril 2026  
**Autor**: Claude (recomendaciones arquitectónicas)  
**Estado**: Guía viva - actualizar según evolucione el proyecto
