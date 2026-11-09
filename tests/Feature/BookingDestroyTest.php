<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_their_own_booking(): void
    {
        $owner = User::factory()->create();
        $booking = Booking::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_another_user_cannot_delete_someone_elses_booking(): void
    {
        $owner = User::factory()->create();
        $booking = Booking::factory()->for($owner)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_deleting_nonexistent_booking_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/bookings/999999')
            ->assertNotFound();
    }

    public function test_non_numeric_id_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/bookings/foo')
            ->assertNotFound();
    }

    public function test_decimal_id_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/bookings/1.5')
            ->assertNotFound();
    }

    public function test_requires_authentication(): void
    {
        $booking = Booking::factory()->create();

        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertUnauthorized();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }
}
