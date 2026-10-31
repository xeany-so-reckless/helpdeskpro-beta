<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        $categories = Category::query()
            ->withCount('tickets')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'tickets_count' => $category->tickets_count,
            ]);

        return Inertia::render('categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('categories.index');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('categories.index');
    }

    /**
     * Aktifkan atau nonaktifkan kategori. Kategori nonaktif tidak tampil di
     * form tiket baru, tetapi tiket lama tetap memakainya.
     */
    public function updateStatus(Request $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $category->update(['is_active' => $request->boolean('is_active')]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $category->is_active ? __('Category activated.') : __('Category deactivated.'),
        ]);

        return to_route('categories.index');
    }
}
