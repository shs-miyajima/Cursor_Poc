import { describe, it, expect } from 'vitest';
import { isChartQuestion, toChartConfig } from './chartDataTransformer.js';

const choiceQuestion = (counts) => ({
  id: 1,
  body: '満足度を教えてください',
  type: 'single',
  options: [
    { id: 11, label: '満足', count: counts[0] },
    { id: 12, label: '普通', count: counts[1] },
    { id: 13, label: '不満', count: counts[2] },
  ],
});

describe('chartDataTransformer', () => {
  // VT-006-dyn: 縦棒グラフ — labels が 3 選択肢・data が [2,1,0]・type が bar
  it('type=bar で labels と data と chart type が返る', () => {
    const config = toChartConfig(choiceQuestion([2, 1, 0]), 'bar');

    expect(config.type).toBe('bar');
    expect(config.data.labels).toEqual(['満足', '普通', '不満']);
    expect(config.data.datasets[0].data).toEqual([2, 1, 0]);
  });

  // VT-007-dyn: 円グラフ — 同じ labels・data で chart type が pie
  it('type=pie で同じ labels・data のまま chart type が pie になる', () => {
    const config = toChartConfig(choiceQuestion([2, 1, 0]), 'pie');

    expect(config.type).toBe('pie');
    expect(config.data.labels).toEqual(['満足', '普通', '不満']);
    expect(config.data.datasets[0].data).toEqual([2, 1, 0]);
  });

  // VT-008-dyn: 0 件 — data が [0,0,0] で返り例外が発生しない
  it('全選択肢 0 件でも例外なく data [0,0,0] が返る', () => {
    const config = toChartConfig(choiceQuestion([0, 0, 0]), 'bar');

    expect(config.data.datasets[0].data).toEqual([0, 0, 0]);
  });

  // VT-009-dyn: 自由記述 — グラフ変換対象外と判定され answers のテキスト配列がそのまま返る
  it('自由記述設問は変換対象外で answers 配列がそのまま返る', () => {
    const textQuestion = {
      id: 2,
      body: '改善点を教えてください',
      type: 'text',
      answers: ['改善希望', '特になし'],
    };

    expect(isChartQuestion(textQuestion)).toBe(false);
    expect(toChartConfig(textQuestion, 'bar')).toEqual(['改善希望', '特になし']);
  });
});
