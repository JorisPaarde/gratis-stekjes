<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verbreedt kolommen voor Laravel encrypted casts en versleutelt bestaande plaintext.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // SQLite negeert VARCHAR-lengte; MySQL heeft ruimte nodig voor ciphertext.
        if ($driver === 'mysql') {
            if (Schema::hasColumn('users', 'telefoon')) {
                DB::statement('ALTER TABLE users MODIFY telefoon TEXT NULL');
            }
            if (Schema::hasColumn('deelplekken', 'contact_email')) {
                DB::statement('ALTER TABLE deelplekken MODIFY contact_email TEXT NULL');
            }
        }

        $this->encryptColumn('users', 'telefoon');
        $this->encryptColumn('deelplekken', 'contact_email');
        $this->encryptColumn('messages', 'body');
        $this->encryptColumn('reports', 'reason');
    }

    public function down(): void
    {
        // Ciphertext terugzetten naar plaintext is bewust niet geautomatiseerd.
    }

    private function encryptColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $column): void {
            foreach ($rows as $row) {
                $value = $row->{$column};
                if ($value === null) {
                    continue;
                }
                $value = (string) $value;
                if ($this->looksEncrypted($value)) {
                    continue;
                }
                DB::table($table)->where('id', $row->id)->update([
                    $column => Crypt::encryptString($value),
                ]);
            }
        });
    }

    private function looksEncrypted(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
};
