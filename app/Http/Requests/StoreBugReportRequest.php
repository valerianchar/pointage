<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreBugReportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Comme sur la maquette : seule la description est obligatoire.
            'subject' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.max' => 'Le sujet ne peut pas dépasser 120 caractères.',
            'description.required' => 'Décrivez le bug rencontré.',
            'description.max' => 'La description ne peut pas dépasser 5000 caractères.',
        ];
    }

    /**
     * Sujet retenu : celui saisi, sinon le début de la description. Aplati sur
     * une ligne dans les deux cas — il part dans un en-tête d'e-mail.
     */
    public function subject(): string
    {
        $subject = $this->string('subject')->squish()->value();

        return $subject !== '' ? $subject : Str::limit(Str::squish($this->description()), 40, '…');
    }

    public function description(): string
    {
        return $this->string('description')->trim()->value();
    }
}
