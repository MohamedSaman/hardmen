<?php

namespace App\Livewire\Salesman;

use App\Models\Sale;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;

#[Title('My Sales')]
#[Layout('components.layouts.app')]
class SalesmanSalesList extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $deliveryFilter = '';
    public $selectedSale = null;
    public $showDetailsModal = false;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedDeliveryFilter()
    {
        $this->resetPage();
    }

    public function viewDetails($saleId)
    {
        $this->selectedSale = Sale::with(['customer', 'items.product', 'payments', 'approvedBy', 'deliveredBy'])
            ->find($saleId);
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->selectedSale = null;
    }

    public function render()
    {
        $query = Sale::where('user_id', Auth::id())
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('sale_id', 'like', '%' . $this->search . '%')
                        ->orWhere('invoice_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('customer', function ($cq) {
                            $cq->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->statusFilter, function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when($this->deliveryFilter, function ($q) {
                $q->where('delivery_status', $this->deliveryFilter);
            })
            ->with(['customer'])
            ->orderBy('created_at', 'desc');

        return view('livewire.salesman.salesman-sales-list', [
            'sales' => $query->paginate(15),
            'pendingCount' => Sale::where('user_id', Auth::id())->where('status', 'pending')->count(),
            'approvedCount' => Sale::where('user_id', Auth::id())->where('status', 'confirm')->count(),
            'rejectedCount' => Sale::where('user_id', Auth::id())->where('status', 'rejected')->count(),
        ]);
    }
}
