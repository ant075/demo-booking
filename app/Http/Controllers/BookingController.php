<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    const string DATE_TIME_VALIDATION_RULES = 'required|date_format:'.DateTimeInterface::ATOM;

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
        if ($end_utc->lessThanOrEqualTo($start_utc)) {
            return response()->json(['error' => 'Время окончания брони должно быть больше времени начала'], 422);
        }
        $delta_minutes = $start_utc->diffInMinutes($end_utc);
        if ($delta_minutes > 240) {
            return response()->json(['error' => 'Срок брони не должен превышать 4 часа'], 422);
        }
        if ($delta_minutes % 15 !== 0) {
            return response()->json(['error' => 'Срок брони должен быть кратным 15 минутам'], 422);
        }
        // Проверим соответствие рабочему времени офиса
        $office_tz = DB::table('rooms')
            ->join('offices', 'rooms.office_id', '=', 'offices.id')
            ->where('rooms.id', '=', $validated['room_id'])
            ->value('offices.timezone');
        // Считаем строковые метки временных зон офисов заведомо валидными.
        // Т.к. их валидация уместна при сохранении записей офисов.
        $start_office = $start_utc->tz($office_tz);
        $end_office = $end_utc->tz($office_tz);
        if ($start_office->hour < 9 || $start_office->hour >= 20 ||
            $end_office->hour < 9 || $end_office->hour > 20 ||
            ($end_office->hour == 20 && $end_office->minute > 0)) {
            return response()->json(['error' => 'Нерабочее время'], 422);
        }
        // Проверяем кратность начала и окончания 15 минутам во временной зоне офиса,
        // т.к. бывают зоны, смещённые от UTC не на целый час.
        if ($start_office->secondOfMinute() !== 0 || $end_office->secondOfMinute() !== 0 ||
            $start_office->minuteOfHour() % 15 !== 0 || $end_office->minuteOfHour() % 15 !== 0) {
            return response()->json(['error' => 'Начало и окончание брони допустимы в моменты, кратные целым 15 минутам'], 422);
        }

        return DB::transaction(function () use ($validated, $booking_data, $start_utc, $end_utc) {
            // Сериализуем проверку конфликта и вставку для одной комнаты между параллельными запросами.
            // Безопасная блокировка на уровне транзакции, а не сессии. Снимается автоматически как при COMMIT, так и при ROLLBACK.
            // Конкурентный запрос не отваливается, а ждёт.
            DB::statement('SELECT pg_advisory_xact_lock(?)', [$validated['room_id']]);

            $conflict = DB::table('bookings')
                ->where('room_id', '=', $validated['room_id'])
                ->where('starts_at', '<', $booking_data['ends_at'])
                ->where('ends_at', '>', $booking_data['starts_at'])
                ->value('id');

            if ($conflict) {
                return response()->json(['error' => 'Комната занята'], 409);
            }

            $booking = Booking::create($booking_data);

            Log::withContext(['booking_id' => $booking->id]);
            Log::channel('bookings')->info('Booking created');

            return $booking;
        });
    }

    public function destroy(Booking $booking)
    {
        Gate::authorize('delete', $booking);

        $booking->delete();

        return response()->noContent();
    }

    public function today(Room $room)
    {
        return $room->bookings()
            ->whereDate('starts_at', today())
            ->get()
            ->map(fn ($b) => [
                'time' => $b->starts_at.' – '.$b->ends_at,
                'author' => $b->user->name,
            ]);
    }
}
