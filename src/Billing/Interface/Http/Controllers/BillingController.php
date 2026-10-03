<?php

namespace Src\Billing\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Billing\Application\Queries\DownloadInvoice;
use Src\Billing\Application\Queries\GetCompanyPlan;
use Src\Billing\Application\Queries\GetCompanyPlanLimit;
use Src\Billing\Application\Queries\ListActivePlans;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

class BillingController extends ApiController
{
    public function pricing(ListActivePlans $query)
    {
        return ApiResponse::success($query());
    }

    public function plan(Request $request, GetCompanyPlan $query)
    {
        return ApiResponse::success($query((int) $request->user()->id));
    }

    public function planLimit(Request $request, GetCompanyPlanLimit $query)
    {
        return ApiResponse::success($query((int) $request->user()->id));
    }

    public function downloadInvoice(Request $request, int $id, DownloadInvoice $query)
    {
        $invoice = $query((int) $request->user()->id, $id);

        return response($invoice['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice['filename'].'"',
        ]);
    }
}
