<?php

namespace Src\Platform\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Platform\Application\Scheduler\CheckSchedulerHealth;
use Src\Shared\Interface\Http\ApiController;

/** 200 while the scheduler's heartbeat is recent, 503 otherwise; no sign-in and no data, for an outside uptime monitor. */
final class SchedulerHealthController extends ApiController
{
    public function __invoke(CheckSchedulerHealth $health): JsonResponse
    {
        $alive = $health->isAlive();

        return response()->json(['status' => $alive ? 'ok' : 'down'], $alive ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
