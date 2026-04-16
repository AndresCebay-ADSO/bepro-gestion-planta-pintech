<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-audit-logs');

        $request->validate([
            'search' => 'nullable|string|max:100',
            'log_name' => 'nullable|string|max:50',
            'event' => 'nullable|string|max:50',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $query = Activity::with('causer')->latest();

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(description) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(event) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(log_name) LIKE ?', ["%{$search}%"])
                    ->orWhereHasMorph('causer', [User::class], function ($uq) use ($search) {
                        $uq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if ($request->filled('log_name') && $request->input('log_name') !== 'all') {
            $query->where('log_name', $request->input('log_name'));
        }

        if ($request->filled('event') && $request->input('event') !== 'all') {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate(20)->onEachSide(1)->withQueryString();

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
            'filters' => $request->only(['search', 'log_name', 'event', 'date_from', 'date_to']),
            'options' => [
                'logNames' => $logNames,
                'events' => $events,
            ],
        ]);
    }
}
