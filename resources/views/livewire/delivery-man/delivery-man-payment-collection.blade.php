<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-cash-stack text-success me-2"></i> Payment Collection
            </h3>
            <p class="text-muted mb-0">Collect payments from customers (requires admin approval)</p>
        </div>
        <a href="{{ route('delivery.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    {{-- Pending Payments Alert --}}
    @if($pendingPayments->count() > 0)
    <div class="card border-0 shadow-sm bg-warning bg-opacity-10 mb-4">
        <div class="card-header bg-warning text-dark">
            <h6 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Your Pending Payment Approvals ({{ $pendingPayments->count() }})</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Invoice</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Collected At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingPayments as $payment)
                        <tr>
                            <td class="ps-3">{{ $payment->sale->invoice_number ?? 'N/A' }}</td>
                            <td>{{ $payment->sale->customer->name ?? 'N/A' }}</td>
                            <td class="fw-semibold">Rs. {{ number_format($payment->amount, 2) }}</td>
                            <td class="text-muted">{{ $payment->collected_at?->format('M d, Y h:i A') }}</td>
                            <td><span class="badge bg-warning">Pending Approval</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Search --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by invoice, customer name or phone...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sales with Dues --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-receipt me-2"></i>Sales with Outstanding Dues</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Invoice</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Due</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                        <tr>
                            <td class="ps-4">
                                <span class="fw-medium">{{ $sale->invoice_number }}</span>
                                <small class="d-block text-muted">{{ $sale->created_at->format('M d, Y') }}</small>
                            </td>
                            <td>
                                {{ $sale->customer->name ?? 'N/A' }}
                                @if($sale->customer->phone ?? false)
                                <small class="d-block text-muted">{{ $sale->customer->phone }}</small>
                                @endif
                            </td>
                            <td>Rs. {{ number_format($sale->total_amount, 2) }}</td>
                            <td class="text-success">Rs. {{ number_format($sale->total_amount - $sale->due_amount, 2) }}</td>
                            <td>
                                <span class="fw-bold text-danger">Rs. {{ number_format($sale->due_amount, 2) }}</span>
                            </td>
                            <td class="text-end pe-4">
                                <button wire:click="openCollectModal({{ $sale->id }})" class="btn btn-sm btn-success">
                                    <i class="bi bi-cash me-1"></i> Collect
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                No outstanding dues! All payments collected.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $sales->links() }}
    </div>

    {{-- Collect Payment Modal --}}
    @if($showCollectModal && $selectedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-cash me-2"></i>Collect Payment
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeCollectModal"></button>
                </div>
                <div class="modal-body">
                    {{-- Sale Info --}}
                    <div class="bg-light rounded p-3 mb-4">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted d-block">Invoice</small>
                                <span class="fw-bold">{{ $selectedSale->invoice_number }}</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Customer</small>
                                <span class="fw-bold">{{ $selectedSale->customer->name ?? 'N/A' }}</span>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-4">
                                <small class="text-muted d-block">Total</small>
                                <span>Rs. {{ number_format($selectedSale->total_amount, 2) }}</span>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Paid</small>
                                <span class="text-success">Rs. {{ number_format($selectedSale->total_amount - $selectedSale->due_amount, 2) }}</span>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Due</small>
                                <span class="fw-bold text-danger">Rs. {{ number_format($selectedSale->due_amount, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Payment Form --}}
                    <form wire:submit="collectPayment">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Amount to Collect</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" wire:model="amount" class="form-control" min="1" max="{{ $selectedSale->due_amount }}" step="0.01" required>
                            </div>
                            <small class="text-muted">Maximum: Rs. {{ number_format($selectedSale->due_amount, 2) }}</small>
                            @error('amount') <small class="text-danger d-block">{{ $message }}</small> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-medium">Payment Method</label>
                            <select wire:model="paymentMethod" class="form-select" required>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-medium">Notes (Optional)</label>
                            <textarea wire:model="paymentNotes" class="form-control" rows="2" placeholder="Any notes about the payment..."></textarea>
                        </div>

                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            This payment will be sent to admin for approval before being processed.
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="collectPayment">
                                    <i class="bi bi-check-circle me-2"></i>Collect Payment
                                </span>
                                <span wire:loading wire:target="collectPayment">
                                    <span class="spinner-border spinner-border-sm me-2"></span>Processing...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeCollectModal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
