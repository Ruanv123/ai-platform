<?php

namespace App\Ecommerce;

use App\Models\Contact;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Support\E164PhoneNumber;
use Illuminate\Support\Facades\DB;

final class StoreCustomerContactLinker
{
    public function syncStoreCustomer(StoreCustomer $storeCustomer): void
    {
        DB::transaction(function () use ($storeCustomer): void {
            $shop = Shop::withoutGlobalScopes()
                ->whereKey($storeCustomer->shop_id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();
            $lockedCustomer = StoreCustomer::withoutGlobalScopes()
                ->lockForUpdate()
                ->whereKey($storeCustomer->getKey())
                ->first();

            if ($shop === null || $lockedCustomer === null) {
                return;
            }

            $lockedCustomer->forceFill(['contact_id' => null])->save();

            $phone = is_string($lockedCustomer->phone_e164)
                ? E164PhoneNumber::formatForCountry($lockedCustomer->phone_e164, $shop->country_code)
                : null;

            if ($phone === null) {
                return;
            }

            $contact = Contact::withoutGlobalScopes()
                ->where('team_id', $lockedCustomer->team_id)
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if ($contact === null) {
                return;
            }

            $isContactLinkedElsewhere = StoreCustomer::withoutGlobalScopes()
                ->where('contact_id', $contact->getKey())
                ->where('id', '!=', $lockedCustomer->getKey())
                ->exists();

            if ($isContactLinkedElsewhere) {
                return;
            }

            $lockedCustomer->forceFill(['contact_id' => $contact->getKey()])->save();
        });
    }

    public function syncContact(Contact $contact): void
    {
        DB::transaction(function () use ($contact): void {
            $shop = Shop::withoutGlobalScopes()
                ->where('team_id', $contact->team_id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();
            $lockedContact = Contact::withoutGlobalScopes()
                ->lockForUpdate()
                ->whereKey($contact->getKey())
                ->first();

            if ($lockedContact === null) {
                return;
            }

            $linkedCustomer = StoreCustomer::withoutGlobalScopes()
                ->where('contact_id', $lockedContact->getKey())
                ->lockForUpdate()
                ->first();

            if ($linkedCustomer !== null) {
                $linkedCustomer->forceFill(['contact_id' => null])->save();
            }

            if ($shop === null) {
                return;
            }

            $phone = E164PhoneNumber::formatForCountry($lockedContact->phone, $shop->country_code);

            if ($phone === null) {
                return;
            }

            $storeCustomer = StoreCustomer::withoutGlobalScopes()
                ->where('shop_id', $shop->getKey())
                ->where('team_id', $lockedContact->team_id)
                ->where('phone_e164', $phone)
                ->whereNull('contact_id')
                ->lockForUpdate()
                ->first();

            if ($storeCustomer !== null) {
                $storeCustomer->forceFill(['contact_id' => $lockedContact->getKey()])->save();
            }
        });
    }
}
