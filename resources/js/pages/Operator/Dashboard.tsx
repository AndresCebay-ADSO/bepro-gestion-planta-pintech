import { Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as productionOrdersIndex } from '@/routes/production-orders';

export default function OperatorDashboard() {
    return (
        <>
            <Head title="Panel del Operador" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        Panel del Operador
                    </h1>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Órdenes de Producción
                            </CardTitle>
                            <ClipboardList className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <Link href={productionOrdersIndex().url}>
                                <Button variant="outline" className="w-full">
                                    Ver órdenes
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
