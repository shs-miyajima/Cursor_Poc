# 変更履歴

<!-- 差戻し・承認・仕様変更の記録。reopened（差分承認で draft に戻した）時は変更理由も書く -->

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
