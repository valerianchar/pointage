<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['requested_by'])]
class AccountDeletionRequest extends Model
{
    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasMany<AccountDeletionApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(AccountDeletionApproval::class);
    }

    /**
     * Ceux qui ont voix au chapitre : le propriétaire et les membres acceptés.
     *
     * @return Collection<int, User>
     */
    public function voters(): Collection
    {
        return $this->account->members()->accepted()->with('user')->get()
            ->map(fn (AccountMember $member): User => $member->user)
            ->prepend($this->account->user)
            ->unique('id')
            ->values();
    }

    public function isVoter(User $user): bool
    {
        return $this->voters()->contains(fn (User $voter): bool => $voter->is($user));
    }

    public function hasApprovalFrom(User $user): bool
    {
        return $this->approvals()->where('user_id', $user->id)->exists();
    }

    public function isUnanimous(): bool
    {
        $approved = $this->approvals()->pluck('user_id');

        return $this->voters()->every(fn (User $voter): bool => $approved->contains($voter->id));
    }
}
