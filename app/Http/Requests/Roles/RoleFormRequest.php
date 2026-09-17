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
 * Reglas compartidas al crear y editar un rol personalizado (docs/PLAN_FASE_2_RBAC.md, 2.4).
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
        return [
            'name' => ['bail', 'required', 'string', 'max:50', $this->availableNameRule()],
            'permissions' => ['present', 'array'],
            'permissions.*' => [
                'bail',
                'string',
                'distinct',
                Rule::in(app(PermissionCatalogService::class)->assignableToCustomRoles()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
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

                $granted = array_map(
                    fn (string $name): Permission => Permission::from($name),
                    $this->input('permissions', []),
                );

                foreach ($granted as $permission) {
                    $missing = array_filter(
                        $permission->dependencies(),
                        fn (Permission $dependency): bool => ! in_array($dependency, $granted, true),
                    );

                    if ($missing !== []) {
                        $validator->errors()->add('permissions', __('":permission" necesita también: :dependencies.', [
                            'permission' => $permission->label(),
                            'dependencies' => implode(', ', array_map(
                                fn (Permission $dependency): string => $dependency->label(),
                                $missing,
                            )),
                        ]));
                    }
                }
            },
        ];
    }

    /**
     * El nombre no puede repetir otro rol ni el nombre o la etiqueta de un rol del sistema (sin distinguir mayúsculas).
     */
    private function availableNameRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $normalized = mb_strtolower((string) $value);

            $systemNames = collect(SystemRole::cases())
                ->flatMap(fn (SystemRole $role): array => [mb_strtolower($role->value), mb_strtolower($role->label())]);

            if ($systemNames->contains($normalized)) {
                $fail(__('Ese nombre pertenece a un rol del sistema.'));

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
