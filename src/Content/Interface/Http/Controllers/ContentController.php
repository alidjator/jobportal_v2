<?php

namespace Src\Content\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Content\Application\Commands\CommentOnPost;
use Src\Content\Application\Commands\SendContactMessage;
use Src\Content\Application\Queries\GetAbout;
use Src\Content\Application\Queries\GetCmsPage;
use Src\Content\Application\Queries\GetPageList;
use Src\Content\Application\Queries\GetPost;
use Src\Content\Application\Queries\ListFaqs;
use Src\Content\Application\Queries\ListPosts;
use Src\Content\Interface\Http\Requests\CommentRequest;
use Src\Content\Interface\Http\Requests\ContactRequest;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\ApiResponse;

class ContentController extends ApiController
{
    public function about(GetAbout $query)
    {
        return ApiResponse::success($query());
    }

    public function posts(Request $request, ListPosts $query)
    {
        return ApiResponse::success($query(
            $request->query('search') !== null ? (string) $request->query('search') : null,
            $request->query('category') !== null ? (string) $request->query('category') : null,
        ));
    }

    public function postDetails(string $slug, GetPost $query)
    {
        return ApiResponse::success($query($slug));
    }

    public function comment(string $slug, CommentRequest $request, CommentOnPost $command)
    {
        $parent = $request->input('parent_id');

        return ApiResponse::success($command(
            (int) $request->user()->id,
            $slug,
            (string) $request->input('body'),
            $parent !== null ? (int) $parent : null,
        ));
    }

    public function faq(ListFaqs $query)
    {
        return ApiResponse::success($query());
    }

    public function termsCondition(GetCmsPage $query)
    {
        return ApiResponse::success($query(GetCmsPage::TERMS));
    }

    public function privacyPolicy(GetCmsPage $query)
    {
        return ApiResponse::success($query(GetCmsPage::PRIVACY));
    }

    public function refundPolicy(GetCmsPage $query)
    {
        return ApiResponse::success($query(GetCmsPage::REFUND));
    }

    public function contact(ContactRequest $request, SendContactMessage $command)
    {
        $command($request->safe()->only(['name', 'email', 'subject', 'message']));

        return ApiResponse::message('Your contact mail has been sent');
    }

    public function pageList(GetPageList $query)
    {
        return ApiResponse::success($query());
    }
}
