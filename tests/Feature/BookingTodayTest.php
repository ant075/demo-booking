<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTodayTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/rooms/1/bookings/today')
            ->assertUnauthorized();
    }

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

    public function test_returns_only_todays_bookings_for_the_room_with_author_name(): void
    {
        $today = Carbon::today();
        $this->travelTo($today->copy()->setTime(8, 0));
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();
        $author = User::factory()->create(['name' => 'Иван Петров']);

        $startsAt = $today->copy()->setTime(10, 0);
        $endsAt = $today->copy()->setTime(11, 0);

        Booking::factory()->for($room)->for($author)->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // Бронь в другой комнате сегодня — не должна попасть в выдачу.
        Booking::factory()->create([
            'starts_at' => $today->copy()->setTime(12, 0),
            'ends_at' => $today->copy()->setTime(13, 0),
        ]);

        // Бронь этой же комнаты, но в другой день — не должна попасть в выдачу.
        Booking::factory()->for($room)->create([
            'starts_at' => $today->copy()->addDay()->setTime(10, 0),
            'ends_at' => $today->copy()->addDay()->setTime(11, 0),
        ]);

        $this->getJson("/api/rooms/{$room->id}/bookings/today")
            ->assertOk()
            ->assertExactJson([
                [
                    'time' => $startsAt->toDateTimeString().' – '.$endsAt->toDateTimeString(),
                    'author' => 'Иван Петров',
                ],
            ]);
    }

    public function test_returns_empty_list_when_room_has_no_bookings_today(): void
    {
        $this->travelTo(Carbon::today()->setTime(8, 0));
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $this->getJson("/api/rooms/{$room->id}/bookings/today")
            ->assertOk()
            ->assertExactJson([]);
    }
}
