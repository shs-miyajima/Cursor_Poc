// アンケート作成・編集フォームの動的 UI（S-07 / S-07a）
import {
  createState,
  createQuestion,
  addQuestion,
  removeQuestion,
  changeType,
  addOption,
  removeOption,
  shouldShowOptions,
} from './modules/surveyFormState.js';

const container = document.getElementById('question-list');

if (container) {
  const initialData = document.getElementById('survey-form-data');
  let state;

  if (initialData) {
    const questions = JSON.parse(initialData.textContent);
    state = createState(
      questions.map((q, i) => ({
        sort_order: i + 1,
        body: q.body,
        type: q.type,
        is_required: q.is_required,
        options: q.options.map((label, j) => ({ sort_order: j + 1, label })),
      })),
    );
  } else {
    state = createState();
  }

  const input = (cls) =>
    `w-full rounded border border-gray-300 px-3 py-2 text-sm ${cls ?? ''}`;

  function render() {
    container.innerHTML = '';

    state.questions.forEach((q, qi) => {
      const card = document.createElement('div');
      card.className = 'rounded border border-gray-200 bg-gray-50 p-4 space-y-3';
      card.dataset.testid = `question-${qi}`;

      const header = document.createElement('div');
      header.className = 'flex items-center justify-between';
      header.innerHTML = `<span class="font-semibold text-sm">設問 ${qi + 1}</span>`;

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.textContent = '設問を削除';
      removeBtn.dataset.testid = `remove-question-${qi}`;
      removeBtn.className = 'text-sm text-red-600 hover:underline';
      removeBtn.addEventListener('click', () => {
        state = removeQuestion(state, qi);
        render();
      });
      header.appendChild(removeBtn);
      card.appendChild(header);

      const body = document.createElement('textarea');
      body.name = `questions[${qi}][body]`;
      body.rows = 2;
      body.placeholder = '設問文';
      body.value = q.body;
      body.className = input();
      body.setAttribute('aria-label', `設問 ${qi + 1} の設問文`);
      body.addEventListener('input', () => {
        state.questions[qi].body = body.value;
      });
      card.appendChild(body);

      const row = document.createElement('div');
      row.className = 'flex items-center gap-4';

      const typeSelect = document.createElement('select');
      typeSelect.name = `questions[${qi}][type]`;
      typeSelect.className = 'rounded border border-gray-300 px-2 py-1 text-sm';
      typeSelect.setAttribute('aria-label', `設問 ${qi + 1} の設問形式`);
      [['single', '単一選択'], ['multiple', '複数選択'], ['text', '自由記述']].forEach(([value, label]) => {
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = label;
        opt.selected = q.type === value;
        typeSelect.appendChild(opt);
      });
      typeSelect.addEventListener('change', () => {
        state = changeType(state, qi, typeSelect.value);
        render();
      });
      row.appendChild(typeSelect);

      const requiredLabel = document.createElement('label');
      requiredLabel.className = 'flex items-center gap-1 text-sm';
      const requiredCheck = document.createElement('input');
      requiredCheck.type = 'checkbox';
      requiredCheck.name = `questions[${qi}][is_required]`;
      requiredCheck.value = '1';
      requiredCheck.checked = q.is_required;
      requiredCheck.addEventListener('change', () => {
        state.questions[qi].is_required = requiredCheck.checked;
      });
      requiredLabel.appendChild(requiredCheck);
      requiredLabel.appendChild(document.createTextNode('必須'));
      row.appendChild(requiredLabel);

      card.appendChild(row);

      if (shouldShowOptions(q)) {
        const optionsWrap = document.createElement('div');
        optionsWrap.className = 'space-y-2';
        optionsWrap.dataset.testid = `options-${qi}`;

        q.options.forEach((o, oi) => {
          const optionRow = document.createElement('div');
          optionRow.className = 'flex items-center gap-2';

          const optionInput = document.createElement('input');
          optionInput.type = 'text';
          optionInput.name = `questions[${qi}][options][${oi}]`;
          optionInput.placeholder = `選択肢 ${oi + 1}`;
          optionInput.value = o.label;
          optionInput.className = input('max-w-sm');
          optionInput.setAttribute('aria-label', `設問 ${qi + 1} の選択肢 ${oi + 1}`);
          optionInput.addEventListener('input', () => {
            state.questions[qi].options[oi].label = optionInput.value;
          });
          optionRow.appendChild(optionInput);

          const removeOptionBtn = document.createElement('button');
          removeOptionBtn.type = 'button';
          removeOptionBtn.textContent = '削除';
          removeOptionBtn.dataset.testid = `remove-option-${qi}-${oi}`;
          removeOptionBtn.className = 'text-sm text-red-600 hover:underline';
          removeOptionBtn.addEventListener('click', () => {
            state = removeOption(state, qi, oi);
            render();
          });
          optionRow.appendChild(removeOptionBtn);

          optionsWrap.appendChild(optionRow);
        });

        const addOptionBtn = document.createElement('button');
        addOptionBtn.type = 'button';
        addOptionBtn.textContent = '選択肢を追加';
        addOptionBtn.dataset.testid = `add-option-${qi}`;
        addOptionBtn.className = 'text-sm text-blue-600 hover:underline';
        addOptionBtn.addEventListener('click', () => {
          state = addOption(state, qi);
          render();
        });
        optionsWrap.appendChild(addOptionBtn);

        card.appendChild(optionsWrap);
      }

      container.appendChild(card);
    });
  }

  document.getElementById('add-question')?.addEventListener('click', () => {
    state = addQuestion(state);
    render();
  });

  render();
}
