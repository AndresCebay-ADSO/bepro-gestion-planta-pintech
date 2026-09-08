import { Eye, Loader2, Pencil, PowerOff, Trash } from 'lucide-react';
import React from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface ActionConfig {
    view?: boolean;
    edit?: boolean;
    delete?: boolean;
}

interface TableActionsProps {
    actions?: ActionConfig;
    permissions?: ActionConfig;
    loading?: ActionConfig;
    disabled?: ActionConfig;
    tooltips?: { view?: string; edit?: string; delete?: string };
    deleteIcon?: 'trash' | 'power-off';
    onView?: () => void;
    onEdit?: () => void;
    onDelete?: () => void;
    className?: string;
    children?: React.ReactNode;
}

export function TableActions({
    actions = { view: true, edit: true, delete: true },
    permissions = { view: true, edit: true, delete: true },
    loading = { view: false, edit: false, delete: false },
    disabled,
    tooltips,
    deleteIcon = 'trash',
    onView,
    onEdit,
    onDelete,
    className,
    children,
}: TableActionsProps) {
    return (
        <TooltipProvider delayDuration={400}>
            <div
                className={cn('flex items-center justify-end gap-2', className)}
            >
                {/* Visualización (View) */}
                {actions.view && permissions.view && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className="inline-flex">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8 h-8 w-8 cursor-pointer"
                                    onClick={onView}
                                    disabled={disabled?.view || loading.view}
                                >
                                    {loading.view ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <Eye />
                                    )}
                                    <span className="sr-only">
                                        {tooltips?.view ?? 'Ver detalles'}
                                    </span>
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {tooltips?.view ?? 'Ver detalles'}
                        </TooltipContent>
                    </Tooltip>
                )}

                {/* Edición (Edit) */}
                {actions.edit && permissions.edit && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className="inline-flex">
                                <Button
                                    variant="warning"
                                    size="icon"
                                    className="size-8 h-8 w-8 cursor-pointer"
                                    onClick={onEdit}
                                    disabled={disabled?.edit || loading.edit}
                                >
                                    {loading.edit ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <Pencil />
                                    )}
                                    <span className="sr-only">
                                        {tooltips?.edit ?? 'Editar registro'}
                                    </span>
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {tooltips?.edit ?? 'Editar registro'}
                        </TooltipContent>
                    </Tooltip>
                )}

                {/* Eliminación (Delete) */}
                {actions.delete && permissions.delete && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className="inline-flex">
                                <Button
                                    variant={
                                        disabled?.delete
                                            ? 'outline'
                                            : deleteIcon === 'power-off'
                                              ? 'warning'
                                              : 'destructive'
                                    }
                                    size="icon"
                                    className="size-8 h-8 w-8 cursor-pointer"
                                    onClick={onDelete}
                                    disabled={
                                        disabled?.delete || loading.delete
                                    }
                                >
                                    {loading.delete ? (
                                        <Loader2 className="animate-spin" />
                                    ) : deleteIcon === 'power-off' ? (
                                        <PowerOff />
                                    ) : (
                                        <Trash />
                                    )}
                                    <span className="sr-only">
                                        {tooltips?.delete ??
                                            'Eliminar registro'}
                                    </span>
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {tooltips?.delete ?? 'Eliminar registro'}
                        </TooltipContent>
                    </Tooltip>
                )}

                {children}
            </div>
        </TooltipProvider>
    );
}
