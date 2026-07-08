<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->timestamps();
            $table->softDeletes();
        });

        // 論理削除済みを除いた一意制約（部分ユニークインデックス）
        DB::statement('CREATE UNIQUE INDEX companies_name_unique ON companies (name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX companies_code_unique ON companies (code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
