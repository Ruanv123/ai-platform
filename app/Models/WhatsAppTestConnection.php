<?php

namespace App\Models;

use Database\Factories\WhatsAppTestConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $channel_id
 * @property string $service_url
 * @property string $instance_name
 * @property string $name
 * @property string $access_token
 * @property string|null $qrcode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Channel $channel
 */
#[Fillable(['channel_id', 'service_url', 'instance_name', 'name', 'access_token', 'qrcode'])]
#[Hidden(['access_token'])]
class WhatsAppTestConnection extends Model
{
    protected $table = 'whatsapp_test_connections';

    /** @use HasFactory<WhatsAppTestConnectionFactory> */
    use HasFactory;

    /**
     * Get the Channel configured with this test connection.
     *
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
        ];
    }
}
