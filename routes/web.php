<?php

use App\Enums\ChannelType;
use App\Http\Controllers\Agents\AgentController;
use App\Http\Controllers\Agents\PlaygroundController;
use App\Http\Controllers\Channels\ChannelController;
use App\Http\Controllers\Channels\WhatsAppController;
use App\Http\Controllers\Channels\WhatsAppTestController;
use App\Http\Controllers\Channels\WhatsAppTestWebhookController;
use App\Http\Controllers\Channels\WhatsAppWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Flows\FlowAiController;
use App\Http\Controllers\Flows\FlowController;
use App\Http\Controllers\Flows\FlowPlaygroundController;
use App\Http\Controllers\Flows\FlowWebhookController;
use App\Http\Controllers\Integrations\IntegrationController;
use App\Http\Controllers\Knowledge\CollectionController;
use App\Http\Controllers\Knowledge\DocumentController;
use App\Http\Controllers\Onboarding\CompanyController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureUserHasTeam;
use App\Http\Middleware\RedirectIfUserHasTeam;
use App\Models\Channel;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::match(['get', 'post'], 'webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->middleware('throttle:whatsapp-webhook')
    ->name('webhooks.whatsapp');

Route::post('webhooks/whatsapp-test', [WhatsAppTestWebhookController::class, 'handle'])
    ->name('webhooks.whatsapp-test');

Route::post('webhooks/flows/{channelId}/{event}', [FlowWebhookController::class, 'handle'])
    ->middleware('throttle:flow-webhook')
    ->name('webhooks.flows');

Route::bind('channel', function (string $value): Channel {
    return Channel::query()
        ->whereKey($value)
        ->where('type', ChannelType::WhatsApp)
        ->firstOrFail();
});

Route::bind('whatsappTestChannel', function (string $value): Channel {
    return Channel::query()
        ->whereKey($value)
        ->where('type', ChannelType::WhatsAppTest)
        ->firstOrFail();
});

Route::middleware(['auth', RedirectIfUserHasTeam::class])->group(function () {
    Route::get('onboarding/company', [CompanyController::class, 'create'])->name('onboarding.company');
    Route::post('onboarding/company', [CompanyController::class, 'store'])->name('onboarding.company.store');
});

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', EnsureUserHasTeam::class])
    ->name('dashboard');

Route::middleware(['auth', EnsureUserHasTeam::class])->group(function () {
    Route::resource('agents', AgentController::class)
        ->only(['index', 'store', 'edit', 'update', 'destroy'])
        ->names('agents');
    Route::post('flows/templates/{template}/clone', [FlowController::class, 'cloneTemplate'])->name('flows.templates.clone');
    Route::resource('flows', FlowController::class)
        ->only(['index', 'show', 'store', 'update'])
        ->names('flows');
    Route::post('flows/{flow}/ai/propose', [FlowAiController::class, 'propose'])->middleware('throttle:playground')->name('flows.ai.propose');
    Route::post('flows/{flow}/ai/apply', [FlowAiController::class, 'apply'])->name('flows.ai.apply');
    Route::post('flows/{flow}/publish', [FlowController::class, 'publish'])->name('flows.publish');
    Route::post('flows/{flow}/pause', [FlowController::class, 'pause'])->name('flows.pause');
    Route::post('flows/{flow}/rollback/{version}', [FlowController::class, 'rollback'])->name('flows.rollback');
    Route::post('flows/{flow}/resume', [FlowController::class, 'resume'])->name('flows.resume');
    Route::post('flows/{flow}/playground', [FlowPlaygroundController::class, 'run'])->name('flows.playground');
    Route::patch('agents/{agent}/toggle', [AgentController::class, 'toggle'])
        ->name('agents.toggle');
    Route::post('agents/playground', [PlaygroundController::class, 'stream'])
        ->middleware('throttle:playground')
        ->name('agents.playground.stream');

    Route::get('channels', [ChannelController::class, 'index'])
        ->name('channels.index');
    Route::get('channels/whatsapp', [WhatsAppController::class, 'edit'])
        ->name('channels.whatsapp.edit');
    Route::get('channels/whatsapp-test', [WhatsAppTestController::class, 'edit'])
        ->name('channels.whatsapp-test.edit');
    Route::get('integrations', [IntegrationController::class, 'index'])
        ->name('integrations.index');
    Route::post('channels/whatsapp', [WhatsAppController::class, 'store'])
        ->name('channels.whatsapp.store');
    Route::post('channels/whatsapp-test', [WhatsAppTestController::class, 'store'])
        ->name('channels.whatsapp-test.store');
    Route::patch('channels/whatsapp/{channel}', [WhatsAppController::class, 'update'])
        ->name('channels.whatsapp.update');
    Route::post('channels/whatsapp/{channel}/test', [WhatsAppController::class, 'test'])
        ->name('channels.whatsapp.test');
    Route::delete('channels/whatsapp/{channel}', [WhatsAppController::class, 'destroy'])
        ->name('channels.whatsapp.destroy');
    Route::patch('channels/whatsapp-test/{whatsappTestChannel}', [WhatsAppTestController::class, 'update'])
        ->name('channels.whatsapp-test.update');
    Route::delete('channels/whatsapp-test/{whatsappTestChannel}', [WhatsAppTestController::class, 'destroy'])
        ->name('channels.whatsapp-test.destroy');

    Route::resource('knowledge/collections', CollectionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('collections');

    Route::get('knowledge/collections/{collection}/documents', [DocumentController::class, 'index'])
        ->name('documents.index');
    Route::post('knowledge/collections/{collection}/documents', [DocumentController::class, 'store'])
        ->name('documents.store');
    Route::get('knowledge/documents/{document}', [DocumentController::class, 'show'])
        ->name('documents.show');
    Route::patch('knowledge/documents/{document}', [DocumentController::class, 'update'])
        ->name('documents.update');
    Route::get('knowledge/documents/{document}/file', [DocumentController::class, 'download'])
        ->name('documents.download');
    Route::patch('knowledge/documents/{document}/move', [DocumentController::class, 'move'])
        ->name('documents.move');
    Route::post('knowledge/documents/{document}/reprocess', [DocumentController::class, 'reprocess'])
        ->name('documents.reprocess');
    Route::delete('knowledge/documents/{document}', [DocumentController::class, 'destroy'])
        ->name('documents.destroy');
});

Route::get('invitations/{invitation}', [TeamInvitationController::class, 'show'])->name('invitations.show');

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
