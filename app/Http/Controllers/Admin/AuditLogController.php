<?php

namespace App\Http\Controllers\Admin;

use App\Filters\AuditLogFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAuditLogRequest;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexAuditLogRequest $request): Response
    {
        $baseQuery = Activity::with('causer')->latest();

        $logs = (new AuditLogFilter($request))
            ->apply($baseQuery)
            ->paginate(20)
            ->onEachSide(1)
            ->withQueryString();

        // Transformar a camelCase para cumplir con estándares de TS del proyecto
        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'logName' => $log->log_name,
                'description' => $log->description,
                'event' => $log->event,
                'subjectType' => $log->subject_type,
                'subjectId' => $log->subject_id,
                'causerType' => $log->causer_type,
                'causerId' => $log->causer_id,
                'causer' => $log->causer ? [
                    'id' => $log->causer->id,
                    'name' => $log->causer->name,
                    'email' => $log->causer->email,
                ] : null,
                'properties' => $log->properties,
                'createdAt' => $log->created_at->toIso8601String(),
            ];
        });

        $logNames = Activity::select('log_name')->distinct()->whereNotNull('log_name')->pluck('log_name');
        $events = Activity::select('event')->distinct()->whereNotNull('event')->pluck('event');

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $request->validated(),
            'options' => [
                'logNames' => $logNames,
                'events' => $events,
            ],
        ]);
    }
}
