<?php

namespace Src\Company\Interface\Http\Controllers;

use Src\Company\Application\Profile\GetPublicCompany;
use Src\Company\Interface\Http\Resources\PublicCompanyResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ApiController;

final class PublicCompanyController extends ApiController
{
    public function show(string $slug, RequestContext $context, GetPublicCompany $get): PublicCompanyResource
    {
        return new PublicCompanyResource($get->execute($slug, $context->language));
    }
}
