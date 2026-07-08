// 集計 API のレスポンスを Chart.js 設定へ変換する（S-08）

const COLORS = [
  '#3b82f6', '#ef4444', '#22c55e', '#f59e0b', '#8b5cf6',
  '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#64748b',
];

// 自由記述設問はグラフ変換の対象外
export function isChartQuestion(question) {
  return question.type !== 'text';
}

export function toChartConfig(question, chartType) {
  if (!isChartQuestion(question)) {
    return question.answers;
  }

  const labels = question.options.map((o) => o.label);
  const data = question.options.map((o) => o.count);

  return {
    type: chartType,
    data: {
      labels,
      datasets: [
        {
          label: '回答数',
          data,
          backgroundColor: labels.map((_, i) => COLORS[i % COLORS.length]),
        },
      ],
    },
    options: {
      responsive: false,
      plugins: { legend: { display: chartType === 'pie' } },
      ...(chartType === 'bar'
        ? { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        : {}),
    },
  };
}
