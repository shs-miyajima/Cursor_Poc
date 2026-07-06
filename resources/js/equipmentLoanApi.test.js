import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import {
    createEquipmentLoan,
    fetchEquipmentLoans,
    updateEquipmentLoanStatus,
} from './equipmentLoanApi.js';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
    },
}));

describe('equipmentLoanApi', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: {} });
        axios.post.mockResolvedValue({ data: {} });
        axios.patch.mockResolvedValue({ data: {} });
    });

    // Vitest-other-001: 一覧 API URL 組み立て OK
    it('一覧取得で mock_user_id と search がクエリに付与される', async () => {
        await fetchEquipmentLoans(1, 'MacBook');

        expect(axios.get).toHaveBeenCalledWith('/api/equipment-loans', {
            params: { mock_user_id: 1, search: 'MacBook' },
        });
    });

    // Vitest-other-002: 一覧 API search 空 OK
    it('search が空のとき search はクエリに含まれない', async () => {
        await fetchEquipmentLoans(1, '');

        expect(axios.get).toHaveBeenCalledWith('/api/equipment-loans', {
            params: { mock_user_id: 1 },
        });
    });

    // Vitest-other-003: 新規申請 API payload OK
    it('新規申請で各項目が POST payload に含まれる', async () => {
        await createEquipmentLoan({
            mockUserId: 1,
            equipmentId: 2,
            requestedFrom: '2026-07-10',
            requestedTo: '2026-07-12',
            reason: '検証作業で使用するため',
        });

        expect(axios.post).toHaveBeenCalledWith('/api/equipment-loans', {
            mock_user_id: 1,
            equipment_id: 2,
            requested_from: '2026-07-10',
            requested_to: '2026-07-12',
            reason: '検証作業で使用するため',
        });
    });

    // Vitest-other-004: 新規申請 API reason 空 OK
    it('reason が未指定のとき空文字に正規化して payload に含まれる', async () => {
        await createEquipmentLoan({
            mockUserId: 1,
            equipmentId: 2,
            requestedFrom: '2026-07-10',
            requestedTo: '2026-07-12',
        });

        expect(axios.post).toHaveBeenCalledWith('/api/equipment-loans', {
            mock_user_id: 1,
            equipment_id: 2,
            requested_from: '2026-07-10',
            requested_to: '2026-07-12',
            reason: '',
        });
    });

    // Vitest-other-005: ステータス更新 API URL と payload OK
    it('ステータス更新で URL に申請 ID が入り mock_user_id と status が送信される', async () => {
        await updateEquipmentLoanStatus(10, 3, 'approved');

        expect(axios.patch).toHaveBeenCalledWith('/api/equipment-loans/10/status', {
            mock_user_id: 3,
            status: 'approved',
        });
    });

    // Vitest-other-006: API エラー伝播
    it('axios がエラーを返すと呼び出し側に reject が伝播する', async () => {
        const error = Object.assign(new Error('Request failed'), {
            response: { status: 403, data: { message: 'この備品は所属部署では申請できません' } },
        });
        axios.get.mockRejectedValue(error);
        axios.post.mockRejectedValue(error);
        axios.patch.mockRejectedValue(error);

        await expect(fetchEquipmentLoans(1)).rejects.toBe(error);
        await expect(
            createEquipmentLoan({ mockUserId: 1, equipmentId: 2, requestedFrom: '2026-07-10', requestedTo: '2026-07-12' }),
        ).rejects.toBe(error);
        await expect(updateEquipmentLoanStatus(10, 3, 'approved')).rejects.toBe(error);
    });
});
