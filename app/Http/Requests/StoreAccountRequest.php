<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Rules\ValidAmount;
use App\Support\Amount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'initial_balance' => ['nullable', new ValidAmount],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Donnez un nom à ce compte.',
            'type.required' => 'Choisissez un type de compte.',
        ];
    }

    public function accountType(): AccountType
    {
        return AccountType::from($this->string('type')->value());
    }

    /**
     * Un solde laissé vide vaut zéro : le compte démarre à plat.
     */
    public function initialBalanceCents(): int
    {
        return Amount::toCents($this->input('initial_balance')) ?? 0;
    }
}
