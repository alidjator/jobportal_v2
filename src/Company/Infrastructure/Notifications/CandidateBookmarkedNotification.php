<?php

namespace Src\Company\Infrastructure\Notifications;

use Illuminate\Notifications\Notification;

class CandidateBookmarkedNotification extends Notification
{
    /**
     * @param  array{id:int,name:string,username:string}  $company
     * @param  array{id:int,name:string,username:string}  $candidate
     */
    public function __construct(private array $company, private array $candidate) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string,string> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => ucfirst($this->company['name']).' has bookmarked you',
            'url' => url('/employer/'.$this->company['username']),
            'title2' => 'You have bookmarked '.$this->candidate['name'],
            'url2' => url('/candidates/'.$this->candidate['username']),
        ];
    }
}
