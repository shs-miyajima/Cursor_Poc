// アンケート作成フォームの状態管理（純ロジック。DOM 操作は surveyForm.js が担当）

export function createOption(sortOrder, label = '') {
  return { sort_order: sortOrder, label };
}

export function createQuestion(sortOrder) {
  return {
    sort_order: sortOrder,
    body: '',
    type: 'single',
    is_required: false,
    options: [createOption(1), createOption(2)],
  };
}

export function createState(questions = null) {
  return { questions: questions ?? [createQuestion(1)] };
}

function renumber(items) {
  return items.map((item, i) => ({ ...item, sort_order: i + 1 }));
}

export function addQuestion(state) {
  return { questions: renumber([...state.questions, createQuestion(state.questions.length + 1)]) };
}

export function removeQuestion(state, index) {
  return { questions: renumber(state.questions.filter((_, i) => i !== index)) };
}

export function changeType(state, index, type) {
  const questions = state.questions.map((q, i) => {
    if (i !== index) return q;
    const next = { ...q, type };
    // 選択式に戻したとき選択肢が足りなければ初期 2 個を用意する
    if (type !== 'text' && next.options.length < 2) {
      next.options = [createOption(1), createOption(2)];
    }
    return next;
  });
  return { questions };
}

export function addOption(state, questionIndex) {
  const questions = state.questions.map((q, i) => {
    if (i !== questionIndex) return q;
    return { ...q, options: renumber([...q.options, createOption(q.options.length + 1)]) };
  });
  return { questions };
}

export function removeOption(state, questionIndex, optionIndex) {
  const questions = state.questions.map((q, i) => {
    if (i !== questionIndex) return q;
    return { ...q, options: renumber(q.options.filter((_, j) => j !== optionIndex)) };
  });
  return { questions };
}

// 自由記述は選択肢欄を表示しない（S-07）
export function shouldShowOptions(question) {
  return question.type !== 'text';
}
