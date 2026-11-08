<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    const string DATE_TIME_VALIDATION_RULES = 'required|date_format:' . DateTimeInterface::ATOM;

    public function index(Request $request)
    {
        return Booking::where('user_id', $request->user()->id)
            ->orderByRaw('starts_at DESC')
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|numeric|integer|exists:rooms,id',
            'starts_at' => self::DATE_TIME_VALIDATION_RULES,
            'ends_at' => self::DATE_TIME_VALIDATION_RULES,
            'title' => 'required|string|max:255',
        ]);

        $start_utc = Carbon::parse($validated['starts_at'])->tz('UTC');
        $end_utc = Carbon::parse($validated['ends_at'])->tz('UTC');

        $booking_data = array_merge($validated, [
            'user_id' => $request->user()->id,
            'starts_at' => $start_utc->toDateTimeString(),
            'ends_at' => $end_utc->toDateTimeString(),
        ]);

        $conflict = DB::select("SELECT id FROM bookings
            WHERE room_id = {$booking_data['room_id']}
            AND starts_at <= '{$booking_data['ends_at']}'
            AND ends_at >= '{$booking_data['starts_at']}'");

        if ($conflict) {
            return response()->json(['error' => 'Комната занята'], 409);
        }

        if ($start_utc->hour < 9 || $start_utc->hour >= 20) {
            return response()->json(['error' => 'Нерабочее время'], 422);
        }

        $booking = Booking::create($booking_data);

        Log::withContext(['booking_id' => $booking->id]);
        Log::channel('bookings')->info('Booking created');

        return $booking;
    }

    public function destroy($id)
    {
        Booking::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function today(Room $room)
    {
        return $room->bookings()
            ->whereDate('starts_at', today())
            ->get()
            ->map(fn ($b) => [
                'time' => $b->starts_at . ' – ' . $b->ends_at,
                'author' => $b->user->name,
            ]);
    }
}
