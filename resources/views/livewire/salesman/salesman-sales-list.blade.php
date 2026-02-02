<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-receipt text-primary me-2"></i> My Sales
            </h3>
            <p class="text-muted mb-0">View and track your sales orders</p>
        </div>
        <a href="{{ route('salesman.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-warning bg-opacity-10">
                <div class="card-body text-center py-3">
                    <h4 class="fw-bold text-warning mb-0">{{ $pendingCount }}</h4>
                    <small class="text-muted">Pending Approval</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-success bg-opacity-10">
                <div class="card-body text-center py-3">
                    <h4 class="fw-bold text-success mb-0">{{ $approvedCount }}</h4>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-danger bg-opacity-10">
                <div class="card-body text-center py-3">
                    <h4 class="fw-bold text-danger mb-0">{{ $rejectedCount }}</h4>
                    <small class="text-muted">Rejected</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by invoice, sale ID or customer...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="statusFilter" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="confirm">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="deliveryFilter" class="form-select">
                        <option value="">All Delivery Status</option>
                        <option value="pending">Pending</option>
                        <option value="in_transit">In Transit</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
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
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Delivery</th>
                            <th>Date</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                        <tr>
                            <td class="ps-4">
                                <span class="fw-medium">{{ $sale->invoice_number }}</span>
                                <small class="d-block text-muted">{{ $sale->sale_id }}</small>
                            </td>
                            <td>{{ $sale->customer->name ?? 'N/A' }}</td>
                            <td class="fw-semibold">Rs. {{ number_format($sale->total_amount, 2) }}</td>
                            <td>
                                @if($sale->status === 'pending')
                                    <span class="badge bg-warning"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                                @elseif($sale->status === 'confirm')
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>
                                @else
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>
                                @endif
                            </td>
                            <td>
                                @if($sale->delivery_status === 'pending')
                                    <span class="badge bg-secondary">Pending</span>
                                @elseif($sale->delivery_status === 'in_transit')
                                    <span class="badge bg-info">In Transit</span>
                                @elseif($sale->delivery_status === 'delivered')
                                    <span class="badge bg-success">Delivered</span>
                                @else
                                    <span class="badge bg-dark">{{ ucfirst($sale->delivery_status ?? 'N/A') }}</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $sale->created_at->format('M d, Y') }}</td>
                            <td class="text-end pe-4">
                                <button wire:click="viewDetails({{ $sale->id }})" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No sales found.
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
                            <p class="mb-1"><strong>Customer:</strong> {{ $selectedSale->customer->name ?? 'N/A' }}</p>
                            <p class="mb-1"><strong>Date:</strong> {{ $selectedSale->created_at->format('M d, Y H:i') }}</p>
                            <p class="mb-0"><strong>Status:</strong>
                                @if($selectedSale->status === 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @elseif($selectedSale->status === 'confirm')
                                    <span class="badge bg-success">Approved</span>
                                @else
                                    <span class="badge bg-danger">Rejected</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            @if($selectedSale->approvedBy)
                            <p class="mb-1"><strong>Approved By:</strong> {{ $selectedSale->approvedBy->name }}</p>
                            <p class="mb-1"><strong>Approved At:</strong> {{ $selectedSale->approved_at?->format('M d, Y H:i') }}</p>
                            @endif
                            @if($selectedSale->rejection_reason)
                            <p class="mb-0 text-danger"><strong>Rejection Reason:</strong> {{ $selectedSale->rejection_reason }}</p>
                            @endif
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
                                    <th class="text-end">Price</th>
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
                                    <td colspan="3" class="text-end fw-bold">Total:</td>
                                    <td class="text-end fw-bold">Rs. {{ number_format($selectedSale->total_amount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- Delivery Info --}}
                    @if($selectedSale->delivery_status)
                    <h6 class="fw-bold mb-2">Delivery Information</h6>
                    <div class="bg-light rounded p-3">
                        <p class="mb-1"><strong>Delivery Status:</strong>
                            @if($selectedSale->delivery_status === 'delivered')
                                <span class="badge bg-success">Delivered</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($selectedSale->delivery_status) }}</span>
                            @endif
                        </p>
                        @if($selectedSale->deliveredBy)
                        <p class="mb-1"><strong>Delivered By:</strong> {{ $selectedSale->deliveredBy->name }}</p>
                        <p class="mb-0"><strong>Delivered At:</strong> {{ $selectedSale->delivered_at?->format('M d, Y H:i') }}</p>
                        @endif
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeDetailsModal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
