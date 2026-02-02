<?php

namespace App\Livewire\Admin;

use App\Models\Payment;
use App\Models\Sale;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Title('Payment Approvals')]
#[Layout('components.layouts.admin')]
class PaymentApproval extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'pending';
    public $selectedPayment = null;
    public $showRejectModal = false;
    public $rejectionReason = '';

    protected $queryString = ['search', 'statusFilter'];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    /**
     * Approve a payment and update sale due amount
     */
    public function approvePayment($paymentId)
    {
        try {
            DB::beginTransaction();

            $payment = Payment::with('sale')->find($paymentId);

            if (!$payment || $payment->status !== 'pending') {
                $this->dispatch('show-toast', type: 'error', message: 'Payment not found or already processed.');
                return;
            }

            // Update payment status
            $payment->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'is_completed' => true,
            ]);

            // Update sale due amount if payment is linked to a sale
            if ($payment->sale) {
                $newDueAmount = max(0, $payment->sale->due_amount - $payment->amount);
                $paymentStatus = $newDueAmount > 0 ? 'partial' : 'paid';

                $payment->sale->update([
                    'due_amount' => $newDueAmount,
                    'payment_status' => $paymentStatus,
                    'payment_type' => $newDueAmount > 0 ? 'partial' : 'full',
                ]);
            }

            DB::commit();

            $this->dispatch('show-toast', type: 'success', message: 'Payment approved successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment approval error: ' . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Error approving payment.');
        }
    }

    public function openRejectModal($paymentId)
    {
        $this->selectedPayment = Payment::find($paymentId);
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function closeRejectModal()
    {
        $this->showRejectModal = false;
        $this->selectedPayment = null;
        $this->rejectionReason = '';
    }

    /**
     * Reject a payment
     */
    public function rejectPayment()
    {
        if (!$this->selectedPayment) {
            return;
        }

        $this->validate([
            'rejectionReason' => 'required|min:5',
        ]);

        try {
            $this->selectedPayment->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => $this->rejectionReason,
                'is_completed' => false,
            ]);

            $this->closeRejectModal();
            $this->dispatch('show-toast', type: 'success', message: 'Payment rejected.');
        } catch (\Exception $e) {
            Log::error('Payment rejection error: ' . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Error rejecting payment.');
        }
    }

    public function render()
    {
        $query = Payment::with(['sale.customer', 'collectedBy', 'customer'])
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->whereHas('sale', function ($saleQ) {
                        $saleQ->where('sale_id', 'like', '%' . $this->search . '%')
                            ->orWhere('invoice_number', 'like', '%' . $this->search . '%');
                    })
                        ->orWhereHas('customer', function ($cq) {
                            $cq->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->statusFilter, function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->orderBy('created_at', 'desc');

        return view('livewire.admin.payment-approval', [
            'payments' => $query->paginate(15),
            'pendingCount' => Payment::where('status', 'pending')->count(),
            'approvedCount' => Payment::whereIn('status', ['approved', 'paid'])->count(),
            'rejectedCount' => Payment::where('status', 'rejected')->count(),
        ]);
    }
}
