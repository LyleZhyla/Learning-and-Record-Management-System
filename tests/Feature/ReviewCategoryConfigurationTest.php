<?php

namespace Tests\Feature;

use App\Models\ReviewCategory;
use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewCategoryConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_manage_a_custom_review_category(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->get('/admin/review-categories')
            ->assertOk()
            ->assertSee('Manage the choices reviewers can use')
            ->assertSee('Choose a review area')
            ->assertSee('Create review category')
            ->assertSee('Edit settings')
            ->assertSee('css/review-categories.css', false)
            ->assertSee('Registration status categories')
            ->assertSee('Registration document decision categories')
            ->assertDontSee('Configurable document decision');

        $this->actingAs($admin)->post('/admin/review-categories', [
            'scope' => 'registration_document',
            'name' => 'For Resubmission',
            'outcome' => 'correction',
            'color' => '#a64b2a',
            'sort_order' => 25,
            'is_active' => 1,
            'is_default' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $category = ReviewCategory::where('name', 'For Resubmission')->firstOrFail();
        $this->assertSame('for-resubmission', $category->slug);
        $this->assertSame('correction', $category->outcome);

        $this->actingAs($admin)->put('/admin/review-categories/'.$category->id, [
            'scope' => 'registration_document',
            'name' => 'Return for Resubmission',
            'outcome' => 'correction',
            'color' => '#b04a35',
            'sort_order' => 26,
            'is_active' => 1,
            'is_default' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Return for Resubmission', $category->fresh()->name);
    }

    public function test_updated_labels_and_automatic_defaults_are_used_without_code_changes(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $verified = ReviewCategory::forScope('registration')->where('slug', 'verified')->firstOrFail();

        $this->actingAs($admin)->put('/admin/review-categories/'.$verified->id, [
            'scope' => 'registration',
            'name' => 'Enrolled and Active',
            'outcome' => 'approved',
            'color' => '#147a59',
            'sort_order' => 30,
            'is_active' => 1,
            'is_default' => 1,
        ])->assertSessionHasNoErrors();

        $registration = new StudentRegistration(['status' => 'verified']);
        $this->assertSame('Enrolled and Active', $registration->statusLabel());
        $this->assertSame('#147a59', $registration->statusColor());
        $this->assertSame('verified', ReviewCategory::defaultSlug('registration', 'approved', 'verified'));

        $this->actingAs($admin)->delete('/admin/review-categories/'.$verified->id)
            ->assertSessionHasErrors('review_category');

        $this->actingAs($admin)->put('/admin/review-categories/'.$verified->id, [
            'scope' => 'registration',
            'name' => 'Enrolled and Active',
            'outcome' => 'approved',
            'color' => '#147a59',
            'sort_order' => 30,
            'is_active' => 0,
            'is_default' => 0,
        ])->assertSessionHasErrors('outcome');
    }

    public function test_admin_can_reset_built_in_categories_without_deleting_custom_categories(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $verified = ReviewCategory::forScope('registration')->where('slug', 'verified')->firstOrFail();
        $verified->update([
            'name' => 'Custom approved label',
            'outcome' => 'approved',
            'color' => '#111111',
            'is_active' => false,
            'is_default' => false,
            'sort_order' => 99,
        ]);
        $custom = ReviewCategory::create([
            'scope' => 'registration',
            'name' => 'Manual approval',
            'slug' => 'manual-approval',
            'outcome' => 'approved',
            'color' => '#222222',
            'is_active' => true,
            'is_default' => true,
            'is_system' => false,
            'sort_order' => 5,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.review-categories.reset-defaults'))
            ->assertRedirect()
            ->assertSessionHas('status', 'The built-in review categories were reset to their default settings. Custom categories were kept.');

        $verified->refresh();
        $this->assertSame('Approved / account created', $verified->name);
        $this->assertSame('#168865', $verified->color);
        $this->assertSame(30, $verified->sort_order);
        $this->assertTrue($verified->is_active);
        $this->assertTrue($verified->is_default);
        $this->assertDatabaseHas('review_categories', ['id' => $custom->id, 'name' => 'Manual approval']);
        $this->assertFalse($custom->fresh()->is_default);
    }
}
