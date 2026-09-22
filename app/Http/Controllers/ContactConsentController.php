<?php

namespace App\Http\Controllers;

use App\Consent\ContactConsentService;
use App\Enums\ConsentAction;
use App\Enums\ConsentPurpose;
use App\Http\Requests\SaveContactConsentRequest;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class ContactConsentController extends Controller
{
    public function store(SaveContactConsentRequest $request, Contact $contact, ContactConsentService $consents): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $purpose = ConsentPurpose::from($data['purpose']);
        $action = ConsentAction::from($data['action']);
        $consent = $consents->record(
            $contact,
            $purpose,
            $action,
            (string) ($data['source'] ?? 'manual'),
            ['user_id' => $request->user()->getKey()],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'purpose' => $purpose->value,
                'granted' => $action === ConsentAction::Granted,
                'source' => $consent->source,
                'at' => $consent->created_at->toIso8601String(),
            ]);
        }

        return to_route('inbox.show', $contact);
    }
}
