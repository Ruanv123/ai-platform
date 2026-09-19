<?php

namespace App\Ai\Tools;

use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchKnowledgeBase implements Tool
{
    /**
     * Create a scoped knowledge-base search tool.
     *
     * @param  array<int, string>  $collectionIds
     */
    public function __construct(
        private Team $team,
        private array $collectionIds,
        private KnowledgeBaseSearch $knowledgeBaseSearch,
    ) {}

    /**
     * Give the model a precise description of when to use the tool.
     */
    public function description(): Stringable|string
    {
        return 'Search the selected knowledge-base Collections for factual information about the Empresa, including products, prices, policies, and procedures.';
    }

    /**
     * Execute a search in only the Collections selected for the Agent.
     */
    public function handle(Request $request): Stringable|string
    {
        $results = $this->knowledgeBaseSearch->search(
            $this->team,
            $this->collectionIds,
            $request->string('query')->toString(),
        );

        return json_encode([
            'consulted' => true,
            'results' => $results->all(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Give the model a required natural-language search query.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The factual question to look up in the knowledge base.')
                ->required(),
        ];
    }

    /**
     * Keep the tool name stable for stream consumers and provider calls.
     */
    public function name(): string
    {
        return 'search_knowledge_base';
    }
}
