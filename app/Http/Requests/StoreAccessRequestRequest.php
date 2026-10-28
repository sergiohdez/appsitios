<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\OrganizationFormVersion;
use App\Support\RequestFormDate;

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
        $versionQuery = OrganizationFormVersion::query()->where('organization_id', $tenantId);

        if ($this->filled('organization_form_version_id')) {
            $versionQuery->whereKey($this->input('organization_form_version_id'));
        } else {
            $versionQuery->orderByDesc('version');
        }

        $version = $versionQuery->first();
        $this->formSchema = $version?->schema ?? ['fields' => []];
        $this->formSchema['fields'] = array_values(array_filter(
            $this->formSchema['fields'] ?? [],
            fn ($field): bool => is_array($field)
                && is_string($field['key'] ?? null)
                && preg_match('/^field_[A-Za-z0-9_-]{8,60}$/', $field['key']) === 1
                && in_array($field['type'] ?? null, ['text', 'textarea', 'date', 'select', 'file'], true),
        ));

        $rules = [
            'site_id' => ['required', 'ulid', Rule::exists('sites', 'id')->where('organization_id', $tenantId)->where('is_active', true)],
            'organization_form_version_id' => [$version ? 'required' : 'nullable', 'ulid', Rule::exists('organization_form_versions', 'id')->where('organization_id', $tenantId)],
            'purpose' => ['required', 'string', 'max:1000'],
            'requested_from' => ['required', 'date', 'after:now'],
            'requested_until' => ['required', 'date', 'after:now', 'after:requested_from'],
            'form_data' => ['sometimes', 'array'],
            'people' => ['required', 'array', 'min:1'],
            'people.*.full_name' => ['required', 'string', 'max:180'],
            'people.*.document_type' => ['nullable', 'string', Rule::in(['CC', 'CE', 'PP', 'NIT'])],
            'people.*.document_number' => ['nullable', 'string', 'max:80'],
            'people.*.company' => ['nullable', 'string', 'max:180'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];

        foreach ($this->formSchema['fields'] as $field) {
            $fieldRules = [($field['required'] ?? false) ? 'required' : 'nullable'];

            switch ($field['type']) {
                case 'text':
                    $maxLength = max(1, min(config('request_forms.text_max_length'), (int) ($field['max_length'] ?? config('request_forms.text_max_length'))));
                    $fieldRules = [...$fieldRules, 'string', 'max:'.$maxLength];
                    break;
                case 'textarea':
                    $maxLines = max(1, min(config('request_forms.textarea_max_lines'), (int) ($field['max_lines'] ?? config('request_forms.textarea_max_lines'))));
                    $fieldRules = [...$fieldRules, 'string', 'max:'.config('request_forms.textarea_max_characters'), function ($attribute, $value, $fail) use ($maxLines): void {
                        $lineCount = $value === '' ? 0 : preg_match_all('/\R/u', (string) $value) + 1;

                        if ($lineCount > $maxLines) {
                            $fail("El campo admite como máximo {$maxLines} líneas.");
                        }
                    }];
                    break;
                case 'date':
                    $dateFormat = array_key_exists($field['date_format'] ?? '', config('request_forms.date_formats'))
                        ? $field['date_format']
                        : 'Y-m-d';
                    $fieldRules = [...$fieldRules, 'date_format:'.$dateFormat, function ($attribute, $value, $fail) use ($dateFormat): void {
                        if (! is_string($value) || ! RequestFormDate::matchesFormat($value, $dateFormat)) {
                            $fail('La fecha no existe o no coincide con el formato indicado.');
                        }
                    }];
                    break;
                case 'select':
                    $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                    $fieldRules = [...$fieldRules, 'string', Rule::in($options)];
                    break;
                case 'file':
                    $extensions = array_values(array_intersect(
                        config('request_forms.file_extensions'),
                        is_array($field['extensions'] ?? null) ? $field['extensions'] : config('request_forms.file_extensions'),
                    ));
                    if ($extensions === []) {
                        $extensions = config('request_forms.file_extensions');
                    }
                    $mimeExtensions = implode(',', config('request_forms.file_extensions'));
                    $fieldRules = [...$fieldRules, 'file', 'mimes:'.$mimeExtensions, 'extensions:'.implode(',', $extensions), 'max:'.config('request_forms.file_max_size_kb')];
                    break;
            }

            $rules['form_data.'.$field['key']] = $fieldRules;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'requested_from.required' => 'Indica la fecha y hora de inicio.',
            'requested_from.date' => 'La fecha y hora de inicio no es válida.',
            'requested_from.after' => 'La fecha y hora de inicio debe ser posterior al momento actual.',
            'requested_until.required' => 'Indica la fecha y hora de finalización.',
            'requested_until.date' => 'La fecha y hora de finalización no es válida.',
            'requested_until.after' => 'La fecha y hora de finalización debe ser posterior a la hora de inicio y al momento actual.',
            'people.*.document_type.in' => 'Selecciona un tipo de documento válido: CC, CE, PP o NIT.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('organization_form_version_id')) {
                $currentVersionId = OrganizationFormVersion::query()
                    ->where('organization_id', $this->attributes->get('tenant')?->id)
                    ->orderByDesc('version')
                    ->value('id');

                if ($currentVersionId && $currentVersionId !== $this->input('organization_form_version_id')) {
                    $validator->errors()->add('organization_form_version_id', 'El formulario del cliente se actualizó. Recarga la página antes de enviar la solicitud.');
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
