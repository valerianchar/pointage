<?php

namespace Database\Seeders;

use App\Actions\CreateAccount;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Reproduit le jeu de données du handoff de design : quatre comptes, leurs tags
 * par défaut, les opérations du mois en cours et quelques mois d'historique pour
 * que le graphe d'évolution du solde ait une vraie pente.
 */
class DemoDataSeeder extends Seeder
{
    public function __construct(private readonly CreateAccount $createAccount) {}

    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'demo@pointage.app'],
            ['name' => 'Marie Olivier', 'password' => 'password', 'email_verified_at' => now()],
        );

        foreach ($this->accountDefinitions() as $definition) {
            $this->seedAccount($user, $definition);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedAccount(User $user, array $definition): void
    {
        $account = $this->createAccount->handle(
            $user,
            $definition['name'],
            $definition['type'],
            initialBalanceCents: 0,
        );

        $tagsByName = $account->tags()->get()->keyBy('name');

        foreach ($definition['transactions'] as $transaction) {
            $this->seedTransaction($account, $tagsByName, $transaction);
        }

        $account->credits()->createMany($definition['credits'] ?? []);

        /*
         * Le solde de la maquette est un solde courant : on en déduit le point de
         * départ du compte pour que solde initial + opérations retombe dessus.
         */
        $account->update([
            'initial_balance_cents' => $definition['balance_cents'] - (int) $account->transactions()->sum('amount_cents'),
        ]);
    }

    /**
     * @param  Collection<string, Tag>  $tagsByName
     * @param  array<string, mixed>  $transaction
     */
    private function seedTransaction(Account $account, Collection $tagsByName, array $transaction): void
    {
        $tag = $tagsByName->get($transaction['tag']);
        $occurredOn = $transaction['date'];

        $template = ($transaction['recurring'] ?? false)
            ? $account->recurringTransactions()->create([
                'label' => $transaction['label'],
                'amount_cents' => $transaction['amount_cents'],
                'day_of_month' => $occurredOn->day,
                'tag_id' => $tag?->id,
            ])
            : null;

        $account->transactions()->create([
            'label' => $transaction['label'],
            'amount_cents' => $transaction['amount_cents'],
            'tag_id' => $tag?->id,
            'recurring_transaction_id' => $template?->id,
            'occurred_on' => $occurredOn->toDateString(),
            'pointed_at' => ($transaction['pointed'] ?? false) ? $occurredOn->addDay() : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountDefinitions(): array
    {
        $month = CarbonImmutable::now()->startOfMonth();

        return [
            [
                'name' => 'Compte principal',
                'type' => AccountType::Current,
                'balance_cents' => 234_050,
                'credits' => [
                    ['name' => 'Prêt auto', 'borrowed_cents' => 1_420_000, 'remaining_cents' => 639_000, 'monthly_cents' => 23_650],
                    ['name' => 'Prêt immobilier', 'borrowed_cents' => 18_000_000, 'remaining_cents' => 14_230_000, 'monthly_cents' => 74_500],
                ],
                'transactions' => [
                    ['label' => 'Salaire', 'amount_cents' => 240_000, 'tag' => 'Salaire', 'date' => $month, 'pointed' => true, 'recurring' => true],
                    ['label' => 'Loyer', 'amount_cents' => -82_000, 'tag' => 'Loyer', 'date' => $month->addDay(), 'pointed' => true, 'recurring' => true],
                    ['label' => 'EDF', 'amount_cents' => -7_340, 'tag' => 'Abonnements', 'date' => $month->addDay(), 'recurring' => true],
                    ['label' => 'Spotify', 'amount_cents' => -1_099, 'tag' => 'Abonnements', 'date' => $month->addDays(2), 'recurring' => true],
                    ['label' => 'Carrefour', 'amount_cents' => -6_490, 'tag' => 'Courses', 'date' => $month->addDays(2)],
                    ['label' => 'Restaurant', 'amount_cents' => -4_250, 'tag' => 'Courses', 'date' => $month->addDays(2)],
                    ['label' => 'Navigo', 'amount_cents' => -8_880, 'tag' => 'Transport', 'date' => $month, 'pointed' => true, 'recurring' => true],
                    ...$this->previousMonths($month, [
                        ['label' => 'Salaire', 'amount_cents' => 240_000, 'tag' => 'Salaire', 'day' => 1],
                        ['label' => 'Loyer', 'amount_cents' => -82_000, 'tag' => 'Loyer', 'day' => 2],
                        ['label' => 'Courses du mois', 'amount_cents' => -31_050, 'tag' => 'Courses', 'day' => 12],
                        ['label' => 'Navigo', 'amount_cents' => -8_880, 'tag' => 'Transport', 'day' => 1],
                    ]),
                ],
            ],
            [
                'name' => 'Livret A',
                'type' => AccountType::Savings,
                'balance_cents' => 820_000,
                'transactions' => [
                    ['label' => 'Versement mensuel', 'amount_cents' => 20_000, 'tag' => 'Versement', 'date' => $month, 'pointed' => true, 'recurring' => true],
                    ...$this->previousMonths($month, [
                        ['label' => 'Versement mensuel', 'amount_cents' => 20_000, 'tag' => 'Versement', 'day' => 1],
                    ]),
                ],
            ],
            [
                'name' => 'PEA',
                'type' => AccountType::StockPlan,
                'balance_cents' => 1_248_030,
                'transactions' => [
                    ['label' => 'Achat 4× Air Liquide', 'amount_cents' => -50_000, 'tag' => 'Achat action', 'date' => $month, 'pointed' => true],
                    ['label' => 'Dividende TotalEnergies', 'amount_cents' => 3_820, 'tag' => 'Dividende', 'date' => $month->addDay()],
                    ...$this->previousMonths($month, [
                        ['label' => 'Versement programmé', 'amount_cents' => 30_000, 'tag' => 'Vente', 'day' => 5],
                    ]),
                ],
            ],
            [
                'name' => 'Crypto',
                'type' => AccountType::Crypto,
                'balance_cents' => 195_075,
                'transactions' => [
                    ['label' => 'Achat BTC', 'amount_cents' => -15_000, 'tag' => 'Achat', 'date' => $month->addDay()],
                    ['label' => 'Récompense staking', 'amount_cents' => 610, 'tag' => 'Staking', 'date' => $month->addDays(2)],
                    ...$this->previousMonths($month, [
                        ['label' => 'Récompense staking', 'amount_cents' => 580, 'tag' => 'Staking', 'day' => 3],
                    ]),
                ],
            ],
        ];
    }

    /**
     * Duplique un modèle d'opérations sur les deux mois précédents, déjà pointées.
     *
     * @param  list<array<string, mixed>>  $patterns
     * @return list<array<string, mixed>>
     */
    private function previousMonths(CarbonImmutable $month, array $patterns): array
    {
        $history = [];

        foreach ([2, 1] as $monthsAgo) {
            $previousMonth = $month->subMonths($monthsAgo);

            foreach ($patterns as $pattern) {
                $history[] = [
                    'label' => $pattern['label'],
                    'amount_cents' => $pattern['amount_cents'],
                    'tag' => $pattern['tag'],
                    'date' => $previousMonth->setDay(min($pattern['day'], $previousMonth->daysInMonth)),
                    'pointed' => true,
                ];
            }
        }

        return $history;
    }
}
