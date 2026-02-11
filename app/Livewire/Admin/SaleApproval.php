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
            $this->showToast('error', 'No sale selected.');
            return;
        }

        if ($this->isProcessing) {
            $this->showToast('warning', 'Request is already being processed. Please wait.');
            return;
        }

        $this->isProcessing = true;

        try {
            DB::beginTransaction();

            $sale = Sale::with(['items', 'customer'])->find($this->selectedSaleId);

            if (!$sale) {
                DB::rollBack();
                $this->isProcessing = false;
                $this->showToast('error', 'Sale not found.');
                return;
            }

            if ($sale->status !== 'pending') {
                DB::rollBack();
                $this->isProcessing = false;
                $this->showToast('error', 'Sale is already processed. Current status: ' . $sale->status);
                return;
            }

            // Validate sale has items
            if (empty($sale->items) || count($sale->items) === 0) {
                DB::rollBack();
                $this->isProcessing = false;
                $this->showToast('error', 'Sale has no items. Cannot approve.');
                return;
            }

            // Update stock for each item using FIFO method
            foreach ($sale->items as $item) {
                // Skip stock validation for old sales (before Feb 7, 2026) - they're historical data
                $isOldSale = $sale->created_at < now()->subDay();

                try {
                    // Use FIFO stock service to deduct from batches and update product stock
                    FIFOStockService::deductStock(
                        $item->product_id,
                        $item->quantity,
                        $item->variant_id,
                        $item->variant_value
                    );
                } catch (\Exception $e) {
                    if (!$isOldSale) {
                        DB::rollBack();
                        $this->isProcessing = false;
                        $errorMsg = "Stock insufficient for {$item->product_name}: " . $e->getMessage();
                        Log::error("Stock deduction failed for product: {$item->product_name} (ID: {$item->product_id})", [
                            'error' => $e->getMessage(),
                            'sale_id' => $sale->id,
                            'quantity_needed' => $item->quantity
                        ]);
                        $this->showToast('error', $errorMsg);
                        return;
                    }
                    // For old sales, log warning but continue
                    Log::warning("Old sale {$sale->id}: Stock deduction failed for {$item->product_name}, but allowing approval: " . $e->getMessage());
                }
            }

            // Update sale status and set due amount
            $sale->status = 'confirm';
            $sale->approved_by = Auth::id();
            $sale->approved_at = now();

            // Check if payments already exist for this sale
            $existingPayments = \App\Models\Payment::where('sale_id', $sale->id)->sum('amount');
            $sale->due_amount = max(0, $sale->total_amount - $existingPayments);

            if ($sale->due_amount <= 0) {
                $sale->payment_status = 'paid';
            } elseif ($existingPayments > 0) {
                $sale->payment_status = 'partial';
            } else {
                $sale->payment_status = 'pending';
            }

            if (!$sale->save()) {
                throw new \Exception('Failed to save sale status update.');
            }

            // Update customer due amount
            if ($sale->customer && $sale->due_amount > 0) {
                try {
                    $sale->customer->due_amount = ($sale->customer->due_amount ?? 0) + $sale->due_amount;
                    $sale->customer->total_due = ($sale->customer->opening_balance ?? 0) + $sale->customer->due_amount;
                    $sale->customer->save();
                } catch (\Exception $e) {
                    Log::warning("Failed to update customer due amount for sale {$sale->id}: " . $e->getMessage());
                    // Continue with approval anyway, customer update is secondary
                }
            }

            DB::commit();

            $this->isProcessing = false;
            $this->showApproveModal = false;
            $this->showDetailsModal = false;
            $this->selectedSaleId = null;

            $this->showToast('success', 'Sale approved successfully! Stock has been updated.');
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->isProcessing = false;

            $errorMsg = $e->getMessage();
            if (empty($errorMsg) || strlen($errorMsg) < 5) {
                $errorMsg = 'An unexpected error occurred while approving the sale. Please try again.';
            }

            Log::error('Sale approval error for sale ID: ' . $this->selectedSaleId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->showToast('error', $errorMsg);
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
            $this->showToast('error', 'No sale selected.');
            return;
        }

        $this->validate([
            'rejectionReason' => 'required|min:5',
        ]);

        try {
            $sale = Sale::find($this->selectedSaleId);
            if (!$sale) {
                $this->showToast('error', 'Sale not found.');
                return;
            }

            if ($sale->status !== 'pending') {
                $this->showToast('error', 'Only pending sales can be rejected. Current status: ' . $sale->status);
                return;
            }

            $result = $sale->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => $this->rejectionReason,
            ]);

            if (!$result) {
                throw new \Exception('Failed to update sale status.');
            }

            $this->closeRejectModal();
            $this->closeDetailsModal();
            $this->showToast('success', 'Sale rejected successfully with reason recorded.');
        } catch (\Exception $e) {
            Log::error('Sale rejection error for sale ID: ' . $this->selectedSaleId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->showToast('error', 'Error rejecting sale: ' . $e->getMessage());
        }
    }

    /**
     * Show toast notification with custom styling
     * 
     * @param string $type - 'success', 'error', 'warning', 'info'
     * @param string $message - The message to display
     */
    private function showToast($type, $message)
    {
        $bgColors = [
            'success' => '#10b981',
            'error' => '#ef4444',
            'warning' => '#f59e0b',
            'info' => '#3b82f6',
        ];

        $icons = [
            'success' => '✓',
            'error' => '✕',
            'warning' => '⚠',
            'info' => 'ℹ',
        ];

        $bg = $bgColors[$type] ?? $bgColors['info'];
        $icon = $icons[$type] ?? $icons['info'];

        $escapedMessage = addslashes($message);

        $this->js("
            const toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;top:20px;right:20px;background:{$bg};color:white;padding:16px 24px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:9999;font-size:14px;font-weight:600;display:flex;align-items:center;gap:12px;animation:slideIn 0.3s ease;min-width:300px;max-width:500px;';
            toast.innerHTML = '<span style=\"font-size:20px;font-weight:bold;\">{$icon}</span><span>{$escapedMessage}</span>';
            document.body.appendChild(toast);
            
            const style = document.createElement('style');
            style.textContent = '@keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } } @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(400px); opacity: 0; } }';
            document.head.appendChild(style);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        ");
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
