<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Company\Application\Images\CompanyImageContent;
use Src\Company\Application\Images\CompanyImageKind;
use Src\Company\Application\Images\GetCompanyImage;
use Src\Company\Application\Images\RemoveCompanyImage;
use Src\Company\Application\Images\UploadCompanyImage;
use Src\Company\Interface\Http\Requests\UploadCompanyImageRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/**
 * The logo and the banner of a company (the route says which one with its `kind` default). Files have no public URL of their own: they are
 * sent by these actions only, as inline images that browsers may not reinterpret.
 */
final class CompanyImageController extends ApiController
{
    public function store(UploadCompanyImageRequest $request, string $kind, ActorResolver $actors, UploadCompanyImage $upload): Response
    {
        $upload->execute($actors->fromRequest($request), CompanyImageKind::from($kind), $request->contents());

        return response()->noContent();
    }

    public function destroy(Request $request, string $kind, ActorResolver $actors, RemoveCompanyImage $remove): Response
    {
        $remove->execute($actors->fromRequest($request), CompanyImageKind::from($kind));

        return response()->noContent();
    }

    public function showOwn(Request $request, string $kind, ActorResolver $actors, GetCompanyImage $get): Response
    {
        return $this->image($get->ofOwnCompany($actors->fromRequest($request), CompanyImageKind::from($kind)));
    }

    public function showPublic(string $slug, string $kind, GetCompanyImage $get): Response
    {
        return $this->image($get->ofPublicCompany($slug, CompanyImageKind::from($kind)));
    }

    private function image(CompanyImageContent $image): Response
    {
        return new Response($image->contents, Response::HTTP_OK, [
            'Content-Type' => $image->mimeType,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
