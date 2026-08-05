<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePointingPeriodRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'period_end_day' => ['required', 'integer', 'min:1', 'max:31'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_start_day.required' => 'Indiquez le jour de début de la période.',
            'period_start_day.min' => 'Le jour de début va du 1 au 31.',
            'period_start_day.max' => 'Le jour de début va du 1 au 31.',
            'period_end_day.required' => 'Indiquez le jour de fin de la période.',
            'period_end_day.min' => 'Le jour de fin va du 1 au 31.',
            'period_end_day.max' => 'Le jour de fin va du 1 au 31.',
        ];
    }
}
