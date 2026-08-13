<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductMediaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['image', 'video'])],
            // Images: compressed client-side already, still capped generously.
            // Video: CLAUDE.md caps at 60s/~20MB, compressed client-side — 20MB in KB.
            'file' => [
                'required',
                'file',
                Rule::when($this->input('type') === 'video', ['mimetypes:video/mp4,video/quicktime', 'max:20480']),
                Rule::when($this->input('type') === 'image', ['image', 'max:8192']),
            ],
            // Video only: a poster frame extracted client-side (CLAUDE.md
            // shop grid "play badges" need a real thumbnail, and generating
            // one server-side would need an ffmpeg binary this app doesn't
            // require — see DECISIONS.md).
            'thumbnail' => ['required_if:type,video', 'nullable', 'image', 'max:4096'],
            'duration' => ['required_if:type,video', 'nullable', 'integer', 'min:1', 'max:60'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
