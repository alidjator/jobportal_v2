<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\GetOfferLetter;
use Src\Recruitment\Application\ListApplicationOffers;
use Src\Recruitment\Application\SendOffer;
use Src\Recruitment\Application\UploadOfferLetter;
use Src\Recruitment\Application\WithdrawOffer;
use Src\Recruitment\Interface\Http\PdfDownload;
use Src\Recruitment\Interface\Http\Requests\SendOfferRequest;
use Src\Recruitment\Interface\Http\Requests\UploadOfferLetterRequest;
use Src\Recruitment\Interface\Http\Resources\OfferLetterUploadResource;
use Src\Recruitment\Interface\Http\Resources\OfferResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The offers of an application, for the members who may see its job. */
final class OfferController extends ApiController
{
    public function index(Request $request, int $id, ActorResolver $actors, ListApplicationOffers $list): AnonymousResourceCollection
    {
        return OfferResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    /** Sends the offer: 201 for a new one, 200 when it replaced the pending one. */
    public function store(SendOfferRequest $request, int $id, ActorResolver $actors, SendOffer $send): JsonResponse
    {
        $result = $send->execute($actors->fromRequest($request), $id, $request->toInput());

        return (new OfferResource($result['view']))->response()->setStatusCode($result['replaced'] ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    /** The letter PDF of one offer, as a download. */
    public function letter(Request $request, int $id, int $offerId, ActorResolver $actors, GetOfferLetter $get): Response
    {
        return PdfDownload::of($get->execute($actors->fromRequest($request), $id, $offerId, $request->ip(), $request->userAgent()));
    }

    /** The company's own letter PDF, uploaded before the offer that uses it (`letter_upload_id`); it waits 24 hours. */
    public function uploadLetter(UploadOfferLetterRequest $request, int $id, ActorResolver $actors, UploadOfferLetter $upload): JsonResponse
    {
        return (new OfferLetterUploadResource($upload->execute($actors->fromRequest($request), $id, $request->contents())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function withdraw(Request $request, int $id, ActorResolver $actors, WithdrawOffer $withdraw): OfferResource
    {
        return new OfferResource($withdraw->execute($actors->fromRequest($request), $id));
    }
}
