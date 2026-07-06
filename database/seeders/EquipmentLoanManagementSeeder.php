<?php

namespace Database\Seeders;

use App\Enums\EquipmentLoanStatus;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\EquipmentLoanRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EquipmentLoanManagementSeeder extends Seeder
{
    public function run(): void
    {
        // 最初の一般社員（山田 太郎）が擬似ログイン初期ユーザーになるよう投入順を固定する
        $yamada = User::query()->firstOrCreate(
            ['email' => 'yamada@example.com'],
            [
                'name' => '山田 太郎',
                'password' => 'dummy-password',
                'department' => '開発部',
                'role' => UserRole::Staff,
            ],
        );

        $sato = User::query()->firstOrCreate(
            ['email' => 'sato@example.com'],
            [
                'name' => '佐藤 花子',
                'password' => 'dummy-password',
                'department' => '総務部',
                'role' => UserRole::Staff,
            ],
        );

        $kanri = User::query()->firstOrCreate(
            ['email' => 'kanri@example.com'],
            [
                'name' => '管理 太郎',
                'password' => 'dummy-password',
                'department' => '総務部',
                'role' => UserRole::Admin,
            ],
        );

        $macbook = Equipment::query()->firstOrCreate(
            ['name' => 'MacBook Pro M3'],
            ['stock_count' => 2, 'target_department' => '開発部'],
        );

        $iphone = Equipment::query()->firstOrCreate(
            ['name' => 'iPhone 15 Pro'],
            ['stock_count' => 1, 'target_department' => null],
        );

        $windowsPc = Equipment::query()->firstOrCreate(
            ['name' => '検証用 Windows PC'],
            ['stock_count' => 1, 'target_department' => '総務部'],
        );

        if (EquipmentLoanRequest::query()->exists()) {
            return;
        }

        // 正常表示用: 山田 太郎の申請中
        EquipmentLoanRequest::create([
            'user_id' => $yamada->id,
            'equipment_id' => $macbook->id,
            'status' => EquipmentLoanStatus::Pending,
            'requested_from' => Carbon::today()->addDays(14)->toDateString(),
            'requested_to' => Carbon::today()->addDays(16)->toDateString(),
            'reason' => '検証作業で使用するため',
        ]);

        // 返却期限超過表示用: 山田 太郎の貸出中（期限切れ）
        EquipmentLoanRequest::create([
            'user_id' => $yamada->id,
            'equipment_id' => $iphone->id,
            'status' => EquipmentLoanStatus::Approved,
            'requested_from' => Carbon::today()->subDays(10)->toDateString(),
            'requested_to' => Carbon::today()->subDays(3)->toDateString(),
            'reason' => '外出先での動作確認のため',
        ]);

        // 返却済表示用: 佐藤 花子の返却済
        EquipmentLoanRequest::create([
            'user_id' => $sato->id,
            'equipment_id' => $windowsPc->id,
            'status' => EquipmentLoanStatus::Returned,
            'requested_from' => Carbon::today()->subDays(20)->toDateString(),
            'requested_to' => Carbon::today()->subDays(15)->toDateString(),
            'reason' => '資料作成用',
        ]);
    }
}
