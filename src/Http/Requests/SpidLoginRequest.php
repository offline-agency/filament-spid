<?php

namespace OfflineAgency\FilamentSpid\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OfflineAgency\FilamentSpid\Constants\SpidLevel;

class SpidLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $availableProviders = array_keys(
            array_filter(
                config('spid-idps', []),
                fn ($idp) => ($idp['isActive'] ?? false)
            )
        );

        return [
            'provider' => [
                'required',
                'string',
                Rule::in($availableProviders),
            ],
            'level' => [
                'nullable',
                'string',
                Rule::in([
                    SpidLevel::LEVEL_1->value,
                    SpidLevel::LEVEL_2->value,
                    SpidLevel::LEVEL_3->value,
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => __('filament-spid::spid.provider_required'),
            'provider.in' => __('filament-spid::spid.provider_required'),
            'level.in' => __('filament-spid::spid.authentication_failed'),
        ];
    }
}


