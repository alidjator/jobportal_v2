<?php

namespace Src\Platform\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Platform\Application\EmailSuppression\ReceiveEmailEvents;
use Src\Shared\Interface\Http\ApiController;

/** The delivery webhook of an email provider (hard bounces and complaints). No sign-in: the provider's signature is the credential. */
final class EmailWebhookController extends ApiController
{
    public function __invoke(Request $request, string $provider, ReceiveEmailEvents $receive): Response
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower((string) $name)] = (string) ($values[0] ?? '');
        }

        $receive->execute($provider, $request->getContent(), $headers);

        return response()->noContent();
    }
}
