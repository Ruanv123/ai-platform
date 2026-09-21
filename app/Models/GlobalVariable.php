<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\GlobalVariableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $team_id
 * @property string $name
 * @property string|null $value
 * @property-read Team $team
 */
#[Fillable(['team_id', 'name', 'value'])]
class GlobalVariable extends Model
{
    /** @use HasFactory<GlobalVariableFactory> */
    use BelongsToTeam, HasFactory, HasUuids;
}
