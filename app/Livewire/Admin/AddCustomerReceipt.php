<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Cheque;
use App\Models\ReturnsProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title("Add Customer Receipt")]
class AddCustomerReceipt extends Component
{
    use WithDynamicLayout;

    use WithPagination;

    public $search = '';
    public $selectedCustomer = null;
    public $customerSales = [];
    public $selectedInvoices = [];
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
    public $showPaymentModal = false;
    public $showViewModal = false;
    public $showReceiptModal = false;
    public $selectedSale = null;
    public $latestPayment = null;
    public $paymentSuccess = false;

    protected $rules = [
        'paymentData.payment_date' => 'required|date',
        'paymentData.payment_method' => 'required|in:cash,cheque,bank_transfer',
        'paymentData.reference_number' => 'nullable|string|max:100',
        'paymentData.notes' => 'nullable|string|max:500',
        'totalPaymentAmount' => 'required|numeric|min:0.01',
        'cheque.cheque_number' => 'required_if:paymentData.payment_method,cheque|string|max:50',
        'cheque.bank_name' => 'required_if:paymentData.payment_method,cheque|string|max:100',
        'cheque.cheque_date' => 'required_if:paymentData.payment_method,cheque|date',
        'bankTransfer.bank_name' => 'required_if:paymentData.payment_method,bank_transfer|string|max:100',
        'bankTransfer.transfer_date' => 'required_if:paymentData.payment_method,bank_transfer|date',
        'bankTransfer.reference_number' => 'required_if:paymentData.payment_method,bank_transfer|string|max:100',
    ];

    protected $messages = [
        'paymentData.payment_date.required' => 'Payment date is required.',
        'paymentData.payment_method.required' => 'Payment method is required.',
        'totalPaymentAmount.required' => 'Payment amount is required.',
        'totalPaymentAmount.min' => 'Payment amount must be at least Rs. 0.01',
        'cheque.cheque_number.required_if' => 'Cheque number is required for cheque payments.',
        'cheque.bank_name.required_if' => 'Bank name is required for cheque payments.',
        'cheque.cheque_date.required_if' => 'Cheque date is required for cheque payments.',
        'bankTransfer.bank_name.required_if' => 'Bank name is required for bank transfer.',
        'bankTransfer.transfer_date.required_if' => 'Transfer date is required for bank transfer.',
        'bankTransfer.reference_number.required_if' => 'Reference number is required for bank transfer.',
    ];

    public function mount()
    {
        $this->paymentData['payment_date'] = now()->format('Y-m-d');
        $this->cheque['cheque_date'] = now()->format('Y-m-d');
        $this->bankTransfer['transfer_date'] = now()->format('Y-m-d');
        $this->totalPaymentAmount = 0;
    }

    public function updatedSearch()
    {
        $this->resetPage();
        $this->selectedCustomer = null;
        $this->customerSales = [];
        $this->resetPaymentData();
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
        // Reset all payment method specific fields first
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
        $this->resetPaymentData();
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
     * Clear invoice selection
     */
    public function clearInvoiceSelection()
    {
        $this->selectedInvoices = [];
        $this->totalDueAmount = 0;
        $this->totalPaymentAmount = 0;
        $this->remainingAmount = 0;
        $this->allocations = [];
    }

    /**
     * Calculate total return amount for a specific sale
     */
    private function calculateReturnAmount($saleId)
    {
        return ReturnsProduct::where('sale_id', $saleId)
            ->sum('total_amount');
    }

    /**
     * Load customer sales with return amounts calculated
     */
    private function loadCustomerSales()
    {
        if (!$this->selectedCustomer) return;

        $query = Sale::with(['items', 'payments', 'returns'])
            ->where('customer_id', $this->selectedCustomer->id)
            ->where(function ($query) {
                $query->where('payment_status', 'pending')
                    ->orWhere('payment_status', 'partial');
            });

        // Filter by user for staff
        if ($this->isStaff()) {
            $query->where('user_id', Auth::id())->where('sale_type', 'staff');
        }

        $sales = $query->orderBy('created_at', 'asc')
            ->get();

        $this->customerSales = $sales->map(function ($sale) {
            $paidAmount = $sale->total_amount - $sale->due_amount;

            // Calculate total return amount for this sale
            $returnAmount = $this->calculateReturnAmount($sale->id);

            // Adjusted amounts after returns
            $adjustedTotalAmount = $sale->total_amount - $returnAmount;
            $adjustedDueAmount = max(0, $sale->due_amount - $returnAmount);

            // If adjusted due amount is 0 or negative, update the sale status
            if ($adjustedDueAmount <= 0.01) {
                $this->autoMarkSaleAsPaid($sale->id, $returnAmount);
            }

            return [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'sale_id' => $sale->sale_id,
                'sale_date' => $sale->created_at->format('M d, Y'),
                'original_total_amount' => $sale->total_amount,
                'total_amount' => $adjustedTotalAmount,
                'original_due_amount' => $sale->due_amount,
                'due_amount' => $adjustedDueAmount,
                'return_amount' => $returnAmount,
                'paid_amount' => $paidAmount,
                'payment_status' => $adjustedDueAmount <= 0.01 ? 'paid' : $sale->payment_status,
                'items_count' => $sale->items->count(),
                'has_returns' => $returnAmount > 0,
            ];
        })->filter(function ($sale) {
            // Only show sales with due amount > 0 after returns
            return $sale['due_amount'] > 0.01;
        })->values()->toArray();

        $this->calculateTotalDue();
    }

    /**
     * Calculate total due amount for selected invoices
     */
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
                $this->allocations[$sale['id']] = [
                    'sale_id' => $sale['id'],
                    'invoice_number' => $sale['invoice_number'],
                    'due_amount' => $sale['due_amount'],
                    'payment_amount' => 0,
                    'is_fully_paid' => false
                ];
            }
        }
    }

    private function autoAllocatePayment()
    {
        $remainingPayment = $this->totalPaymentAmount;

        foreach ($this->customerSales as $sale) {
            $saleId = $sale['id'];

            // Only allocate to selected invoices
            if (!in_array($saleId, $this->selectedInvoices)) {
                continue;
            }

            $dueAmount = $sale['due_amount'];

            if ($remainingPayment <= 0) {
                $this->allocations[$saleId]['payment_amount'] = 0;
                $this->allocations[$saleId]['is_fully_paid'] = false;
            } elseif ($remainingPayment >= $dueAmount) {
                $this->allocations[$saleId]['payment_amount'] = $dueAmount;
                $this->allocations[$saleId]['is_fully_paid'] = true;
                $remainingPayment -= $dueAmount;
            } else {
                $this->allocations[$saleId]['payment_amount'] = $remainingPayment;
                $this->allocations[$saleId]['is_fully_paid'] = false;
                $remainingPayment = 0;
            }
        }
    }

    public function openPaymentModal()
    {
        if (empty($this->selectedInvoices)) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Please select at least one invoice to make a payment.'
            ]);
            return;
        }

        // Validate payment amount
        if (!$this->totalPaymentAmount || $this->totalPaymentAmount <= 0) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Please enter a payment amount greater than zero.'
            ]);
            return;
        }

        if ($this->totalPaymentAmount > $this->totalDueAmount) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Payment amount cannot exceed total due amount.'
            ]);
            return;
        }

        // Validate cheque amounts if payment method is cheque
        if ($this->paymentData['payment_method'] === 'cheque') {
            if ($this->cheque['amount'] != $this->totalPaymentAmount) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Cheque amount must equal the payment amount.'
                ]);
                return;
            }
        }

        // Allocate payment
        $this->autoAllocatePayment();

        // Show modal
        $this->showPaymentModal = true;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->paymentSuccess = false;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedSale = null;
    }

    public function openReceiptModal()
    {
        $this->showReceiptModal = true;
    }

    public function closeReceiptModal()
    {
        $this->showReceiptModal = false;
        $this->latestPayment = null;

        // Reset everything
        $this->selectedCustomer = null;
        $this->customerSales = [];
        $this->selectedInvoices = [];
        $this->allocations = [];
        $this->totalDueAmount = 0;
        $this->totalPaymentAmount = 0;
        $this->remainingAmount = 0;
        $this->cheque = [
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'amount' => 0
        ];
        $this->search = '';
        $this->resetPaymentData();

        // Reset page
        $this->resetPage();

        // Dispatch event to refresh the page
        $this->dispatch('payment-completed');
    }

    private function resetPaymentData()
    {
        $this->paymentData = [
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'reference_number' => '',
            'notes' => ''
        ];
        $this->totalPaymentAmount = 0;
        $this->cheque = [
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'amount' => 0
        ];
        $this->bankTransfer = [
            'bank_name' => '',
            'transfer_date' => now()->format('Y-m-d'),
            'reference_number' => ''
        ];
    }

    public function viewSale($saleId)
    {
        $this->selectedSale = Sale::with(['customer', 'items', 'payments', 'returns.product'])->find($saleId);

        // Calculate return amount for display
        if ($this->selectedSale) {
            $this->selectedSale->return_amount = $this->calculateReturnAmount($saleId);
            $this->selectedSale->adjusted_total = $this->selectedSale->total_amount - $this->selectedSale->return_amount;
            $this->selectedSale->adjusted_due = max(0, $this->selectedSale->due_amount - $this->selectedSale->return_amount);
        }

        $this->showViewModal = true;
    }

    /**
     * Automatically mark sale as paid if returns cover the full due amount
     */
    private function autoMarkSaleAsPaid($saleId, $returnAmount)
    {
        try {
            $sale = Sale::find($saleId);
            if ($sale && $sale->due_amount <= $returnAmount) {
                DB::beginTransaction();

                // Create a system payment record for the return adjustment
                $payment = Payment::create([
                    'customer_id' => $sale->customer_id,
                    'amount' => min($sale->due_amount, $returnAmount),
                    'payment_method' => 'return_adjustment',
                    'payment_reference' => 'AUTO-RETURN-' . $sale->invoice_number,
                    'payment_date' => now(),
                    'status' => 'paid',
                    'is_completed' => 1,
                    'notes' => 'Automatically adjusted due to product returns covering the full amount',
                    'created_by' => Auth::id() ?? 1,
                ]);

                // Create payment allocation
                DB::table('payment_allocations')->insert([
                    'payment_id' => $payment->id,
                    'sale_id' => $saleId,
                    'allocated_amount' => min($sale->due_amount, $returnAmount),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update sale
                $sale->due_amount = 0;
                $sale->payment_status = 'paid';
                $sale->save();

                DB::commit();

                Log::info('Sale automatically marked as paid due to returns', [
                    'sale_id' => $saleId,
                    'return_amount' => $returnAmount,
                    'payment_id' => $payment->id
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to auto-mark sale as paid', [
                'sale_id' => $saleId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function processPayment()
    {
        Log::info('Payment processing started', [
            'customer_id' => $this->selectedCustomer->id,
            'amount' => $this->totalPaymentAmount,
            'method' => $this->paymentData['payment_method']
        ]);

        // Validate inputs
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);

            // Get first error message
            $firstError = collect($e->errors())->flatten()->first();

            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => $firstError ?? 'Please fill all required fields correctly.'
            ]);
            return;
        }

        // Check payment amount
        if ($this->totalPaymentAmount > $this->totalDueAmount) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Payment amount cannot exceed total due amount.'
            ]);
            return;
        }

        // Additional validation: Check for duplicate cheque numbers in database
        if ($this->paymentData['payment_method'] === 'cheque') {
            $existingCheque = Cheque::where('cheque_number', $this->cheque['cheque_number'])->first();
            if ($existingCheque) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => "Cheque number '{$this->cheque['cheque_number']}' already exists in the system. Please use a different cheque number."
                ]);
                return;
            }
        }

        try {
            DB::beginTransaction();

            $totalProcessed = 0;
            $processedInvoices = [];

            // Create a single payment record for customer (not tied to specific sale)
            $paymentData = [
                'customer_id' => $this->selectedCustomer->id,
                'amount' => $this->totalPaymentAmount,
                'payment_method' => $this->paymentData['payment_method'],
                'payment_reference' => $this->paymentData['reference_number'] ?? null,
                'payment_date' => $this->paymentData['payment_date'],
                'status' => 'paid',
                'is_completed' => 1,
                'notes' => $this->paymentData['notes'] ?? null,
                'created_by' => Auth::id(),
            ];

            // Add bank transfer details if payment method is bank transfer
            if ($this->paymentData['payment_method'] === 'bank_transfer') {
                $paymentData['bank_name'] = $this->bankTransfer['bank_name'];
                $paymentData['transfer_date'] = $this->bankTransfer['transfer_date'];
                $paymentData['transfer_reference'] = $this->bankTransfer['reference_number'];
            }

            $payment = Payment::create($paymentData);

            Log::info('Main payment created', ['payment_id' => $payment->id]);

            // Process cheques if payment method is cheque
            if ($this->paymentData['payment_method'] === 'cheque') {
                Cheque::create([
                    'payment_id' => $payment->id,
                    'cheque_number' => $this->cheque['cheque_number'],
                    'bank_name' => $this->cheque['bank_name'],
                    'cheque_date' => $this->cheque['cheque_date'],
                    'cheque_amount' => $this->cheque['amount'],
                    'status' => 'pending',
                    'customer_id' => $this->selectedCustomer->id,
                ]);
                Log::info('Cheque created');
            }

            // Process each sale allocation
            foreach ($this->customerSales as $sale) {
                $saleId = $sale['id'];

                // Only process selected invoices
                if (!in_array($saleId, $this->selectedInvoices)) {
                    continue;
                }

                $allocation = $this->allocations[$saleId];
                $paymentAmount = $allocation['payment_amount'];

                if ($paymentAmount <= 0) continue;

                // Update sale with adjusted due amount
                $saleModel = Sale::find($saleId);
                if ($saleModel) {
                    $newDueAmount = $saleModel->due_amount - $paymentAmount;
                    $returnAmount = $this->calculateReturnAmount($saleId);

                    // Adjust for returns
                    $adjustedDueAmount = max(0, $newDueAmount - $returnAmount);
                    $saleModel->due_amount = $adjustedDueAmount;

                    if ($saleModel->due_amount <= 0.01) {
                        $saleModel->payment_status = 'paid';
                        $saleModel->due_amount = 0;
                    } else {
                        $saleModel->payment_status = 'partial';
                    }

                    $saleModel->save();

                    // Create payment allocation record
                    DB::table('payment_allocations')->insert([
                        'payment_id' => $payment->id,
                        'sale_id' => $saleId,
                        'allocated_amount' => $paymentAmount,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Log::info('Sale updated and allocation created', [
                        'sale_id' => $saleId,
                        'payment_amount' => $paymentAmount,
                        'return_amount' => $returnAmount,
                        'new_due' => $saleModel->due_amount
                    ]);
                }

                $totalProcessed += $paymentAmount;
                $processedInvoices[] = $sale['invoice_number'];
            }

            DB::commit();

            Log::info('Payment processed successfully', [
                'total_processed' => $totalProcessed,
                'invoices' => $processedInvoices,
                'payment_id' => $payment->id
            ]);

            $this->paymentSuccess = true;
            $this->showPaymentModal = false;
            $this->latestPayment = $payment;

            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => "Payment of Rs." . number_format($totalProcessed, 2) . " processed successfully!"
            ]);

            // Open receipt modal
            $this->openReceiptModal();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Check if it's a duplicate entry error for cheque number
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'Duplicate entry') !== false && strpos($errorMessage, 'cheques_cheque_number_unique') !== false) {
                // Extract cheque number from error message
                preg_match("/Duplicate entry '([^']+)'/", $errorMessage, $matches);
                $chequeNumber = $matches[1] ?? 'unknown';

                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => "Cheque number '{$chequeNumber}' already exists in the system. Please use a different cheque number."
                ]);
            } else {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Failed to process payment. Please check your input and try again.'
                ]);
            }
        }
    }

    public function downloadReceipt()
    {
        if (!$this->latestPayment) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'No payment receipt available to download.'
            ]);
            return;
        }

        try {
            // Load payment with relationships
            $payment = Payment::with(['cheques'])
                ->find($this->latestPayment->id);

            // Get allocations from payment_allocations table with return information
            $allocations = DB::table('payment_allocations')
                ->join('sales', 'payment_allocations.sale_id', '=', 'sales.id')
                ->where('payment_allocations.payment_id', $payment->id)
                ->select(
                    'sales.id as sale_id',
                    'sales.invoice_number',
                    'sales.total_amount',
                    'payment_allocations.allocated_amount'
                )
                ->get()
                ->map(function ($allocation) {
                    $returnAmount = $this->calculateReturnAmount($allocation->sale_id);
                    $allocation->return_amount = $returnAmount;
                    $allocation->adjusted_total = $allocation->total_amount - $returnAmount;
                    return $allocation;
                });

            $receiptData = [
                'payment' => $payment,
                'customer' => $this->selectedCustomer,
                'received_by' => Auth::user()->name,
                'payment_date' => $payment->payment_date,
                'allocations' => $allocations,
            ];

            $pdf = PDF::loadView('admin.receipts.payment-receipt', $receiptData);
            $pdf->setPaper('a4', 'portrait');

            $filename = 'payment-receipt-' . $payment->id . '-' . date('Y-m-d') . '.pdf';

            return response()->streamDownload(
                function () use ($pdf) {
                    echo $pdf->output();
                },
                $filename
            );
        } catch (\Exception $e) {
            Log::error('Receipt download failed', [
                'error' => $e->getMessage(),
                'payment_id' => $this->latestPayment->id
            ]);

            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Failed to generate receipt: ' . $e->getMessage()
            ]);
        }
    }

    public function getCustomersProperty()
    {
        return Customer::with(['sales' => function ($query) {
            $query->where(function ($q) {
                $q->where('payment_status', 'pending')
                    ->orWhere('payment_status', 'partial');
            });
        }])
            ->whereHas('sales', function ($query) {
                $query->where(function ($q) {
                    $q->where('payment_status', 'pending')
                        ->orWhere('payment_status', 'partial');
                });
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('phone', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('name')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.admin.add-customer-receipt', [
            'customers' => $this->customers
        ])->layout($this->layout);
    }
}
