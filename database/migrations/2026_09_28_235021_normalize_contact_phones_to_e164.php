<?php

use App\WhatsApp\E164PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('contacts')->orderBy('id')->each(function (object $contact): void {
            $phone = E164PhoneNumber::format($contact->phone);

            if ($phone === null || $phone === $contact->phone) {
                return;
            }

            $isTakenByAnotherContact = DB::table('contacts')
                ->where('team_id', $contact->team_id)
                ->where('phone', $phone)
                ->exists();

            if ($isTakenByAnotherContact) {
                return;
            }

            DB::table('contacts')->where('id', $contact->id)->update(['phone' => $phone]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('contacts')
            ->where('phone', 'like', '+%')
            ->update(['phone' => DB::raw('substr(phone, 2)')]);
    }
};
