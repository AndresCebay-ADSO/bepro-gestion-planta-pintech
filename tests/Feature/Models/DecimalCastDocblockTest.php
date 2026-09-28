<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

// El cast `decimal:N` devuelve un string. Con strict_types, un docblock `float` invita a pasar el valor a un parámetro
// `float`, y eso lanza TypeError en tiempo de ejecución (B43). Cada atributo decimal se documenta como `string`,
// con `|null` solo si la columna admite nulos.
test('los atributos con cast decimal se documentan como string con la nulabilidad de su columna', function () {
    $errors = [];
    $checked = 0;

    foreach (glob(app_path('Models/*.php')) as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');
        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
            continue;
        }

        preg_match_all('/@property(?:-read)?\s+(\S+)\s+\$(\w+)/', (string) $reflection->getDocComment(), $matches, PREG_SET_ORDER);
        $documented = collect($matches)->mapWithKeys(fn (array $match) => [$match[2] => $match[1]]);

        /** @var Model $model */
        $model = new $class;
        $columns = collect(Schema::getColumns($model->getTable()))->keyBy('name');

        foreach ($model->getCasts() as $attribute => $cast) {
            if (! str_starts_with((string) $cast, 'decimal:')) {
                continue;
            }

            $checked++;
            $expected = ($columns[$attribute]['nullable'] ?? false) ? 'string|null' : 'string';
            $actual = $documented[$attribute] ?? null;
            $types = $actual === null ? [] : explode('|', $actual);
            sort($types);
            $expectedTypes = explode('|', $expected);
            sort($expectedTypes);

            if ($types !== $expectedTypes) {
                $errors[] = "{$class}::\${$attribute}: se esperaba «{$expected}», el docblock dice «".($actual ?? 'nada').'»';
            }
        }
    }

    expect($checked)->toBeGreaterThan(0)
        ->and($errors)->toBe([], implode(PHP_EOL, $errors));
});
