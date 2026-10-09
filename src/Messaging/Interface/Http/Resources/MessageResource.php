<?php

namespace Src\Messaging\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Messaging\Application\MessagePayload;
use Src\Messaging\Application\MessageView;

/** @property MessageView $resource */
final class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return MessagePayload::of($this->resource);
    }
}
