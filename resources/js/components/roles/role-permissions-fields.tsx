/**
 * Permisos de un rol agrupados por módulo (docs/PLAN_FASE_2_RBAC.md, 2.4).
 *
 * Al marcar un permiso se marcan sus dependencias; al desmarcarlo se desmarcan los que dependen de él.
 * Los permisos obligatorios no se pueden desmarcar. El servidor valida las mismas reglas (RoleFormRequest).
 */
import { useMemo } from 'react';

import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { Permission } from '@/types/permissions';
import type { PermissionModuleGroup } from '@/types/roles';

interface Props {
    modules: PermissionModuleGroup[];
    selected: Permission[];
    onChange?: (permissions: Permission[]) => void;
    readOnly?: boolean;
    disabled?: boolean;
}

export default function RolePermissionsFields({
    modules,
    selected,
    onChange,
    readOnly = false,
    disabled = false,
}: Props) {
    const options = useMemo(
        () => modules.flatMap((module) => module.permissions),
        [modules],
    );

    const labels = useMemo(
        () =>
            new Map<Permission, string>(
                options.map((option) => [option.name, option.label]),
            ),
        [options],
    );

    const requiredSet = useMemo(
        () =>
            new Set(
                options
                    .filter((option) => option.required)
                    .map((option) => option.name),
            ),
        [options],
    );

    const selectedSet = new Set(selected);
    const locked = readOnly || disabled;

    const grant = (names: Permission[]) => {
        const next = new Set(selected);

        for (const name of names) {
            next.add(name);
            options
                .find((option) => option.name === name)
                ?.dependencies.forEach((dependency) => next.add(dependency));
        }

        onChange?.(options.map((o) => o.name).filter((n) => next.has(n)));
    };

    const revoke = (names: Permission[]) => {
        const removed = new Set(names.filter((name) => !requiredSet.has(name)));

        // Quien depende de un permiso retirado también se retira (salvo los obligatorios).
        for (const option of options) {
            if (
                !requiredSet.has(option.name) &&
                option.dependencies.some((dependency) =>
                    removed.has(dependency),
                )
            ) {
                removed.add(option.name);
            }
        }

        onChange?.(selected.filter((name) => !removed.has(name)));
    };

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {modules.map((module) => {
                const names = module.permissions.map((p) => p.name);
                const grantedCount = names.filter((name) =>
                    selectedSet.has(name),
                ).length;
                const moduleState =
                    grantedCount === 0
                        ? false
                        : grantedCount === names.length
                          ? true
                          : 'indeterminate';
                const moduleId = `module-${module.key}`;

                return (
                    <fieldset
                        key={module.key}
                        className={`rounded-lg border border-border p-4 ${readOnly && grantedCount === 0 ? 'opacity-60' : ''}`}
                    >
                        <legend className="sr-only">{module.label}</legend>
                        <div className="mb-3 flex items-center justify-between gap-3 border-b border-border pb-3">
                            <div className="flex items-center gap-3">
                                {!readOnly && (
                                    <Checkbox
                                        id={moduleId}
                                        checked={moduleState}
                                        disabled={locked}
                                        onCheckedChange={(checked) =>
                                            checked === true
                                                ? grant(names)
                                                : revoke(names)
                                        }
                                        aria-label={`Todo el módulo ${module.label}`}
                                    />
                                )}
                                <Label
                                    htmlFor={readOnly ? undefined : moduleId}
                                    className="font-semibold"
                                >
                                    {module.label}
                                </Label>
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {grantedCount}/{names.length}
                            </span>
                        </div>

                        <div className="space-y-3">
                            {module.permissions.map((permission) => {
                                const id = `permission-${permission.name}`;
                                const dependencyLabels = permission.dependencies
                                    .map((dependency) => labels.get(dependency))
                                    .filter(Boolean);

                                return (
                                    <div
                                        key={permission.name}
                                        className="flex items-start gap-3"
                                    >
                                        <Checkbox
                                            id={id}
                                            checked={
                                                selectedSet.has(
                                                    permission.name,
                                                ) ||
                                                (!readOnly &&
                                                    permission.required)
                                            }
                                            disabled={
                                                locked || permission.required
                                            }
                                            onCheckedChange={(checked) =>
                                                checked === true
                                                    ? grant([permission.name])
                                                    : revoke([permission.name])
                                            }
                                        />
                                        <div className="grid gap-0.5">
                                            <Label
                                                htmlFor={id}
                                                className="text-sm font-normal"
                                            >
                                                {permission.label}
                                            </Label>
                                            {!readOnly &&
                                                permission.required && (
                                                    <span className="text-xs text-muted-foreground">
                                                        Obligatorio: sin él, los
                                                        usuarios no pueden
                                                        entrar al inicio.
                                                    </span>
                                                )}
                                            {!readOnly &&
                                                dependencyLabels.length > 0 && (
                                                    <span className="text-xs text-muted-foreground">
                                                        Incluye:{' '}
                                                        {dependencyLabels.join(
                                                            ', ',
                                                        )}
                                                    </span>
                                                )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </fieldset>
                );
            })}
        </div>
    );
}
