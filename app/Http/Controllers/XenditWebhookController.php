<?php

namespace App\Http\Controllers;

use App\Http\Requests\XenditDpWebhookRequest;
use App\Http\Requests\XenditDisbursementWebhookRequest;
use App\Services\DpPaymentService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;

class XenditWebhookController extends Controller
{
    public function handleDpPayment(XenditDpWebhookRequest $request, DpPaymentService $dpPaymentService): JsonResponse
    {
        $dpPaymentService->processWebhook($request->validated());

        return response()->json(['status' => 'ok']);
    }

    public function handleDisbursement(XenditDisbursementWebhookRequest $request, WithdrawalService $withdrawalService): JsonResponse
    {
        $data = $request->validated();
        $withdrawalService->handleDisbursementWebhook($data['external_id'], $data['status']);

        return response()->json(['status' => 'ok']);
    }
}
