<?php

namespace App\Http\Requests;

use App\Enums\TransactionDirection;
use App\Rules\ValidAmount;
use App\Support\Amount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(TransactionDirection::class)],
            'amount' => ['required', new ValidAmount(minimumCents: 1)],
            'label' => ['required', 'string', 'max:120'],
            'occurred_on' => ['required', 'date'],
            // Un tag n'est retenu que s'il appartient au compte de l'opération.
            'tag_id' => [
                'nullable',
                Rule::exists('tags', 'id')->where('account_id', $this->route('transaction')->account_id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Donnez un libellé à cette opération.',
            'amount.required' => 'Saisissez un montant.',
            'occurred_on.required' => 'Indiquez la date de l\'opération.',
            'occurred_on.date' => 'Cette date est invalide.',
            'tag_id.exists' => 'Ce tag n\'appartient pas à ce compte.',
        ];
    }

    public function signedAmountCents(): int
    {
        $direction = TransactionDirection::from($this->string('direction')->value());

        return $direction->signedCents(Amount::toCents($this->input('amount')));
    }
}
