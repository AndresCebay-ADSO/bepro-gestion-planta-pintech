/**
 * Permisos de un rol agrupados por módulo (docs/MATRIZ_RBAC.md §8.3).
 *
 * Al marcar un permiso se marcan sus dependencias; al desmarcarlo se desmarcan los que dependen de él.
 * Los permisos obligatorios no se pueden desmarcar. El servidor valida las mismas reglas (RoleFormRequest).
 */
import { Check } from 'lucide-react';
import { useMemo } from 'react';

import { Badge } from '@/components/ui/badge';
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

    if (readOnly) {
        return (
            <div className="grid gap-4 md:grid-cols-2">
                {modules.map((module) => {
                    const names = module.permissions.map((p) => p.name);
                    const grantedPermissions = module.permissions.filter((p) =>
                        selectedSet.has(p.name),
                    );
                    const grantedCount = grantedPermissions.length;
                    const headingId = `module-heading-${module.key}`;

                    return (
                        <section
                            key={module.key}
                            aria-labelledby={headingId}
                            className="rounded-lg border border-border bg-card/40 p-4"
                        >
                            <div className="mb-3 flex items-center justify-between gap-3 border-b border-border pb-3">
                                <h3
                                    id={headingId}
                                    className="text-sm font-semibold text-foreground"
                                >
                                    {module.label}
                                </h3>
                                <Badge
                                    variant={
                                        grantedCount > 0
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                    className="text-xs font-normal"
                                >
                                    {grantedCount} de {names.length}
                                </Badge>
                            </div>

                            {grantedCount === 0 ? (
                                <p className="text-xs text-muted-foreground italic">
                                    Sin permisos asignados en este módulo.
                                </p>
                            ) : (
                                <ul role="list" className="space-y-2">
                                    {grantedPermissions.map((permission) => (
                                        <li
                                            key={permission.name}
                                            className="flex items-center gap-2 text-sm text-foreground"
                                        >
                                            <Check
                                                className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                                aria-hidden="true"
                                            />
                                            <span>{permission.label}</span>
                                            {permission.required && (
                                                <Badge
                                                    variant="outline"
                                                    className="px-1.5 py-0 text-[10px] font-normal text-muted-foreground"
                                                >
                                                    Obligatorio
                                                </Badge>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    );
                })}
            </div>
        );
    }

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
                        className="rounded-lg border border-border p-4"
                    >
                        <legend className="sr-only">{module.label}</legend>
                        <div className="mb-3 flex items-center justify-between gap-3 border-b border-border pb-3">
                            <div className="flex items-center gap-3">
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
                                <Label
                                    htmlFor={moduleId}
                                    aria-hidden="true"
                                    className="cursor-pointer font-semibold"
                                >
                                    {module.label}
                                </Label>
                            </div>
                            <span
                                className="text-xs text-muted-foreground"
                                aria-label={`${grantedCount} de ${names.length} permisos seleccionados`}
                            >
                                {grantedCount}/{names.length}
                            </span>
                        </div>

                        <div className="space-y-3">
                            {module.permissions.map((permission) => {
                                const id = `permission-${permission.name}`;
                                const descId = `desc-${permission.name}`;
                                const dependencyLabels = permission.dependencies
                                    .map((dependency) => labels.get(dependency))
                                    .filter(Boolean);
                                const hasHelpText =
                                    permission.required ||
                                    dependencyLabels.length > 0;

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
                                                ) || permission.required
                                            }
                                            disabled={
                                                locked || permission.required
                                            }
                                            aria-describedby={
                                                hasHelpText ? descId : undefined
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
                                                className="cursor-pointer text-sm font-normal"
                                            >
                                                {permission.label}
                                            </Label>
                                            {hasHelpText && (
                                                <div
                                                    id={descId}
                                                    className="space-y-0.5"
                                                >
                                                    {permission.required && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            Obligatorio: sin él,
                                                            los usuarios no
                                                            pueden entrar al
                                                            inicio.
                                                        </span>
                                                    )}
                                                    {dependencyLabels.length >
                                                        0 && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            Incluye:{' '}
                                                            {dependencyLabels.join(
                                                                ', ',
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
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
