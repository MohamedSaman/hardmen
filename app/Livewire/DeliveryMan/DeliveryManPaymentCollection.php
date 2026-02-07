<?php

namespace App\Livewire\DeliveryMan;

use App\Models\Sale;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Cheque;
use App\Models\ReturnsProduct;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Title('Payment Collection')]
#[Layout('components.layouts.delivery-man')]
class DeliveryManPaymentCollection extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedCustomer = null;
    public $customerSales = [];
    public $selectedInvoices = [];
    public $showCollectModal = false;

    // Payment form fields
    public $paymentData = [
        'payment_date' => '',
        'payment_method' => 'cash',
        'reference_number' => '',
        'notes' => ''
    ];

    // Cheque related properties
    public $cheque = [
        'cheque_number' => '',
        'bank_name' => '',
        'cheque_date' => '',
        'amount' => 0
    ];

    public $bankTransfer = [
        'bank_name' => '',
        'transfer_date' => '',
        'reference_number' => ''
    ];

    public $allocations = [];
    public $totalDueAmount = 0;
    public $totalPaymentAmount = 0;
    public $remainingAmount = 0;

    protected $rules = [
        'paymentData.payment_date' => 'required|date',
        'paymentData.payment_method' => 'required|in:cash,cheque,bank_transfer',
        'totalPaymentAmount' => 'required|numeric|min:0.01',
        'cheque.cheque_number' => 'required_if:paymentData.payment_method,cheque|string|max:50',
        'cheque.bank_name' => 'required_if:paymentData.payment_method,cheque|string|max:100',
        'cheque.cheque_date' => 'required_if:paymentData.payment_method,cheque|date',
        'bankTransfer.bank_name' => 'required_if:paymentData.payment_method,bank_transfer|string|max:100',
        'bankTransfer.transfer_date' => 'required_if:paymentData.payment_method,bank_transfer|date',
        'bankTransfer.reference_number' => 'required_if:paymentData.payment_method,bank_transfer|string|max:100',
    ];

    public function mount($customer_id = null)
    {
        $this->paymentData['payment_date'] = now()->format('Y-m-d');
        $this->cheque['cheque_date'] = now()->format('Y-m-d');
        $this->bankTransfer['transfer_date'] = now()->format('Y-m-d');

        // Auto-select customer if provided
        if ($customer_id) {
            $this->selectCustomer($customer_id);
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedTotalPaymentAmount()
    {
        if ($this->totalPaymentAmount > $this->totalDueAmount) {
            $this->totalPaymentAmount = $this->totalDueAmount;
        }

        if ($this->totalPaymentAmount < 0) {
            $this->totalPaymentAmount = 0;
        }

        $this->calculateRemainingAmount();
        $this->autoAllocatePayment();

        // Update cheque amount if payment method is cheque
        if ($this->paymentData['payment_method'] === 'cheque') {
            $this->cheque['amount'] = $this->totalPaymentAmount;
        }
    }

    public function updatedPaymentDataPaymentMethod($value)
    {
        // Reset payment method specific fields
        $this->cheque = [
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'amount' => $this->totalPaymentAmount
        ];

        $this->bankTransfer = [
            'bank_name' => '',
            'transfer_date' => now()->format('Y-m-d'),
            'reference_number' => ''
        ];
    }

    public function selectCustomer($customerId)
    {
        $this->selectedCustomer = Customer::find($customerId);
        $this->loadCustomerSales();
        $this->selectedInvoices = [];
        $this->totalPaymentAmount = 0;
        $this->totalDueAmount = 0;
        $this->initializeAllocations();
    }

    public function clearSelectedCustomer()
    {
        $this->selectedCustomer = null;
        $this->customerSales = [];
        $this->selectedInvoices = [];
        $this->allocations = [];
        $this->totalDueAmount = 0;
        $this->totalPaymentAmount = 0;
        $this->remainingAmount = 0;
    }

    /**
     * Toggle invoice selection
     */
    public function toggleInvoiceSelection($saleId)
    {
        if (in_array($saleId, $this->selectedInvoices)) {
            $this->selectedInvoices = array_values(array_diff($this->selectedInvoices, [$saleId]));
        } else {
            $this->selectedInvoices[] = $saleId;
        }

        $this->calculateTotalDue();
        $this->totalPaymentAmount = 0;
        $this->remainingAmount = $this->totalDueAmount;
        $this->initializeAllocations();
    }

    /**
     * Select all invoices
     */
    public function selectAllInvoices()
    {
        $this->selectedInvoices = array_column($this->customerSales, 'id');
        $this->calculateTotalDue();
        $this->totalPaymentAmount = 0;
        $this->remainingAmount = $this->totalDueAmount;
        $this->initializeAllocations();
    }

    /**
     * Calculate return amount for a sale
     */
    private function calculateReturnAmount($saleId)
    {
        return ReturnsProduct::where('sale_id', $saleId)->sum('total_amount');
    }

    /**
     * Load customer sales with opening balance
     */
    private function loadCustomerSales()
    {
        if (!$this->selectedCustomer) return;

        $salesList = [];

        // Add opening balance if exists
        if ($this->selectedCustomer->opening_balance > 0) {
            $salesList[] = [
                'id' => 'opening_balance_' . $this->selectedCustomer->id,
                'invoice_number' => 'Opening Balance',
                'sale_id' => 'OB',
                'sale_date' => 'N/A',
                'total_amount' => $this->selectedCustomer->opening_balance,
                'due_amount' => $this->selectedCustomer->opening_balance,
                'return_amount' => 0,
                'payment_status' => 'pending',
                'is_opening_balance' => true,
            ];
        }

        // Load delivered sales with due amounts
        $sales = Sale::where('customer_id', $this->selectedCustomer->id)
            ->where('status', 'confirm')
            ->where('delivery_status', 'delivered')
            ->where('due_amount', '>', 0)
            ->with(['items', 'payments', 'returns'])
            ->orderBy('created_at', 'asc')
            ->get();

        $mappedSales = $sales->map(function ($sale) {
            $returnAmount = $this->calculateReturnAmount($sale->id);
            $adjustedDueAmount = max(0, $sale->due_amount - $returnAmount);

            return [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'sale_id' => $sale->sale_id,
                'sale_date' => $sale->created_at->format('M d, Y'),
                'total_amount' => $sale->total_amount - $returnAmount,
                'due_amount' => $adjustedDueAmount,
                'return_amount' => $returnAmount,
                'payment_status' => $adjustedDueAmount <= 0.01 ? 'paid' : $sale->payment_status,
                'is_opening_balance' => false,
            ];
        })->filter(function ($sale) {
            return $sale['due_amount'] > 0.01;
        })->values()->toArray();

        $this->customerSales = array_merge($salesList, $mappedSales);
        $this->calculateTotalDue();
    }

    private function calculateTotalDue()
    {
        $this->totalDueAmount = collect($this->customerSales)
            ->whereIn('id', $this->selectedInvoices)
            ->sum('due_amount');
        $this->remainingAmount = $this->totalDueAmount;
    }

    private function calculateRemainingAmount()
    {
        $this->remainingAmount = $this->totalDueAmount - $this->totalPaymentAmount;
    }

    private function initializeAllocations()
    {
        $this->allocations = [];

        foreach ($this->customerSales as $sale) {
            if (in_array($sale['id'], $this->selectedInvoices)) {
                $this->allocations[] = [
                    'id' => $sale['id'],
                    'sale_id' => $sale['id'],
                    'invoice_number' => $sale['invoice_number'],
                    'due_amount' => $sale['due_amount'],
                    'payment_amount' => 0,
                    'is_fully_paid' => false,
                    'is_opening_balance' => isset($sale['is_opening_balance']) && $sale['is_opening_balance'],
                ];
            }
        }
    }

    private function autoAllocatePayment()
    {
        $remainingPayment = $this->totalPaymentAmount;

        // Update allocations with payment amounts
        for ($i = 0; $i < count($this->allocations); $i++) {
            $dueAmount = $this->allocations[$i]['due_amount'];

            if ($remainingPayment <= 0) {
                $this->allocations[$i]['payment_amount'] = 0;
                $this->allocations[$i]['is_fully_paid'] = false;
            } elseif ($remainingPayment >= $dueAmount) {
                $this->allocations[$i]['payment_amount'] = $dueAmount;
                $this->allocations[$i]['is_fully_paid'] = true;
                $remainingPayment -= $dueAmount;
            } else {
                $this->allocations[$i]['payment_amount'] = $remainingPayment;
                $this->allocations[$i]['is_fully_paid'] = false;
                $remainingPayment = 0;
            }
        }
    }

    public function openCollectModal()
    {
        if (empty($this->selectedInvoices)) {
            $this->dispatch('show-toast', type: 'error', message: 'Please select at least one invoice.');
            return;
        }

        if (!$this->totalPaymentAmount || $this->totalPaymentAmount <= 0) {
            $this->dispatch('show-toast', type: 'error', message: 'Please enter a payment amount.');
            return;
        }

        if ($this->totalPaymentAmount > $this->totalDueAmount) {
            $this->dispatch('show-toast', type: 'error', message: 'Payment amount cannot exceed total due.');
            return;
        }

        // Recalculate allocations before opening modal
        $this->initializeAllocations();
        $this->autoAllocatePayment();

        // Verify we have allocations
        if (empty($this->allocations)) {
            $this->dispatch('show-toast', type: 'error', message: 'Unable to allocate payment. Please try again.');
            return;
        }

        $this->showCollectModal = true;
    }

    public function closeCollectModal()
    {
        $this->showCollectModal = false;
    }

    /**
     * Collect payment (creates payment with pending status for admin approval)
     */
    public function collectPayment()
    {
        // Validate basic requirements
        if (empty($this->allocations)) {
            $this->dispatch('show-toast', type: 'error', message: 'No payment allocations found. Please try again.');
            return;
        }

        if (!$this->selectedCustomer) {
            $this->dispatch('show-toast', type: 'error', message: 'No customer selected.');
            return;
        }

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            $this->dispatch('show-toast', type: 'error', message: $firstError ?? 'Please fill all required fields.');
            return;
        }

        if ($this->paymentData['payment_method'] === 'cheque') {
            $existingCheque = Cheque::where('cheque_number', $this->cheque['cheque_number'])->first();
            if ($existingCheque) {
                $this->dispatch('show-toast', type: 'error', message: 'Cheque number already exists.');
                return;
            }
        }

        try {
            DB::beginTransaction();

            // Create payment with PENDING status (requires admin approval)
            $paymentData = [
                'customer_id' => $this->selectedCustomer->id,
                'amount' => $this->totalPaymentAmount,
                'payment_method' => $this->paymentData['payment_method'],
                'payment_reference' => $this->paymentData['reference_number'] ?? null,
                'payment_date' => $this->paymentData['payment_date'] ?? now()->format('Y-m-d'),
                'status' => 'pending', // Requires admin approval
                'notes' => $this->paymentData['notes'] ?? null,
                'collected_by' => Auth::id(),
                'collected_at' => now(),
                'created_by' => Auth::id(),
            ];

            if ($this->paymentData['payment_method'] === 'bank_transfer') {
                $paymentData['bank_name'] = $this->bankTransfer['bank_name'] ?? null;
                $paymentData['transfer_date'] = $this->bankTransfer['transfer_date'] ?? null;
                $paymentData['transfer_reference'] = $this->bankTransfer['reference_number'] ?? null;
            }

            $payment = Payment::create($paymentData);

            // Create cheque if payment method is cheque
            if ($this->paymentData['payment_method'] === 'cheque') {
                Cheque::create([
                    'payment_id' => $payment->id,
                    'cheque_number' => $this->cheque['cheque_number'],
                    'bank_name' => $this->cheque['bank_name'],
                    'cheque_date' => $this->cheque['cheque_date'],
                    'cheque_amount' => $this->totalPaymentAmount, // Use actual payment amount
                    'status' => 'pending',
                    'customer_id' => $this->selectedCustomer->id,
                ]);
            }

            // Create allocations for all selected items
            $allocationsCreated = 0;
            foreach ($this->allocations as $allocation) {
                $paymentAmount = $allocation['payment_amount'];

                if ($paymentAmount <= 0) continue;

                // Determine sale_id: null for opening balance, actual ID for sales
                $saleId = null;
                if (!$allocation['is_opening_balance']) {
                    $saleId = $allocation['id'];
                }

                // Create payment allocation
                DB::table('payment_allocations')->insert([
                    'payment_id' => $payment->id,
                    'sale_id' => $saleId,
                    'allocated_amount' => $paymentAmount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $allocationsCreated++;
            }

            // Ensure at least one allocation was created
            if ($allocationsCreated === 0) {
                throw new \Exception('No payment allocations were created.');
            }

            DB::commit();

            $this->closeCollectModal();
            $this->clearSelectedCustomer();
            $this->dispatch('show-toast', type: 'success', message: 'Payment collected! Awaiting admin approval.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment collection error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            $this->dispatch('show-toast', type: 'error', message: 'Error: ' . $e->getMessage());
        }
    }

    public function render()
    {
        // Show distributor customers with delivered sales that have due amounts OR opening balance
        $customers = Customer::where('type', 'distributor')
            ->where(function ($query) {
                $query->whereHas('sales', function ($q) {
                    $q->where('status', 'confirm')
                        ->where('delivery_status', 'delivered')
                        ->where('due_amount', '>', 0);
                })
                    ->orWhere('opening_balance', '>', 0);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('phone', 'like', '%' . $this->search . '%');
                });
            })
            ->with(['sales' => function ($query) {
                $query->where('status', 'confirm')
                    ->where('delivery_status', 'delivered')
                    ->where('due_amount', '>', 0);
            }])
            ->orderBy('name')
            ->paginate(15);

        // Get pending payments collected by this user
        $pendingPayments = Payment::where('collected_by', Auth::id())
            ->where('status', 'pending')
            ->with(['customer'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.delivery-man.delivery-man-payment-collection', [
            'customers' => $customers,
            'pendingPayments' => $pendingPayments,
            'selectedCustomer' => $this->selectedCustomer,
            'customerSales' => $this->customerSales,
            'selectedInvoices' => $this->selectedInvoices,
            'totalDueAmount' => $this->totalDueAmount,
            'totalPaymentAmount' => $this->totalPaymentAmount,
            'remainingAmount' => $this->remainingAmount,
            'showCollectModal' => $this->showCollectModal,
            'paymentData' => $this->paymentData,
            'cheque' => $this->cheque,
            'bankTransfer' => $this->bankTransfer,
            'allocations' => $this->allocations,
        ]);
    }
}
