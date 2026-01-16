<?php

namespace App\Livewire\Staff;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\SaleItem;
use App\Models\StaffProduct;
use App\Models\ProductStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

#[Layout('components.layouts.staff')]
#[Title('Add New Sale')]
class StaffSalesSystem extends Component
{
    public $search = '';
    public $cart = [];
    public $customers = [];
    public $customerId;
    public $paidAmount = 0;
    public $notes = '';
    public $discount = 0;
    public $discountType = 'fixed'; // 'fixed' or 'percentage'

    // Customer modal
    public $showCustomerModal = false;
    public $customerName = '';
    public $customerPhone = '';
    public $customerEmail = '';
    public $customerAddress = '';
    public $customerType = 'retail';

    // Sale complete modal
    public $showSaleModal = false;
    public $createdSale = null;

    public function mount()
    {
        $this->loadCustomers();
        $this->setDefaultCustomer();
    }

    public function loadCustomers()
    {
        $walkingCustomer = Customer::where('name', 'Walking Customer')->first();
        $staffCustomers = Customer::where('created_by', Auth::id())->orderBy('name')->get();

        // Combine Walking Customer (if exists) with staff's own customers
        if ($walkingCustomer) {
            $this->customers = collect([$walkingCustomer])->merge($staffCustomers);
        } else {
            $this->customers = $staffCustomers;
        }
    }

    public function setDefaultCustomer()
    {
        $walkingCustomer = Customer::where('name', 'Walking Customer')->first();
        if ($walkingCustomer) {
            $this->customerId = $walkingCustomer->id;
        }
    }

    public function addToCart($product)
    {
        $productData = is_array($product) ? $product : json_decode($product, true);

        // Check if product already exists in cart
        $existingIndex = collect($this->cart)->search(function ($item) use ($productData) {
            return $item['id'] == $productData['id'];
        });

        if ($existingIndex !== false) {
            // Increase quantity if exists
            $this->cart[$existingIndex]['quantity']++;
            $this->cart[$existingIndex]['total'] = $this->cart[$existingIndex]['quantity'] * $this->cart[$existingIndex]['price'];
        } else {
            // Add new item to cart
            $this->cart[] = [
                'id' => $productData['id'],
                'name' => $productData['name'],
                'code' => $productData['code'] ?? '',
                'model' => $productData['model'] ?? '',
                'price' => $productData['price'],
                'quantity' => 1,
                'stock' => $productData['stock'],
                'total' => $productData['price'],
                'image' => $productData['image'] ?? asset('images/default-product.png'),
                'key' => uniqid(),
            ];
        }

        // Clear search after adding product
        $this->search = '';
    }

    public function updateQuantity($index, $quantity)
    {
        if ($quantity <= 0) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
            return;
        }

        if ($quantity > $this->cart[$index]['stock']) {
            $this->dispatch('showToast', [
                'type' => 'warning',
                'message' => 'Quantity exceeds available stock!'
            ]);
            $quantity = $this->cart[$index]['stock'];
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['total'] = $quantity * $this->cart[$index]['price'];
    }

    public function updatePrice($index, $price)
    {
        $price = max(0, (float)$price);
        $this->cart[$index]['price'] = $price;
        $this->cart[$index]['total'] = $this->cart[$index]['quantity'] * $price;
    }

    public function removeFromCart($index)
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function decrementQuantity($index)
    {
        if (isset($this->cart[$index])) {
            $newQty = $this->cart[$index]['quantity'] - 1;
            $this->updateQuantity($index, $newQty);
        }
    }

    public function incrementQuantity($index)
    {
        if (isset($this->cart[$index])) {
            $newQty = $this->cart[$index]['quantity'] + 1;
            $this->updateQuantity($index, $newQty);
        }
    }

    public function clearCart()
    {
        $this->cart = [];
    }

    public function getSubtotalProperty()
    {
        return collect($this->cart)->sum('total');
    }

    public function getDiscountAmountProperty()
    {
        if ($this->discountType === 'percentage') {
            return ($this->subtotal * $this->discount) / 100;
        }
        return $this->discount;
    }

    public function getGrandTotalProperty()
    {
        return max(0, $this->subtotal - $this->discountAmount);
    }

    public function getDueAmountProperty()
    {
        return max(0, $this->grandTotal - $this->paidAmount);
    }

    public function toggleDiscountType()
    {
        $this->discountType = $this->discountType === 'fixed' ? 'percentage' : 'fixed';
        $this->discount = 0;
    }

    public function removeDiscount()
    {
        $this->discount = 0;
    }

    public function openCustomerModal()
    {
        $this->showCustomerModal = true;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerAddress = '';
        $this->customerType = 'retail';
    }

    public function closeCustomerModal()
    {
        $this->showCustomerModal = false;
        $this->resetErrorBag();
    }

    public function createCustomer()
    {
        $this->validate([
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'required|string|max:20',
            'customerAddress' => 'required|string|max:500',
            'customerEmail' => 'nullable|email|max:255',
            'customerType' => 'required|in:retail,wholesale',
        ]);

        try {
            $customer = Customer::create([
                'name' => $this->customerName,
                'phone' => $this->customerPhone,
                'email' => $this->customerEmail,
                'address' => $this->customerAddress,
                'type' => $this->customerType,
                'created_by' => Auth::id(),
            ]);

            $this->loadCustomers();
            $this->customerId = $customer->id;
            $this->closeCustomerModal();

            $this->dispatch('showToast', [
                'type' => 'success',
                'message' => 'Customer created successfully!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('showToast', [
                'type' => 'error',
                'message' => 'Error creating customer: ' . $e->getMessage()
            ]);
        }
    }

    public function validateAndCreateSale()
    {
        if (empty($this->cart)) {
            $this->dispatch('showToast', [
                'type' => 'error',
                'message' => 'Cart is empty!'
            ]);
            return;
        }

        if (!$this->customerId) {
            $this->dispatch('showToast', [
                'type' => 'error',
                'message' => 'Please select a customer!'
            ]);
            return;
        }

        // Check stock availability for all items
        foreach ($this->cart as $item) {
            $staffProducts = StaffProduct::where('product_id', $item['id'])
                ->where('staff_id', Auth::id())
                ->get();

            $totalRemaining = $staffProducts->sum(function ($sp) {
                return $sp->quantity - $sp->sold_quantity;
            });

            if ($totalRemaining < $item['quantity']) {
                $this->dispatch('showToast', [
                    'type' => 'error',
                    'message' => "Insufficient stock for {$item['name']}"
                ]);
                return;
            }
        }

        $this->createSale();
    }

    public function createSale()
    {
        try {
            DB::transaction(function () {
                // Calculate totals
                $subtotal = collect($this->cart)->sum('total');
                $discountAmount = $this->discountType === 'percentage'
                    ? ($subtotal * $this->discount) / 100
                    : $this->discount;
                $grandTotal = max(0, $subtotal - $discountAmount);
                $dueAmount = max(0, $grandTotal - $this->paidAmount);

                // Determine payment status
                $paymentStatus = 'pending';
                if ($this->paidAmount >= $grandTotal) {
                    $paymentStatus = 'paid';
                } elseif ($this->paidAmount > 0) {
                    $paymentStatus = 'partial';
                }

                // Create sale
                $sale = Sale::create([
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'customer_id' => $this->customerId,
                    'user_id' => Auth::id(),
                    'sale_type' => 'staff',
                    'total_amount' => $grandTotal,
                    'discount' => $discountAmount,
                    'payment_type' => 'cash',
                    'payment_status' => $paymentStatus,
                    'paid_amount' => $this->paidAmount,
                    'due_amount' => $dueAmount,
                    'notes' => $this->notes,
                    'status' => 'completed',
                ]);

                // Create sale items and update staff product quantities
                foreach ($this->cart as $item) {
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $item['id'],
                        'product_name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'total_price' => $item['total'],
                    ]);

                    // Reduce staff product allocation using FIFO
                    $this->reduceStaffProduct($item['id'], $item['quantity']);
                }

                $this->createdSale = $sale->load(['customer', 'items', 'user']);
                $this->showSaleModal = true;

                // Clear cart
                $this->cart = [];
                $this->paidAmount = 0;
                $this->notes = '';
                $this->discount = 0;
                $this->setDefaultCustomer();

                $this->dispatch('showToast', [
                    'type' => 'success',
                    'message' => 'Sale created successfully!'
                ]);
            });
        } catch (\Exception $e) {
            $this->dispatch('showToast', [
                'type' => 'error',
                'message' => 'Error creating sale: ' . $e->getMessage()
            ]);
        }
    }

    private function reduceStaffProduct($productId, $quantity)
    {
        $remaining = $quantity;

        // Get staff products allocated to this staff member, ordered by date (FIFO)
        $staffProducts = StaffProduct::where('product_id', $productId)
            ->where('staff_id', Auth::id())
            ->whereRaw('quantity - sold_quantity > 0')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($staffProducts as $staffProduct) {
            if ($remaining <= 0) break;

            $remainingQty = $staffProduct->quantity - $staffProduct->sold_quantity;
            $deductQty = min($remaining, $remainingQty);

            $staffProduct->sold_quantity += $deductQty;

            $staffProduct->save();
            $remaining -= $deductQty;
        }
    }

    private function generateInvoiceNumber()
    {
        $prefix = 'INV-';
        $lastSale = Sale::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastSale) {
            $lastNumber = intval(substr($lastSale->invoice_number, strlen($prefix)));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function downloadInvoice()
    {
        if ($this->createdSale) {
            $pdf = Pdf::loadView('pdf.invoice', ['sale' => $this->createdSale]);
            return response()->streamDownload(
                fn() => print($pdf->output()),
                'invoice-' . $this->createdSale->invoice_number . '.pdf'
            );
        }
    }

    public function createNewSale()
    {
        $this->showSaleModal = false;
        $this->createdSale = null;
    }

    public function closeModal()
    {
        $this->showSaleModal = false;
        $this->createdSale = null;
    }

    public function render()
    {
        // Get only staff allocated products
        $products = StaffProduct::with(['product.category', 'product.brand'])
            ->where('staff_id', Auth::id())
            ->whereRaw('quantity - sold_quantity > 0')
            ->when($this->search, function ($query) {
                $query->whereHas('product', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%')
                        ->orWhere('model', 'like', '%' . $this->search . '%');
                });
            })
            ->get()
            ->map(function ($staffProduct) {
                $product = $staffProduct->product;
                $remainingQty = $staffProduct->quantity - $staffProduct->sold_quantity;
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'model' => $product->model,
                    'price' => $product->selling_price,
                    'stock' => $remainingQty,
                    'image' => $product->image ? asset('storage/' . $product->image) : asset('images/default-product.png'),
                    'category' => $product->category->category_name ?? 'N/A',
                    'brand' => $product->brand->brand_name ?? 'N/A',
                ];
            });

        // Calculate totals
        $subtotal = collect($this->cart)->sum('total');
        $discountAmount = $this->discountType === 'percentage'
            ? ($subtotal * $this->discount) / 100
            : $this->discount;
        $grandTotal = max(0, $subtotal - $discountAmount);
        $dueAmount = max(0, $grandTotal - $this->paidAmount);

        return view('livewire.staff.staff-sales-system', [
            'products' => $products,
            'subtotal' => $subtotal,
            'discountAmount' => $discountAmount,
            'grandTotal' => $grandTotal,
            'dueAmount' => $dueAmount,
        ]);
    }
}
