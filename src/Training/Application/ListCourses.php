<?php

namespace Src\Training\Application;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Training\Domain\Course;

/** The course catalog, searched by name and cut into pages in memory (the platform has few courses). */
final readonly class ListCourses
{
    public function __construct(private TrainingCatalog $catalog) {}

    /**
     * @return Page<Course>
     *
     * @throws ExternalServiceUnavailable
     */
    public function execute(?string $search, PageRequest $request): Page
    {
        $courses = $this->catalog->courses();

        if ($search !== null && $search !== '') {
            $courses = array_values(array_filter($courses, fn (Course $course): bool => mb_stripos($course->name, $search) !== false));
        }

        return new Page(array_slice($courses, $request->offset(), $request->perPage), count($courses), $request);
    }
}
