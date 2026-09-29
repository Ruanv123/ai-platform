<?php

use App\Enums\ChannelType;
use App\Http\Controllers\Agents\AgentController;
use App\Http\Controllers\Agents\PlaygroundController;
use App\Http\Controllers\Channels\ChannelController;
use App\Http\Controllers\Channels\WhatsAppController;
use App\Http\Controllers\Channels\WhatsAppTestController;
use App\Http\Controllers\Channels\WhatsAppTestProfilePhotoController;
use App\Http\Controllers\Channels\WhatsAppTestWebhookController;
use App\Http\Controllers\Channels\WhatsAppWebhookController;
use App\Http\Controllers\ContactConsentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Events\FlowEventController;
use App\Http\Controllers\Events\FlowEventWebhookController;
use App\Http\Controllers\Flows\FlowAiController;
use App\Http\Controllers\Flows\FlowController;
use App\Http\Controllers\Flows\FlowExecutionController;
use App\Http\Controllers\Flows\FlowPlaygroundController;
use App\Http\Controllers\Flows\FlowWebhookController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InboxMediaController;
use App\Http\Controllers\Integrations\AgendaCalendarController;
use App\Http\Controllers\Integrations\CrmAttendedContactSettingsController;
use App\Http\Controllers\Integrations\CrmCommercialStagesController;
use App\Http\Controllers\Integrations\CrmConnectionController;
use App\Http\Controllers\Integrations\CrmDefaultOwnerController;
use App\Http\Controllers\Integrations\CrmFieldVisibilityController;
use App\Http\Controllers\Integrations\CrmOperationController;
use App\Http\Controllers\Integrations\IntegrationController;
use App\Http\Controllers\Integrations\IntegrationWebhookFailureController;
use App\Http\Controllers\Integrations\RdStationCrmWebhookController;
use App\Http\Controllers\Integrations\ShopifyConnectionController;
use App\Http\Controllers\Integrations\ShopifyEmbeddedController;
use App\Http\Controllers\Integrations\ShopifyInstallationController;
use App\Http\Controllers\Integrations\ShopifyWebhookController;
use App\Http\Controllers\Knowledge\CollectionController;
use App\Http\Controllers\Knowledge\DocumentController;
use App\Http\Controllers\Onboarding\CompanyController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Templates\WhatsAppTemplateController;
use App\Http\Middleware\EnsureUserHasTeam;
use App\Http\Middleware\RedirectIfUserHasTeam;
use App\Http\Middleware\SetShopifyEmbeddedSecurityHeaders;
use App\Models\Channel;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::match(['get', 'post'], 'webhooks/whatsapp', [WhatsAppWebhookController::class, 'handleTechProvider'])
    ->middleware('throttle:whatsapp-webhook')
    ->name('webhooks.whatsapp.tech-provider');

Route::match(['get', 'post'], 'webhooks/whatsapp/app-proprio', [WhatsAppWebhookController::class, 'handle'])
    ->middleware('throttle:whatsapp-webhook')
    ->name('webhooks.whatsapp.app-proprio');

Route::post('webhooks/whatsapp-test', [WhatsAppTestWebhookController::class, 'handle'])
    ->name('webhooks.whatsapp-test');

Route::post('webhooks/shopify', [ShopifyWebhookController::class, 'handle'])
    ->middleware('throttle:shopify-webhook')
    ->name('webhooks.shopify');

Route::post('webhooks/crm/rd-station', [RdStationCrmWebhookController::class, 'handle'])
    ->middleware('throttle:crm-webhook')
    ->name('webhooks.crm.rd-station');

Route::get('shopify/embedded', [ShopifyEmbeddedController::class, 'bootstrap'])
    ->middleware(SetShopifyEmbeddedSecurityHeaders::class)
    ->name('shopify.embedded.bootstrap');

Route::post('shopify/embedded/authenticate', [ShopifyEmbeddedController::class, 'authenticate'])
    ->middleware('throttle:30,1')
    ->name('shopify.embedded.authenticate');

Route::get('shopify/embedded/home', [ShopifyEmbeddedController::class, 'home'])
    ->middleware(['signed', SetShopifyEmbeddedSecurityHeaders::class])
    ->name('shopify.embedded.home');

Route::get('shopify/install/{handoffToken}', [ShopifyInstallationController::class, 'start'])
    ->where('handoffToken', '[A-Za-z0-9]{64}')
    ->middleware('throttle:10,1')
    ->name('shopify.install.start');

Route::middleware('auth')->group(function (): void {
    Route::get('shopify/install', [ShopifyInstallationController::class, 'resume'])
        ->name('shopify.install.resume');
    Route::post('shopify/install', [ShopifyInstallationController::class, 'store'])
        ->name('shopify.install.store');
});

Route::get(
    'webhooks/whatsapp-test/profile-photo/{photoToken}/{extension}',
    [WhatsAppTestProfilePhotoController::class, 'show'],
)
    ->where([
        'photoToken' => '[A-Za-z0-9]{64}',
        'extension' => 'jpg|png',
    ])
    ->middleware('signed')
    ->name('channels.whatsapp-test.profile.photo.serve');

Route::post('webhooks/flows/{channelId}/{event}', [FlowWebhookController::class, 'handle'])
    ->middleware('throttle:flow-webhook')
    ->name('webhooks.flows');

Route::bind('channel', function (string $value): Channel {
    $query = Channel::query()->whereKey($value);
    if (request()->routeIs('inbox.*')) {
        $query->whereIn('type', [ChannelType::WhatsApp, ChannelType::WhatsAppTest]);
    } else {
        $query->where('type', ChannelType::WhatsApp);
    }

    return $query->firstOrFail();
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
    Route::get('atendimento', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('atendimento/{contact}/{channel}', [InboxController::class, 'show'])->name('inbox.show');
    Route::post('atendimento/{contact}/{channel}/take', [InboxController::class, 'take'])->name('inbox.take');
    Route::post('atendimento/{contact}/{channel}/pause-agent', [InboxController::class, 'pauseAgent'])->name('inbox.agent.pause');
    Route::post('atendimento/{contact}/{channel}/take-over-agent', [InboxController::class, 'takeOverAgent'])->name('inbox.agent.take-over');
    Route::post('atendimento/{contact}/{channel}/messages', [InboxController::class, 'storeMessage'])->name('inbox.messages.store');
    Route::get('atendimento/{contact}/{channel}/messages/{message}/media', [InboxMediaController::class, 'show'])
        ->name('inbox.messages.media');
    Route::post('atendimento/{contact}/{channel}/consents', [ContactConsentController::class, 'store'])->name('inbox.consents.store');
    Route::post('atendimento/{contact}/{channel}/return', [InboxController::class, 'returnToBot'])->name('inbox.return');

    Route::resource('agents', AgentController::class)
        ->only(['index', 'store', 'edit', 'update', 'destroy'])
        ->names('agents');
    Route::post('flows/templates/{template}/clone', [FlowController::class, 'cloneTemplate'])->name('flows.templates.clone');
    Route::get('flows/{flow}/executions/{execution}', [FlowExecutionController::class, 'show'])
        ->name('flows.executions.show');
    Route::resource('flows', FlowController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy'])
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
    Route::post('agents/{agent}/publish', [AgentController::class, 'publish'])
        ->name('agents.publish');
    Route::post('agents/playground', [PlaygroundController::class, 'stream'])
        ->middleware('throttle:playground')
        ->name('agents.playground.stream');

    Route::get('channels', [ChannelController::class, 'index'])
        ->name('channels.index');
    Route::get('channels/whatsapp', [WhatsAppController::class, 'edit'])
        ->name('channels.whatsapp.edit');
    Route::get('channels/whatsapp-test', [WhatsAppTestController::class, 'edit'])
        ->name('channels.whatsapp-test.edit');
    Route::get('templates', [WhatsAppTemplateController::class, 'index'])
        ->name('templates.index');
    Route::post('templates', [WhatsAppTemplateController::class, 'store'])
        ->name('templates.store');
    Route::post('templates/sync', [WhatsAppTemplateController::class, 'sync'])
        ->name('templates.sync');
    Route::get('templates/{template}', [WhatsAppTemplateController::class, 'show'])
        ->name('templates.show');
    Route::put('templates/{template}', [WhatsAppTemplateController::class, 'update'])
        ->name('templates.update');
    Route::post('templates/{template}/submit', [WhatsAppTemplateController::class, 'submit'])
        ->name('templates.submit');

    Route::get('integrations', [IntegrationController::class, 'index'])
        ->name('integrations.index');
    Route::get('integrations/ecommerce/shopify/connect', [ShopifyConnectionController::class, 'connect'])
        ->name('integrations.shopify.connect');
    Route::get('integrations/ecommerce/shopify/callback', [ShopifyConnectionController::class, 'callback'])
        ->name('integrations.shopify.callback');
    Route::get('integrations/agenda/google/connect', [IntegrationController::class, 'connect'])
        ->name('integrations.agenda.connect');
    Route::get('integrations/agenda/google/callback', [IntegrationController::class, 'callback'])
        ->name('integrations.agenda.callback');
    Route::get('integrations/crm/rd-station/connect', [CrmConnectionController::class, 'connect'])
        ->name('integrations.crm.rd-station.connect');
    Route::get('integrations/crm/rd-station/callback', [CrmConnectionController::class, 'callback'])
        ->name('integrations.crm.rd-station.callback');
    Route::put('integrations/crm/fields/visible-to-agent', [CrmFieldVisibilityController::class, 'update'])
        ->name('integrations.crm.fields.visible-to-agent.update');
    Route::put('integrations/crm/default-owner', [CrmDefaultOwnerController::class, 'update'])
        ->name('integrations.crm.default-owner.update');
    Route::put('integrations/crm/attended-contact-registration', [CrmAttendedContactSettingsController::class, 'update'])
        ->name('integrations.crm.attended-contact-registration.update');
    Route::get('integrations/crm/operations', [CrmOperationController::class, 'index'])
        ->name('integrations.crm.operations.index');
    Route::post('integrations/crm/operations/{operation}/retry', [CrmOperationController::class, 'retry'])
        ->name('integrations.crm.operations.retry');
    Route::put('integrations/crm/commercial-stages', [CrmCommercialStagesController::class, 'update'])
        ->name('integrations.crm.commercial-stages.update');
    Route::post('integrations', [IntegrationController::class, 'store'])
        ->name('integrations.store');
    Route::post('integrations/agenda/calendars', [AgendaCalendarController::class, 'store'])
        ->name('integrations.agenda.calendars.store');
    Route::patch('integrations/agenda/calendars/{schedulableCalendar}', [AgendaCalendarController::class, 'update'])
        ->name('integrations.agenda.calendars.update');
    Route::delete('integrations/agenda/calendars/{schedulableCalendar}', [AgendaCalendarController::class, 'destroy'])
        ->name('integrations.agenda.calendars.destroy');
    Route::get('integrations/{type}', [IntegrationController::class, 'show'])
        ->whereIn('type', ['agenda', 'crm', 'ecommerce'])
        ->name('integrations.show');
    Route::patch('integrations/{integration}', [IntegrationController::class, 'update'])
        ->name('integrations.update');
    Route::post('integrations/{integration}/test', [IntegrationController::class, 'test'])
        ->name('integrations.test');
    Route::delete('integrations/{integration}', [IntegrationController::class, 'destroy'])
        ->name('integrations.destroy');
    Route::get('integrations/webhook-failures', [IntegrationWebhookFailureController::class, 'index'])
        ->name('integrations.webhook-failures.index');
    Route::post('integrations/webhook-failures/{delivery}/retry', [IntegrationWebhookFailureController::class, 'retry'])
        ->name('integrations.webhook-failures.retry');
    Route::get('events', [FlowEventController::class, 'index'])
        ->name('events.index');
    Route::post('events', [FlowEventController::class, 'store'])
        ->name('events.store');
    Route::patch('events/{flowEvent}', [FlowEventController::class, 'update'])
        ->name('events.update');
    Route::delete('events/{flowEvent}', [FlowEventController::class, 'destroy'])
        ->name('events.destroy');
    Route::post('events/{flowEvent}/webhooks', [FlowEventWebhookController::class, 'store'])
        ->name('events.webhooks.store');
    Route::delete('events/{flowEvent}/webhooks/{webhook}', [FlowEventWebhookController::class, 'destroy'])
        ->name('events.webhooks.destroy');
    Route::post('channels/whatsapp', [WhatsAppController::class, 'store'])
        ->name('channels.whatsapp.store');
    Route::post('channels/whatsapp/connect-with-meta', [WhatsAppController::class, 'connectWithMeta'])
        ->name('channels.whatsapp.connect-with-meta');
    Route::post('channels/whatsapp-test', [WhatsAppTestController::class, 'store'])
        ->name('channels.whatsapp-test.store');
    Route::patch('channels/whatsapp/{channel}', [WhatsAppController::class, 'update'])
        ->name('channels.whatsapp.update');
    Route::patch('channels/whatsapp/{channel}/agent', [WhatsAppController::class, 'updateAgent'])
        ->name('channels.whatsapp.update-agent');
    Route::post('channels/whatsapp/{channel}/test', [WhatsAppController::class, 'test'])
        ->name('channels.whatsapp.test');
    Route::post('channels/whatsapp/{channel}/profile/texts', [WhatsAppController::class, 'updateProfileTexts'])
        ->name('channels.whatsapp.profile.texts');
    Route::post('channels/whatsapp/{channel}/profile/photo', [WhatsAppController::class, 'updateProfilePicture'])
        ->name('channels.whatsapp.profile.photo');
    Route::post('channels/whatsapp/{channel}/profile/display-name', [WhatsAppController::class, 'requestDisplayName'])
        ->name('channels.whatsapp.profile.display-name');
    Route::delete('channels/whatsapp/{channel}', [WhatsAppController::class, 'destroy'])
        ->name('channels.whatsapp.destroy');
    Route::patch('channels/whatsapp-test/{whatsappTestChannel}', [WhatsAppTestController::class, 'update'])
        ->name('channels.whatsapp-test.update');
    Route::post('channels/whatsapp-test/{whatsappTestChannel}/profile/texts', [WhatsAppTestController::class, 'updateProfileTexts'])
        ->name('channels.whatsapp-test.profile.texts');
    Route::post('channels/whatsapp-test/{whatsappTestChannel}/profile/photo', [WhatsAppTestController::class, 'updateProfilePicture'])
        ->name('channels.whatsapp-test.profile.photo');
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
