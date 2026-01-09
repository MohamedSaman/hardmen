<?php

namespace App\Livewire\Staff;

use App\Models\StaffReturn;
use App\Models\Product;
use App\Models\Customer;
use App\Models\StaffProduct;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StaffReturnManagement extends Component
{
    use WithPagination;

    public $product_id;
    public $customer_id;
    public $quantity = 1;
    public $unit_price;
    public $is_damaged = false;
    public $reason;
    public $notes;

    public $search = '';
    public $selectedProduct;

    protected $rules = [
        'product_id' => 'required|exists:products,id',
        'quantity' => 'required|integer|min:1',
        'unit_price' => 'required|numeric|min:0',
        'is_damaged' => 'boolean',
        'reason' => 'required|string|max:500',
        'notes' => 'nullable|string|max:1000',
    ];

    public function updatedProductId($value)
    {
        if ($value) {
            $product = Product::find($value);
            if ($product) {
                $this->selectedProduct = $product;
                $this->unit_price = $product->productPrices->selling_price ?? 0;
            }
        }
    }

    public function submitReturn()
    {
        $this->validate();

        DB::beginTransaction();
        try {
            // Create the staff return record
            $return = StaffReturn::create([
                'staff_id' => Auth::id(),
                'product_id' => $this->product_id,
                'customer_id' => $this->customer_id,
                'quantity' => $this->quantity,
                'unit_price' => $this->unit_price,
                'total_amount' => $this->quantity * $this->unit_price,
                'is_damaged' => $this->is_damaged,
                'reason' => $this->reason,
                'notes' => $this->notes,
                'status' => 'approved', // Auto-approve for staff
            ]);

            // If not damaged, add back to staff allocated stock
            if (!$this->is_damaged) {
                $staffProduct = StaffProduct::where('staff_id', Auth::id())
                    ->where('product_id', $this->product_id)
                    ->first();

                if ($staffProduct) {
                    // Decrease sold_quantity (adding back to available stock)
                    $staffProduct->decrement('sold_quantity', $this->quantity);
                } else {
                    // If no allocation exists, create one
                    StaffProduct::create([
                        'staff_id' => Auth::id(),
                        'product_id' => $this->product_id,
                        'quantity' => $this->quantity,
                        'sold_quantity' => 0,
                    ]);
                }
            }

            DB::commit();

            session()->flash('message', 'Product return processed successfully. ' .
                ($this->is_damaged ? 'Marked as damaged (non-saleable).' : 'Added back to your stock.'));

            $this->reset(['product_id', 'customer_id', 'quantity', 'unit_price', 'is_damaged', 'reason', 'notes', 'selectedProduct']);
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error processing return: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $products = Product::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('barcode', 'like', '%' . $this->search . '%');
            })
            ->whereHas('staffProducts', function ($query) {
                $query->where('staff_id', Auth::id());
            })
            ->with('productPrices')
            ->paginate(10);

        $customers = Customer::where('user_id', Auth::id())->get();

        return view('livewire.staff.staff-return-management', [
            'products' => $products,
            'customers' => $customers,
        ]);
    }
}
