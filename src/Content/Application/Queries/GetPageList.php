<?php

namespace Src\Content\Application\Queries;

/** URL halaman frontend (aplikasi web lama), basis URL dari config `content.website_url`. */
final class GetPageList
{
    /** @return array<string, string> */
    public function __invoke(): array
    {
        $base = rtrim((string) config('content.website_url'), '/');

        return [
            'blog' => $base.'/posts',
            'about' => $base.'/about',
            'contact' => $base.'/contact',
            'faq' => $base.'/faq',
            'privacy_policy' => $base.'/privacy-policy',
            'term_condition' => $base.'/terms-condition',
            'refund_policy' => $base.'/refund-policy',
        ];
    }
}
