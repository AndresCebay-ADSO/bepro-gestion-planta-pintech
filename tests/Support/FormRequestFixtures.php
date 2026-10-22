<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\SalesOrder;
use App\Models\ShrinkWrapType;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Spatie\Permission\Models\Role;

/**
 * Cómo leer las reglas de todos los FormRequest sin atender una petición: los tests que recorren las reglas
 * (`NumericRequestRulesTest`, `ValidationAttributesTest`) comparten la búsqueda de clases y los modelos que algunos
 * `rules()` leen de la ruta (`$this->route('product')`).
 */
final class FormRequestFixtures
{
    /**
     * @return list<class-string<FormRequest>>
     */
    public static function classes(bool $includeIndex = false): array
    {
        $classes = [];
        $directory = app_path('Http/Requests');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (! $includeIndex && str_starts_with($file->getBasename(), 'Index')) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($directory) + 1, -4);
            $class = 'App\\Http\\Requests\\'.str_replace('/', '\\', $relative);

            if (class_exists($class) && is_subclass_of($class, FormRequest::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * Los modelos que los `rules()` leen de la ruta, por el nombre del parámetro.
     *
     * @return array<string, mixed>
     */
    public static function parameters(): array
    {
        return [
            'user' => User::factory()->create(),
            'client' => Client::factory()->create(),
            'product' => Product::factory()->create(),
            'product_category' => ProductCategory::factory()->create(),
            'production_order' => ProductionOrder::factory()->create(),
            'quotation' => Quotation::factory()->create(),
            'raw_material' => RawMaterial::factory()->create(),
            'raw_material_category' => RawMaterialCategory::factory()->create(),
            'role' => Role::create(['name' => 'Rol de prueba', 'guard_name' => 'web']),
            'sales_order' => SalesOrder::factory()->create(),
            'shrink_wrap_type' => ShrinkWrapType::factory()->create(),
            'unit_of_measure' => UnitOfMeasure::factory()->create(),
            'variant' => ProductVariant::factory()->create(),
            'warehouse' => Warehouse::factory()->create(),
        ];
    }

    /**
     * El request listo para leer `rules()` y `attributes()`: con el usuario y la ruta con sus modelos.
     *
     * @param  class-string<FormRequest>  $class
     * @param  array<string, mixed>  $parameters
     */
    public static function make(string $class, array $parameters): FormRequest
    {
        $request = $class::create('/', 'POST');
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $parameters['user']);

        $route = new Route('POST', '/', []);
        $route->bind($request);

        foreach ($parameters as $name => $model) {
            $route->setParameter($name, $model);
        }

        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
