<?php

namespace Src\Messaging\Interface\Http\Requests;

use Illuminate\Http\Request;
use Src\Messaging\Application\AttachmentUpload;
use Src\Messaging\Application\MessageInput;

/**
 * The `body` and `files[]` fields of every request that sends a message. Only the shape is checked here (text, and that every file arrived whole): the length,
 * the number and size of the files and their content are checked by the use case, which ignores the type the client sent. File names are passed on for display only.
 */
final class MessageFields
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'body' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file'],
        ];
    }

    public static function input(Request $request): MessageInput
    {
        $files = $request->file('files');
        $uploads = [];

        foreach (is_array($files) ? $files : [] as $file) {
            $uploads[] = new AttachmentUpload($file->getClientOriginalName(), (string) $file->getContent());
        }

        return new MessageInput($request->filled('body') ? $request->string('body')->toString() : null, $uploads);
    }
}
