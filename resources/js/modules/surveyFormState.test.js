import { describe, it, expect } from 'vitest';
import {
  createState,
  createQuestion,
  addQuestion,
  removeQuestion,
  changeType,
  addOption,
  removeOption,
  shouldShowOptions,
  validateSurveyState,
} from './surveyFormState.js';

describe('surveyFormState', () => {
  // VT-001-dyn: 設問追加 — 設問が 2 問になり sort_order が 1・2 の連番になる
  it('addQuestion で設問が 2 問になり sort_order が連番になる', () => {
    const state = createState();
    expect(state.questions).toHaveLength(1);

    const next = addQuestion(state);

    expect(next.questions).toHaveLength(2);
    expect(next.questions.map((q) => q.sort_order)).toEqual([1, 2]);
  });

  // VT-002-dyn: 設問削除 — 3 問から 2 問目を削除すると残りの sort_order が 1・2 に振り直される
  it('removeQuestion で設問が減り sort_order が振り直される', () => {
    let state = createState();
    state = addQuestion(state);
    state = addQuestion(state);
    state.questions[0].body = 'Q1';
    state.questions[1].body = 'Q2';
    state.questions[2].body = 'Q3';

    const next = removeQuestion(state, 1);

    expect(next.questions).toHaveLength(2);
    expect(next.questions.map((q) => q.body)).toEqual(['Q1', 'Q3']);
    expect(next.questions.map((q) => q.sort_order)).toEqual([1, 2]);
  });

  // VT-003-dyn: 形式変更(選択式→自由記述) — 選択肢欄の表示判定が false になる
  it('type を text に変更すると選択肢欄の表示判定が false になる', () => {
    let state = createState();
    state = addOption(state, 0); // 選択肢 3 個の単一選択

    const next = changeType(state, 0, 'text');

    expect(shouldShowOptions(next.questions[0])).toBe(false);
  });

  // VT-004-dyn: 形式変更(自由記述→複数選択) — 表示判定が true になり選択肢が初期 2 個で用意される
  it('text から multiple に戻すと選択肢が初期 2 個で用意される', () => {
    const question = { ...createQuestion(1), type: 'text', options: [] };
    const state = createState([question]);

    const next = changeType(state, 0, 'multiple');

    expect(shouldShowOptions(next.questions[0])).toBe(true);
    expect(next.questions[0].options).toHaveLength(2);
  });

  // VT-005-dyn: 選択肢追加・削除 — 2 個→addOption 2 回→removeOption 1 回で 3 個・sort_order 連番
  it('addOption / removeOption で選択肢数と sort_order が正しく更新される', () => {
    let state = createState();
    expect(state.questions[0].options).toHaveLength(2);

    state = addOption(state, 0);
    state = addOption(state, 0);
    state = removeOption(state, 0, 1);

    expect(state.questions[0].options).toHaveLength(3);
    expect(state.questions[0].options.map((o) => o.sort_order)).toEqual([1, 2, 3]);
  });

  // VT-010-dyn: 選択肢1個 — errors に選択肢数エラーが1件含まれる
  it('選択肢が1個のとき validateSurveyState がエラーを返す', () => {
    let state = createState();
    state = removeOption(state, 0, 1);

    expect(validateSurveyState(state)).toEqual(['選択肢は 2 個以上 10 個以内で入力してください']);
  });

  // VT-011-dyn: 選択肢11個 — errors に選択肢数エラーが1件含まれる
  it('選択肢が11個のとき validateSurveyState がエラーを返す', () => {
    let state = createState();
    for (let i = 0; i < 9; i += 1) {
      state = addOption(state, 0);
    }

    expect(validateSurveyState(state).length).toBe(1);
    expect(validateSurveyState(state)[0]).toBe('選択肢は 2 個以上 10 個以内で入力してください');
  });

  // VT-012-dyn: 選択肢2個境界 — errors が0件
  it('選択肢が2個のとき validateSurveyState はエラーなし', () => {
    let state = createState();
    state = removeOption(state, 0, 1);
    state = addOption(state, 0);

    expect(validateSurveyState(state)).toEqual([]);
  });

  // VT-013-dyn: 選択肢10個境界 — errors が0件
  it('選択肢が10個のとき validateSurveyState はエラーなし', () => {
    let state = createState();
    for (let i = 0; i < 8; i += 1) {
      state = addOption(state, 0);
    }

    expect(validateSurveyState(state)).toEqual([]);
  });

  // VT-014-dyn: 設問51個 — errors に設問数エラーが1件含まれる
  it('設問が51問のとき validateSurveyState がエラーを返す', () => {
    let state = createState();
    for (let i = 0; i < 50; i += 1) {
      state = addQuestion(state);
    }

    expect(validateSurveyState(state)).toEqual(['設問は 50 問以内にしてください']);
  });

  // VT-015-dyn: 設問50個境界 — errors が0件
  it('設問が50問のとき validateSurveyState はエラーなし', () => {
    let state = createState();
    for (let i = 0; i < 49; i += 1) {
      state = addQuestion(state);
    }

    expect(validateSurveyState(state)).toEqual([]);
  });
});
