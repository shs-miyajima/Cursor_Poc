<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained();
            $table->string('role', 20)->default('user');
            $table->foreignId('department_id')->nullable()->constrained();
            $table->string('gender', 20)->default('no_answer');
            $table->date('birth_date')->nullable();
            $table->date('hired_month')->nullable();
            $table->softDeletes();

            // グローバル unique は企業内 unique に置き換えるため削除
            $table->dropUnique('users_email_unique');

            $table->index('company_id');
            $table->index('department_id');
        });

        // メールの企業内一意（論理削除済みを除く）。company_id NULL 同士（スーパーユーザー）も一意にする
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX users_company_email_unique ON users (company_id, email) NULLS NOT DISTINCT WHERE deleted_at IS NULL');
        } else {
            // sqlite（テスト環境）: NULLS NOT DISTINCT 非対応のため COALESCE で NULL を単一値に畳む
            DB::statement('CREATE UNIQUE INDEX users_company_email_unique ON users (COALESCE(company_id, 0), email) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_company_email_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
            $table->dropIndex(['company_id']);
            $table->dropIndex(['department_id']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['role', 'gender', 'birth_date', 'hired_month', 'deleted_at']);
        });
    }
};
