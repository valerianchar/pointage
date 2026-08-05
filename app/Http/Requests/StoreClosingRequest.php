<?php

namespace App\Http\Requests;

use App\Rules\ValidAmount;
use App\Support\Amount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClosingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id),
            ],
            // Un découvert est un solde comme un autre : le négatif est accepté.
            'real_balance' => ['required', new ValidAmount],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Choisissez le compte à clôturer.',
            'account_id.exists' => 'Ce compte est introuvable.',
            'real_balance.required' => 'Saisissez le solde réel de votre relevé.',
            'note.max' => 'Le commentaire ne peut pas dépasser 500 caractères.',
        ];
    }

    public function realBalanceCents(): int
    {
        return Amount::toCents($this->input('real_balance')) ?? 0;
    }
}
