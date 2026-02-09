<?php

namespace App\Livewire\Salesman;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ReturnsProduct;
use App\Models\ProductStock;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Title('My Sales')]
#[Layout('components.layouts.salesman')]
class SalesmanSalesList extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $deliveryFilter = '';
    public $selectedSale = null;
    public $showDetailsModal = false;

    // Edit sale properties
    public $showEditModal = false;
    public $editingSale = null;
    public $editItems = [];
    public $editNotes = '';
    public $editDiscount = 0;

    // Return properties
    public $showReturnModal = false;
    public $returnItems = [];
    public $returnNotes = '';
    public $saleReturns = [];

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
        $this->selectedSale = Sale::with(['customer', 'items.product', 'payments', 'approvedBy', 'deliveredBy', 'returns.product'])
            ->find($saleId);

        // Load returns for this sale
        $this->saleReturns = ReturnsProduct::where('sale_id', $saleId)
            ->with('product')
            ->get();

        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->selectedSale = null;
        $this->saleReturns = [];
    }

    // Edit Sale Methods
    public function openEditModal($saleId)
    {
        $sale = Sale::with(['items', 'customer'])->find($saleId);

        if (!$sale) {
            $this->dispatch('show-toast', type: 'error', message: 'Sale not found.');
            return;
        }

        // Only allow editing of pending sales
        if ($sale->status !== 'pending') {
            $this->dispatch('show-toast', type: 'error', message: 'Only pending sales can be edited.');
            return;
        }

        // Only allow editing own sales
        if ($sale->user_id !== Auth::id()) {
            $this->dispatch('show-toast', type: 'error', message: 'You can only edit your own sales.');
            return;
        }

        $this->editingSale = $sale;
        $this->editNotes = $sale->notes ?? '';
        $this->editDiscount = $sale->discount_amount ?? 0;

        // Prepare edit items
        $this->editItems = [];
        foreach ($sale->items as $item) {
            $this->editItems[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount_per_unit ?? 0,
            ];
        }

        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingSale = null;
        $this->editItems = [];
        $this->editNotes = '';
        $this->editDiscount = 0;
    }

    public function updateEditItemQuantity($index, $quantity)
    {
        if (isset($this->editItems[$index])) {
            $this->editItems[$index]['quantity'] = max(1, (int)$quantity);
        }
    }

    public function removeEditItem($index)
    {
        if (count($this->editItems) > 1) {
            unset($this->editItems[$index]);
            $this->editItems = array_values($this->editItems);
        } else {
            $this->dispatch('show-toast', type: 'error', message: 'Sale must have at least one item.');
        }
    }

    public function getEditSubtotalProperty()
    {
        $subtotal = 0;
        foreach ($this->editItems as $item) {
            $subtotal += ($item['unit_price'] - ($item['discount'] ?? 0)) * $item['quantity'];
        }
        return $subtotal;
    }

    public function getEditTotalProperty()
    {
        return $this->editSubtotal - $this->editDiscount;
    }

    public function saveEditedSale()
    {
        if (!$this->editingSale || $this->editingSale->status !== 'pending') {
            $this->dispatch('show-toast', type: 'error', message: 'Cannot update this sale.');
            return;
        }

        try {
            DB::beginTransaction();

            // Update sale items
            foreach ($this->editItems as $editItem) {
                $saleItem = SaleItem::find($editItem['id']);
                if ($saleItem) {
                    $newTotal = ($editItem['unit_price'] - ($editItem['discount'] ?? 0)) * $editItem['quantity'];
                    $saleItem->update([
                        'quantity' => $editItem['quantity'],
                        'discount_per_unit' => $editItem['discount'] ?? 0,
                        'total' => $newTotal,
                    ]);
                }
            }

            // Recalculate sale totals
            $subtotal = $this->editSubtotal;
            $total = $this->editTotal;

            $this->editingSale->update([
                'subtotal' => $subtotal,
                'discount_amount' => $this->editDiscount,
                'total_amount' => $total,
                'due_amount' => $total - ($this->editingSale->paid_amount ?? 0),
                'notes' => $this->editNotes,
            ]);

            DB::commit();

            $this->closeEditModal();
            $this->dispatch('show-toast', type: 'success', message: 'Sale updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale edit error: ' . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Error updating sale.');
        }
    }

    // Return Methods
    public function openReturnModal($saleId)
    {
        $sale = Sale::with(['items.product', 'customer'])->find($saleId);

        if (!$sale) {
            $this->dispatch('show-toast', type: 'error', message: 'Sale not found.');
            return;
        }

        // Only allow returns on approved/confirmed sales
        if ($sale->status !== 'confirm') {
            $this->dispatch('show-toast', type: 'error', message: 'Returns can only be made on approved sales.');
            return;
        }

        $this->selectedSale = $sale;
        $this->returnNotes = '';

        // Prepare return items
        $this->returnItems = [];
        foreach ($sale->items as $item) {
            // Calculate already returned quantity
            $returnedQty = ReturnsProduct::where('sale_id', $saleId)
                ->where('product_id', $item->product_id)
                ->sum('return_quantity');

            $availableQty = $item->quantity - $returnedQty;

            $this->returnItems[] = [
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'original_qty' => $item->quantity,
                'returned_qty' => $returnedQty,
                'available_qty' => $availableQty,
                'return_qty' => 0,
                'unit_price' => $item->unit_price,
            ];
        }

        $this->showReturnModal = true;
    }

    public function closeReturnModal()
    {
        $this->showReturnModal = false;
        $this->returnItems = [];
        $this->returnNotes = '';
    }

    public function updateReturnQty($index, $qty)
    {
        if (isset($this->returnItems[$index])) {
            $maxQty = $this->returnItems[$index]['available_qty'];
            $this->returnItems[$index]['return_qty'] = max(0, min((int)$qty, $maxQty));
        }
    }

    public function getReturnTotalProperty()
    {
        $total = 0;
        foreach ($this->returnItems as $item) {
            $total += $item['return_qty'] * $item['unit_price'];
        }
        return $total;
    }

    public function processReturn()
    {
        if (!$this->selectedSale) {
            return;
        }

        // Check if any items are being returned
        $hasReturns = false;
        foreach ($this->returnItems as $item) {
            if ($item['return_qty'] > 0) {
                $hasReturns = true;
                break;
            }
        }

        if (!$hasReturns) {
            $this->dispatch('show-toast', type: 'error', message: 'Please select at least one item to return.');
            return;
        }

        try {
            DB::beginTransaction();

            $totalReturnAmount = 0;

            foreach ($this->returnItems as $item) {
                if ($item['return_qty'] > 0) {
                    $returnAmount = $item['return_qty'] * $item['unit_price'];
                    $totalReturnAmount += $returnAmount;

                    // Create return record
                    ReturnsProduct::create([
                        'sale_id' => $this->selectedSale->id,
                        'product_id' => $item['product_id'],
                        'return_quantity' => $item['return_qty'],
                        'selling_price' => $item['unit_price'],
                        'total_amount' => $returnAmount,
                        'notes' => $this->returnNotes,
                        'return_type' => 'customer',
                        'user_id' => Auth::id(),
                    ]);

                    // Update stock (add back returned items)
                    $stock = ProductStock::where('product_id', $item['product_id'])->first();
                    if ($stock) {
                        $stock->available_stock += $item['return_qty'];
                        $stock->updateTotals();
                    }
                }
            }

            // Update sale amounts
            $newTotal = $this->selectedSale->total_amount - $totalReturnAmount;
            $newDue = $this->selectedSale->due_amount - $totalReturnAmount;
            if ($newDue < 0) $newDue = 0;

            $this->selectedSale->update([
                'total_amount' => $newTotal,
                'due_amount' => $newDue,
            ]);

            DB::commit();

            $this->closeReturnModal();
            $this->dispatch('show-toast', type: 'success', message: 'Return processed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Return processing error: ' . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Error processing return.');
        }
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
