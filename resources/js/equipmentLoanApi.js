import axios from 'axios';

const BASE_URL = '/api/equipment-loans';

/**
 * 申請一覧を取得する。search が空のときはクエリに含めない。
 */
export function fetchEquipmentLoans(mockUserId, search = '') {
    const params = { mock_user_id: mockUserId };
    if (search !== null && search !== undefined && search !== '') {
        params.search = search;
    }
    return axios.get(BASE_URL, { params });
}

/**
 * 新規申請を登録する。
 */
export function createEquipmentLoan({ mockUserId, equipmentId, requestedFrom, requestedTo, reason }) {
    return axios.post(BASE_URL, {
        mock_user_id: mockUserId,
        equipment_id: equipmentId,
        requested_from: requestedFrom,
        requested_to: requestedTo,
        reason: reason ?? '',
    });
}

/**
 * 申請ステータスを更新する。
 */
export function updateEquipmentLoanStatus(loanId, mockUserId, status) {
    return axios.patch(`${BASE_URL}/${loanId}/status`, {
        mock_user_id: mockUserId,
        status,
    });
}
