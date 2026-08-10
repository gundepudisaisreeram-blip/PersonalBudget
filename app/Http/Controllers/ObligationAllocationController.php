<?php

namespace App\Http\Controllers;

use App\Domain\Services\ObligationAllocationService;
use App\Http\Requests\StoreObligationAllocationRequest;
use App\Models\ObligationAllocation;
use App\Models\PaymentObligation;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ObligationAllocationController extends Controller
{
    public function create(Request $request, PaymentObligation $paymentObligation): View
    {
        $this->authorize('update', $paymentObligation);

        $eligibleTransactions = $request->user()->transactions()
            ->whereIn('transaction_type', Transaction::TYPES_ELIGIBLE_FOR_ALLOCATION)
            ->orderByDesc('transaction_date')
            ->get();

        return view('obligations.allocations.create', [
            'obligation' => $paymentObligation,
            'eligibleTransactions' => $eligibleTransactions,
        ]);
    }

    public function store(
        StoreObligationAllocationRequest $request,
        PaymentObligation $paymentObligation,
        ObligationAllocationService $allocations,
    ): RedirectResponse {
        $transaction = $request->user()->transactions()->findOrFail($request->integer('transaction_id'));

        $allocations->allocate($request->user(), $paymentObligation, $transaction, (string) $request->input('amount'));

        return redirect()->route('obligations.show', $paymentObligation)->with('status', 'Transaction linked to obligation.');
    }

    public function destroy(
        Request $request,
        PaymentObligation $paymentObligation,
        ObligationAllocation $obligationAllocation,
        ObligationAllocationService $allocations,
    ): RedirectResponse {
        $this->authorize('delete', $obligationAllocation);

        $allocations->removeAllocation($request->user(), $obligationAllocation);

        return redirect()->route('obligations.show', $paymentObligation)->with('status', 'Allocation removed.');
    }
}
