<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Recruitment\Application\GetMyOffer;
use Src\Recruitment\Application\RespondToOffer;
use Src\Recruitment\Interface\Http\Requests\DeclineOfferRequest;
use Src\Recruitment\Interface\Http\Resources\OfferResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The pending offer of the job seeker's own application: read it, accept it (hired) or decline it (the application is withdrawn). */
final class MyOfferController extends ApiController
{
    /** The pending offer, or `{"data": null}` when the application has none. */
    public function show(Request $request, int $id, ActorResolver $actors, GetMyOffer $get): OfferResource|array
    {
        $view = $get->execute($actors->fromRequest($request), $id);

        return $view === null ? ['data' => null] : new OfferResource($view);
    }

    public function accept(Request $request, int $id, ActorResolver $actors, RespondToOffer $respond): OfferResource
    {
        return new OfferResource($respond->accept($actors->fromRequest($request), $id));
    }

    public function decline(DeclineOfferRequest $request, int $id, ActorResolver $actors, RespondToOffer $respond): OfferResource
    {
        return new OfferResource($respond->decline($actors->fromRequest($request), $id, $request->note()));
    }
}
