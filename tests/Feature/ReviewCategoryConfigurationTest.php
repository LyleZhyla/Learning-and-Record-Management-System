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
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($admin)->get('/nstp-admin/review-categories')
            ->assertOk()
            ->assertSee('Registration status categories')
            ->assertSee('Registration document decision categories')
            ->assertDontSee('Configurable document decision');

        $this->actingAs($admin)->post('/nstp-admin/review-categories', [
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

        $this->actingAs($admin)->put('/nstp-admin/review-categories/'.$category->id, [
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
}
