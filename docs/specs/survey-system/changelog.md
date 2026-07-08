# 変更履歴

<!-- 差戻し・承認・仕様変更の記録。reopened（差分承認で draft に戻した）時は変更理由も書く -->

## 2026-07-08

- フェーズ: 実装・テスト
- 操作: completed
- 内容: 全機能実装（migration 8・Enum 4・Model 8・Service 7 + DTO 4・Controller 11・
  Form Request 11・Blade 17・JS 4）。PHPUnit 43 件・Vitest 9 件は全件成功。
  Playwright E2E 192 件はテストコード実装まで完了（環境制約により未実行。
  04-completion-report.md §4 に理由と今後の対応を明記）。
  Test ID 突合: ERROR 0 / WARN 0（244/244 件一致）

## 2026-07-08

- フェーズ: テスト設計
- 操作: approved
- 内容: 03-test-plan.md + CSV 3 種（E2E 192 件・PHPUnit 43 件・Vitest 9 件）。
  機械的 lint（ERROR 0 / WARN 0）と独立レビュー（指摘 9 件: 機械修正 7 件反映、
  要判断 2 件は対応案を承認確認に明記）を経て承認。要判断 2 件の確定方針:
  (1) 締切自動終了 E2E は締切=現在+1 分・表示変化ポーリング（上限 90 秒）方式
  (2) グラフ描画検証は canvas の検証用データ属性 + 回答件数 DOM + Vitest で担保
  （実装時に canvas へ検証用属性を付与。画面仕様への影響なしのため 02-design 差分承認は不要）

## 2026-07-08

- フェーズ: 設計
- 操作: approved
- 内容: 02-design.md（Controller 11・Service 7・Model/Enum 12・Form Request 11・migration 8 + seeder・
  Blade 17・JS 4 + Chart.js 導入・インデックス設計一覧）。独立レビュー（指摘 7 件自動修正）と
  差戻し 1 回（gender の key 保存・インデックス設計）を反映して承認

## 2026-07-08

- フェーズ: 設計
- 操作: rejected
- 理由: (1) gender カラムの DB 保存値が日本語文字列だった → key（英語コード）を保存する方式に変更すること
  (2) DB 設計にインデックス方針の整理が不足 → インデックスを張るテーブル／カラムを明記すること
- 対応: 02-design.md §2.3（Gender enum）・§2.6（migration・インデックス一覧）を修正して再提示

## 2026-07-08

- フェーズ: 仕様整理
- 操作: approved
- 内容: 01-requirements.md 確定版（画面 16 面、UC 31 件、VAL 30 件、AC 33 件、NFR 9 件）。
  open-questions.md の 5 回・計 45 問の回答で仮定をすべて解消
  （部署マスタ・企業コードログイン・CSV 新規/更新確認画面などを追加）
