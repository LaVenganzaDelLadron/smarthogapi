<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsReportRequest;
use App\Http\Resources\AnalyticsResponseResource;
use App\Models\Farms;
use App\Services\Analytics\DescriptiveAnalyticsService;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsController extends Controller
{
    public function __construct(
        public DescriptiveAnalyticsService $descriptiveAnalyticsService
    ) {}

    public function dashboard(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('dashboard', $this->descriptiveAnalyticsService->dashboard($this->scopedFilters($request, $request->validated())));
    }

    public function feedReport(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('feed-report', $this->descriptiveAnalyticsService->feedReport($this->scopedFilters($request, $request->validated())));
    }

    public function growthReport(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('growth-report', $this->descriptiveAnalyticsService->growthReport($this->scopedFilters($request, $request->validated())));
    }

    public function environmentReport(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('environment-report', $this->descriptiveAnalyticsService->environmentReport($this->scopedFilters($request, $request->validated())));
    }

    public function alertsReport(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('alerts-report', $this->descriptiveAnalyticsService->alertsReport($this->scopedFilters($request, $request->validated())));
    }

    public function penRanking(AnalyticsReportRequest $request): JsonResource
    {
        return $this->response('pen-ranking', $this->descriptiveAnalyticsService->penRanking($this->scopedFilters($request, $request->validated())));
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

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function scopedFilters(AnalyticsReportRequest $request, array $filters): array
    {
        if (isset($filters['farm_id'])) {
            abort_unless(Farms::query()
                ->where('id', $filters['farm_id'])
                ->where('user_id', $request->user()->id)
                ->exists(), 403);
        }

        return [
            ...$filters,
            'user_id' => $request->user()->id,
        ];
    }
}
