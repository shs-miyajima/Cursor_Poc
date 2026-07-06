import { createEquipmentLoan, fetchEquipmentLoans, updateEquipmentLoanStatus } from './equipmentLoanApi.js';
import { clearError, renderLoanTable, renderOverdueAlert, showError } from './equipmentLoanView.js';

function extractErrorMessage(error) {
    return error?.response?.data?.message ?? 'エラーが発生しました。時間をおいて再度お試しください。';
}

function initEquipmentLoanPage() {
    const root = document.getElementById('equipment-loan-app');
    if (!root) {
        return;
    }

    const userSelect = document.getElementById('mock-user-select');
    const overdueAlert = document.getElementById('overdue-alert');
    const errorMessage = document.getElementById('error-message');
    const searchInput = document.getElementById('loan-search-input');
    const searchButton = document.getElementById('loan-search-button');
    const tableBody = document.getElementById('loan-table-body');
    const newLoanForm = document.getElementById('new-loan-form');

    async function reloadList() {
        clearError(errorMessage);
        try {
            const { data } = await fetchEquipmentLoans(userSelect.value, searchInput.value);
            renderLoanTable(tableBody, data.items);
            renderOverdueAlert(overdueAlert, data.overdue_summary);
        } catch (error) {
            showError(errorMessage, extractErrorMessage(error));
        }
    }

    userSelect.addEventListener('change', () => {
        reloadList();
    });

    searchButton.addEventListener('click', () => {
        reloadList();
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            reloadList();
        }
    });

    newLoanForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearError(errorMessage);
        try {
            await createEquipmentLoan({
                mockUserId: userSelect.value,
                equipmentId: newLoanForm.elements.equipment_id.value,
                requestedFrom: newLoanForm.elements.requested_from.value,
                requestedTo: newLoanForm.elements.requested_to.value,
                reason: newLoanForm.elements.reason.value,
            });
            newLoanForm.reset();
            await reloadList();
        } catch (error) {
            showError(errorMessage, extractErrorMessage(error));
        }
    });

    tableBody.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-loan-id][data-status]');
        if (!button) {
            return;
        }
        clearError(errorMessage);
        try {
            await updateEquipmentLoanStatus(button.dataset.loanId, userSelect.value, button.dataset.status);
            await reloadList();
        } catch (error) {
            showError(errorMessage, extractErrorMessage(error));
        }
    });

    reloadList();
}

document.addEventListener('DOMContentLoaded', initEquipmentLoanPage);
