<?php

namespace App\Observers;

use App\Crm\CrmFieldMappingService;
use App\Models\ContactFieldValue;

class CrmContactFieldValueObserver
{
    public function __construct(private CrmFieldMappingService $fieldMappings) {}

    public function created(ContactFieldValue $contactFieldValue): void
    {
        $this->fieldMappings->queueChangedField($contactFieldValue);
    }

    public function updated(ContactFieldValue $contactFieldValue): void
    {
        if ($contactFieldValue->wasChanged('value')) {
            $this->fieldMappings->queueChangedField($contactFieldValue);
        }
    }

    public function deleted(ContactFieldValue $contactFieldValue): void
    {
        $this->fieldMappings->queueChangedField($contactFieldValue);
    }
}
