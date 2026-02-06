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
    use WithPagination;

    public $search = '';
    public $statusFilter = 'pending';
    public $selectedSaleId = null;
    public $showDetailsModal = false;
    public $showRejectModal = false;
    public $showApproveModal = false;
    public $rejectionReason = '';
    public $perPage = 10;
    public $isProcessing = false;

    // Computed property to get selected sale
    public function getSelectedSaleProperty()
    {
        if (!$this->selectedSaleId) {
            return null;
        }
        return Sale::with(['customer', 'items.product', 'user'])->find($this->selectedSaleId);
    }

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
        $this->selectedSaleId = $saleId;
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->selectedSaleId = null;
    }

    /**
     * Approve a sale and reduce stock
     */
    public function approveSale()
    {
        if (!$this->selectedSaleId) {
            return;
        }

        if ($this->isProcessing) {
            return;
        }

        $this->isProcessing = true;

        try {
            DB::beginTransaction();

            $sale = Sale::with(['items', 'customer'])->find($this->selectedSaleId);

            if (!$sale) {
                $this->isProcessing = false;
                $this->dispatch('show-toast', type: 'error', message: 'Sale not found.');
                $this->closeApproveModal();
                return;
            }

            if ($sale->status !== 'pending') {
                $this->isProcessing = false;
                $this->dispatch('show-toast', type: 'error', message: 'Sale is already processed.');
                $this->closeApproveModal();
                return;
            }

            // Update stock for each item
            foreach ($sale->items as $item) {
                $stock = null;

                // Check if item has variant
                if ($item->variant_id || $item->variant_value) {
                    // Handle variant stock - try multiple approaches
                    $stockQuery = ProductStock::where('product_id', $item->product_id);

                    if ($item->variant_id) {
                        $stockQuery->where('variant_id', $item->variant_id);
                    }
                    if ($item->variant_value) {
                        $stockQuery->where('variant_value', $item->variant_value);
                    }

                    $stock = $stockQuery->first();
                } else {
                    // Handle single product stock - check for null or empty variant_value
                    $stock = ProductStock::where('product_id', $item->product_id)
                        ->where(function ($q) {
                            $q->whereNull('variant_value')
                                ->orWhere('variant_value', '')
                                ->orWhere('variant_value', 'null');
                        })
                        ->whereNull('variant_id')
                        ->first();

                    // If still not found, try just by product_id (single stock product)
                    if (!$stock) {
                        $stock = ProductStock::where('product_id', $item->product_id)->first();
                    }
                }

                if (!$stock) {
                    DB::rollBack();
                    $this->isProcessing = false;
                    Log::error("Stock not found for product: {$item->product_name} (ID: {$item->product_id})");
                    $this->dispatch('show-toast', type: 'error', message: "Stock record not found for {$item->product_name}.");
                    $this->closeApproveModal();
                    return;
                }

                if ($stock->available_stock < $item->quantity) {
                    DB::rollBack();
                    $this->isProcessing = false;
                    $this->dispatch('show-toast', type: 'error', message: "Insufficient stock for {$item->product_name}. Available: {$stock->available_stock}, Required: {$item->quantity}");
                    $this->closeApproveModal();
                    return;
                }

                $stock->available_stock -= $item->quantity;
                $stock->total_stock -= $item->quantity;
                $stock->sold_count = ($stock->sold_count ?? 0) + $item->quantity;
                $stock->save();
            }

            // Update sale status and set due amount
            $sale->status = 'confirm';
            $sale->approved_by = Auth::id();
            $sale->approved_at = now();
            $sale->due_amount = $sale->total_amount;
            $sale->save();

            // Update customer due amount
            if ($sale->customer) {
                $sale->customer->due_amount = ($sale->customer->due_amount ?? 0) + $sale->total_amount;
                $sale->customer->save();
            }

            DB::commit();

            $this->isProcessing = false;
            $this->showApproveModal = false;
            $this->showDetailsModal = false;
            $this->selectedSaleId = null;

            $this->dispatch('show-toast', type: 'success', message: 'Sale approved successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->isProcessing = false;
            Log::error('Sale approval error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->dispatch('show-toast', type: 'error', message: 'Error approving sale: ' . $e->getMessage());
            $this->closeApproveModal();
        }
    }

    public function openRejectModal($saleId)
    {
        $this->selectedSaleId = $saleId;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function closeRejectModal()
    {
        $this->showRejectModal = false;
        $this->selectedSaleId = null;
        $this->rejectionReason = '';
    }

    public function openApproveModal($saleId)
    {
        $this->selectedSaleId = $saleId;
        $this->showApproveModal = true;
    }

    public function closeApproveModal()
    {
        $this->showApproveModal = false;
        $this->selectedSaleId = null;
        $this->isProcessing = false;
    }

    /**
     * Reject a sale
     */
    public function rejectSale()
    {
        if (!$this->selectedSaleId) {
            return;
        }

        $this->validate([
            'rejectionReason' => 'required|min:5',
        ]);

        try {
            $sale = Sale::find($this->selectedSaleId);
            if (!$sale) {
                $this->dispatch('show-toast', type: 'error', message: 'Sale not found.');
                $this->closeRejectModal();
                return;
            }

            $sale->update([
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
