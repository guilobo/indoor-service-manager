<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company_name',
        'document',
        'email',
        'phone',
        'whatsapp',
        'address',
        'notes',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
