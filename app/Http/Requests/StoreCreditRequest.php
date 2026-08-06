<?php

namespace App\Http\Requests;

use App\Rules\ValidAmount;
use App\Support\Amount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCreditRequest extends FormRequest
{
    /** Cinquante ans, la durée d'un prêt immobilier très long. */
    private const MAXIMUM_TERM_MONTHS = 600;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                // Le compte visé : le sien, ou un compte joint où il est membre accepté.
                Rule::in($this->user()->accessibleAccounts()->pluck('id')->all()),
            ],
            'name' => ['required', 'string', 'max:120'],
            'monthly' => ['required', new ValidAmount(minimumCents: 1)],
            /*
             * Un seul des deux capitaux suffit : l'autre s'en déduit, comme sur la
             * maquette. Un crédit sans aucun capital, en revanche, n'a rien à suivre.
             */
            'borrowed' => ['required_without:remaining', 'nullable', new ValidAmount(minimumCents: 1)],
            'remaining' => ['required_without:borrowed', 'nullable', new ValidAmount(minimumCents: 1)],
            'term_months' => ['required', 'integer', 'min:1', 'max:'.self::MAXIMUM_TERM_MONTHS],
            'payment_day' => ['required', 'integer', 'min:1', 'max:31'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Choisissez le compte qui porte ce crédit.',
            'account_id.in' => 'Ce compte est introuvable.',
            'name.required' => 'Donnez un nom à ce crédit.',
            'monthly.required' => 'Saisissez la mensualité.',
            'borrowed.required_without' => 'Saisissez le capital emprunté ou le capital restant.',
            'remaining.required_without' => 'Saisissez le capital restant ou le capital emprunté.',
            'term_months.required' => 'Indiquez la durée du crédit, en mois.',
            'term_months.integer' => 'La durée doit être un nombre entier de mois.',
            'term_months.max' => 'La durée ne peut pas dépasser '.self::MAXIMUM_TERM_MONTHS.' mois.',
            'payment_day.required' => 'Indiquez le jour du mois où vous êtes prélevé.',
            'payment_day.min' => 'Le jour de prélèvement va du 1 au 31.',
            'payment_day.max' => 'Le jour de prélèvement va du 1 au 31.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->remainingCents() > $this->borrowedCents()) {
                $validator->errors()->add(
                    'remaining',
                    'Le capital restant ne peut pas dépasser le capital emprunté.',
                );
            }
        });
    }

    public function monthlyCents(): int
    {
        return Amount::toCents($this->input('monthly'));
    }

    /**
     * À défaut de capital emprunté, on part du capital restant : le crédit démarre
     * alors comme s'il n'avait rien remboursé.
     */
    public function borrowedCents(): int
    {
        return Amount::toCents($this->input('borrowed'))
            ?? Amount::toCents($this->input('remaining'))
            ?? 0;
    }

    public function remainingCents(): int
    {
        return Amount::toCents($this->input('remaining'))
            ?? Amount::toCents($this->input('borrowed'))
            ?? 0;
    }
}
