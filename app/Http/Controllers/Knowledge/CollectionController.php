<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\SaveCollectionRequest;
use App\Models\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * Display the current Empresa's knowledge collections.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user?->company();

        abort_if($team === null, 404);
        Gate::authorize('viewAny', Collection::class);

        return Inertia::render('knowledge/collections', [
            'collections' => $team->collections()
                ->withCount('documents')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (Collection $collection): array => [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'description' => $collection->description,
                    'isDefault' => $collection->is_default,
                    'documentsCount' => $collection->documents_count,
                ])
                ->values(),
            'permissions' => $user->toTeamPermissions($team),
        ]);
    }

    /**
     * Store a new knowledge collection.
     */
    public function store(SaveCollectionRequest $request): RedirectResponse
    {
        $team = $request->user()->company();

        abort_if($team === null, 404);

        $team->collections()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Coleção criada com sucesso.',
        ]);

        return to_route('collections.index');
    }

    /**
     * Update a knowledge collection.
     */
    public function update(SaveCollectionRequest $request, Collection $collection): RedirectResponse
    {
        $collection->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Coleção atualizada com sucesso.',
        ]);

        return to_route('collections.index');
    }

    /**
     * Delete a knowledge collection.
     */
    public function destroy(Collection $collection): RedirectResponse
    {
        Gate::authorize('delete', $collection);

        $collection->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Coleção excluída com sucesso.',
        ]);

        return to_route('collections.index');
    }
}
