<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationFormVersion;
use App\Services\ClientAdminAudit;
use App\Support\RequestFormDate;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientRequestFormController extends Controller
{
    public function edit(Request $request): ViewContract
    {
        $organization = $request->attributes->get('tenant');
        $formVersion = $organization->requestFormVersions()->orderByDesc('version')->first();

        return view('client.request-form', compact('organization', 'formVersion'));
    }

    public function update(Request $request, ClientAdminAudit $audit): RedirectResponse
    {
        $organization = $request->attributes->get('tenant');
        $data = $request->validate([
            'form_fields' => ['sometimes', 'array'],
            'form_fields.*.key' => ['nullable', 'string', 'regex:/^field_[A-Za-z0-9_-]{8,60}$/', 'distinct'],
            'form_fields.*.label' => ['required', 'string', 'max:120'],
            'form_fields.*.type' => ['required', Rule::in(['text', 'textarea', 'date', 'select', 'file'])],
            'form_fields.*.required' => ['sometimes', 'boolean'],
            'form_fields.*.options' => ['nullable', 'string'],
            'form_fields.*.max_length' => ['nullable', 'integer', 'min:1', 'max:'.config('request_forms.text_max_length')],
            'form_fields.*.max_lines' => ['nullable', 'integer', 'min:1', 'max:'.config('request_forms.textarea_max_lines')],
            'form_fields.*.date_format' => ['nullable', Rule::in(array_keys(config('request_forms.date_formats')))],
            'form_fields.*.default' => ['nullable', 'string', 'max:'.config('request_forms.textarea_max_characters')],
            'form_fields.*.extensions' => ['nullable', 'array'],
            'form_fields.*.extensions.*' => ['string', 'distinct', Rule::in(config('request_forms.file_extensions'))],
        ]);

        $fields = [];

        foreach ($data['form_fields'] ?? [] as $index => $field) {
            $options = $field['type'] === 'select'
                ? array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $field['options'] ?? '') ?: []), fn (string $option): bool => $option !== '')))
                : [];
            $default = (string) ($field['default'] ?? '');

            if ($field['type'] === 'select' && $options === []) {
                throw ValidationException::withMessages([
                    "form_fields.{$index}.options" => 'Agrega al menos una opción para este campo de selección.',
                ]);
            }

            $definition = [
                'key' => ($field['key'] ?? null) ?: 'field_'.Str::lower(Str::random(24)),
                'label' => trim($field['label']),
                'type' => $field['type'],
                'required' => $request->boolean("form_fields.{$index}.required"),
            ];

            if ($field['type'] === 'text') {
                $maxLength = (int) ($field['max_length'] ?? 0);

                if ($maxLength < 1) {
                    throw ValidationException::withMessages(["form_fields.{$index}.max_length" => 'Indica el máximo de caracteres para este campo.']);
                }
                if (mb_strlen($default) > $maxLength) {
                    throw ValidationException::withMessages(["form_fields.{$index}.default" => 'El valor por defecto supera el máximo de caracteres configurado.']);
                }

                $definition['max_length'] = $maxLength;
                $definition['default'] = $default === '' ? null : $default;
            } elseif ($field['type'] === 'textarea') {
                $maxLines = (int) ($field['max_lines'] ?? 0);

                if ($maxLines < 1) {
                    throw ValidationException::withMessages(["form_fields.{$index}.max_lines" => 'Indica el máximo de líneas para este campo.']);
                }
                if (mb_strlen($default) > config('request_forms.textarea_max_characters') || $this->lineCount($default) > $maxLines) {
                    throw ValidationException::withMessages(["form_fields.{$index}.default" => 'El valor por defecto supera el límite de caracteres o líneas configurado.']);
                }

                $definition['max_lines'] = $maxLines;
                $definition['default'] = $default === '' ? null : $default;
            } elseif ($field['type'] === 'date') {
                $dateFormat = $field['date_format'] ?? '';

                if (! array_key_exists($dateFormat, config('request_forms.date_formats'))) {
                    throw ValidationException::withMessages(["form_fields.{$index}.date_format" => 'Selecciona un formato de fecha válido.']);
                }
                if ($default !== '' && ! RequestFormDate::matchesFormat($default, $dateFormat)) {
                    throw ValidationException::withMessages(["form_fields.{$index}.default" => 'La fecha por defecto no existe o no coincide con el formato seleccionado.']);
                }

                $definition['date_format'] = $dateFormat;
                $definition['date_format_label'] = config('request_forms.date_formats.'.$dateFormat);
                $definition['default'] = $default === '' ? null : $default;
            } elseif ($field['type'] === 'select') {
                if ($default !== '' && ! in_array($default, $options, true)) {
                    throw ValidationException::withMessages(["form_fields.{$index}.default" => 'El valor por defecto debe coincidir con una de las opciones configuradas.']);
                }

                $definition['options'] = $options;
                $definition['default'] = $default === '' ? null : $default;
            } else {
                $extensions = array_values(array_intersect(
                    config('request_forms.file_extensions'),
                    $field['extensions'] ?? [],
                ));

                if ($extensions === []) {
                    throw ValidationException::withMessages(["form_fields.{$index}.extensions" => 'Selecciona al menos una extensión permitida.']);
                }

                $definition['extensions'] = $extensions;
            }

            $fields[] = $definition;
        }

        $schema = ['fields' => $fields];

        $version = DB::transaction(function () use ($organization, $schema, $request): OrganizationFormVersion {
            $lockedOrganization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $latest = $lockedOrganization->requestFormVersions()->orderByDesc('version')->first();

            if ($latest?->schema === $schema) {
                return $latest;
            }

            return $lockedOrganization->requestFormVersions()->create([
                'version' => ($latest?->version ?? 0) + 1,
                'schema' => $schema,
                'published_by' => $request->user()->id,
                'published_at' => now(),
            ]);
        });

        $audit->record($organization, 'client.request_form.published', OrganizationFormVersion::class, $version->id, [
            'version' => $version->version,
            'field_count' => count($version->schema['fields'] ?? []),
        ]);

        return redirect()->route('client.request-form.edit')->with('status', 'Formulario de solicitudes guardado. Se aplicará a todos los sitios del cliente.');
    }

    private function lineCount(string $value): int
    {
        return $value === '' ? 0 : preg_match_all('/\R/u', $value) + 1;
    }
}
