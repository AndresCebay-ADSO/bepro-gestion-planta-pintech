import { Badge } from '@/components/ui/badge';
import { finishedMovementReasonLabels } from '@/types/finished-inventory';
import type {
    FinishedMovementReason,
    FinishedMovementType,
} from '@/types/finished-inventory';

export function FinishedMovementTypeBadge({
    type,
}: {
    type: FinishedMovementType;
}) {
    return (
        <Badge
            variant="outline"
            className={
                type === 'entry'
                    ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                    : 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
            }
        >
            {type === 'entry' ? 'Entrada' : 'Salida'}
        </Badge>
    );
}

export function FinishedMovementReasonBadge({
    reason,
}: {
    reason: FinishedMovementReason;
}) {
    return (
        <Badge variant="secondary">
            {finishedMovementReasonLabels[reason] ?? reason}
        </Badge>
    );
}
