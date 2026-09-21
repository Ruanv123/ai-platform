<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\TeamRole;
use App\Models\Collection as TeamCollection;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $segment
 * @property string|null $website
 * @property string|null $business_description
 * @property string|null $products_services
 * @property string|null $target_audience
 * @property array<string, array{enabled?: bool, open?: string|null, close?: string|null}>|null $business_hours
 * @property string|null $timezone
 * @property string|null $phone
 * @property string|null $public_email
 * @property string|null $address
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Contact> $contacts
 * @property-read Collection<int, Message> $messages
 * @property-read Collection<int, TeamCollection> $collections
 * @property-read Collection<int, Agent> $agents
 * @property-read Collection<int, Channel> $channels
 * @property-read Collection<int, Flow> $flows
 * @property-read Collection<int, ContactField> $contactFields
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, GlobalVariable> $globalVariables
 * @property-read Collection<int, FlowExecution> $flowExecutions
 * @property-read Collection<int, Document> $documents
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 */
#[Fillable([
    'name',
    'segment',
    'website',
    'business_description',
    'products_services',
    'target_audience',
    'business_hours',
    'timezone',
    'phone',
    'public_email',
    'address',
    'slug',
])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get all contacts for this Empresa.
     *
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Get all messages for this Empresa.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the knowledge collections for this Empresa.
     *
     * @return HasMany<TeamCollection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(TeamCollection::class);
    }

    /**
     * Get the Agents for this Empresa.
     *
     * @return HasMany<Agent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Get the atendimento Channels configured for this Empresa.
     *
     * @return HasMany<Channel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    /** @return HasMany<Flow, $this> */
    public function flows(): HasMany
    {
        return $this->hasMany(Flow::class);
    }

    /** @return HasMany<ContactField, $this> */
    public function contactFields(): HasMany
    {
        return $this->hasMany(ContactField::class);
    }

    /** @return HasMany<Tag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /** @return HasMany<GlobalVariable, $this> */
    public function globalVariables(): HasMany
    {
        return $this->hasMany(GlobalVariable::class);
    }

    /** @return HasMany<FlowExecution, $this> */
    public function flowExecutions(): HasMany
    {
        return $this->hasMany(FlowExecution::class);
    }

    /**
     * Get all documents for this Empresa.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
