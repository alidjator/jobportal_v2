<?php

namespace Src\Identity\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Identity\Application\Account\GetMyAvatar;
use Src\Identity\Application\Account\RemoveMyAvatar;
use Src\Identity\Application\Account\UploadMyAvatar;
use Src\Identity\Interface\Http\Requests\UploadAvatarRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The profile picture of the signed-in job seeker or company member. Nobody else's picture is ever served here. */
final class AvatarController extends ApiController
{
    public function store(UploadAvatarRequest $request, ActorResolver $actors, UploadMyAvatar $upload): Response
    {
        $upload->execute($actors->fromRequest($request), $request->contents());

        return response()->noContent();
    }

    public function show(Request $request, ActorResolver $actors, GetMyAvatar $get): Response
    {
        $image = $get->execute($actors->fromRequest($request));

        return new Response($image->contents, Response::HTTP_OK, [
            'Content-Type' => $image->mimeType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    public function destroy(Request $request, ActorResolver $actors, RemoveMyAvatar $remove): Response
    {
        $remove->execute($actors->fromRequest($request));

        return response()->noContent();
    }
}
