<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\CrmProvider;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    /**
     * Display the available CRM integrations.
     */
    public function index(): Response
    {
        return Inertia::render('integrations/index', [
            'integrations' => CrmProvider::catalog(),
        ]);
    }
}
