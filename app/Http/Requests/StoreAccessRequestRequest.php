<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccessRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->membershipFor(
            $this->attributes->get('tenant')?->id
        )?->can_request;
    }

    public function rules(): array
    {
        $tenantId = $this->attributes->get('tenant')?->id;

        return [
            'site_id' => ['required', 'ulid', Rule::exists('sites', 'id')->where('organization_id', $tenantId)->where('is_active', true)],
            'purpose' => ['required', 'string', 'max:1000'],
            'requested_from' => ['required', 'date', 'after_or_equal:now'],
            'requested_until' => ['required', 'date', 'after:requested_from'],
            'form_data' => ['sometimes', 'array'],
            'people' => ['required', 'array', 'min:1', 'max:50'],
            'people.*.full_name' => ['required', 'string', 'max:180'],
            'people.*.document_type' => ['nullable', 'string', 'max:40'],
            'people.*.document_number' => ['nullable', 'string', 'max:80'],
            'people.*.company' => ['nullable', 'string', 'max:180'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
