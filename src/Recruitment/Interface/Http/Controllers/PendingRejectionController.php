<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Recruitment\Application\UndoRejection;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Takes back a rejection that still waits its undo window, for one application or for every application of one bulk action. */
final class PendingRejectionController extends ApiController
{
    /** 204; 409 `rejection-committed` once the window has passed, 404 when the application waits for no rejection. */
    public function destroy(Request $request, int $id, ActorResolver $actors, UndoRejection $undo): Response
    {
        $undo->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    /** 204; 404 when the batch has nothing left to undo (the window passed, or the batch is not of the company). */
    public function destroyBatch(Request $request, string $batchId, ActorResolver $actors, UndoRejection $undo): Response
    {
        $undo->executeBatch($actors->fromRequest($request), $batchId);

        return response()->noContent();
    }
}
