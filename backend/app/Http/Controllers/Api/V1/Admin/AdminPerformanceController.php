<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\PerformanceRunQueryRequest;
use App\Http\Requests\Api\V1\Admin\PerformanceThresholdCheckRequest;
use App\Http\Requests\Api\V1\Admin\StartPerformanceRunRequest;
use App\Http\Resources\Api\V1\Admin\PerformanceBenchmarkRunResource;
use App\Http\Resources\Api\V1\Admin\PerformanceBenchmarkStepResource;
use App\Http\Resources\Api\V1\Admin\PerformanceGovernanceResource;
use App\Http\Resources\Api\V1\Admin\PerformanceProfileResource;
use App\Http\Resources\Api\V1\Admin\PerformanceThresholdResource;
use App\Models\PerformanceBenchmarkRun;
use App\Services\Performance\PerformanceBenchmarkService;
use App\Services\Performance\PerformanceGovernanceAuditService;
use App\Services\Performance\PerformanceThresholdGateService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminPerformanceController extends Controller
{
    public function profiles(): AnonymousResourceCollection
    {
        $profiles = collect(config('performance_governance.profiles'))->map(fn ($profile, $key) => ['profile' => $key] + $profile)->values();
        return PerformanceProfileResource::collection($profiles);
    }

    public function runs(PerformanceRunQueryRequest $request): AnonymousResourceCollection
    {
        $runs = PerformanceBenchmarkRun::query()
            ->when($request->validated('profile'), fn ($q, $v) => $q->where('profile', $v))
            ->when($request->validated('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(25);
        return PerformanceBenchmarkRunResource::collection($runs);
    }

    public function show(PerformanceBenchmarkRun $run): PerformanceBenchmarkRunResource
    {
        return new PerformanceBenchmarkRunResource($run);
    }

    public function steps(PerformanceBenchmarkRun $run): AnonymousResourceCollection
    {
        return PerformanceBenchmarkStepResource::collection($run->steps()->orderBy('id')->get());
    }

    public function store(StartPerformanceRunRequest $request, PerformanceBenchmarkService $service): PerformanceBenchmarkRunResource
    {
        return new PerformanceBenchmarkRunResource($service->run($request->validated('profile'), $request->user()?->id));
    }

    public function threshold(PerformanceThresholdCheckRequest $request, PerformanceBenchmarkRun $run, PerformanceThresholdGateService $service): PerformanceThresholdResource
    {
        return new PerformanceThresholdResource($service->evaluate($run));
    }

    public function governance(PerformanceGovernanceAuditService $service): PerformanceGovernanceResource
    {
        return new PerformanceGovernanceResource(['signals' => $service->evaluate()]);
    }
}
