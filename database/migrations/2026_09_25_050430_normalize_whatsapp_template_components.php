<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_template_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_template_id')->constrained('whatsapp_templates')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('format', 32)->nullable();
            $table->text('text')->nullable();
            $table->string('media_path')->nullable();
            $table->text('media_handle')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['whatsapp_template_id', 'type']);
            $table->index(['whatsapp_template_id', 'position']);
        });

        Schema::create('whatsapp_template_variables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_template_component_id')->constrained('whatsapp_template_components')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('example')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_template_component_id', 'position']);
        });

        Schema::create('whatsapp_template_buttons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_template_component_id')->constrained('whatsapp_template_components')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('text')->nullable();
            $table->text('url')->nullable();
            $table->string('phone_number', 32)->nullable();
            $table->text('example')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['whatsapp_template_component_id', 'position']);
        });

        DB::table('whatsapp_templates')
            ->select(['id', 'body', 'header_type', 'header_text', 'footer', 'buttons', 'variables'])
            ->orderBy('id')
            ->chunkById(100, function ($templates): void {
                foreach ($templates as $template) {
                    $componentIds = [];
                    $components = [];

                    if (filled($template->header_type) || filled($template->header_text)) {
                        $components[] = [
                            'type' => 'header',
                            'format' => filled($template->header_type) ? strtolower($template->header_type) : 'text',
                            'text' => $template->header_text,
                        ];
                    }

                    $components[] = [
                        'type' => 'body',
                        'format' => null,
                        'text' => $template->body,
                    ];

                    if (filled($template->footer)) {
                        $components[] = [
                            'type' => 'footer',
                            'format' => null,
                            'text' => $template->footer,
                        ];
                    }

                    foreach ($components as $position => $component) {
                        $componentIds[$component['type']] = DB::table('whatsapp_template_components')->insertGetId([
                            'whatsapp_template_id' => $template->id,
                            ...$component,
                            'media_path' => null,
                            'media_handle' => null,
                            'position' => $position,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $buttons = json_decode((string) $template->buttons, true);
                    if (is_array($buttons) && $buttons !== []) {
                        $componentId = DB::table('whatsapp_template_components')->insertGetId([
                            'whatsapp_template_id' => $template->id,
                            'type' => 'buttons',
                            'format' => null,
                            'text' => null,
                            'media_path' => null,
                            'media_handle' => null,
                            'position' => count($components),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $componentIds['buttons'] = $componentId;

                        foreach (array_values($buttons) as $position => $button) {
                            if (! is_array($button)) {
                                continue;
                            }

                            $example = $button['example'] ?? null;
                            DB::table('whatsapp_template_buttons')->insert([
                                'whatsapp_template_component_id' => $componentId,
                                'type' => strtolower((string) ($button['type'] ?? 'quick_reply')),
                                'text' => $button['text'] ?? null,
                                'url' => $button['url'] ?? null,
                                'phone_number' => $button['phone_number'] ?? null,
                                'example' => is_array($example) ? ($example[0] ?? null) : $example,
                                'position' => $position,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            if (strtolower((string) ($button['type'] ?? '')) === 'url' && filled($button['url'] ?? null)) {
                                preg_match_all('/{{\s*(\d+)\s*}}/', (string) $button['url'], $matches);

                                foreach (array_unique($matches[1]) as $variablePosition) {
                                    DB::table('whatsapp_template_variables')->insert([
                                        'whatsapp_template_component_id' => $componentId,
                                        'position' => (int) $variablePosition,
                                        'example' => is_array($example) ? ($example[0] ?? null) : $example,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                                }
                            }
                        }
                    }

                    $variables = json_decode((string) $template->variables, true);
                    if (is_array($variables)) {
                        foreach ($variables as $variable) {
                            if (! is_array($variable)) {
                                continue;
                            }

                            $component = strtolower((string) ($variable['component'] ?? 'body'));
                            if (! isset($componentIds[$component])) {
                                continue;
                            }

                            DB::table('whatsapp_template_variables')->insert([
                                'whatsapp_template_component_id' => $componentIds[$component],
                                'position' => (int) ($variable['position'] ?? 0),
                                'example' => $variable['example'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            });

        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->string('quality_rating', 32)->nullable();
            $table->dropColumn(['body', 'header_type', 'header_text', 'footer', 'buttons', 'variables']);
        });

        Schema::table('whatsapp_template_histories', function (Blueprint $table): void {
            $table->dropColumn('details');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->text('body')->nullable();
            $table->string('header_type', 32)->nullable();
            $table->text('header_text')->nullable();
            $table->text('footer')->nullable();
            $table->json('buttons')->nullable();
            $table->json('variables')->nullable();
        });

        Schema::table('whatsapp_template_histories', function (Blueprint $table): void {
            $table->json('details')->nullable();
        });

        DB::table('whatsapp_templates')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($templates): void {
                foreach ($templates as $template) {
                    $components = DB::table('whatsapp_template_components')
                        ->where('whatsapp_template_id', $template->id)
                        ->orderBy('position')
                        ->get();
                    $componentIds = $components->pluck('id')->all();
                    $componentByType = $components->keyBy('type');
                    $buttonsComponent = $componentByType->get('buttons');
                    $buttons = $buttonsComponent === null
                        ? []
                        : DB::table('whatsapp_template_buttons')
                            ->where('whatsapp_template_component_id', $buttonsComponent->id)
                            ->orderBy('position')
                            ->get()
                            ->map(fn ($button): array => array_filter([
                                'type' => strtoupper($button->type),
                                'text' => $button->text,
                                'url' => $button->url,
                                'phone_number' => $button->phone_number,
                                'example' => $button->example,
                            ], fn (mixed $value): bool => $value !== null))
                            ->values()
                            ->all();
                    $variables = $componentIds === []
                        ? []
                        : DB::table('whatsapp_template_variables')
                            ->whereIn('whatsapp_template_component_id', $componentIds)
                            ->get()
                            ->map(fn ($variable): array => [
                                'position' => $variable->position,
                                'component' => $components->firstWhere('id', $variable->whatsapp_template_component_id)?->type,
                                'example' => $variable->example,
                            ])
                            ->values()
                            ->all();

                    DB::table('whatsapp_templates')->where('id', $template->id)->update([
                        'body' => $componentByType->get('body')?->text ?? '',
                        'header_type' => $componentByType->get('header')?->format === null
                            ? null
                            : strtoupper($componentByType->get('header')->format),
                        'header_text' => $componentByType->get('header')?->text,
                        'footer' => $componentByType->get('footer')?->text,
                        'buttons' => json_encode($buttons, JSON_THROW_ON_ERROR),
                        'variables' => json_encode($variables, JSON_THROW_ON_ERROR),
                    ]);
                }
            });

        Schema::table('whatsapp_templates', function (Blueprint $table): void {
            $table->dropColumn('quality_rating');
        });

        Schema::dropIfExists('whatsapp_template_buttons');
        Schema::dropIfExists('whatsapp_template_variables');
        Schema::dropIfExists('whatsapp_template_components');
    }
};
