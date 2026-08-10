<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('name')
            ->get();

        return view('categories.index', ['categories' => $categories]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Category::class);

        $parentOptions = $request->user()->categories()->orderBy('name')->get();

        return view('categories.create', ['parentOptions' => $parentOptions]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $request->user()->categories()->create($request->validated());

        return redirect()->route('categories.index')->with('status', 'Category created.');
    }

    public function edit(Request $request, Category $category): View
    {
        $this->authorize('update', $category);

        $parentOptions = $request->user()->categories()
            ->where('id', '!=', $category->id)
            ->orderBy('name')
            ->get();

        return view('categories.edit', ['category' => $category, 'parentOptions' => $parentOptions]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('status', 'Category updated.');
    }

    public function deactivate(Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update(['is_active' => false]);

        return redirect()->route('categories.index')->with('status', 'Category deactivated.');
    }
}
