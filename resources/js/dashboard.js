// ダッシュボード（S-08）: 集計 API の取得と Chart.js 描画
import axios from 'axios';
import Chart from 'chart.js/auto';
import { isChartQuestion, toChartConfig } from './modules/chartDataTransformer.js';

const root = document.getElementById('dashboard');

if (root) {
  const surveySelect = document.getElementById('survey-select');
  const chartTypeSelect = document.getElementById('chart-type-select');
  const filterForm = document.getElementById('filter-form');
  const totalEl = document.getElementById('total-responses');
  const questionsEl = document.getElementById('question-results');

  let charts = [];
  let currentData = null;

  function filterParams() {
    const params = new URLSearchParams();
    new FormData(filterForm).forEach((value, key) => {
      if (value !== '') params.append(key, value);
    });
    return params;
  }

  async function load() {
    const surveyId = surveySelect.value;
    if (!surveyId) return;

    const { data } = await axios.get(`/api/surveys/${surveyId}/results`, {
      params: Object.fromEntries(filterParams()),
    });

    currentData = data;
    renderResults();
  }

  function renderResults() {
    charts.forEach((c) => c.destroy());
    charts = [];
    questionsEl.innerHTML = '';

    totalEl.textContent = `${currentData.total_responses} 件`;

    const chartType = chartTypeSelect.value;

    currentData.questions.forEach((question) => {
      const section = document.createElement('section');
      section.className = 'rounded border border-gray-200 bg-white p-4 space-y-2';
      section.dataset.testid = `question-result-${question.id}`;

      const heading = document.createElement('h3');
      heading.className = 'font-semibold text-sm';
      heading.textContent = question.body;
      section.appendChild(heading);

      if (isChartQuestion(question)) {
        const config = toChartConfig(question, chartType);

        const canvas = document.createElement('canvas');
        canvas.width = 480;
        canvas.height = 280;
        // E2E 検証用: グラフ種別と集計値を DOM 属性で公開する（テスト計画の要判断事項 2 の決定）
        canvas.dataset.chartType = chartType;
        canvas.dataset.chartValues = JSON.stringify(
          Object.fromEntries(question.options.map((o) => [o.label, o.count])),
        );
        canvas.dataset.testid = `chart-${question.id}`;
        section.appendChild(canvas);

        charts.push(new Chart(canvas, config));
      } else {
        const list = document.createElement('ul');
        list.className = 'list-disc pl-5 text-sm space-y-1';
        list.dataset.testid = `text-answers-${question.id}`;
        question.answers.forEach((text) => {
          const li = document.createElement('li');
          li.textContent = text;
          list.appendChild(li);
        });
        section.appendChild(list);
      }

      questionsEl.appendChild(section);
    });
  }

  surveySelect.addEventListener('change', load);
  chartTypeSelect.addEventListener('change', () => {
    if (currentData) renderResults();
  });
  filterForm.addEventListener('submit', (e) => {
    e.preventDefault();
    load();
  });

  load();
}
