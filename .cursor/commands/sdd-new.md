# SDD 新機能の開始

仕様駆動開発（SDD）で新機能を開始する。`.cursor/skills/sdd-feature/SKILL.md` と
`.cursor/rules/sdd-workflow.mdc` に従うこと。

このコマンドの後に指定された引数を以下として解釈する:

- 第 1 引数: slug（英小文字・ハイフン。例: `csv-import`）
- 残り: 機能の日本語名や仕様の説明（任意）

slug が指定されていない場合は、機能内容を確認してから slug を提案し、合意を得てから進める。

## 手順

1. `docs/specs/_templates/` を `docs/specs/<slug>/` にコピーする
2. `meta.yaml` の `display_name`・`slug`・`created`・`updated` を設定する
3. `docs/specs/_registry.md` に行を追加する（`feature_id` は TBD）
4. フェーズ 1（仕様整理）を開始する:
   - 提供された仕様（チャット・Markdown）を読み、`01-requirements.md` を作成する
   - `effort-report.md` の「§1 人手想定工数」に、人手作業した場合のフェーズ別見積を根拠つきで記入する
   - 仕様に書かれていないことは**推測で埋めず**、`open-questions.md` に質問として列挙する
   - どうしても仮置きが必要な場合は本文に**【仮定】**と明記する
5. `open-questions.md` の質問をユーザーに提示して**停止**する

## 禁止事項

- フェーズ 1 の承認前に `02-design.md` 以降のファイルを作成しない
- `app/`・`resources/`・`routes/`・`database/` 等の実装コードを変更しない
