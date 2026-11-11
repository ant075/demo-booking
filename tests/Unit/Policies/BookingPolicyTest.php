<?php

namespace Tests\Unit\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use Tests\TestCase;

class BookingPolicyTest extends TestCase
{
    public function test_allows_owner_to_delete_their_booking(): void
    {
        $owner = new User;
        $owner->id = 1;

        $booking = new Booking;
        $booking->user_id = $owner->id;

        $this->assertTrue((new BookingPolicy)->delete($owner, $booking));
    }

    public function test_forbids_deleting_another_users_booking(): void
    {
        $owner = new User;
        $owner->id = 1;

        $otherUser = new User;
        $otherUser->id = 2;

        $booking = new Booking;
        $booking->user_id = $owner->id;

        $this->assertFalse((new BookingPolicy)->delete($otherUser, $booking));
    }
}
