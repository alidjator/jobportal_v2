<?php

namespace Src\Content\Infrastructure;

use Src\Content\Domain\AboutRepository;
use Src\Content\Domain\BlogRepository;
use Src\Content\Domain\CaptchaVerifier;
use Src\Content\Domain\CmsPageRepository;
use Src\Content\Domain\ContactNotifier;
use Src\Content\Domain\ContactRepository;
use Src\Content\Domain\FaqRepository;
use Src\Content\Domain\LanguageCodes;
use Src\Content\Infrastructure\Adapters\GoogleRecaptchaVerifier;
use Src\Content\Infrastructure\Adapters\MailContactNotifier;
use Src\Content\Infrastructure\Persistence\DatabaseLanguageCodes;
use Src\Content\Infrastructure\Persistence\EloquentAboutRepository;
use Src\Content\Infrastructure\Persistence\EloquentBlogRepository;
use Src\Content\Infrastructure\Persistence\EloquentCmsPageRepository;
use Src\Content\Infrastructure\Persistence\EloquentContactRepository;
use Src\Content\Infrastructure\Persistence\EloquentFaqRepository;
use Src\Shared\Infrastructure\ContextServiceProvider;

class ContentServiceProvider extends ContextServiceProvider
{
    public array $bindings = [
        BlogRepository::class => EloquentBlogRepository::class,
        CmsPageRepository::class => EloquentCmsPageRepository::class,
        FaqRepository::class => EloquentFaqRepository::class,
        AboutRepository::class => EloquentAboutRepository::class,
        ContactRepository::class => EloquentContactRepository::class,
        LanguageCodes::class => DatabaseLanguageCodes::class,
        ContactNotifier::class => MailContactNotifier::class,
        CaptchaVerifier::class => GoogleRecaptchaVerifier::class,
    ];
}
