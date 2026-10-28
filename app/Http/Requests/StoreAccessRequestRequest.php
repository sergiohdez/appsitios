<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Site;
use App\Models\SiteFormVersion;

class StoreAccessRequestRequest extends FormRequest
{
    private array $formSchema = ['fields' => []];

    public function authorize(): bool
    {
        return (bool) $this->user()?->membershipFor(
            $this->attributes->get('tenant')?->id
        )?->canRequest();
    }

    public function rules(): array
    {
        $tenantId = $this->attributes->get('tenant')?->id;
        $siteId = $this->input('site_id');
        $site = Site::query()->where('organization_id', $tenantId)->whereKey($siteId)->first();
        $versionQuery = SiteFormVersion::query()->where('organization_id', $tenantId)->where('site_id', $siteId);

        if ($this->filled('form_version_id')) {
            $versionQuery->whereKey($this->input('form_version_id'));
        } else {
            $versionQuery->orderByDesc('version');
        }

        $version = $versionQuery->first();
        $legacySchema = $site?->form_schema;
        $this->formSchema = $version?->schema ?? (is_array($legacySchema) ? $legacySchema : ['fields' => []]);
        $this->formSchema['fields'] = array_values(array_filter(
            $this->formSchema['fields'] ?? [],
            fn ($field): bool => is_array($field)
                && is_string($field['key'] ?? null)
                && preg_match('/^field_[A-Za-z0-9_-]{8,60}$/', $field['key']) === 1
                && in_array($field['type'] ?? null, ['text', 'textarea', 'date', 'select', 'file'], true),
        ));

        $rules = [
            'site_id' => ['required', 'ulid', Rule::exists('sites', 'id')->where('organization_id', $tenantId)->where('is_active', true)],
            'form_version_id' => ['nullable', 'ulid', Rule::exists('site_form_versions', 'id')->where('organization_id', $tenantId)->where('site_id', $siteId)],
            'purpose' => ['required', 'string', 'max:1000'],
            'requested_from' => ['required', 'date', 'after_or_equal:now'],
            'requested_until' => ['required', 'date', 'after:requested_from'],
            'form_data' => ['sometimes', 'array'],
            'people' => ['required', 'array', 'min:1'],
            'people.*.full_name' => ['required', 'string', 'max:180'],
            'people.*.document_type' => ['nullable', 'string', 'max:40'],
            'people.*.document_number' => ['nullable', 'string', 'max:80'],
            'people.*.company' => ['nullable', 'string', 'max:180'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];

        foreach ($this->formSchema['fields'] as $field) {
            $fieldRules = [($field['required'] ?? false) ? 'required' : 'nullable'];

            switch ($field['type']) {
                case 'text':
                    $fieldRules = [...$fieldRules, 'string', 'max:2000'];
                    break;
                case 'textarea':
                    $fieldRules = [...$fieldRules, 'string', 'max:10000'];
                    break;
                case 'date':
                    $fieldRules = [...$fieldRules, 'date_format:Y-m-d'];
                    break;
                case 'select':
                    $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                    $fieldRules = [...$fieldRules, 'string', Rule::in($options)];
                    break;
                case 'file':
                    $fieldRules = [...$fieldRules, 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
                    break;
            }

            $rules['form_data.'.$field['key']] = $fieldRules;
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('form_version_id')) {
                $currentVersionId = SiteFormVersion::query()
                    ->where('organization_id', $this->attributes->get('tenant')?->id)
                    ->where('site_id', $this->input('site_id'))
                    ->orderByDesc('version')
                    ->value('id');

                if ($currentVersionId && $currentVersionId !== $this->input('form_version_id')) {
                    $validator->errors()->add('form_version_id', 'El formulario de este sitio se actualizó. Recarga la página antes de enviar la solicitud.');
                }
            }

            $submitted = array_merge(
                array_keys(is_array($this->input('form_data')) ? $this->input('form_data') : []),
                array_keys(is_array($this->file('form_data')) ? $this->file('form_data') : []),
            );
            $allowed = array_column($this->formSchema['fields'], 'key');

            foreach (array_diff(array_unique($submitted), $allowed) as $unknownKey) {
                $validator->errors()->add('form_data', 'La solicitud contiene un campo que no pertenece al formulario vigente. Recarga la página e inténtalo de nuevo.');
                break;
            }
        });
    }
}
