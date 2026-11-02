<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Catalog\GetCatalogTest;
use Src\Assessment\Application\Catalog\ListBatteryCatalog;
use Src\Assessment\Application\Catalog\ListLibraryTests;
use Src\Assessment\Application\Catalog\ListSendableTests;
use Src\Assessment\Application\Example\DownloadExampleReport;
use Src\Assessment\Interface\Http\Requests\ListCatalogRequest;
use Src\Assessment\Interface\Http\Requests\ListSendableTestsRequest;
use Src\Assessment\Interface\Http\Resources\CatalogEntryResource;
use Src\Assessment\Interface\Http\Resources\CatalogTestResource;
use Src\Assessment\Interface\Http\Resources\SendableTestResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** The tests a company member may send: the system library, and the batteries an admin granted when the company may use them. */
final class CatalogController extends ApiController
{
    public function library(ListCatalogRequest $request, ActorResolver $actors, ListLibraryTests $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->search(), $request->pageRequest());

        return CatalogTestResource::collection(PaginatedResources::paginator($page, $request));
    }

    /** Every test the member may send, in one list (`assessments.send`): own published tests, the library and the granted batteries. */
    public function sendable(ListSendableTestsRequest $request, ActorResolver $actors, ListSendableTests $list): AnonymousResourceCollection
    {
        return SendableTestResource::collection($list->execute($actors->fromRequest($request), $request->search()));
    }

    public function batteries(ListCatalogRequest $request, ActorResolver $actors, ListBatteryCatalog $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->search(), $request->pageRequest());

        return CatalogTestResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetCatalogTest $get): CatalogEntryResource
    {
        return new CatalogEntryResource($get->execute($actors->fromRequest($request), $id));
    }

    /** The example report a provider attached to the battery (a PDF about a fictitious candidate); the entry says whether there is one (`has_example_report`). */
    public function exampleReport(Request $request, int $id, ActorResolver $actors, DownloadExampleReport $download): Response
    {
        $file = $download->execute($actors->fromRequest($request), $id);

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->name, (string) preg_replace('/[^\x20-\x7E]/', '_', str_replace(['%', '/', '\\', '"'], '_', $file->name))),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
