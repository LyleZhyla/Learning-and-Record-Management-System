<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentSubmission;
use App\Models\ReviewCategory;
use App\Models\StudentRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $prefix = $this->prefix($request);

        return view('admin.review-categories.index', [
            'layout' => $prefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin',
            'routePrefix' => $prefix,
            'groups' => ReviewCategory::query()->orderBy('scope')->orderBy('sort_order')->orderBy('name')->get()->groupBy('scope'),
            'scopes' => ReviewCategory::SCOPES,
            'outcomes' => ReviewCategory::OUTCOMES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = ReviewCategory::uniqueSlug($validated['scope'], $validated['name']);
        $validated['is_system'] = false;
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        DB::transaction(function () use ($validated): void {
            if ($validated['is_default']) {
                ReviewCategory::forScope($validated['scope'])->where('outcome', $validated['outcome'])->update(['is_default' => false]);
            }
            ReviewCategory::create($validated);
        });

        return back()->with('status', 'The review category was created and is ready to use.');
    }

    public function update(Request $request, ReviewCategory $reviewCategory): RedirectResponse
    {
        $validated = $this->validated($request, $reviewCategory);
        $validated['updated_by'] = $request->user()->id;

        DB::transaction(function () use ($validated, $reviewCategory): void {
            if ($validated['is_default']) {
                ReviewCategory::forScope($reviewCategory->scope)->where('outcome', $validated['outcome'])
                    ->where('id', '!=', $reviewCategory->id)->update(['is_default' => false]);
            }
            $reviewCategory->update($validated);
        });

        return back()->with('status', 'The review category was updated.');
    }

    public function destroy(Request $request, ReviewCategory $reviewCategory): RedirectResponse
    {
        if ($reviewCategory->is_system || $reviewCategory->is_default || $this->isInUse($reviewCategory)) {
            throw ValidationException::withMessages([
                'review_category' => 'This category is required by the workflow or existing records. Deactivate it instead.',
            ]);
        }

        $reviewCategory->delete();

        return back()->with('status', 'The unused review category was deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ReviewCategory $category = null): array
    {
        $request->merge([
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
        ]);
        $validated = $request->validate([
            'scope' => ['required', Rule::in(array_keys(ReviewCategory::SCOPES))],
            'name' => ['required', 'string', 'max:100'],
            'outcome' => ['required', Rule::in(array_keys(ReviewCategory::OUTCOMES))],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
        ]);

        if ($category && $validated['scope'] !== $category->scope) {
            throw ValidationException::withMessages(['scope' => 'The scope of an existing category cannot be changed.']);
        }
        if ($validated['is_default'] && ! $validated['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'An automatic default category must remain active.']);
        }
        if ($category
            && ($validated['outcome'] !== $category->outcome || ! $validated['is_active'])
            && ! ReviewCategory::forScope($category->scope)->where('outcome', $category->outcome)
                ->where('is_active', true)->where('id', '!=', $category->id)->exists()) {
            throw ValidationException::withMessages([
                'outcome' => 'Create and activate another category for this workflow outcome before removing its last active category.',
            ]);
        }

        return $validated;
    }

    private function isInUse(ReviewCategory $category): bool
    {
        return match ($category->scope) {
            'registration' => StudentRegistration::where('status', $category->slug)->exists(),
            'registration_document' => StudentRegistration::where('cor_review_status', $category->slug)
                ->orWhere('formal_photo_review_status', $category->slug)->exists(),
            'document_submission' => DocumentSubmission::where('status', $category->slug)->exists(),
            default => false,
        };
    }

    private function prefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }
}
