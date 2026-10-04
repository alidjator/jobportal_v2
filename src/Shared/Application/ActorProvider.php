<?php

namespace Src\Shared\Application;

/** Implemented by the authenticatable account models, so the HTTP layer can build an `Actor` without knowing their context. */
interface ActorProvider
{
    public function toActor(): Actor;
}
