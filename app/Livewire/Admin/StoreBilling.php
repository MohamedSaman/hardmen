<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use App\Models\Customer;
use App\Models\ProductDetail;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Cheque;
use App\Models\POSSession;
use App\Services\FIFOStockService;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

#[Title('POS')]
class StoreBilling extends Component
{
    use WithFileUploads;

    // POS Session Management
    public $currentSession = null;
    public $showCloseRegisterModal = false;
    public $closeRegisterCash = 0;
    public $closeRegisterNotes = '';

    // Opening Cash Modal
    public $showOpeningCashModal = false;
    public $openingCashAmount = 0;

    // Session Summary Data
    public $sessionSummary = [];

    // Basic Properties
    public $search = '';
    public $searchResults = [];
    public $customerId = '';

    // Cart Items
    public $cart = [];

    // Customer Properties
    public $customers = [];
    public $selectedCustomer = null;

    // Customer Form (for new customer - only used in modal)
    public $customerName = '';
    public $customerPhone = '';
    public $customerEmail = '';
    public $customerAddress = '';
    public $customerType = 'retail';
    public $businessName = '';

    // Sale Properties
    public $notes = '';

    // Payment Properties
    public $paymentMethod = 'cash'; // 'cash', 'credit', 'cheque', 'bank_transfer'
    public $paidAmount = 0;

    // Cash Payment
    public $cashAmount = 0;

    // Cheque Payment
    public $cheques = [];
    public $tempChequeNumber = '';
    public $tempBankName = '';
    public $tempChequeDate = '';
    public $tempChequeAmount = 0;

    // Bank Transfer Payment
    public $bankTransferAmount = 0;
    public $bankTransferBankName = '';
    public $bankTransferReferenceNumber = '';

    // Discount Properties
    public $additionalDiscount = 0;
    public $additionalDiscountType = 'fixed'; // 'fixed' or 'percentage'

    // Modals
    public $showSaleModal = false;
    public $showCustomerModal = false;
    public $showPaymentConfirmModal = false;
    public $lastSaleId = null;
    public $createdSale = null;
    public $pendingDueAmount = 0;

    public function mount()
    {
        // Check for yesterday's open session - auto-close it
        $yesterdaySession = POSSession::where('user_id', Auth::id())
            ->whereDate('session_date', now()->subDay()->toDateString())
            ->where('status', 'open')
            ->first();

        if ($yesterdaySession) {
            // Auto-close yesterday's session
            try {
                DB::beginTransaction();

                // Calculate yesterday's summary
                $yesterday = now()->subDay()->toDateString();

                // Get yesterday's POS sales
                $yesterdaySales = Sale::whereDate('created_at', $yesterday)
                    ->where('sale_type', 'pos')
                    ->pluck('id');

                $cashPayments = Payment::whereIn('sale_id', $yesterdaySales)
                    ->where('payment_method', 'cash')
                    ->sum('amount');

                $totalSales = Sale::whereDate('created_at', $yesterday)
                    ->where('sale_type', 'pos')
                    ->sum('total_amount');

                $expenses = DB::table('expenses')
                    ->whereDate('date', $yesterday)
                    ->sum('amount');

                $refunds = DB::table('returns_products')
                    ->whereDate('created_at', $yesterday)
                    ->sum('total_amount');

                $deposits = DB::table('deposits')
                    ->whereDate('date', $yesterday)
                    ->sum('amount');

                // Calculate expected closing cash
                $expectedClosingCash = $yesterdaySession->opening_cash + $cashPayments - $expenses - $refunds - $deposits;

                // Close the session
                $yesterdaySession->update([
                    'closing_cash' => $expectedClosingCash,
                    'total_sales' => $totalSales,
                    'cash_sales' => $cashPayments,
                    'expenses' => $expenses,
                    'refunds' => $refunds,
                    'cash_deposit_bank' => $deposits,
                    'status' => 'closed',
                    'closed_at' => now(),
                    'notes' => 'Auto-closed at midnight',
                ]);

                DB::commit();

                Log::info("Auto-closed yesterday's POS session for user: " . Auth::id());
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to auto-close yesterday's session: " . $e->getMessage());
            }
        }

        // Check for open session
        $this->currentSession = POSSession::getTodaySession(Auth::id());

        // If no session exists OR session is closed, show opening cash modal
        // This ensures modal shows on:
        // 1. First time opening POS each day (no session exists)
        // 2. After closing and reopening POS (session exists but is closed)
        if (!$this->currentSession || $this->currentSession->isClosed()) {

            // Check if there's an existing session for today (closed or open)
            $todaySession = POSSession::where('user_id', Auth::id())
                ->whereDate('session_date', now()->toDateString())
                ->first();

            if ($todaySession) {
                // If session exists (reopening scenario), use the session's opening cash
                $this->openingCashAmount = $todaySession->opening_cash;
            } else {
                // If no session exists (first time opening), get from cash_in_hands table
                $cashInHandRecord = DB::table('cash_in_hands')
                    ->whereIn('key', ['cash_amount', 'cash in hand'])
                    ->first();
                $this->openingCashAmount = $cashInHandRecord ? $cashInHandRecord->value : 0;
            }

            // Show opening cash modal
            $this->showOpeningCashModal = true;
        }

        $this->loadCustomers();
        $this->setDefaultCustomer();
        $this->tempChequeDate = now()->format('Y-m-d');
    }

    /**
     * Update Cash in Hands Table
     * Add for cash payments, subtract for expenses
     */
    private function updateCashInHands($amount)
    {
        // Update cash_amount record
        $cashAmountRecord = DB::table('cash_in_hands')->where('key', 'cash_amount')->first();

        if ($cashAmountRecord) {
            // Update existing record
            DB::table('cash_in_hands')
                ->where('key', 'cash_amount')
                ->update([
                    'value' => $cashAmountRecord->value + $amount,
                    'updated_at' => now()
                ]);
        } else {
            // Create new record
            DB::table('cash_in_hands')->insert([
                'key' => 'cash_amount',
                'value' => $amount,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Also update cash_in_hand record
        $cashInHandRecord = DB::table('cash_in_hands')->where('key', 'cash in hand')->first();

        if ($cashInHandRecord) {
            // Update existing record
            DB::table('cash_in_hands')
                ->where('key', 'cash in hand')
                ->update([
                    'value' => $cashInHandRecord->value + $amount,
                    'updated_at' => now()
                ]);
        } else {
            // Create new record
            DB::table('cash_in_hands')->insert([
                'key' => 'cash in hand',
                'value' => $amount,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    // Set default walking customer
    public function setDefaultCustomer()
    {
        // Find or create walking customer (only one)
        $walkingCustomer = Customer::where('name', 'Walking Customer')->first();

        if (!$walkingCustomer) {
            $walkingCustomer = Customer::create([
                'name' => 'Walking Customer',
                'phone' => 'xxxxx', // Empty phone number
                'email' => null,
                'address' => 'xxxxx',
                'type' => 'retail',
                'business_name' => null,
            ]);

            $this->loadCustomers(); // Reload customers after creating new one
        }

        $this->customerId = $walkingCustomer->id;
        $this->selectedCustomer = $walkingCustomer;
    }

    // Load customers for dropdown
    public function loadCustomers()
    {
        $this->customers = Customer::orderBy('name')->get();
    }

    // Computed Properties for Totals
    public function getSubtotalProperty()
    {
        return collect($this->cart)->sum('total');
    }

    public function getTotalDiscountProperty()
    {
        return collect($this->cart)->sum(function ($item) {
            return ($item['discount'] * $item['quantity']);
        });
    }

    public function getSubtotalAfterItemDiscountsProperty()
    {
        return $this->subtotal;
    }

    public function getAdditionalDiscountAmountProperty()
    {
        if (empty($this->additionalDiscount) || $this->additionalDiscount <= 0) {
            return 0;
        }

        if ($this->additionalDiscountType === 'percentage') {
            return ($this->subtotalAfterItemDiscounts * $this->additionalDiscount) / 100;
        }

        return min($this->additionalDiscount, $this->subtotalAfterItemDiscounts);
    }

    public function getGrandTotalProperty()
    {
        return $this->subtotalAfterItemDiscounts - $this->additionalDiscountAmount;
    }

    public function getTotalPaidAmountProperty()
    {
        $total = 0;

        if ($this->paymentMethod === 'cash') {
            $total = $this->cashAmount;
        } elseif ($this->paymentMethod === 'cheque') {
            $total = collect($this->cheques)->sum('amount');
        } elseif ($this->paymentMethod === 'bank_transfer') {
            $total = $this->bankTransferAmount;
        }

        return $total;
    }

    public function getDueAmountProperty()
    {
        if ($this->paymentMethod === 'credit') {
            return $this->grandTotal;
        }
        return max(0, $this->grandTotal - (int)$this->totalPaidAmount);
    }

    public function getPaymentStatusProperty()
    {
        if ($this->paymentMethod === 'credit' || (int)$this->totalPaidAmount <= 0) {
            return 'pending';
        } elseif ((int)$this->totalPaidAmount >= $this->grandTotal) {
            return 'paid';
        } else {
            return 'partial';
        }
    }

    // Determine payment_type for database (must be 'full' or 'partial')
    public function getDatabasePaymentTypeProperty()
    {
        if ($this->paymentMethod === 'credit') {
            return 'partial';
        }
        if ((int)$this->totalPaidAmount >= $this->grandTotal) {
            return 'full';
        } else {
            return 'partial';
        }
    }

    // When customer is selected from dropdown
    public function updatedCustomerId($value)
    {
        if ($value) {
            $customer = Customer::find($value);
            if ($customer) {
                $this->selectedCustomer = $customer;
            }
        } else {
            // If customer is deselected, set back to walking customer
            $this->setDefaultCustomer();
        }
    }

    // When payment method changes
    public function updatedPaymentMethod($value)
    {
        // Reset all payment fields
        $this->cashAmount = 0;
        $this->cheques = [];
        $this->bankTransferAmount = 0;
        $this->bankTransferBankName = '';
        $this->bankTransferReferenceNumber = '';

        if ($value === 'cash') {
            $this->cashAmount = $this->grandTotal;
        } elseif ($value === 'bank_transfer') {
            $this->bankTransferAmount = $this->grandTotal;
        }
    }

    // Auto-update cash amount when cart changes (if payment method is cash)
    public function updated($propertyName)
    {
        // If cart or discount changes, update payment amounts
        if (
            str_contains($propertyName, 'cart') ||
            str_contains($propertyName, 'additionalDiscount') ||
            str_contains($propertyName, 'additionalDiscountType')
        ) {

            if ($this->paymentMethod === 'cash') {
                $this->cashAmount = $this->grandTotal;
            } elseif ($this->paymentMethod === 'bank_transfer') {
                $this->bankTransferAmount = $this->grandTotal;
            }
        }
    }

    // Add Cheque
    public function addCheque()
    {
        $this->validate([
            'tempChequeNumber' => 'required|string|max:50',
            'tempBankName' => 'required|string|max:100',
            'tempChequeDate' => 'required|date',
            'tempChequeAmount' => 'required|numeric|min:0.01',
        ], [
            'tempChequeNumber.required' => 'Cheque number is required',
            'tempBankName.required' => 'Bank name is required',
            'tempChequeDate.required' => 'Cheque date is required',
            'tempChequeAmount.required' => 'Cheque amount is required',
            'tempChequeAmount.min' => 'Cheque amount must be greater than 0',
        ]);

        // Check if cheque number already exists
        $existingCheque = Cheque::where('cheque_number', $this->tempChequeNumber)->first();
        if ($existingCheque) {
            $this->js("Swal.fire('Error!', 'Cheque number already exists. Please use a different cheque number.', 'error');");
            return;
        }

        $this->cheques[] = [
            'number' => $this->tempChequeNumber,
            'bank_name' => $this->tempBankName,
            'date' => $this->tempChequeDate,
            'amount' => $this->tempChequeAmount,
        ];

        // Reset temporary fields
        $this->tempChequeNumber = '';
        $this->tempBankName = '';
        $this->tempChequeDate = now()->format('Y-m-d');
        $this->tempChequeAmount = 0;

        $this->js("Swal.fire('Success!', 'Cheque added successfully!', 'success')");
    }

    // Remove Cheque
    public function removeCheque($index)
    {
        unset($this->cheques[$index]);
        $this->cheques = array_values($this->cheques);
        $this->js("Swal.fire('success', 'Cheque removed successfully!', 'success')");
    }

    // Reset customer fields
    public function resetCustomerFields()
    {
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerAddress = '';
        $this->customerType = 'retail';
        $this->businessName = '';
    }

    // Open customer modal
    public function openCustomerModal()
    {
        $this->resetCustomerFields();
        $this->showCustomerModal = true;
    }

    // Close customer modal
    public function closeCustomerModal()
    {
        $this->showCustomerModal = false;
        $this->resetCustomerFields();
    }

    // Create new customer
    public function createCustomer()
    {
        $this->validate([
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'nullable|string|max:10|unique:customers,phone',
            'customerEmail' => 'nullable|email|unique:customers,email',
            'customerAddress' => 'required|string',
            'customerType' => 'required|in:retail,wholesale',
        ]);

        try {
            $customer = Customer::create([
                'name' => $this->customerName,
                'phone' => $this->customerPhone ?: null,
                'email' => $this->customerEmail,
                'address' => $this->customerAddress,
                'type' => $this->customerType,
                'business_name' => $this->businessName,
                'user_id' => Auth::id(),
            ]);

            $this->loadCustomers();
            $this->customerId = $customer->id;
            $this->selectedCustomer = $customer;
            $this->closeCustomerModal();

            // Set success message in session
            session()->flash('customer_success', 'Customer created successfully!');
        } catch (\Exception $e) {
            $this->js("Swal.fire('error', 'Failed to create customer: " . $e->getMessage() . "', 'error')");
        }
    }

    // Search Products
    public function updatedSearch()
    {
        if (strlen($this->search) >= 2) {
            $this->searchResults = ProductDetail::with(['stock', 'price'])
                ->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('code', 'like', '%' . $this->search . '%')
                ->orWhere('model', 'like', '%' . $this->search . '%')
                ->take(10)
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'code' => $product->code,
                        'model' => $product->model,
                        'price' => $product->price->selling_price ?? 0,
                        'stock' => $product->stock->available_stock ?? 0,
                        'sold' => $product->stock->sold_count ?? 0,
                        'image' => $product->image
                    ];
                });
        } else {
            $this->searchResults = [];
        }
    }

    // Add to Cart
    public function addToCart($product)
    {
        // Check stock availability
        if (($product['stock'] ?? 0) <= 0) {
            $this->js("Swal.fire('error', 'Not enough stock available!', 'error')");
            return;
        }

        $existing = collect($this->cart)->firstWhere('id', $product['id']);

        if ($existing) {
            // Check if adding more exceeds stock
            if (($existing['quantity'] + 1) > $product['stock']) {
                $this->js("Swal.fire('error', 'Not enough stock available!', 'error')");
                return;
            }

            $this->cart = collect($this->cart)->map(function ($item) use ($product) {
                if ($item['id'] == $product['id']) {
                    $item['quantity'] += 1;
                    $item['total'] = ($item['price'] - $item['discount']) * $item['quantity'];
                    // Ensure key exists
                    if (!isset($item['key'])) {
                        $item['key'] = uniqid('cart_');
                    }
                }
                return $item;
            })->toArray();
        } else {
            $discountPrice = ProductDetail::find($product['id'])->price->discount_price ?? 0;

            $newItem = [
                'key' => uniqid('cart_'),  // Add unique key to maintain state
                'id' => $product['id'],
                'name' => $product['name'],
                'code' => $product['code'],
                'model' => $product['model'],
                'price' => $product['price'],
                'quantity' => 1,
                'discount' => $discountPrice,
                'total' => $product['price'] - $discountPrice,
                'stock' => $product['stock']
            ];

            // Prepend new item to the beginning of the cart so it appears at the top
            array_unshift($this->cart, $newItem);
        }

        $this->search = '';
        $this->searchResults = [];
    }

    // Update Quantity
    public function updateQuantity($index, $quantity)
    {
        if ($quantity < 1) $quantity = 1;

        $productStock = $this->cart[$index]['stock'];
        if ($quantity > $productStock) {
            $this->js("Swal.fire('error', 'Not enough stock available! Maximum: ' . $productStock, 'error')");
            return;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $quantity;
    }

    // Increment Quantity
    public function incrementQuantity($index)
    {
        $currentQuantity = $this->cart[$index]['quantity'];
        $productStock = $this->cart[$index]['stock'];

        if (($currentQuantity + 1) > $productStock) {
            $this->js("Swal.fire('error', 'Not enough stock available! Maximum: ' . $productStock, 'error')");
            return;
        }

        $this->cart[$index]['quantity'] += 1;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $this->cart[$index]['quantity'];
    }

    // Decrement Quantity
    public function decrementQuantity($index)
    {
        if ($this->cart[$index]['quantity'] > 1) {
            $this->cart[$index]['quantity'] -= 1;
            $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $this->cart[$index]['discount']) * $this->cart[$index]['quantity'];
        }
    }

    // Update Price
    public function updatePrice($index, $price)
    {
        if ($price < 0) $price = 0;

        $this->cart[$index]['price'] = $price;
        $this->cart[$index]['total'] = ($price - $this->cart[$index]['discount']) * $this->cart[$index]['quantity'];
    }

    // Update Discount
    public function updateDiscount($index, $discount)
    {
        if ($discount < 0) $discount = 0;
        if ($discount > $this->cart[$index]['price']) {
            $discount = $this->cart[$index]['price'];
        }

        $this->cart[$index]['discount'] = $discount;
        $this->cart[$index]['total'] = ($this->cart[$index]['price'] - $discount) * $this->cart[$index]['quantity'];
    }

    // Remove from Cart
    public function removeFromCart($index)
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->js("Swal.fire('success', 'Product removed from sale!', 'success')");
    }

    // Clear Cart
    public function clearCart()
    {
        $this->cart = [];
        $this->additionalDiscount = 0;
        $this->additionalDiscountType = 'fixed';
        $this->resetPaymentFields();
        $this->js("Swal.fire('success', 'Cart cleared!', 'success')");
    }

    // Reset payment fields
    public function resetPaymentFields()
    {
        $this->cashAmount = 0;
        $this->cheques = [];
        $this->bankTransferAmount = 0;
        $this->bankTransferBankName = '';
        $this->bankTransferReferenceNumber = '';
        $this->paymentMethod = 'cash';
    }

    // Update additional discount
    public function updatedAdditionalDiscount($value)
    {
        if ($value === '') {
            $this->additionalDiscount = 0;
            return;
        }

        if ($value < 0) {
            $this->additionalDiscount = 0;
            return;
        }

        if ($this->additionalDiscountType === 'percentage' && $value > 100) {
            $this->additionalDiscount = 100;
            return;
        }

        if ($this->additionalDiscountType === 'fixed' && $value > $this->subtotalAfterItemDiscounts) {
            $this->additionalDiscount = $this->subtotalAfterItemDiscounts;
            return;
        }
    }

    public function toggleDiscountType()
    {
        $this->additionalDiscountType = $this->additionalDiscountType === 'percentage' ? 'fixed' : 'percentage';
        $this->additionalDiscount = 0;
    }

    public function removeAdditionalDiscount()
    {
        $this->additionalDiscount = 0;
        $this->js("Swal.fire('success', 'Additional discount removed!', 'success')");
    }

    // Validate Payment Before Creating Sale
    public function validateAndCreateSale()
    {
        if (empty($this->cart)) {
            $this->js("Swal.fire('error', 'Please add at least one product to the sale.', 'error')");
            return;
        }

        // If no customer selected, use walking customer
        if (!$this->selectedCustomer && !$this->customerId) {
            $this->js("Swal.fire('error', 'Please select a customer.', 'error')");
            return;
            $this->setDefaultCustomer();
        }

        // Validate payment method specific fields
        if ($this->paymentMethod === 'cash') {
            if ($this->cashAmount < 0) {
                $this->js("Swal.fire('error', 'Please enter cash amount.', 'error')");
                return;
            }
        } elseif ($this->paymentMethod === 'cheque') {
            if (empty($this->cheques)) {
                $this->js("Swal.fire('error', 'Please add at least one cheque.', 'error')");
                return;
            }

            // Validate that total cheque amount does not exceed grand total
            $totalChequeAmount = collect($this->cheques)->sum('amount');
            if ($totalChequeAmount > $this->grandTotal) {
                $this->js("Swal.fire('error', 'Total cheque amount (Rs. " . number_format($totalChequeAmount, 2) . ") cannot be greater than the grand total (Rs. " . number_format($this->grandTotal, 2) . ").', 'error')");
                return;
            }
        } elseif ($this->paymentMethod === 'bank_transfer') {
            if ($this->bankTransferAmount <= 0) {
                $this->js("Swal.fire('error', 'Please enter bank transfer amount.', 'error')");
                return;
            }
        }

        // Check if payment amount matches grand total (except for credit)
        if ($this->paymentMethod !== 'credit') {
            if ((int)$this->totalPaidAmount < $this->grandTotal) {
                // Show confirmation modal for due amount
                $this->pendingDueAmount = $this->grandTotal - (int)$this->totalPaidAmount;
                $this->showPaymentConfirmModal = true;
                return;
            }
        }

        // Proceed to create sale
        $this->createSale();
    }

    // Confirm and Create Sale with Due Amount
    public function confirmSaleWithDue()
    {
        $this->showPaymentConfirmModal = false;
        $this->createSale();
    }

    // Cancel Sale Confirmation
    public function cancelSaleConfirmation()
    {
        $this->showPaymentConfirmModal = false;
        $this->pendingDueAmount = 0;
    }

    // Create Sale
    public function createSale()
    {
        try {
            DB::beginTransaction();

            // Get customer data
            $customer = $this->selectedCustomer ?? Customer::find($this->customerId);

            if (!$customer) {
                $this->js("Swal.fire('error', 'Customer not found.', 'error')");
                return;
            }

            // Create sale
            $sale = Sale::create([
                'sale_id' => Sale::generateSaleId(),
                'invoice_number' => Sale::generateInvoiceNumber(),
                'customer_id' => $customer->id,
                'customer_type' => $customer->type,
                'subtotal' => $this->subtotal,
                'discount_amount' => $this->totalDiscount + $this->additionalDiscountAmount,
                'total_amount' => $this->grandTotal,
                'payment_type' => $this->databasePaymentType,
                'payment_status' => $this->paymentStatus,
                'due_amount' => $this->dueAmount,
                'notes' => $this->notes,
                'user_id' => Auth::id(),
                'status' => 'confirm',
                'sale_type' => 'pos'
            ]);

            // Create sale items and update stock
            foreach ($this->cart as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['id'],
                    'product_code' => $item['code'],
                    'product_name' => $item['name'],
                    'product_model' => $item['model'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'discount_per_unit' => $item['discount'],
                    'total_discount' => $item['discount'] * $item['quantity'],
                    'total' => $item['total']
                ]);

                // Update product stock
                $product = ProductDetail::find($item['id']);
                if ($product && $product->stock) {
                    $product->stock->available_stock -= $item['quantity'];
                    $product->stock->save();
                }
            }

            // Create Payment Record
            if ($this->paymentMethod !== 'credit' && (int)$this->totalPaidAmount > 0) {
                $payment = Payment::create([
                    'customer_id' => $customer->id,
                    'sale_id' => $sale->id,
                    'amount' => (int)$this->totalPaidAmount,
                    'payment_method' => $this->paymentMethod,
                    'payment_date' => now(),
                    'is_completed' => true,
                    'status' =>  'paid',
                ]);

                // Handle payment method specific data
                if ($this->paymentMethod === 'cash') {
                    $payment->update([
                        'payment_reference' => 'CASH-' . now()->format('YmdHis'),
                    ]);

                    // Update cash in hands - add cash payment
                    $this->updateCashInHands((int)$this->totalPaidAmount);
                } elseif ($this->paymentMethod === 'cheque') {
                    // Create cheque records
                    foreach ($this->cheques as $cheque) {
                        Cheque::create([
                            'cheque_number' => $cheque['number'],
                            'cheque_date' => $cheque['date'],
                            'bank_name' => $cheque['bank_name'],
                            'cheque_amount' => $cheque['amount'],
                            'status' => 'pending',
                            'customer_id' => $customer->id,
                            'payment_id' => $payment->id,
                        ]);
                    }

                    $payment->update([
                        'payment_reference' => 'CHQ-' . collect($this->cheques)->pluck('number')->implode(','),
                        'bank_name' => collect($this->cheques)->pluck('bank_name')->unique()->implode(', '),
                    ]);
                } elseif ($this->paymentMethod === 'bank_transfer') {
                    $payment->update([
                        'payment_reference' => $this->bankTransferReferenceNumber ?: 'BANK-' . now()->format('YmdHis'),
                        'bank_name' => $this->bankTransferBankName,
                        'transfer_date' => now(),
                        'transfer_reference' => $this->bankTransferReferenceNumber,
                    ]);
                }
            }

            DB::commit();

            // Ensure there is an open POS session for this user and update its totals
            $this->currentSession = POSSession::getTodaySession(Auth::id());
            if (! $this->currentSession) {
                // If no open session, create one with zero opening cash so sales still get tracked
                $this->currentSession = POSSession::openSession(Auth::id(), 0);
            }

            // Recalculate session totals from sales/payments for the day
            try {
                $this->currentSession->updateFromSales();
                // Recalculate expected cash (cash difference will stay null until close)
                $this->currentSession->calculateDifference();
            } catch (\Exception $e) {
                Log::error('Failed to update POS session after sale: ' . $e->getMessage());
            }

            $this->lastSaleId = $sale->id;
            $this->createdSale = Sale::with(['customer', 'items', 'payments'])->find($sale->id);
            $this->showSaleModal = true;

            $statusMessage = 'Sale created successfully! Payment status: ' . ucfirst($this->paymentStatus);
            if ($this->dueAmount > 0) {
                $statusMessage .= ' | Due Amount: Rs.' . number_format($this->dueAmount, 2);
            }

            // Clear cart and reset payment fields after successful sale
            $this->cart = [];
            $this->additionalDiscount = 0;
            $this->additionalDiscountType = 'fixed';
            $this->resetPaymentFields();
            $this->notes = '';

            // Reset to walking customer
            $this->setDefaultCustomer();

            $this->js("Swal.fire('success', '$statusMessage', 'success')");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->js("Swal.fire('error', 'Failed to create sale: " . $e->getMessage() . "', 'error')");
        }
    }

    // Download Invoice
    public function downloadInvoice()
    {
        if (!$this->lastSaleId) {
            $this->js("Swal.fire('error', 'No sale found to download.', 'error')");
            return;
        }

        $sale = Sale::with(['customer', 'items', 'returns' => function ($q) {
            $q->with('product');
        }])->find($this->lastSaleId);

        if (!$sale) {
            $this->js("Swal.fire('error', 'Sale not found.', 'error')");
            return;
        }

        $pdf = PDF::loadView('admin.sales.invoice', compact('sale'));
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('dpi', 150);
        $pdf->setOption('defaultFont', 'sans-serif');

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->output();
            },
            'invoice-' . $sale->invoice_number . '.pdf'
        );
    }

    // Print Sale Receipt
    public function printSaleReceipt()
    {
        if (!$this->createdSale) {
            $this->js("Swal.fire('error', 'No sale found to print.', 'error')");
            return;
        }

        $sale = Sale::with(['customer', 'items', 'payments', 'returns' => function ($q) {
            $q->with('product');
        }])->find($this->createdSale->id);

        if (!$sale) {
            $this->js("Swal.fire('error', 'Sale not found.', 'error')");
            return;
        }

        // Store sale ID in session for print route
        session(['print_sale_id' => $sale->id]);

        // Open print page in new window
        $this->js("
            const printUrl = '" . route('admin.print.sale', $sale->id) . "';
            const printWindow = window.open(printUrl, '_blank', 'width=800,height=600');
            if (printWindow) {
                printWindow.focus();
            }
        ");
    }

    // Download Close Register Report
    public function downloadCloseRegisterReport()
    {
        if (!$this->currentSession) {
            $this->js("Swal.fire('error', 'No session found to download.', 'error')");
            return;
        }

        // Prepare data for PDF
        $sessionData = [
            'session' => $this->currentSession,
            'summary' => $this->sessionSummary,
            'close_date' => now()->format('d/m/Y'),
            'close_time' => now()->format('H:i'),
            'user' => Auth::user()->name,
        ];

        $pdf = PDF::loadView('admin.pos.close-register-report', $sessionData);

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->output();
            },
            'close-register-' . now()->format('Y-m-d-His') . '.pdf'
        );
    }

    // Close Modal
    public function closeModal()
    {
        $this->showSaleModal = false;
        $this->lastSaleId = null;
        $this->createdSale = null;
    }

    // Continue creating new sale
    public function createNewSale()
    {
        $this->resetExcept(['customers', 'currentSession']);
        $this->loadCustomers();
        $this->setDefaultCustomer(); // Set walking customer again for new sale
        $this->showSaleModal = false;

        // Dispatch event to clean up modal backdrop
        $this->dispatch('saleSaved');
    }

    /**
     * Submit Opening Cash and Create/Reopen POS Session
     */
    public function submitOpeningCash()
    {
        $this->validate([
            'openingCashAmount' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Check if a closed session exists for today
            $existingSession = POSSession::where('user_id', Auth::id())
                ->whereDate('session_date', now()->toDateString())
                ->where('status', 'closed')
                ->first();

            if ($existingSession) {
                // Reopen existing closed session with new opening cash
                $existingSession->update([
                    'status' => 'open',
                    'opening_cash' => $this->openingCashAmount,
                    'closed_at' => null,
                    'notes' => ($existingSession->notes ? $existingSession->notes . ' | ' : '') . 'Reopened with opening cash: Rs. ' . number_format($this->openingCashAmount, 2)
                ]);
                $this->currentSession = $existingSession;
                $message = 'POS Session Reopened!';

                // For reopening, don't update cash_in_hands as it should retain the session's opening amount
            } else {
                // Create new POS session with opening cash (first time opening)
                $this->currentSession = POSSession::openSession(Auth::id(), $this->openingCashAmount);
                $message = 'POS Session Started!';

                // Update cash_in_hands table only for new sessions (first time opening)
                $cashInHandRecord = DB::table('cash_in_hands')->where('key', 'cash_amount')->first();

                if ($cashInHandRecord) {
                    DB::table('cash_in_hands')
                        ->where('key', 'cash_amount')
                        ->update([
                            'value' => $this->openingCashAmount,
                            'updated_at' => now()
                        ]);
                } else {
                    DB::table('cash_in_hands')->insert([
                        'key' => 'cash_amount',
                        'value' => $this->openingCashAmount,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            DB::commit();

            // Close the modal
            $this->showOpeningCashModal = false;

            $this->js("Swal.fire({
                icon: 'success',
                title: '$message',
                text: 'Opening cash: Rs. " . number_format($this->openingCashAmount, 2) . "',
                timer: 2000,
                showConfirmButton: false
            })");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to open/reopen POS session: ' . $e->getMessage());
            $this->js("Swal.fire('Error!', 'Failed to start POS session: " . addslashes($e->getMessage()) . "', 'error')");
        }
    }

    /**
     * View Close Register Report - Show summary WITHOUT closing the session
     */
    public function viewCloseRegisterReport()
    {
        // Refresh session data
        $this->currentSession = POSSession::getTodaySession(Auth::id());

        // If no session exists, show info message
        if (!$this->currentSession) {
            $this->js("Swal.fire({
                icon: 'info',
                title: 'No Active Session',
                text: 'Please open a POS session first by accessing the POS page.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3b5b0c'
            })");
            return;
        }

        // If session is already closed, show alert
        if ($this->currentSession->isClosed()) {
            $this->js("Swal.fire({
                icon: 'warning',
                title: 'Register Already Closed',
                text: 'The POS register has already been closed for today. You cannot access the close register function again.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3b5b0c'
            })");
            return;
        }

        $today = now()->toDateString();

        // 1. Cash in Hand - Get Opening Amount from session
        $sessionOpeningCash = $this->currentSession->opening_cash;

        // Get today's POS sales IDs (sale_type = 'pos')
        $posSalesToday = Sale::whereDate('created_at', $today)
            ->where('sale_type', 'pos')
            ->pluck('id');

        // Get today's Admin sales IDs (sale_type = 'admin')
        $adminSalesToday = Sale::whereDate('created_at', $today)
            ->where('sale_type', 'admin')
            ->pluck('id');

        // 2. POS Cash Sale - Get from payment table where sale_type = 'pos' and method = 'cash'
        $posCashPayments = Payment::whereIn('sale_id', $posSalesToday)
            ->where('payment_method', 'cash')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        // 3. POS Cheque Payment - Get from payment table where sale_type = 'pos' and method = 'cheque'
        $posChequePayments = Payment::whereIn('sale_id', $posSalesToday)
            ->where('payment_method', 'cheque')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        // POS Bank Transfer Payment - Get from payment table where sale_type = 'pos' and method = 'bank_transfer'
        $posBankTransfers = Payment::whereIn('sale_id', $posSalesToday)
            ->where('payment_method', 'bank_transfer')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        // 4. Late Payments - Include both Admin Sales and payments with null sale_id
        // 4.1 Admin Cash Payments (from admin sales)
        $adminCashPayments = Payment::whereIn('sale_id', $adminSalesToday)
            ->where('payment_method', 'cash')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        // 4.1.1 Late Cash Payments (sale_id is null)
        $lateCashPayments = Payment::whereNull('sale_id')
            ->where('payment_method', 'cash')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        // Total Cash Payments from Admin and Late Payments
        $totalAdminCashPayments = $adminCashPayments + $lateCashPayments;

        // 4.2 Admin Cheque Payments (from admin sales)
        $adminChequePayments = Payment::whereIn('sale_id', $adminSalesToday)
            ->where('payment_method', 'cheque')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        // 4.2.1 Late Cheque Payments (sale_id is null)
        $lateChequePayments = Payment::whereNull('sale_id')
            ->where('payment_method', 'cheque')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        // Total Cheque Payments from Admin and Late Payments
        $totalAdminChequePayments = $adminChequePayments + $lateChequePayments;

        // 4.3 Admin Bank Transfer Payments (from admin sales)
        $adminBankTransfers = Payment::whereIn('sale_id', $adminSalesToday)
            ->where('payment_method', 'bank_transfer')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        // 4.3.1 Late Bank Transfer Payments (sale_id is null)
        $lateBankTransfers = Payment::whereNull('sale_id')
            ->where('payment_method', 'bank_transfer')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        // Total Bank Transfer Payments from Admin and Late Payments
        $totalAdminBankTransfers = $adminBankTransfers + $lateBankTransfers;

        // Calculate total late payments (admin + null sale_id)
        $totalAdminPayments = $totalAdminCashPayments + $totalAdminChequePayments + $totalAdminBankTransfers;

        // 5. Total Cash Amount (POS Cash + Admin Cash + Late Cash)
        $totalCashFromSales = $posCashPayments + $totalAdminCashPayments;

        // 6. Total POS Sales - Get from sales table where sale_type = 'pos'
        $totalPosSales = Sale::whereDate('created_at', $today)
            ->where('sale_type', 'pos')
            ->sum('total_amount');

        // 7. Total Admin Sales - Get from sales table where sale_type = 'admin'
        $totalAdminSales = Sale::whereDate('created_at', $today)
            ->where('sale_type', 'admin')
            ->sum('total_amount');

        // 8. Total Cash from Payment Table (All cash payments for the day)
        $totalCashPaymentsToday = Payment::whereDate('payment_date', $today)
            ->where('payment_method', 'cash')
            ->sum('amount');

        // 9. Expenses, Refunds, and Cash Deposit Bank
        // Get refunds today (returns)
        $refundsToday = DB::table('returns_products')
            ->whereDate('created_at', $today)
            ->sum('total_amount');

        // Get expenses today
        $expensesToday = DB::table('expenses')
            ->whereDate('date', $today)
            ->where('expense_type', 'daily')
            ->sum('amount');

        // Get cash deposits to bank from deposit table
        $cashDepositBank = DB::table('deposits')
            ->whereDate('date', $today)
            ->sum('amount');
        $supplierPaymentToday = DB::table('purchase_payments')

            ->whereDate('payment_date', $today)
            ->sum('amount');

        $supplierCashPaymentToday = DB::table('purchase_payments')
            ->where('payment_method', 'cash')
            ->whereDate('payment_date', $today)
            ->sum('amount');

        // Calculate Total Cash in Hand
        $totalCashInHand = ($sessionOpeningCash + $totalCashPaymentsToday) - ($refundsToday + $expensesToday + $cashDepositBank + $supplierCashPaymentToday);

        // Update session data
        $this->currentSession->update([
            'total_sales' => $totalPosSales,
            'cash_sales' => $totalCashFromSales,
            'late_payment_bulk' => $totalAdminPayments,
            'cheque_payment' => $posChequePayments,
            'bank_transfer' => $posBankTransfers,
            'refunds' => $refundsToday,
            'expenses' => $expensesToday,
            'cash_deposit_bank' => $cashDepositBank,
            'spupplier_payment' => $supplierPaymentToday,
        ]);

        // Prepare summary data
        $this->sessionSummary = [
            'opening_cash' => $sessionOpeningCash,

            // POS Sales Breakdown
            'pos_cash_sales' => $posCashPayments,
            'pos_cheque_payment' => $posChequePayments,
            'pos_bank_transfer' => $posBankTransfers,
            'total_pos_sales' => $totalPosSales,

            // Admin Sales (Late Payments) Breakdown
            'admin_cash_payment' => $adminCashPayments,
            'admin_cheque_payment' => $adminChequePayments,
            'admin_bank_transfer' => $adminBankTransfers,

            // Late Payments (sale_id is null)
            'late_cash_payment' => $lateCashPayments,
            'late_cheque_payment' => $lateChequePayments,
            'late_bank_transfer' => $lateBankTransfers,

            // Combined Late Payments
            'total_admin_cash_payment' => $totalAdminCashPayments,
            'total_admin_cheque_payment' => $totalAdminChequePayments,
            'total_admin_bank_transfer' => $totalAdminBankTransfers,
            'total_admin_payment' => $totalAdminPayments,
            'total_admin_sales' => $totalAdminSales,

            // Combined Totals
            'total_cash_from_sales' => $totalCashFromSales, // POS Cash + Admin Cash
            'total_cash_payment_today' => $totalCashPaymentsToday, // All cash payments

            // Deductions
            'refunds' => $refundsToday,
            'expenses' => $expensesToday,
            'cash_deposit_bank' => $cashDepositBank,
            'supplier_payment' => $supplierPaymentToday,
            'supplier_cash_payment' => $supplierCashPaymentToday,

            // Final Cash in Hand
            'expected_cash' => $totalCashInHand,
        ];

        $this->closeRegisterCash = $this->sessionSummary['expected_cash'];

        // Just show the modal, don't close the session yet
        $this->showCloseRegisterModal = true;

        $this->dispatch('showModal', 'closeRegisterModal');
    }

    /**
     * Cancel Close Register - Just close modal without doing anything
     */
    public function cancelCloseRegister()
    {
        $this->showCloseRegisterModal = false;
    }

    /**
     * Close Register and Redirect to Dashboard
     * This actually closes the POS session when user clicks "Close & Go to Dashboard"
     */
    public function closeRegisterAndRedirect()
    {
        try {
            DB::beginTransaction();

            // Refresh session data
            $this->currentSession = POSSession::where('user_id', Auth::id())
                ->whereDate('session_date', now()->toDateString())
                ->where('status', 'open')
                ->first();

            if (!$this->currentSession) {
                DB::rollBack();

                session()->flash('error', 'No active POS session found.');

                return redirect()->route('admin.dashboard');
            }

            // Get the expected closing cash from sessionSummary
            $expectedClosingCash = $this->sessionSummary['expected_cash'] ?? $this->closeRegisterCash;

            // Close the session
            $this->currentSession->update([
                'closing_cash' => $expectedClosingCash,
                'total_sales' => $this->sessionSummary['total_pos_sales'] ?? 0,
                'cash_sales' => $this->sessionSummary['total_cash_from_sales'] ?? 0,
                'late_payment_bulk' => $this->sessionSummary['total_admin_payment'] ?? 0,
                'cheque_payment' => $this->sessionSummary['pos_cheque_payment'] ?? 0,
                'bank_transfer' => $this->sessionSummary['pos_bank_transfer'] ?? 0,
                'refunds' => $this->sessionSummary['refunds'] ?? 0,
                'expenses' => $this->sessionSummary['expenses'] ?? 0,
                'cash_deposit_bank' => $this->sessionSummary['cash_deposit_bank'] ?? 0,
                'status' => 'closed',
                'closed_at' => now(),
                'notes' => $this->closeRegisterNotes ?? 'Closed from close register modal',
            ]);

            // Update both 'cash in hand' and 'cash_amount' keys in cash_in_hands table
            $keysToUpdate = ['cash in hand', 'cash_amount'];

            foreach ($keysToUpdate as $key) {
                $cashInHandRecord = DB::table('cash_in_hands')->where('key', $key)->first();

                if ($cashInHandRecord) {
                    DB::table('cash_in_hands')
                        ->where('key', $key)
                        ->update([
                            'value' => $expectedClosingCash,
                            'updated_at' => now()
                        ]);
                } else {
                    DB::table('cash_in_hands')->insert([
                        'key' => $key,
                        'value' => $expectedClosingCash,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            DB::commit();

            // Close modal
            $this->showCloseRegisterModal = false;

            // Flash success message
            session()->flash('success', 'POS register closed successfully! Closing cash: Rs. ' . number_format($expectedClosingCash, 2));

            // Redirect to dashboard
            return redirect()->route('admin.dashboard');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to close POS session: ' . $e->getMessage());

            session()->flash('error', 'Failed to close register: ' . $e->getMessage());

            return redirect()->route('admin.dashboard');
        }
    }

    /**
     * Reopen today's closed POS session (for admin)
     * Called via AJAX from header modal
     */
    public function reopenPOSSession()
    {
        $today = now()->toDateString();
        $userId = Auth::id();
        $session = POSSession::where('user_id', $userId)
            ->whereDate('session_date', $today)
            ->where('status', 'closed')
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'No closed POS session found for today.'
            ], 404);
        }

        try {
            // Reset specified columns to 0 and change status to open
            $session->update([
                'status' => 'open',
                'closing_cash' => 0,
                'total_sales' => 0,
                'cash_sales' => 0,
                'cheque_payment' => 0,
                'credit_card_payment' => 0,
                'bank_transfer' => 0,
                'late_payment_bulk' => 0,
                'refunds' => 0,
                'expenses' => 0,
                'cash_deposit_bank' => 0,
                'expected_cash' => 0,
                'cash_difference' => 0,
                'notes' => null,
                'closed_at' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'POS session reopened successfully. All transaction data has been reset.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reopen POS session: ' . $e->getMessage()
            ], 500);
        }
    }
    public function render()
    {
        return view('livewire.admin.store-billing', [
            'subtotal' => $this->subtotal,
            'totalDiscount' => $this->totalDiscount,
            'subtotalAfterItemDiscounts' => $this->subtotalAfterItemDiscounts,
            'additionalDiscountAmount' => $this->additionalDiscountAmount,
            'grandTotal' => $this->grandTotal,
            'dueAmount' => $this->dueAmount,
            'paymentStatus' => $this->paymentStatus,
            'databasePaymentType' => $this->databasePaymentType,
            'totalPaidAmount' => (int)$this->totalPaidAmount,
            'searchResults' => $this->searchResults, // Explicitly pass search results
        ]);
    }
}
