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
    public $statusFilter = 'pending';
    public $selectedSale = null;
    public $showDetailsModal = false;
    public $showRejectModal = false;
    public $showApproveModal = false;
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
    public function approveSale()
    {
        if (!$this->selectedSale) {
            return;
        }

        try {
            DB::beginTransaction();

            $sale = Sale::with('items')->find($this->selectedSale->id);

            if (!$sale || $sale->status !== 'pending') {
                $this->dispatch('show-toast', type: 'error', message: 'Sale not found or already processed.');
                return;
            }

            // Update stock for each item
            foreach ($sale->items as $item) {
                // Check if item has variant
                if ($item->variant_id || $item->variant_value) {
                    // Handle variant stock
                    $stock = ProductStock::where('product_id', $item->product_id)
                        ->where(function ($q) use ($item) {
                            if ($item->variant_id) {
                                $q->where('variant_id', $item->variant_id);
                            }
                            if ($item->variant_value) {
                                $q->where('variant_value', $item->variant_value);
                            }
                        })
                        ->first();
                } else {
                    // Handle single product stock
                    $stock = ProductStock::where('product_id', $item->product_id)
                        ->whereNull('variant_value')
                        ->first();
                }

                if ($stock) {
                    if ($stock->available_stock < $item->quantity) {
                        DB::rollBack();
                        $this->dispatch('show-toast', type: 'error', message: "Insufficient stock for {$item->product_name}. Available: {$stock->available_stock}");
                        $this->closeApproveModal();
                        return;
                    }

                    $stock->available_stock -= $item->quantity;
                    $stock->total_stock -= $item->quantity;
                    $stock->save();
                } else {
                    DB::rollBack();
                    $this->dispatch('show-toast', type: 'error', message: "Stock record not found for {$item->product_name}.");
                    $this->closeApproveModal();
                    return;
                }
            }

            // Update sale status and set due amount
            $sale->update([
                'status' => 'confirm',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'due_amount' => $sale->total_amount,
            ]);

            // Update customer due amount
            $customer = $sale->customer;
            if ($customer) {
                $customer->due_amount = ($customer->due_amount ?? 0) + $sale->total_amount;
                $customer->save();
            }

            DB::commit();

            $this->closeApproveModal();
            $this->closeDetailsModal();
            $this->dispatch('show-toast', type: 'success', message: 'Sale approved successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale approval error: ' . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Error approving sale: ' . $e->getMessage());
            $this->closeApproveModal();
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

    public function openApproveModal($saleId)
    {
        $this->selectedSale = Sale::with('items')->find($saleId);
        $this->showApproveModal = true;
    }

    public function closeApproveModal()
    {
        $this->showApproveModal = false;
        $this->selectedSale = null;
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

        // Apply status filter - only if statusFilter is not empty
        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
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
