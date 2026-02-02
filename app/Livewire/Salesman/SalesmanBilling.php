<?php

namespace App\Livewire\Salesman;

use App\Models\Customer;
use App\Models\ProductDetail;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\StockAvailabilityService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Title('Create Sales Order')]
#[Layout('components.layouts.salesman')]
class SalesmanBilling extends Component
{
    // Search and Products
    public $search = '';
    public $searchResults = [];
    public $products = [];

    // Cart
    public $cart = [];

    // Customer
    public $customers = [];
    public $customerId = '';
    public $selectedCustomer = null;

    // Sale Details
    public $notes = '';
    public $additionalDiscount = 0;
    public $additionalDiscountType = 'fixed';
    public $priceType = 'retail';

    // Stock Service
    protected StockAvailabilityService $stockService;

    public function boot(StockAvailabilityService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function mount()
    {
        $this->loadCustomers();
        $this->loadProducts();
    }

    public function loadCustomers()
    {
        $this->customers = Customer::orderBy('name')->get();
    }

    public function loadProducts()
    {
        $query = ProductDetail::with(['stock', 'price', 'stocks', 'prices'])
            ->where(function ($q) {
                $q->whereHas('stock', function ($sq) {
                    $sq->where('available_stock', '>', 0);
                })->orWhereHas('stocks', function ($sq) {
                    $sq->where('available_stock', '>', 0);
                });
            });

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        $rawProducts = $query->limit(20)->get();

        $this->products = $rawProducts->map(function ($product) {
            $stock = $product->stock->available_stock ?? 0;
            $stockInfo = $this->stockService->getAvailableStock($product->id);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'price' => $this->priceType === 'retail'
                    ? ($product->price->retail_price ?? 0)
                    : ($product->price->wholesale_price ?? 0),
                'stock' => $stockInfo['stock'],
                'available' => $stockInfo['available'],
                'pending' => $stockInfo['pending'],
                'image' => $product->image ?? '',
            ];
        })->toArray();
    }

    public function updatedSearch()
    {
        $this->loadProducts();
    }

    public function updatedPriceType()
    {
        $this->loadProducts();
    }

    public function updatedCustomerId($value)
    {
        if ($value) {
            $this->selectedCustomer = Customer::find($value);
        } else {
            $this->selectedCustomer = null;
        }
    }

    public function addToCart($product)
    {
        // Check available stock
        if (($product['available'] ?? 0) <= 0) {
            $this->dispatch('show-toast', type: 'error', message: 'Not enough stock available!');
            return;
        }

        $existing = collect($this->cart)->firstWhere('id', $product['id']);

        if ($existing) {
            if (($existing['quantity'] + 1) > $product['available']) {
                $this->dispatch('show-toast', type: 'error', message: 'Not enough available stock!');
                return;
            }

            $this->cart = collect($this->cart)->map(function ($item) use ($product) {
                if ($item['id'] == $product['id']) {
                    $item['quantity'] += 1;
                    $item['total'] = ($item['price'] - $item['discount']) * $item['quantity'];
                }
                return $item;
            })->toArray();
        } else {
            $this->cart[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'code' => $product['code'] ?? '',
                'price' => $product['price'],
                'quantity' => 1,
                'discount' => 0,
                'total' => $product['price'],
                'available' => $product['available'],
                'image' => $product['image'] ?? '',
            ];
        }
    }

    public function updateQuantity($index, $quantity)
    {
        if ($quantity < 1) $quantity = 1;
        if ($quantity > $this->cart[$index]['available']) {
            $this->dispatch('show-toast', type: 'error', message: 'Exceeds available stock!');
            return;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $quantity;
    }

    public function removeFromCart($index)
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->additionalDiscount = 0;
    }

    // Computed Properties
    public function getSubtotalProperty()
    {
        return collect($this->cart)->sum('total');
    }

    public function getTotalDiscountProperty()
    {
        return collect($this->cart)->sum(function ($item) {
            return $item['discount'] * $item['quantity'];
        });
    }

    public function getAdditionalDiscountAmountProperty()
    {
        if ($this->additionalDiscountType === 'percentage') {
            return ($this->subtotal * $this->additionalDiscount) / 100;
        }
        return min($this->additionalDiscount, $this->subtotal);
    }

    public function getGrandTotalProperty()
    {
        return $this->subtotal - $this->additionalDiscountAmount;
    }

    /**
     * Create sale with PENDING status (requires admin approval)
     */
    public function createSale()
    {
        if (empty($this->cart)) {
            $this->dispatch('show-toast', type: 'error', message: 'Please add products to cart!');
            return;
        }

        if (!$this->customerId) {
            $this->dispatch('show-toast', type: 'error', message: 'Please select a customer!');
            return;
        }

        try {
            DB::beginTransaction();

            $customer = Customer::find($this->customerId);

            // Create sale with PENDING status
            $sale = Sale::create([
                'sale_id' => Sale::generateSaleId(),
                'invoice_number' => Sale::generateInvoiceNumber(),
                'customer_id' => $customer->id,
                'customer_type' => $customer->type,
                'subtotal' => $this->subtotal,
                'discount_amount' => $this->totalDiscount + $this->additionalDiscountAmount,
                'total_amount' => $this->grandTotal,
                'payment_type' => 'partial', // Will be updated after payment
                'payment_status' => 'pending',
                'status' => 'pending', // Requires admin approval
                'due_amount' => $this->grandTotal,
                'notes' => $this->notes,
                'user_id' => Auth::id(),
                'sale_type' => 'pos',
                'delivery_status' => 'pending',
            ]);

            // Create sale items (stock NOT reduced until approved)
            foreach ($this->cart as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['id'],
                    'product_code' => $item['code'] ?? '',
                    'product_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'discount_per_unit' => $item['discount'],
                    'total_discount' => $item['discount'] * $item['quantity'],
                    'total' => $item['total'],
                ]);
            }

            DB::commit();

            // Reset cart
            $this->cart = [];
            $this->notes = '';
            $this->additionalDiscount = 0;
            $this->customerId = '';
            $this->selectedCustomer = null;
            $this->loadProducts();

            $this->dispatch('show-toast', type: 'success', message: 'Sales order created successfully! Pending admin approval.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Salesman billing error: ' . $e->getMessage());
            $this->dispatch('show-toast', type: 'error', message: 'Error creating sale: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.salesman.salesman-billing');
    }
}
