<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $guarded = ['id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public static function byCode(string $code): self
    {
        return static::where('code', $code)->firstOrFail();
    }

    /** "Daawah › Daawah programmes" */
    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }
}
