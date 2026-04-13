import { Eye, Loader2, Pencil, Trash } from "lucide-react";
import React from "react";
import { Button } from "@/components/ui/button";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";

interface ActionConfig {
    view?: boolean;
    edit?: boolean;
    delete?: boolean;
}

interface TableActionsProps {
    actions?: ActionConfig;
    permissions?: ActionConfig;
    loading?: ActionConfig;
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
    onView,
    onEdit,
    onDelete,
    className,
    children,
}: TableActionsProps) {
    return (
        <TooltipProvider delayDuration={400}>
            <div className={cn("flex items-center gap-2 justify-end", className)}>
                {/* Visualización (View) */}
                {actions.view && permissions.view && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-8 h-8 w-8 cursor-pointer"
                                onClick={onView}
                                disabled={loading.view}
                            >
                                {loading.view ? (
                                    <Loader2 className="animate-spin" />
                                ) : (
                                    <Eye />
                                )}
                                <span className="sr-only">Ver detalles</span>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Ver detalles</TooltipContent>
                    </Tooltip>
                )}

                {/* Edición (Edit) */}
                {actions.edit && permissions.edit && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="warning"
                                size="icon"
                                className="size-8 h-8 w-8 cursor-pointer"
                                onClick={onEdit}
                                disabled={loading.edit}
                            >
                                {loading.edit ? (
                                    <Loader2 className="animate-spin" />
                                ) : (
                                    <Pencil />
                                )}
                                <span className="sr-only">Editar registro</span>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Editar registro</TooltipContent>
                    </Tooltip>
                )}

                {/* Eliminación (Delete) */}
                {actions.delete && permissions.delete && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="destructive"
                                size="icon"
                                className="size-8 h-8 w-8 cursor-pointer"
                                onClick={onDelete}
                                disabled={loading.delete}
                            >
                                {loading.delete ? (
                                    <Loader2 className="animate-spin" />
                                ) : (
                                    <Trash />
                                )}
                                <span className="sr-only">Eliminar registro</span>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Eliminar registro</TooltipContent>
                    </Tooltip>
                )}

                {children}
            </div>
        </TooltipProvider>
    );
}
