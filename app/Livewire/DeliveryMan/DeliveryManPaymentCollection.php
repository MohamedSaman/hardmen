<?php

namespace App\Livewire\DeliveryMan;

use App\Models\Sale;
use App\Models\Payment;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Title('Payment Collection')]
#[Layout('components.layouts.delivery-man')]
class DeliveryManPaymentCollection extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedSale = null;
    public $showCollectModal = false;

    // Payment form
    public $amount = 0;
    public $paymentMethod = 'cash';
    public $paymentNotes = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function openCollectModal($saleId)
    {
        $this->selectedSale = Sale::with(['customer', 'payments'])->find($saleId);

        if ($this->selectedSale) {
            $this->amount = $this->selectedSale->due_amount;
            $this->paymentMethod = 'cash';
            $this->paymentNotes = '';
            $this->showCollectModal = true;
        }
    }

    public function closeCollectModal()
    {
        $this->showCollectModal = false;
        $this->selectedSale = null;
        $this->reset(['amount', 'paymentMethod', 'paymentNotes']);
    }

    /**
     * Collect payment (creates payment with pending status for admin approval)
     */
    public function collectPayment()
    {
        $this->validate([
            'amount' => 'required|numeric|min:1',
            'paymentMethod' => 'required|in:cash,cheque,bank_transfer',
        ]);

        if (!$this->selectedSale) {
            return;
        }

        if ($this->amount > $this->selectedSale->due_amount) {
            $this->dispatch('show-toast', type: 'error', message: 'Amount cannot exceed due amount.');
            return;
        }

        try {
            DB::beginTransaction();

            // Create payment with PENDING status (requires admin approval)
            Payment::create([
                'sale_id' => $this->selectedSale->id,
                'customer_id' => $this->selectedSale->customer_id,
                'amount' => $this->amount,
                'payment_method' => $this->paymentMethod,
                'payment_date' => now(),
                'status' => 'pending', // Requires admin approval
                'notes' => $this->paymentNotes,
                'collected_by' => Auth::id(),
                'collected_at' => now(),
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            $this->closeCollectModal();
            $this->dispatch('show-toast', type: 'success', message: 'Payment collected! Awaiting admin approval.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment collection error: ' . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Error collecting payment.');
        }
    }

    public function render()
    {
        // Show sales with due amounts (approved sales only)
        $sales = Sale::where('status', 'confirm')
            ->where('due_amount', '>', 0)
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('sale_id', 'like', '%' . $this->search . '%')
                        ->orWhere('invoice_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('customer', function ($cq) {
                            $cq->where('name', 'like', '%' . $this->search . '%')
                                ->orWhere('phone', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->with(['customer', 'payments'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Get pending payments collected by this user
        $pendingPayments = Payment::where('collected_by', Auth::id())
            ->where('status', 'pending')
            ->with(['sale.customer'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.delivery-man.delivery-man-payment-collection', [
            'sales' => $sales,
            'pendingPayments' => $pendingPayments,
        ]);
    }
}
