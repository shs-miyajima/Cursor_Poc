import { beforeEach, describe, expect, it } from 'vitest';
import {
    clearError,
    renderLoanTable,
    renderOverdueAlert,
    showError,
    statusBadge,
} from './equipmentLoanView.js';

function makeItem(overrides = {}) {
    return {
        id: 1,
        user_name: '山田 太郎',
        equipment_name: 'MacBook Pro M3',
        status: 'pending',
        status_label: '申請中',
        requested_from: '2026-07-10',
        requested_to: '2026-07-12',
        reason: '検証作業で使用するため',
        is_overdue: false,
        can_update_status: false,
        ...overrides,
    };
}

describe('equipmentLoanView', () => {
    let tbody;
    let alertElement;
    let errorElement;

    beforeEach(() => {
        document.body.innerHTML = `
            <div id="overdue-alert" hidden></div>
            <div id="error-message" hidden></div>
            <table><tbody id="loan-table-body"></tbody></table>
        `;
        tbody = document.getElementById('loan-table-body');
        alertElement = document.getElementById('overdue-alert');
        errorElement = document.getElementById('error-message');
    });

    // Vitest-dsp-001: 一覧行 通常描画 OK
    it('通常行の各項目が描画される', () => {
        renderLoanTable(tbody, [makeItem()]);

        const row = tbody.querySelector('[data-testid="loan-row"]');
        expect(row).not.toBeNull();
        expect(row.textContent).toContain('山田 太郎');
        expect(row.textContent).toContain('MacBook Pro M3');
        expect(row.textContent).toContain('申請中');
        expect(row.textContent).toContain('2026-07-10');
        expect(row.textContent).toContain('2026-07-12');
        expect(row.textContent).toContain('検証作業で使用するため');
    });

    // Vitest-dsp-002: 空一覧表示 OK
    it('items が空のとき0件メッセージが表示され既存行が残らない', () => {
        renderLoanTable(tbody, [makeItem()]);
        renderLoanTable(tbody, []);

        expect(tbody.querySelector('[data-testid="loan-row"]')).toBeNull();
        expect(tbody.querySelector('[data-testid="loan-empty-row"]').textContent).toContain('申請はありません');
    });

    // Vitest-dsp-003: エラーメッセージ表示 OK
    it('エラーメッセージがエラー領域に表示される', () => {
        showError(errorElement, 'この備品は所属部署では申請できません');

        expect(errorElement.hidden).toBe(false);
        expect(errorElement.textContent).toBe('この備品は所属部署では申請できません');

        clearError(errorElement);
        expect(errorElement.hidden).toBe(true);
        expect(errorElement.textContent).toBe('');
    });

    // Vitest-dsp-004: ステータスバッジ pending
    it('pending は申請中ラベルと黄色系クラスで返る', () => {
        const badge = statusBadge('pending');
        expect(badge.label).toBe('申請中');
        expect(badge.className).toContain('yellow');
    });

    // Vitest-dsp-005: ステータスバッジ approved
    it('approved は貸出中ラベルと青系クラスで返る', () => {
        const badge = statusBadge('approved');
        expect(badge.label).toBe('貸出中');
        expect(badge.className).toContain('blue');
    });

    // Vitest-dsp-006: ステータスバッジ returned
    it('returned は返却済ラベルとグレー系クラスで返る', () => {
        const badge = statusBadge('returned');
        expect(badge.label).toBe('返却済');
        expect(badge.className).toContain('gray');
    });

    // Vitest-dsp-007: ステータスバッジ rejected
    it('rejected は却下ラベルと赤系クラスで返る', () => {
        const badge = statusBadge('rejected');
        expect(badge.label).toBe('却下');
        expect(badge.className).toContain('red');
    });

    // Vitest-dsp-008: 期限切れアラート表示
    it('count が 1 以上のときアラートに件数と対象概要が表示される', () => {
        renderOverdueAlert(alertElement, {
            count: 1,
            items: [
                { id: 2, user_name: '山田 太郎', equipment_name: 'iPhone 15 Pro', requested_to: '2026-07-03' },
            ],
        });

        expect(alertElement.hidden).toBe(false);
        expect(alertElement.textContent).toContain('1 件');
        expect(alertElement.textContent).toContain('山田 太郎');
        expect(alertElement.textContent).toContain('iPhone 15 Pro');
        expect(alertElement.textContent).toContain('2026-07-03');
    });

    // Vitest-dsp-009: 期限切れアラート非表示
    it('count が 0 のときアラートが非表示になり対象概要は描画されない', () => {
        renderOverdueAlert(alertElement, { count: 1, items: [{ id: 2, user_name: 'A', equipment_name: 'B', requested_to: '2026-07-03' }] });
        renderOverdueAlert(alertElement, { count: 0, items: [] });

        expect(alertElement.hidden).toBe(true);
        expect(alertElement.innerHTML).toBe('');
    });

    // Vitest-dsp-010: 期限切れ注意アイコン表示
    it('is_overdue が true の行に注意アイコンが表示される', () => {
        renderLoanTable(tbody, [makeItem({ is_overdue: true })]);

        expect(tbody.querySelector('[data-testid="overdue-icon"]')).not.toBeNull();
    });

    // Vitest-dsp-011: 期限切れ注意アイコン非表示
    it('is_overdue が false の行に注意アイコンが表示されない', () => {
        renderLoanTable(tbody, [makeItem({ is_overdue: false })]);

        expect(tbody.querySelector('[data-testid="overdue-icon"]')).toBeNull();
    });

    // Vitest-dsp-012: 理由 null の行描画（任意項目のためデータ上 null があり得る）
    it('reason が null の行は理由セルが空欄のまま描画される', () => {
        renderLoanTable(tbody, [makeItem({ reason: null })]);

        const row = tbody.querySelector('[data-testid="loan-row"]');
        expect(row).not.toBeNull();
        expect(row.textContent).toContain('山田 太郎');
    });

    // Vitest-dsp-013: overdue_summary 欠落時のアラート非表示
    it('overdueSummary が null のときアラートは非表示になる', () => {
        renderOverdueAlert(alertElement, null);

        expect(alertElement.hidden).toBe(true);
        expect(alertElement.innerHTML).toBe('');
    });

    // Vitest-auth-001: 管理者ボタン表示
    it('can_update_status が true の行に承認・却下・返却ボタンが表示される', () => {
        renderLoanTable(tbody, [makeItem({ can_update_status: true })]);

        expect(tbody.querySelector('[data-testid="status-button-approved"]')).not.toBeNull();
        expect(tbody.querySelector('[data-testid="status-button-rejected"]')).not.toBeNull();
        expect(tbody.querySelector('[data-testid="status-button-returned"]')).not.toBeNull();
    });

    // Vitest-auth-002: 管理者ボタン非表示
    it('can_update_status が false の行にステータス更新ボタンが表示されない', () => {
        renderLoanTable(tbody, [makeItem({ can_update_status: false })]);

        expect(tbody.querySelector('[data-testid="status-button-approved"]')).toBeNull();
        expect(tbody.querySelector('[data-testid="status-button-rejected"]')).toBeNull();
        expect(tbody.querySelector('[data-testid="status-button-returned"]')).toBeNull();
    });
});
