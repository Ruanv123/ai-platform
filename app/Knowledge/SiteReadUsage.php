<?php

namespace App\Knowledge;

use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\Data\Usage;

class SiteReadUsage
{
    public function recordFirecrawlCredit(int $siteReadId): void
    {
        DB::table('site_reads')
            ->where('id', $siteReadId)
            ->update([
                'firecrawl_credits_used' => DB::raw('COALESCE(firecrawl_credits_used, 0) + 1'),
            ]);
    }

    public function recordAiUsage(int $siteReadId, Usage $usage): void
    {
        DB::table('site_reads')
            ->where('id', $siteReadId)
            ->update([
                'ai_input_tokens' => DB::raw('COALESCE(ai_input_tokens, 0) + '.max(0, $usage->promptTokens)),
                'ai_output_tokens' => DB::raw('COALESCE(ai_output_tokens, 0) + '.max(0, $usage->completionTokens)),
            ]);
    }
}
