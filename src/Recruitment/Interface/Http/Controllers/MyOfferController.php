<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Recruitment\Application\GetMyOffer;
use Src\Recruitment\Application\GetMyOfferLetter;
use Src\Recruitment\Application\RespondToOffer;
use Src\Recruitment\Interface\Http\PdfDownload;
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

    /** The letter PDF of the offer the job seeker can answer or has accepted, as a download. */
    public function letter(Request $request, int $id, ActorResolver $actors, GetMyOfferLetter $get): Response
    {
        return PdfDownload::of($get->execute($actors->fromRequest($request), $id));
    }

    public function accept(Request $request, int $id, ActorResolver $actors, RespondToOffer $respond): OfferResource
    {
        return new OfferResource($respond->accept($actors->fromRequest($request), $id));
    }

    public function decline(DeclineOfferRequest $request, int $id, ActorResolver $actors, RespondToOffer $respond): OfferResource
    {
        return new OfferResource($respond->decline($actors->fromRequest($request), $id, $request->note(), $request->reason()));
    }
}
