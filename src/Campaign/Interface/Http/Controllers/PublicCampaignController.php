<?php

namespace Src\Campaign\Interface\Http\Controllers;

use Src\Campaign\Application\GetPublicCampaign;
use Src\Campaign\Interface\Http\Resources\PublicCampaignResource;
use Src\Shared\Interface\Http\ApiController;

/** The page of a running campaign, for the registration screen. */
final class PublicCampaignController extends ApiController
{
    public function show(string $code, GetPublicCampaign $get): PublicCampaignResource
    {
        return new PublicCampaignResource($get->execute($code));
    }
}
