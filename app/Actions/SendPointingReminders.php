<?php

namespace App\Actions;

use App\Models\Account;
use App\Notifications\PointingPeriodEnded;
use Carbon\CarbonInterface;

final class SendPointingReminders
{
    /**
     * Prévient chaque abonné dont un compte termine sa période de pointage à la
     * date donnée et garde des opérations non pointées. Un compte joint
     * rappelle tous ses membres : chacun peut pointer.
     *
     * @return int Nombre de rappels envoyés
     */
    public function handle(CarbonInterface $date): int
    {
        $sentCount = 0;

        $accounts = Account::query()
            ->withCount(['transactions as pending_count' => fn ($query) => $query->whereNull('pointed_at')])
            ->with([
                'user.pushSubscriptions',
                'members' => fn ($query) => $query->accepted()->with('user.pushSubscriptions'),
            ])
            ->get();

        foreach ($accounts as $account) {
            $endsToday = $account->pointingPeriod($date)->end->isSameDay($date);

            if (! $endsToday || $account->pending_count === 0) {
                continue;
            }

            $recipients = $account->members->map->user
                ->prepend($account->user)
                ->filter(fn ($user) => $user->pushSubscriptions->isNotEmpty());

            foreach ($recipients as $recipient) {
                $recipient->notify(new PointingPeriodEnded($account, $account->pending_count));
                $sentCount++;
            }
        }

        return $sentCount;
    }
}
