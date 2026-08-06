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
            /*
             * Comptes à positions : on ne saisit pas de solde, on déclare ses
             * avoirs — le solde initial en découle, au cours du jour.
             */
            'positions' => ['nullable', 'array', 'max:30'],
            'positions.*.asset_id' => ['required', 'string', 'max:64'],
            'positions.*.quantity' => ['required', 'numeric', 'gt:0'],
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
            'positions.*.asset_id.required' => 'Chaque position doit nommer son actif.',
            'positions.*.quantity.required' => 'Chaque position doit porter une quantité.',
            'positions.*.quantity.gt' => 'La quantité doit être supérieure à zéro.',
            'positions.*.quantity.numeric' => 'La quantité est un nombre — point ou virgule acceptés.',
        ];
    }

    /**
     * Les quantités arrivent comme on les tape : virgule décimale, espaces.
     */
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('positions'))) {
            return;
        }

        $this->merge([
            'positions' => array_map(function ($position) {
                if (is_array($position) && is_string($position['quantity'] ?? null)) {
                    $position['quantity'] = str_replace([' ', ','], ['', '.'], trim($position['quantity']));
                }

                return $position;
            }, $this->input('positions')),
        ]);
    }

    public function accountType(): AccountType
    {
        return AccountType::from($this->string('type')->value());
    }

    /**
     * Positions déclarées, identifiants mis à la casse canonique de la source
     * de cours. Un même actif saisi deux fois ne compte qu'une fois.
     *
     * @return list<array{asset_id: string, quantity: string}>
     */
    public function positions(): array
    {
        $provider = $this->accountType()->assetProvider();

        if ($provider === null) {
            return [];
        }

        return collect($this->input('positions') ?? [])
            ->map(fn (array $position): array => [
                'asset_id' => $provider->normalizeAssetId($position['asset_id']),
                'quantity' => (string) $position['quantity'],
            ])
            ->keyBy('asset_id')
            ->values()
            ->all();
    }

    /**
     * Un solde laissé vide vaut zéro : le compte démarre à plat.
     */
    public function initialBalanceCents(): int
    {
        return Amount::toCents($this->input('initial_balance')) ?? 0;
    }
}
