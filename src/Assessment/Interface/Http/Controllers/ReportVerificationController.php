<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Assessment\Application\Reports\VerifyReport;
use Src\Assessment\Interface\Http\Requests\VerifyReportRequest;
use Src\Assessment\Interface\Http\Resources\ReportVerificationResource;
use Src\Shared\Interface\Http\ApiController;

/** The public check of a psychological report number: for anyone, no sign-in, limited per address. */
final class ReportVerificationController extends ApiController
{
    /** An unknown number is `{valid: false}` (200), so a reader can tell a wrong number from a failed call. */
    public function __invoke(VerifyReportRequest $request, VerifyReport $verify): ReportVerificationResource|JsonResponse
    {
        $check = $verify->execute($request->number());

        return $check === null ? response()->json(['data' => ['valid' => false]]) : new ReportVerificationResource($check);
    }
}
