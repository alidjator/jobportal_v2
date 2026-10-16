<?php

namespace Src\Organization\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Src\Organization\Application\Support\RecordSupportRead;
use Src\Shared\Interface\Http\ActorResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a platform admin's read act under the support access of the group named by the `group_id` query parameter (`Actor::supportGroupId`): the use cases then check
 * that the group's grant is active and refuse every change. A read that succeeded is audited as `group.support_read` and so shows in the group activity log.
 */
final readonly class ActAsSupport
{
    public function __construct(private ActorResolver $actors, private RecordSupportRead $record) {}

    /** @throws ValidationException when `group_id` is missing or not a number */
    public function handle(Request $request, Closure $next): Response
    {
        $groupId = $request->query('group_id');

        if (! is_string($groupId) || ! ctype_digit($groupId) || (int) $groupId < 1) {
            throw ValidationException::withMessages(['group_id' => ['The group_id query parameter is required: the group whose support access you read under.']]);
        }

        $request->attributes->set('support_group_id', (int) $groupId);

        $response = $next($request);

        if ($response->isSuccessful()) {
            $this->record->execute($this->actors->fromRequest($request), $request->method().' /'.$request->path(), $request->ip(), $request->userAgent());
        }

        return $response;
    }
}
