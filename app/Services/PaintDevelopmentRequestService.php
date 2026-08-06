<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\PaintDevelopmentRequestStatus;
use App\Models\PaintDevelopmentRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaintDevelopmentRequestService
{
    public function __construct(
        private readonly AlertService $alertService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user, bool $draft = true): PaintDevelopmentRequest
    {
        return DB::transaction(function () use ($data, $user, $draft): PaintDevelopmentRequest {
            $request = new PaintDevelopmentRequest([
                'status' => PaintDevelopmentRequestStatus::Draft,
                ...$this->fillData($data),
                'schema_version' => 1,
                'created_by' => $user->id,
            ]);
            $request->request_number = $this->generateRequestNumber();
            $request->save();

            if (! $draft) {
                $this->submit($request);
            }

            return $request->load(['creator']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PaintDevelopmentRequest $paintRequest, array $data, bool $draft = true): PaintDevelopmentRequest
    {
        return DB::transaction(function () use ($paintRequest, $data, $draft): PaintDevelopmentRequest {
            $locked = PaintDevelopmentRequest::query()
                ->where('id', $paintRequest->id)
                ->lockForUpdate()
                ->first();

            if ($locked?->status !== PaintDevelopmentRequestStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => __('Solo se pueden editar solicitudes en borrador.'),
                ]);
            }

            $paintRequest->update($this->fillData($data));

            if (! $draft) {
                $this->submit($paintRequest);
            }

            return $paintRequest->fresh(['creator']);
        });
    }

    public function submit(PaintDevelopmentRequest $paintRequest): PaintDevelopmentRequest
    {
        return DB::transaction(function () use ($paintRequest): PaintDevelopmentRequest {
            $locked = PaintDevelopmentRequest::query()
                ->where('id', $paintRequest->id)
                ->lockForUpdate()
                ->first();

            if ($locked?->status !== PaintDevelopmentRequestStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => __('Solo se pueden enviar solicitudes en borrador.'),
                ]);
            }

            $paintRequest->update(['status' => PaintDevelopmentRequestStatus::Submitted]);

            $this->alertService->createPaintDevelopmentAlert(
                type: AlertType::PaintDevelopmentRequest,
                severity: AlertSeverity::Media,
                message: sprintf(
                    'Nueva solicitud de desarrollo %s — %s',
                    $paintRequest->request_number,
                    $paintRequest->client_name,
                ),
            );

            return $paintRequest->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateStatus(PaintDevelopmentRequest $paintRequest, array $data): PaintDevelopmentRequest
    {
        return DB::transaction(function () use ($paintRequest, $data): PaintDevelopmentRequest {
            $newStatus = PaintDevelopmentRequestStatus::tryFrom($data['status']);

            if ($newStatus === null) {
                throw ValidationException::withMessages([
                    'status' => __('Estado no válido.'),
                ]);
            }

            $allowed = $paintRequest->status->nextTransitions();

            if (! in_array($newStatus, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => __(
                        'No se puede transicionar de :from a :to.',
                        [
                            'from' => $paintRequest->status->label(),
                            'to' => $newStatus->label(),
                        ]
                    ),
                ]);
            }

            $updateData = ['status' => $newStatus];

            if ($newStatus === PaintDevelopmentRequestStatus::InReview
                || $newStatus === PaintDevelopmentRequestStatus::Approved
                || $newStatus === PaintDevelopmentRequestStatus::Rejected
            ) {
                $updateData['reviewed_by'] = auth()->id();
                $updateData['reviewed_at'] = now();
                $updateData['review_notes'] = $data['review_notes'] ?? null;
            }

            $paintRequest->update($updateData);

            return $paintRequest->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillData(array $data): array
    {
        return [
            'client_name' => $data['client_name'],
            'project_name' => $data['project_name'],
            'responsible' => $data['responsible'],
            'city' => $data['city'],
            'sample_due_date' => $data['sample_due_date'],
            'current_product' => $data['current_product'] ?? null,
            'context_payload' => $data['context_payload'] ?? [],
            'performance_payload' => $data['performance_payload'] ?? [],
            'application_payload' => $data['application_payload'] ?? [],
            'specifications_payload' => $data['specifications_payload'] ?? [],
        ];
    }

    private function generateRequestNumber(): int
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(801340)');
        }

        $lastNumber = PaintDevelopmentRequest::query()->max('request_number');

        return $lastNumber !== null
            ? (int) $lastNumber + 1
            : 1;
    }
}
