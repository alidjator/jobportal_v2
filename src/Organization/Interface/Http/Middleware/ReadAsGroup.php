<?php

namespace Src\Organization\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Src\Organization\Application\Reading\RecordGroupRecruitmentRead;
use Src\Organization\Application\Reading\RecruitmentReadAccess;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a group member's read act as a read-only member of the tenant named by the `tenant_id` query parameter (`group.recruitment.view`; the use cases of the recruitment
 * contexts then answer as for a recruiter). With the `audited` argument a read that succeeded is audited as `group.recruitment_read`: every read of applicants is.
 */
final readonly class ReadAsGroup
{
    public function __construct(private RecruitmentReadAccess $access, private RecordGroupRecruitmentRead $record) {}

    /**
     * @throws ValidationException when `tenant_id` is missing or not a number
     * @throws AccessDenied
     */
    public function handle(Request $request, Closure $next, string $mode = ''): Response
    {
        $tenantId = $request->query('tenant_id');
        $userId = $request->user()?->getAuthIdentifier();

        if (! is_string($tenantId) || ! ctype_digit($tenantId) || (int) $tenantId < 1) {
            throw ValidationException::withMessages(['tenant_id' => ['The tenant_id query parameter is required: the tenant whose recruitment you read.']]);
        }

        if (! is_int($userId)) {
            throw new AccessDenied('Only a group member can read a tenant of the group.', 'not-a-group-member');
        }

        $read = $this->access->of($userId, (int) $tenantId);
        $request->attributes->set('group_read_membership', $read->membership);

        $response = $next($request);

        if ($mode === 'audited' && $response->isSuccessful()) {
            $this->record->execute($userId, $read, $request->method().' /'.$request->path(), $request->ip(), $request->userAgent());
        }

        return $response;
    }
}
