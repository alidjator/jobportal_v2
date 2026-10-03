<?php

namespace Src\Content\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Src\Content\Domain\CaptchaVerifier;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $captcha = app(CaptchaVerifier::class)->isActive();

        return [
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|string|max:255',
            'subject' => 'required|string|min:5|max:255',
            'message' => 'required|string|min:10',
            'g-recaptcha-response' => $captcha ? 'required' : 'nullable',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'g-recaptcha-response.required' => 'Please verify that you are not a robot.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $captcha = app(CaptchaVerifier::class);

            if ($captcha->isActive() && ! $validator->errors()->has('g-recaptcha-response')
                && ! $captcha->verify($this->input('g-recaptcha-response'), $this->ip())) {
                $validator->errors()->add('g-recaptcha-response', 'Captcha error! try again later or contact site admin.');
            }
        }];
    }
}
