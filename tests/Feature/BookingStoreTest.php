<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Office;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingStoreTest extends TestCase
{
    use RefreshDatabase;

    private function bookingPayload(int $roomId, Carbon $startsAt, Carbon $endsAt, array $overrides = []): array
    {
        return array_merge([
            'room_id' => $roomId,
            'starts_at' => $startsAt->toAtomString(),
            'ends_at' => $endsAt->toAtomString(),
            'title' => 'Планирование спринта',
        ], $overrides);
    }

    public function test_uses_authenticated_user_id_and_ignores_submitted_user_id(): void
    {
        $actingUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $room = Room::factory()->create();

        Sanctum::actingAs($actingUser);

        $startsAt = Carbon::create(2026, 11, 10, 12, 0, 0, 'UTC');
        $endsAt = $startsAt->copy()->addHour();

        $response = $this->postJson('/api/bookings', $this->bookingPayload(
            $room->id, $startsAt, $endsAt, ['user_id' => $otherUser->id]
        ));

        $response->assertCreated();

        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'user_id' => $actingUser->id,
        ]);
        $this->assertDatabaseMissing('bookings', [
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_stores_submitted_times_converted_to_utc(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        // 12:00 по Москве (+03:00) = 09:00 UTC — попадает в рабочие часы по UTC.
        $startsAt = Carbon::create(2026, 11, 10, 12, 0, 0, 'Europe/Moscow');
        $endsAt = $startsAt->copy()->addHour();

        $this->postJson('/api/bookings', $this->bookingPayload($room->id, $startsAt, $endsAt))
            ->assertCreated();

        $stored = DB::table('bookings')->first();

        $this->assertSame('2026-11-10 09:00:00', $stored->starts_at);
        $this->assertSame('2026-11-10 10:00:00', $stored->ends_at);
    }

    public function test_rejects_request_missing_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['room_id', 'starts_at', 'ends_at', 'title']);
    }

    public function test_rejects_room_id_that_does_not_exist(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $startsAt = Carbon::create(2026, 11, 10, 12, 0, 0, 'UTC');

        $this->postJson('/api/bookings', $this->bookingPayload(999999, $startsAt, $startsAt->copy()->addHour()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);
    }

    public function test_rejects_dates_not_matching_atom_format(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2026-11-10 12:00:00',
            'ends_at' => '2026-11-10 13:00:00',
            'title' => 'Встреча',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['starts_at', 'ends_at']);
    }

    public function test_rejects_overlapping_booking_for_the_same_room(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'starts_at' => Carbon::create(2026, 11, 10, 10, 0, 0, 'UTC'),
            'ends_at' => Carbon::create(2026, 11, 10, 11, 0, 0, 'UTC'),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $startsAt = Carbon::create(2026, 11, 10, 10, 30, 0, 'UTC');
        $endsAt = $startsAt->copy()->addHour();

        $this->postJson('/api/bookings', $this->bookingPayload($room->id, $startsAt, $endsAt))
            ->assertStatus(409)
            ->assertJson(['error' => 'Комната занята']);
    }

    public function test_allows_overlapping_time_slot_in_a_different_room(): void
    {
        $busyRoom = Room::factory()->create();
        $freeRoom = Room::factory()->create();

        Booking::factory()->for($busyRoom)->create([
            'starts_at' => Carbon::create(2026, 11, 10, 10, 0, 0, 'UTC'),
            'ends_at' => Carbon::create(2026, 11, 10, 11, 0, 0, 'UTC'),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $startsAt = Carbon::create(2026, 11, 10, 10, 0, 0, 'UTC');
        $endsAt = $startsAt->copy()->addHour();

        $this->postJson('/api/bookings', $this->bookingPayload($freeRoom->id, $startsAt, $endsAt))
            ->assertCreated();
    }

    public function test_rejects_booking_when_ends_at_equals_starts_at(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $startsAt = Carbon::create(2026, 11, 10, 10, 0, 0, 'UTC');
        $endsAt = $startsAt->copy();

        $this->postJson('/api/bookings', $this->bookingPayload($room->id, $startsAt, $endsAt))
            ->assertStatus(422)
            ->assertJson(['error' => 'Booking ends_at must be greater than its starts_at']);
    }

    public function test_rejects_booking_when_ends_at_is_before_starts_at(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $startsAt = Carbon::create(2026, 11, 10, 10, 0, 0, 'UTC');
        $endsAt = $startsAt->copy()->subHour();

        $this->postJson('/api/bookings', $this->bookingPayload($room->id, $startsAt, $endsAt))
            ->assertStatus(422)
            ->assertJson(['error' => 'Booking ends_at must be greater than its starts_at']);
    }

    public function test_accepts_booking_within_office_local_hours_even_outside_utc_window(): void
    {
        Sanctum::actingAs(User::factory()->create());
        // Комната в офисе Europe/Moscow (+03:00): локально 09:00–10:00 — это 06:00–07:00 UTC,
        // то есть вне рабочего окна по UTC, но внутри по времени офиса.
        $room = Room::factory()->create();

        $startsAt = Carbon::create(2026, 11, 10, 9, 0, 0, 'Europe/Moscow');
        $endsAt = $startsAt->copy()->addHour();

        $this->postJson('/api/bookings', $this->bookingPayload($room->id, $startsAt, $endsAt))
            ->assertCreated();
    }

    public function test_rejects_booking_outside_office_local_hours_even_within_utc_window(): void
    {
        Sanctum::actingAs(User::factory()->create());
        // Офис Asia/Novosibirsk (+07:00): 13:00 UTC — это 20:00 локально (уже нерабочее время),
        // хотя 13:00 само по себе укладывалось бы в 9–20 по UTC.
        $room = Room::factory()->for(Office::factory()->novosibirsk())->create();

        $startsAt = Carbon::create(2026, 11, 10, 13, 0, 0, 'UTC');
        $endsAt = $startsAt->copy()->addHour();

        $this->postJson('/api/bookings', $this->bookingPayload($room->id, $startsAt, $endsAt))
            ->assertStatus(422)
            ->assertJson(['error' => 'Нерабочее время']);
    }

    public function test_allows_booking_ending_exactly_at_office_local_20_00(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $startsAt = Carbon::create(2026, 11, 10, 19, 0, 0, 'Europe/Moscow');
        $endsAt = Carbon::create(2026, 11, 10, 20, 0, 0, 'Europe/Moscow');

        $this->postJson('/api/bookings', $this->bookingPayload($room->id, $startsAt, $endsAt))
            ->assertCreated();
    }

    public function test_rejects_booking_ending_after_office_local_20_00_with_nonzero_minutes(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $startsAt = Carbon::create(2026, 11, 10, 19, 0, 0, 'Europe/Moscow');
        $endsAt = Carbon::create(2026, 11, 10, 20, 30, 0, 'Europe/Moscow');

        $this->postJson('/api/bookings', $this->bookingPayload($room->id, $startsAt, $endsAt))
            ->assertStatus(422)
            ->assertJson(['error' => 'Нерабочее время']);
    }
}
