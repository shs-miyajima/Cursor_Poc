const STATUS_BADGES = {
    pending: { label: '申請中', className: 'bg-yellow-100 text-yellow-800' },
    approved: { label: '貸出中', className: 'bg-blue-100 text-blue-800' },
    return_requested: { label: '返却申請中', className: 'bg-orange-100 text-orange-800' },
    returned: { label: '返却済', className: 'bg-gray-100 text-gray-600' },
    rejected: { label: '却下', className: 'bg-red-100 text-red-800' },
};

const UPDATE_ACTIONS = [
    { status: 'approved', label: '承認', className: 'bg-blue-600 hover:bg-blue-500' },
    { status: 'rejected', label: '却下', className: 'bg-red-600 hover:bg-red-500' },
    { status: 'returned', label: '返却', className: 'bg-gray-600 hover:bg-gray-500' },
];

const RETURN_REQUEST_ACTION = { status: 'return_requested', label: '返却申請', className: 'bg-emerald-600 hover:bg-emerald-500' };

/**
 * ステータスバッジの表示情報を返す。
 */
export function statusBadge(status) {
    return STATUS_BADGES[status];
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function renderRow(item) {
    const badge = statusBadge(item.status);
    const overdueIcon = item.is_overdue
        ? '<span data-testid="overdue-icon" title="返却期限超過" class="text-red-600 font-bold">⚠</span>'
        : '';
    const updateButtons = item.can_update_status
        ? UPDATE_ACTIONS.map(
              (action) =>
                  `<button type="button" data-testid="status-button-${action.status}" data-loan-id="${item.id}" data-status="${action.status}" class="rounded px-2 py-1 text-xs text-white ${action.className}">${action.label}</button>`,
          ).join(' ')
        : '';
    const returnRequestButton = item.can_request_return
        ? `<button type="button" data-testid="return-request-button" data-loan-id="${item.id}" data-status="${RETURN_REQUEST_ACTION.status}" class="rounded px-2 py-1 text-xs text-white ${RETURN_REQUEST_ACTION.className}">${RETURN_REQUEST_ACTION.label}</button>`
        : '';
    const buttons = [updateButtons, returnRequestButton].filter(Boolean).join(' ');

    return `
        <tr data-testid="loan-row" data-loan-id="${item.id}" class="border-b ${item.is_overdue ? 'bg-red-50' : ''}">
            <td class="px-3 py-2">${overdueIcon}</td>
            <td class="px-3 py-2">${escapeHtml(item.user_name)}</td>
            <td class="px-3 py-2">${escapeHtml(item.equipment_name)}</td>
            <td class="px-3 py-2"><span data-testid="status-badge" data-status="${item.status}" class="rounded px-2 py-1 text-xs font-medium ${badge.className}">${badge.label}</span></td>
            <td class="px-3 py-2">${escapeHtml(item.requested_from)}</td>
            <td class="px-3 py-2">${escapeHtml(item.requested_to)}</td>
            <td class="px-3 py-2">${escapeHtml(item.reason ?? '')}</td>
            <td class="px-3 py-2 whitespace-nowrap">${buttons}</td>
        </tr>
    `;
}

/**
 * 申請一覧を tbody に描画する。0 件時はメッセージ行を表示する。
 */
export function renderLoanTable(tbody, items) {
    if (!items || items.length === 0) {
        tbody.innerHTML =
            '<tr data-testid="loan-empty-row"><td colspan="8" class="px-3 py-4 text-center text-gray-500">申請はありません</td></tr>';
        return;
    }
    tbody.innerHTML = items.map(renderRow).join('');
}

/**
 * 返却期限超過アラートを描画する。count が 0 のときは非表示にする。
 */
export function renderOverdueAlert(alertElement, overdueSummary) {
    const count = overdueSummary?.count ?? 0;
    if (count === 0) {
        alertElement.hidden = true;
        alertElement.innerHTML = '';
        return;
    }

    const details = overdueSummary.items
        .map(
            (item) =>
                `<li>${escapeHtml(item.user_name)} - ${escapeHtml(item.equipment_name)}（返却期限: ${escapeHtml(item.requested_to)}）</li>`,
        )
        .join('');
    alertElement.innerHTML = `
        <p class="font-bold">返却期限超過の申請が ${count} 件あります</p>
        <ul class="mt-1 list-disc list-inside text-sm">${details}</ul>
    `;
    alertElement.hidden = false;
}

/**
 * エラーメッセージを表示する。
 */
export function showError(errorElement, message) {
    errorElement.textContent = message;
    errorElement.hidden = false;
}

/**
 * エラーメッセージを消す。
 */
export function clearError(errorElement) {
    errorElement.textContent = '';
    errorElement.hidden = true;
}
