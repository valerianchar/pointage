<?php

namespace App\Http\Requests;

use App\Enums\TransactionDirection;
use App\Rules\ValidAmount;
use App\Support\Amount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
            'direction' => ['required', Rule::enum(TransactionDirection::class)],
            'amount' => ['required', new ValidAmount(minimumCents: 1)],
            'label' => ['required', 'string', 'max:120'],
            // Un tag n'est retenu que s'il appartient bien au compte visé.
            'tag_id' => [
                'nullable',
                Rule::exists('tags', 'id')->where('account_id', $this->integer('account_id')),
            ],
            'is_recurring' => ['boolean'],
            // Jour de chaque mois où la récurrente doit se produire.
            'recurring_day' => ['nullable', 'integer', 'min:1', 'max:31'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Choisissez le compte concerné.',
            'account_id.exists' => 'Ce compte est introuvable.',
            'label.required' => 'Donnez un libellé à cette opération.',
            'amount.required' => 'Saisissez un montant.',
            'tag_id.exists' => 'Ce tag n’appartient pas à ce compte.',
            'recurring_day.min' => 'Le jour va du 1 au 31.',
            'recurring_day.max' => 'Le jour va du 1 au 31.',
            'recurring_day.integer' => 'Le jour va du 1 au 31.',
        ];
    }

    public function direction(): TransactionDirection
    {
        return TransactionDirection::from($this->string('direction')->value());
    }

    public function signedAmountCents(): int
    {
        return $this->direction()->signedCents(Amount::toCents($this->input('amount')));
    }
}
