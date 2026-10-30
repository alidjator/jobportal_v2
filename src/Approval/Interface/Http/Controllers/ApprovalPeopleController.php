<?php

namespace Src\Approval\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Approval\Application\Instance\ApprovalPeople;
use Src\Approval\Interface\Http\Resources\ApprovalPersonResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\MemberChoice;

/** The colleagues of the own company a member can name in an approval: the delegate of a delegation and the names in the text of a flow. */
final class ApprovalPeopleController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ApprovalPeople $people): AnonymousResourceCollection
    {
        return ApprovalPersonResource::collection(MemberChoice::listOf($people->execute($actors->fromRequest($request))));
    }
}
