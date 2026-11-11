<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTodayTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_non_numeric_room_id_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/rooms/foo/bookings/today')
            ->assertNotFound();
    }

    public function test_decimal_room_id_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/rooms/1.5/bookings/today')
            ->assertNotFound();
    }
}
