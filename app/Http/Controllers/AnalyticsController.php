<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsReportRequest;
use App\Http\Resources\AnalyticsResponseResource;
use App\Services\Analytics\DescriptiveAnalyticsService;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsController extends Controller
{
    public function __construct(
        public DescriptiveAnalyticsService $descriptiveAnalyticsService
    ) {}

    public function dashboard(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('dashboard', $this->descriptiveAnalyticsService->dashboard($request->validated()));
    }

    public function feedReport(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('feed-report', $this->descriptiveAnalyticsService->feedReport($request->validated()));
    }

    public function growthReport(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('growth-report', $this->descriptiveAnalyticsService->growthReport($request->validated()));
    }

    public function environmentReport(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('environment-report', $this->descriptiveAnalyticsService->environmentReport($request->validated()));
    }

    public function alertsReport(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('alerts-report', $this->descriptiveAnalyticsService->alertsReport($request->validated()));
    }

    public function penRanking(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('pen-ranking', $this->descriptiveAnalyticsService->penRanking($request->validated()));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function response(string $reportType, array $data): JsonResource
    {
        return AnalyticsResponseResource::make([
            'data' => $data,
            'meta' => [
                'report' => $reportType,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
