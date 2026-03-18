<?php

namespace App\Models;

use App\DomainStatus;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'contract_id',
        'domain_name',
        'status',
        'notes',
        'credentials',
        'ftp_host',
        'ftp_user',
        'ftp_password',
        'hosting',
        'panel_url',
        'email_accounts',
        'other_data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'credentials' => 'encrypted:array',
            'ftp_password' => 'encrypted',
            'email_accounts' => 'encrypted:array',
            'other_data' => 'array',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
