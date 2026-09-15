<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        RateLimiter::for('playground', function (Request $request): Limit {
            return Limit::perHour(30)
                ->by('playground:user:'.$request->user()->getKey())
                ->response(fn (Request $request, array $headers): JsonResponse => response()->json([
                    'message' => 'Você atingiu o limite de 30 mensagens por hora no Playground. Tente novamente mais tarde.',
                ], 429, $headers));
        });

        RateLimiter::for('whatsapp-webhook', function (Request $request): Limit {
            $phoneNumberId = data_get(
                $request->json()->all(),
                'entry.0.changes.0.value.metadata.phone_number_id',
            );
            $phoneNumberKey = is_scalar($phoneNumberId) && filled($phoneNumberId)
                ? (string) $phoneNumberId
                : 'unknown';

            return Limit::perMinute(300)
                ->by('whatsapp-webhook:'.(string) $request->ip().':'.$phoneNumberKey);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
