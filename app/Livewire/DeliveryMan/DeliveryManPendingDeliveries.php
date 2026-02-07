<?php

namespace App\Livewire\DeliveryMan;

use App\Models\Sale;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;

#[Title('Pending Deliveries')]
#[Layout('components.layouts.delivery-man')]
class DeliveryManPendingDeliveries extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedSale = null;
    public $showDetailsModal = false;
    public $showConfirmModal = false;
    public $confirmAction = '';
    public $confirmSaleId = null;
    public $showPaymentModal = false;
    public $paymentSale = null;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function viewDetails($saleId)
    {
        $this->selectedSale = Sale::with(['customer', 'items.product', 'user', 'payments'])
            ->find($saleId);
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->selectedSale = null;
    }

    /**
     * Show confirmation modal
     */
    public function showConfirmation($action, $saleId)
    {
        $this->confirmAction = $action;
        $this->confirmSaleId = $saleId;
        $this->showConfirmModal = true;
    }

    /**
     * Close confirmation modal
     */
    public function closeConfirmModal()
    {
        $this->showConfirmModal = false;
        $this->confirmAction = '';
        $this->confirmSaleId = null;
    }

    /**
     * Execute confirmed action
     */
    public function executeConfirmedAction()
    {
        if ($this->confirmAction === 'transit') {
            $this->markInTransit($this->confirmSaleId);
        } elseif ($this->confirmAction === 'delivered') {
            $this->markDelivered($this->confirmSaleId);
        }

        $this->closeConfirmModal();
    }

    /**
     * Mark delivery as in transit
     */
    public function markInTransit($saleId)
    {
        $sale = Sale::find($saleId);

        if ($sale && $sale->status === 'confirm') {
            $sale->update([
                'delivery_status' => 'in_transit',
                'delivered_by' => Auth::id(),
            ]);

            $this->dispatch('show-toast', type: 'success', message: 'Marked as in transit.');
        }
    }

    /**
     * Mark delivery as completed
     */
    public function markDelivered($saleId)
    {
        $sale = Sale::find($saleId);

        if ($sale && $sale->status === 'confirm') {
            $sale->update([
                'delivery_status' => 'delivered',
                'delivered_by' => Auth::id(),
                'delivered_at' => now(),
            ]);

            // Check if there's a due amount
            if ($sale->due_amount > 0) {
                $this->paymentSale = $sale;
                $this->showPaymentModal = true;
                $this->closeDetailsModal();
            } else {
                $this->dispatch('show-toast', type: 'success', message: 'Delivery completed!');
                $this->closeDetailsModal();
            }
        }
    }

    /**
     * Close payment modal
     */
    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->paymentSale = null;
    }

    /**
     * Redirect to payment page
     */
    public function goToPayment()
    {
        if ($this->paymentSale) {
            return redirect()->route('delivery.payments', ['customer_id' => $this->paymentSale->customer_id]);
        }
    }

    public function render()
    {
        $sales = Sale::where('status', 'confirm')
            ->whereIn('delivery_status', ['pending', 'in_transit'])
            ->whereNotNull('approved_by') // Only show approved sales
            ->whereHas('user', function ($q) {
                $q->where('staff_type', 'salesman'); // Only show sales created by salesmen
            })
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
            ->with(['customer', 'items'])
            ->orderBy('created_at', 'asc')
            ->paginate(15);

        return view('livewire.delivery-man.delivery-man-pending-deliveries', [
            'sales' => $sales,
        ]);
    }
}
