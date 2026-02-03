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

    // Cart
    public $cart = [];

    // Customer
    public $customers = [];
    public $customerId = '';
    public $selectedCustomer = null;

    // Customer Form
    public $showCustomerModal = false;
    public $customerName = '';
    public $customerPhone = '';
    public $customerEmail = '';
    public $customerAddress = '';
    public $customerType = 'regular';
    public $businessName = '';

    // Sale Details
    public $notes = '';
    public $additionalDiscount = 0;
    public $additionalDiscountType = 'fixed';

    // Modal states
    public $showSaleModal = false;
    public $createdSale = null;

    // Edit Mode
    public $editMode = false;
    public $editingSaleId = null;
    public $editingSale = null;

    // Stock Service
    protected StockAvailabilityService $stockService;

    public function boot(StockAvailabilityService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function mount($saleId = null)
    {
        $this->loadCustomers();

        // Load existing sale for editing if saleId provided
        if ($saleId) {
            $this->loadSaleForEditing($saleId);
        }
    }

    public function loadCustomers()
    {
        $this->customers = Customer::orderBy('name')->get();
    }

    /**
     * Load existing sale for editing
     */
    public function loadSaleForEditing($saleId)
    {
        try {
            $sale = Sale::with(['items', 'customer'])->findOrFail($saleId);

            // Only allow editing pending or draft sales
            if (!in_array($sale->status, ['pending', 'draft'])) {
                session()->flash('error', 'Only pending sales can be edited');
                return;
            }

            $this->editMode = true;
            $this->editingSaleId = $sale->id;
            $this->editingSale = $sale;
            $this->customerId = $sale->customer_id;
            $this->selectedCustomer = $sale->customer;
            $this->notes = $sale->notes ?? '';
            $this->additionalDiscount = $sale->discount_amount ?? 0;

            // Load cart items from sale items
            $this->cart = [];
            foreach ($sale->items as $item) {
                $this->cart[] = [
                    'cart_key' => $item->product_id . ($item->variant_value ? '_' . $item->variant_value : ''),
                    'id' => $item->product_id,
                    'variant_id' => $item->variant_id ?? null,
                    'variant_value' => $item->variant_value ?? null,
                    'name' => $item->product_name,
                    'code' => $item->product_code,
                    'price' => $item->unit_price,
                    'distributor_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'discount' => $item->discount_per_unit,
                    'total' => $item->total,
                    'available' => 999, // Doesn't matter for edit mode
                    'image' => '',
                    'is_variant' => $item->variant_id ? true : false,
                ];
            }

            session()->flash('message', 'Sale loaded for editing');
        } catch (\Exception $e) {
            Log::error('Failed to load sale for editing: ' . $e->getMessage());
            session()->flash('error', 'Failed to load sale: ' . $e->getMessage());
        }
    }

    /**
     * Calculate available stock considering pending sales
     * Actual stock - Pending order quantities = Available to sell
     */
    private function getAvailableStock($productId, $variantValue = null)
    {
        $stockInfo = $this->stockService->getAvailableStock($productId);
        $actualAvailable = $stockInfo['available'] ?? 0;

        // Get pending quantity from pending sales for this product
        $pendingQuantity = SaleItem::whereHas('sale', function ($q) {
            $q->where('status', 'pending'); // Only pending sales (awaiting approval)
        })
            ->where('product_id', $productId)
            ->sum('quantity');

        // Return: actual stock minus pending orders
        return max(0, $actualAvailable - $pendingQuantity);
    }

    public function updatedSearch()
    {
        if (strlen($this->search) >= 2) {
            $products = ProductDetail::with(['stock', 'price', 'prices', 'stocks', 'variant'])
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%');
                })
                ->limit(10)
                ->get();

            $this->searchResults = [];

            foreach ($products as $product) {
                if ($product->hasVariants() && $product->variant) {
                    // Product has variants - show each variant as a separate item
                    $variantPrices = $product->prices()->where('pricing_mode', 'variant')->get();
                    $variantStocks = $product->stocks()->whereNotNull('variant_value')->get();

                    foreach ($product->variant->variant_values as $variantValue) {
                        // Check if variant value matches search term or product name matches
                        $variantMatches = stripos($variantValue, $this->search) !== false;
                        $productMatches = stripos($product->name, $this->search) !== false || stripos($product->code, $this->search) !== false;

                        if ($variantMatches || $productMatches) {
                            $variantPrice = $variantPrices->where('variant_value', $variantValue)->first();
                            $variantStock = $variantStocks->where('variant_value', $variantValue)->first();

                            if ($variantPrice && $variantStock) {
                                $price = $variantPrice->distributor_price ?? 0;

                                // Get pending quantity for this variant
                                $pendingQuantity = SaleItem::whereHas('sale', function ($q) {
                                    $q->where('status', 'pending');
                                })
                                    ->where('product_id', $product->id)
                                    ->sum('quantity');

                                $availableStock = max(0, ($variantStock->available_stock ?? 0) - $pendingQuantity);

                                if ($availableStock > 0) {
                                    $this->searchResults[] = [
                                        'id' => $product->id,
                                        'variant_id' => $product->variant_id,
                                        'variant_value' => $variantValue,
                                        'name' => $product->name,
                                        'code' => $product->code,
                                        'display_name' => $product->name . ' (' . $product->variant->variant_name . ': ' . $variantValue . ')',
                                        'price' => $price,
                                        'distributor_price' => $price,
                                        'stock' => $variantStock->total_stock ?? 0,
                                        'available' => $availableStock,
                                        'pending' => $pendingQuantity,
                                        'image' => $product->image ?? '',
                                        'is_variant' => true,
                                    ];
                                }
                            }
                        }
                    }
                } else {
                    // Single product (no variants) - original logic
                    if ($product->stock && $product->stock->available_stock > 0) {
                        $stockInfo = $this->stockService->getAvailableStock($product->id);

                        // Get pending quantity for this product
                        $pendingQuantity = SaleItem::whereHas('sale', function ($q) {
                            $q->where('status', 'pending');
                        })
                            ->where('product_id', $product->id)
                            ->sum('quantity');

                        $availableStock = max(0, ($stockInfo['available'] ?? 0) - $pendingQuantity);

                        $this->searchResults[] = [
                            'id' => $product->id,
                            'variant_id' => null,
                            'variant_value' => null,
                            'name' => $product->name,
                            'code' => $product->code,
                            'display_name' => $product->name,
                            'price' => $product->price->distributor_price ?? 0,
                            'distributor_price' => $product->price->distributor_price ?? 0,
                            'stock' => $stockInfo['stock'],
                            'available' => $availableStock,
                            'pending' => $pendingQuantity,
                            'image' => $product->image ?? '',
                            'is_variant' => false,
                        ];
                    }
                }
            }
        } else {
            $this->searchResults = [];
        }
    }

    public function updatedCustomerId($value)
    {
        if ($value) {
            $this->selectedCustomer = Customer::find($value);
        } else {
            $this->selectedCustomer = null;
        }
    }

    // Customer Management
    public function openCustomerModal()
    {
        $this->resetCustomerForm();
        $this->showCustomerModal = true;
    }

    public function closeCustomerModal()
    {
        $this->showCustomerModal = false;
        $this->resetCustomerForm();
    }

    public function resetCustomerForm()
    {
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerAddress = '';
        $this->customerType = 'regular';
        $this->businessName = '';
    }

    public function createCustomer()
    {
        $this->validate([
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'required|string|max:20',
            'customerEmail' => 'nullable|email|max:255',
            'customerAddress' => 'required|string|max:500',
            'customerType' => 'required|in:regular,wholesale',
        ]);

        try {
            $customer = Customer::create([
                'name' => $this->customerName,
                'phone' => $this->customerPhone,
                'email' => $this->customerEmail,
                'address' => $this->customerAddress,
                'type' => $this->customerType,
                'business_name' => $this->businessName,
            ]);

            $this->customerId = $customer->id;
            $this->selectedCustomer = $customer;
            $this->loadCustomers();
            $this->closeCustomerModal();

            session()->flash('success', 'Customer created successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create customer: ' . $e->getMessage());
        }
    }

    public function addToCart($product)
    {
        // Check available stock
        if (($product['available'] ?? 0) <= 0) {
            session()->flash('error', 'Not enough stock available!');
            return;
        }

        // Create a unique identifier for cart items (product_id + variant info)
        $cartKey = $product['id'] . ($product['is_variant'] ? '_' . $product['variant_value'] : '');

        $existing = collect($this->cart)->firstWhere('cart_key', $cartKey);

        if ($existing) {
            if (($existing['quantity'] + 1) > $product['available']) {
                session()->flash('error', 'Not enough available stock!');
                return;
            }

            $this->cart = collect($this->cart)->map(function ($item) use ($cartKey) {
                if ($item['cart_key'] == $cartKey) {
                    $item['quantity'] += 1;
                    $item['total'] = ($item['price'] - $item['discount']) * $item['quantity'];
                }
                return $item;
            })->toArray();
        } else {
            $this->cart[] = [
                'cart_key' => $cartKey, // Unique identifier for cart items
                'id' => $product['id'],
                'variant_id' => $product['variant_id'] ?? null,
                'variant_value' => $product['variant_value'] ?? null,
                'name' => $product['display_name'], // Use display name which includes variant info
                'code' => $product['code'] ?? '',
                'price' => $product['price'],
                'distributor_price' => $product['distributor_price'] ?? 0,
                'quantity' => 1,
                'discount' => 0,
                'total' => $product['price'],
                'available' => $product['available'],
                'image' => $product['image'] ?? '',
                'is_variant' => $product['is_variant'] ?? false,
            ];
        }

        $this->search = '';
        $this->searchResults = [];
    }

    public function updateQuantity($index, $quantity)
    {
        $quantity = (int)$quantity;
        if ($quantity <= 0) {
            $this->removeFromCart($index);
            return;
        }
        if ($quantity > $this->cart[$index]['available']) {
            session()->flash('error', 'Exceeds available stock!');
            return;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $quantity;
    }

    public function updateDiscount($index, $discount)
    {
        $discount = max(0, min($discount, $this->cart[$index]['price']));
        $this->cart[$index]['discount'] = $discount;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $discount) * $this->cart[$index]['quantity'];
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
        $this->notes = '';
    }

    public function createSale()
    {
        if (!$this->customerId) {
            session()->flash('error', 'Please select a customer');
            return;
        }

        if (empty($this->cart)) {
            session()->flash('error', 'Please add items to cart');
            return;
        }

        try {
            DB::beginTransaction();

            if ($this->editMode && $this->editingSaleId) {
                // Update existing sale
                $sale = Sale::findOrFail($this->editingSaleId);

                $sale->update([
                    'customer_id' => $this->customerId,
                    'subtotal' => $this->subtotal,
                    'discount_amount' => $this->totalDiscount + $this->additionalDiscountAmount,
                    'total_amount' => $this->grandTotal,
                    'notes' => $this->notes,
                ]);

                // Delete existing items
                SaleItem::where('sale_id', $sale->id)->delete();

                // Create new Sale Items
                foreach ($this->cart as $item) {
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $item['id'],
                        'product_code' => $item['code'],
                        'product_name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'discount_per_unit' => $item['discount'],
                        'total_discount' => $item['discount'] * $item['quantity'],
                        'total' => $item['total'],
                    ]);
                }

                DB::commit();
                $this->createdSale = $sale->load(['customer', 'items.product']);
                $this->showSaleModal = true;
                session()->flash('success', 'Sale order updated successfully!');
            } else {
                // Create new sale
                $saleId = Sale::generateSaleId();
                $invoiceNumber = Sale::generateInvoiceNumber();

                // Create Sale (status pending means awaiting admin approval)
                $sale = Sale::create([
                    'sale_id' => $saleId,
                    'invoice_number' => $invoiceNumber,
                    'customer_id' => $this->customerId,
                    'sale_type' => 'staff',
                    'user_id' => Auth::id(),
                    'subtotal' => $this->subtotal,
                    'discount_amount' => $this->totalDiscount + $this->additionalDiscountAmount,
                    'total_amount' => $this->grandTotal,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'notes' => $this->notes,
                ]);

                // Create Sale Items
                foreach ($this->cart as $item) {
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $item['id'],
                        'product_code' => $item['code'],
                        'product_name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'discount_per_unit' => $item['discount'],
                        'total_discount' => $item['discount'] * $item['quantity'],
                        'total' => $item['total'],
                    ]);
                }

                DB::commit();
                $this->createdSale = $sale->load(['customer', 'items.product']);
                $this->showSaleModal = true;
                session()->flash('success', 'Sale order created successfully! Pending admin approval.');
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Sale creation failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to create sale: ' . $e->getMessage());
        }
    }

    public function createNewSale()
    {
        $this->clearCart();
        $this->customerId = '';
        $this->selectedCustomer = null;
        $this->showSaleModal = false;
        $this->createdSale = null;
        $this->editMode = false;
        $this->editingSaleId = null;
        $this->editingSale = null;
    }

    public function cancelEdit()
    {
        $this->createNewSale();
        redirect()->route('salesman.sales');
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
        return max(0, $this->subtotal - $this->totalDiscount - $this->additionalDiscountAmount);
    }

    public function render()
    {
        return view('livewire.salesman.salesman-billing');
    }
}
