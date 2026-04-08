import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Warehouse } from 'lucide-react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

type WarehouseOption = {
    id: number;
    name: string;
    city: string;
};

type WarehouseContext = {
    current: WarehouseOption | null;
    available: WarehouseOption[];
};

export default function WarehouseSelector() {
    const { warehouseContext } = usePage<{ warehouseContext?: WarehouseContext }>().props;

    if (!warehouseContext?.current || warehouseContext.available.length <= 1) {
        return null;
    }

    const changeWarehouse = (warehouseId: number) => {
        router.post(
            route('warehouses.set-current'),
            { warehouse_id: warehouseId },
            { preserveScroll: true },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" className="h-10 max-w-[220px] justify-between gap-2 text-sm">
                    <span className="inline-flex min-w-0 items-center gap-2">
                        <Warehouse className="h-4 w-4 shrink-0" />
                        <span className="truncate">{warehouseContext.current.name}</span>
                    </span>
                    <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-70" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-72">
                <DropdownMenuLabel>Bodega activa</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {warehouseContext.available.map((warehouse) => (
                    <DropdownMenuItem
                        key={warehouse.id}
                        onClick={() => changeWarehouse(warehouse.id)}
                        className="flex items-center justify-between"
                    >
                        <div className="grid">
                            <span className="font-medium">{warehouse.name}</span>
                            <span className="text-xs text-muted-foreground">{warehouse.city}</span>
                        </div>
                        <Check
                            className={cn(
                                'h-4 w-4',
                                warehouseContext.current?.id === warehouse.id ? 'opacity-100' : 'opacity-0',
                            )}
                        />
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

