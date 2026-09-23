<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Services\PermissionCatalogService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

/**
 * Reglas compartidas al crear y editar un rol personalizado (docs/MATRIZ_RBAC.md §8).
 */
abstract class RoleFormRequest extends FormRequest
{
    /**
     * Rol que se edita, para excluirlo de la comprobación de nombre repetido.
     */
    abstract protected function ignoredRoleId(): ?int;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => preg_replace('/\s+/', ' ', trim($this->input('name')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $catalog = app(PermissionCatalogService::class);

        return [
            // Sin `|`: Spatie lo usa como separador de roles en hasRole() y en el scope role().
            'name' => ['bail', 'required', 'string', 'max:50', 'regex:/^[\pL\pN]+(?:[ _-][\pL\pN]+)*$/u', $this->availableNameRule()],
            'permissions' => ['present', 'array', 'contains:'.implode(',', $catalog->requiredForCustomRoles())],
            'permissions.*' => [
                'bail',
                'string',
                'distinct',
                Rule::in($catalog->assignableToCustomRoles()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('El nombre solo admite letras, números, espacios, guiones y guion bajo.'),
            'permissions.contains' => __('Todo rol debe incluir ":permission": sin él, sus usuarios no pueden entrar al inicio.', [
                'permission' => Permission::DashboardView->label(),
            ]),
            'permissions.*.in' => __('Ese permiso no se puede asignar a un rol personalizado.'),
        ];
    }

    /**
     * Un permiso sin sus dependencias deja pantallas o acciones que responden 403.
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $violations = app(PermissionCatalogService::class)->customRoleViolations($this->input('permissions', []));

                foreach ($violations['missing_dependencies'] as $permission => $dependencies) {
                    $validator->errors()->add('permissions', __('":permission" necesita también: :dependencies.', [
                        'permission' => Permission::from($permission)->label(),
                        'dependencies' => implode(', ', array_map(
                            fn (string $dependency): string => Permission::from($dependency)->label(),
                            $dependencies,
                        )),
                    ]));
                }
            },
        ];
    }

    /**
     * El nombre no puede repetir otro rol ni la etiqueta de un rol del sistema, sin distinguir mayúsculas (ni tildes, en las etiquetas).
     */
    private function availableNameRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $normalized = mb_strtolower((string) $value);

            if (SystemRole::isReservedLabel((string) $value)) {
                $fail(__('Ese nombre está reservado para un rol del sistema.'));

                return;
            }

            $taken = Role::query()
                ->whereRaw('LOWER(name) = ?', [$normalized])
                ->when($this->ignoredRoleId(), fn ($query, int $id) => $query->whereKeyNot($id))
                ->exists();

            if ($taken) {
                $fail(__('Ya existe un rol con ese nombre.'));
            }
        };
    }
}
