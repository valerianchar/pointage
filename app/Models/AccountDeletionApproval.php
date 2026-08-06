<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id'])]
class AccountDeletionApproval extends Model
{
    /** @return BelongsTo<AccountDeletionRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(AccountDeletionRequest::class, 'account_deletion_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
