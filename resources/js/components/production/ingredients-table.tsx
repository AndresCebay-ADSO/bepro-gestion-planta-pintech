import { FormattedNumber } from '@/components/formatted-number';
import { Input } from '@/components/ui/input';
import type {
    ProductionOrderFormData,
    ProductionOrderIngredientFormRow,
    ProductionOrderSetData,
} from '@/types/production-orders';

type IngredientsTableProps = {
    rows: ProductionOrderIngredientFormRow[];
    data: ProductionOrderFormData;
    setData: ProductionOrderSetData;
    isReadOnly: boolean;
    showCosts?: boolean;
};

export function IngredientsTable({
    rows,
    data,
    setData,
    isReadOnly,
    showCosts = true,
}: IngredientsTableProps) {
    return (
        <div className="overflow-hidden rounded-md border">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50">
                        <tr>
                            <th className="p-3 text-left">Materia Prima</th>
                            <th className="p-3 text-right">Planeado</th>
                            <th className="w-32 p-3 text-right">
                                Real Gastado
                            </th>
                            {showCosts && (
                                <>
                                    <th className="p-3 text-right">
                                        Costo Unit.
                                    </th>
                                    <th className="p-3 text-right">
                                        Costo Total
                                    </th>
                                </>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((ingredient, index) => (
                            <tr
                                key={ingredient.id}
                                className="border-b last:border-0"
                            >
                                <td className="p-3 font-medium">
                                    {ingredient.raw_material_name}
                                </td>
                                <td className="p-3 text-right text-muted-foreground">
                                    <FormattedNumber
                                        value={ingredient.planned_quantity}
                                        maxDecimals={2}
                                    />
                                </td>
                                <td className="p-3">
                                    <Input
                                        className="h-8 text-right"
                                        type="number"
                                        step="0.0001"
                                        value={ingredient.actual_quantity}
                                        onChange={(event) => {
                                            const newIngredients = [
                                                ...data.ingredients,
                                            ];
                                            newIngredients[index] = {
                                                ...newIngredients[index],
                                                actual_quantity:
                                                    event.target.value,
                                            };
                                            setData(
                                                'ingredients',
                                                newIngredients,
                                            );
                                        }}
                                        disabled={isReadOnly}
                                    />
                                </td>
                                {showCosts && (
                                    <>
                                        <td className="p-3 text-right text-muted-foreground">
                                            <FormattedNumber
                                                value={ingredient.unit_cost}
                                                currency
                                                maxDecimals={2}
                                            />
                                        </td>
                                        <td className="p-3 text-right font-medium">
                                            <FormattedNumber
                                                value={ingredient.total_cost}
                                                currency
                                                maxDecimals={2}
                                            />
                                        </td>
                                    </>
                                )}
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td
                                    className="p-3 text-muted-foreground"
                                    colSpan={showCosts ? 5 : 3}
                                >
                                    Esta orden no tiene insumos planificados.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
