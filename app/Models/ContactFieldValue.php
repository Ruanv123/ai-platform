<?php

namespace App\Models;

use Database\Factories\ContactFieldValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contact_field_id', 'contact_id', 'value'])]
class ContactFieldValue extends Model
{
    /** @use HasFactory<ContactFieldValueFactory> */
    use HasFactory;

    /** @return BelongsTo<ContactField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ContactField::class, 'contact_field_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
