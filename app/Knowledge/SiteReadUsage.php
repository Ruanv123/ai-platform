<?php

namespace App\Knowledge;

use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\Data\Usage;

class SiteReadUsage
{
    public function recordFirecrawlCredits(int $siteReadId, int $credits): void
    {
        DB::table('site_reads')
            ->where('id', $siteReadId)
            ->update([
                'firecrawl_credits_used' => DB::raw('CASE WHEN firecrawl_credits_used IS NULL THEN NULL ELSE firecrawl_credits_used + '.max(0, $credits).' END'),
            ]);
    }

    public function markFirecrawlCreditsUnknown(int $siteReadId): void
    {
        DB::table('site_reads')
            ->where('id', $siteReadId)
            ->update(['firecrawl_credits_used' => null]);
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
