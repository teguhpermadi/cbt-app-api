<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ActivityLogResource;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $activities = QueryBuilder::for(Activity::class)
            ->allowedFilters([
                'log_name',
                'description',
                AllowedFilter::exact('subject_type'),
                AllowedFilter::exact('subject_id'),
                AllowedFilter::exact('causer_type'),
                AllowedFilter::exact('causer_id'),
            ])
            ->allowedSorts(['created_at', 'id'])
            ->defaultSort('-created_at')
            ->with(['causer', 'subject'])
            ->paginate($request->get('per_page', 15));

        return ActivityLogResource::collection($activities);
    }

    public function mine(Request $request)
    {
        $activities = QueryBuilder::for(Activity::class)
            ->where('causer_id', $request->user()->id)
            ->allowedFilters([
                'log_name',
                'description',
                AllowedFilter::exact('subject_type'),
                AllowedFilter::exact('subject_id'),
            ])
            ->allowedSorts(['created_at', 'id'])
            ->defaultSort('-created_at')
            ->with(['subject'])
            ->paginate($request->get('per_page', 15));

        return ActivityLogResource::collection($activities);
    }
}
