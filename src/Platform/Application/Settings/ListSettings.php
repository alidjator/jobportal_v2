<?php

namespace Src\Platform\Application\Settings;

/** Every registered setting with its global value in force. The catalog is small and fixed by the code, so the list is not paginated. */
final readonly class ListSettings
{
    public function __construct(private SettingViews $views) {}

    /** @return list<SettingView> sorted by key */
    public function execute(): array
    {
        return $this->views->all();
    }
}
