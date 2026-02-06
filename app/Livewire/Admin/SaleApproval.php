<?php

namespace App\Livewire\Admin;

use App\Models\Sale;
use App\Models\ProductStock;
use App\Services\FIFOStockService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Title('Sale Approvals')]
#[Layout('components.layouts.admin')]
class SaleApproval extends Component
{
    use WithPagination,  WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $selectedSale = null;
    public $showDetailsModal = false;
    public $showRejectModal = false;
    public $rejectionReason = '';
    public $perPage = 10;

    protected $queryString = ['search', 'statusFilter'];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function viewDetails($saleId)
    {
        $this->selectedSale = Sale::with(['customer', 'items.product', 'user'])
            ->find($saleId);
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->selectedSale = null;
    }

    /**
     * Approve a sale and reduce stock
     */
    public function approveSale($saleId)
    {
        try {
            DB::beginTransaction();

            $sale = Sale::with('items')->find($saleId);

            if (!$sale || $sale->status !== 'pending') {
                $this->dispatch('show-toast', type: 'error', message: 'Sale not found or already processed.');
                return;
            }

            // Update stock for each item
            foreach ($sale->items as $item) {
                $stock = ProductStock::where('product_id', $item->product_id)->first();

                if ($stock) {
                    if ($stock->available_stock < $item->quantity) {
                        DB::rollBack();
                        $this->dispatch('show-toast', type: 'error', message: "Insufficient stock for {$item->product_name}. Available: {$stock->available_stock}");
                        return;
                    }

                    $stock->available_stock -= $item->quantity;
                    $stock->save();
                }
            }

            // Update sale status
            $sale->update([
                'status' => 'confirm',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            $this->closeDetailsModal();
            $this->dispatch('show-toast', type: 'success', message: 'Sale approved successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale approval error: ' . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Error approving sale: ' . $e->getMessage());
        }
    }

    public function openRejectModal($saleId)
    {
        $this->selectedSale = Sale::find($saleId);
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function closeRejectModal()
    {
        $this->showRejectModal = false;
        $this->selectedSale = null;
        $this->rejectionReason = '';
    }

    /**
     * Reject a sale
     */
    public function rejectSale()
    {
        if (!$this->selectedSale) {
            return;
        }

        $this->validate([
            'rejectionReason' => 'required|min:5',
        ]);

        try {
            $this->selectedSale->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => $this->rejectionReason,
            ]);

            $this->closeRejectModal();
            $this->dispatch('show-toast', type: 'success', message: 'Sale rejected successfully.');
        } catch (\Exception $e) {
            Log::error('Sale rejection error: ' . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Error rejecting sale.');
        }
    }

    public function render()
    {
        $query = Sale::with(['customer', 'user']);

        // If not admin, show only their own sales
        if (Auth::user()->role !== 'admin') {
            $query->where('user_id', Auth::id());
        }

        // Default filter - show pending sales for approval
        // If statusFilter is set, use that instead
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        } else {
            $query->where('status', 'pending');
        }

        $query->when($this->search, function ($q) {
            $q->where(function ($sq) {
                $sq->where('sale_id', 'like', '%' . $this->search . '%')
                    ->orWhere('invoice_number', 'like', '%' . $this->search . '%')
                    ->orWhereHas('customer', function ($cq) {
                        $cq->where('name', 'like', '%' . $this->search . '%');
                    });
            });
        })
            ->orderBy('created_at', 'desc');

        return view('livewire.admin.sale-approval', [
            'sales' => $query->paginate($this->perPage),
            'pendingCount' => Sale::when(Auth::user()->role !== 'admin', function ($q) {
                $q->where('user_id', Auth::id());
            })->where('status', 'pending')->count(),
            'approvedCount' => Sale::when(Auth::user()->role !== 'admin', function ($q) {
                $q->where('user_id', Auth::id());
            })->where('status', 'confirm')->count(),
            'rejectedCount' => Sale::when(Auth::user()->role !== 'admin', function ($q) {
                $q->where('user_id', Auth::id());
            })->where('status', 'rejected')->count(),
        ]);
    }
}
