<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_only_own_bookings(): void
    {
        $user = User::factory()->create();
        $own = Booking::factory()->for($user)->create();
        Booking::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_returns_bookings_ordered_by_starts_at_descending(): void
    {
        $user = User::factory()->create();
        $earliest = Booking::factory()->for($user)->create([
            'starts_at' => Carbon::create(2026, 11, 10, 9, 0, 0, 'UTC'),
            'ends_at' => Carbon::create(2026, 11, 10, 10, 0, 0, 'UTC'),
        ]);
        $latest = Booking::factory()->for($user)->create([
            'starts_at' => Carbon::create(2026, 11, 12, 9, 0, 0, 'UTC'),
            'ends_at' => Carbon::create(2026, 11, 12, 10, 0, 0, 'UTC'),
        ]);
        $middle = Booking::factory()->for($user)->create([
            'starts_at' => Carbon::create(2026, 11, 11, 9, 0, 0, 'UTC'),
            'ends_at' => Carbon::create(2026, 11, 11, 10, 0, 0, 'UTC'),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $earliest->id);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/bookings')->assertUnauthorized();
    }
}
