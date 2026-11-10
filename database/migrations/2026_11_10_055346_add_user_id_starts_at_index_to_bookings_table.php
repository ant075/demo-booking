<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Фильтр по user_id + сортировка по starts_at desc в index() — Blueprint не умеет
        // задавать направление сортировки по столбцу индекса, поэтому создаём сырым SQL.
        DB::statement('create index bookings_user_id_starts_at_index on bookings (user_id, starts_at desc)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index bookings_user_id_starts_at_index');
    }
};
