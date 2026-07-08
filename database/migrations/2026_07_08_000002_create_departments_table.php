<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name', 100);
            $table->timestamps();
            $table->softDeletes();
        });

        // 企業内一意（論理削除済みを除く）。先頭カラムが company_id のため自社部署検索も兼ねる
        DB::statement('CREATE UNIQUE INDEX departments_company_name_unique ON departments (company_id, name) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
