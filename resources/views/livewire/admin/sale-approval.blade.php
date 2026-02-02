<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-clipboard-check text-primary me-2"></i> Sale Approvals
            </h3>
            <p class="text-muted mb-0">Review and approve/reject pending sales from salesmen</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by invoice, sale ID or customer...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="statusFilter" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="confirm">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="">All Status</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Sales List --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Invoice</th>
                            <th>Salesman</th>
                            <th>Customer</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                        <tr>
                            <td class="ps-4">
                                <span class="fw-medium">{{ $sale->invoice_number }}</span>
                                <small class="d-block text-muted">{{ $sale->sale_id }}</small>
                            </td>
                            <td>{{ $sale->user->name ?? 'N/A' }}</td>
                            <td>{{ $sale->customer->name ?? 'N/A' }}</td>
                            <td class="text-end fw-semibold">Rs. {{ number_format($sale->total_amount, 2) }}</td>
                            <td>
                                @if($sale->status === 'pending')
                                    <span class="badge bg-warning"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                                @elseif($sale->status === 'confirm')
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>
                                @else
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $sale->created_at->format('M d, Y') }}</td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <button wire:click="viewDetails({{ $sale->id }})" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    @if($sale->status === 'pending')
                                    <button wire:click="approveSale({{ $sale->id }})" class="btn btn-sm btn-success"
                                            wire:confirm="Are you sure you want to approve this sale? Stock will be reduced.">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button wire:click="openRejectModal({{ $sale->id }})" class="btn btn-sm btn-danger">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                No sales to display.
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

    {{-- Details Modal --}}
    @if($showDetailsModal && $selectedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-receipt me-2"></i>Sale Details - {{ $selectedSale->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeDetailsModal"></button>
                </div>
                <div class="modal-body">
                    {{-- Sale Info --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Salesman:</strong> {{ $selectedSale->user->name ?? 'N/A' }}</p>
                            <p class="mb-1"><strong>Customer:</strong> {{ $selectedSale->customer->name ?? 'N/A' }}</p>
                            <p class="mb-0"><strong>Date:</strong> {{ $selectedSale->created_at->format('M d, Y H:i') }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Status:</strong>
                                @if($selectedSale->status === 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @elseif($selectedSale->status === 'confirm')
                                    <span class="badge bg-success">Approved</span>
                                @else
                                    <span class="badge bg-danger">Rejected</span>
                                @endif
                            </p>
                            <p class="mb-0"><strong>Total Amount:</strong> <span class="fw-bold text-primary">Rs. {{ number_format($selectedSale->total_amount, 2) }}</span></p>
                        </div>
                    </div>

                    {{-- Items --}}
                    <h6 class="fw-bold mb-2">Order Items</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($selectedSale->items as $item)
                                <tr>
                                    <td>{{ $item->product_name }}</td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-end">Rs. {{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-end">Rs. {{ number_format($item->total, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Subtotal:</td>
                                    <td class="text-end">Rs. {{ number_format($selectedSale->subtotal, 2) }}</td>
                                </tr>
                                @if($selectedSale->discount_amount > 0)
                                <tr>
                                    <td colspan="3" class="text-end text-danger">Discount:</td>
                                    <td class="text-end text-danger">- Rs. {{ number_format($selectedSale->discount_amount, 2) }}</td>
                                </tr>
                                @endif
                                <tr class="table-primary">
                                    <td colspan="3" class="text-end fw-bold">Grand Total:</td>
                                    <td class="text-end fw-bold">Rs. {{ number_format($selectedSale->total_amount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if($selectedSale->notes)
                    <div class="bg-light rounded p-3">
                        <strong>Notes:</strong> {{ $selectedSale->notes }}
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    @if($selectedSale->status === 'pending')
                    <button wire:click="approveSale({{ $selectedSale->id }})" class="btn btn-success"
                            wire:confirm="Are you sure you want to approve this sale? Stock will be reduced.">
                        <i class="bi bi-check-circle me-2"></i>Approve
                    </button>
                    <button wire:click="openRejectModal({{ $selectedSale->id }})" class="btn btn-danger">
                        <i class="bi bi-x-circle me-2"></i>Reject
                    </button>
                    @endif
                    <button type="button" class="btn btn-secondary" wire:click="closeDetailsModal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Reject Modal --}}
    @if($showRejectModal && $selectedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Reject Sale</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeRejectModal"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to reject sale <strong>{{ $selectedSale->invoice_number }}</strong>.</p>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea wire:model="rejectionReason" class="form-control" rows="3" placeholder="Please provide a reason for rejection..."></textarea>
                        @error('rejectionReason') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeRejectModal">Cancel</button>
                    <button wire:click="rejectSale" class="btn btn-danger">
                        <i class="bi bi-x-circle me-2"></i>Reject Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
