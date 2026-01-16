<div class="container-fluid py-3">
    {{-- PAGE HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-cash-stack text-success me-2"></i> My Sales Management
            </h3>
            <p class="text-muted mb-0">View and manage your sales</p>
        </div>
        <div>
            <a href="{{ route('staff.sales-system') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i> New Sale
            </a>
        </div>
    </div>

    {{-- STATISTICS CARDS --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-start border-primary border-4 shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Sales</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $stats['total_sales'] }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-cart-check fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-start border-success border-4 shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Revenue</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">Rs.{{ number_format($stats['total_amount'], 2) }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-currency-dollar fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-start border-warning border-4 shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Payments</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">Rs.{{ number_format($stats['pending_payments'], 2) }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-clock-history fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-start border-info border-4 shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">Today's Sales</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $stats['today_sales'] }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-calendar-day fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTERS --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control"
                            placeholder="Search by invoice, customer name or phone..."
                            wire:model.live="search">
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Payment Status</label>
                    <select class="form-select" wire:model.live="paymentStatusFilter">
                        <option value="all">All Status</option>
                        <option value="paid">Paid</option>
                        <option value="partial">Partial</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date Filter</label>
                    <input type="date" class="form-control" wire:model.live="dateFilter">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Per Page</label>
                    <select class="form-select" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- SALES TABLE --}}
    <div class="card shadow">
        <div class="card-header">
            <h5 class="mb-0 fw-semibold">
                <i class="bi bi-list-ul me-2"></i>Sales List
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Paid Amount</th>
                            <th>Due Amount</th>
                            <th>Payment Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                        <tr>
                            <td><strong class="text-primary">{{ $sale->invoice_number }}</strong></td>
                            <td>
                                <div>{{ $sale->customer->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $sale->customer->phone ?? '' }}</small>
                            </td>
                            <td>{{ $sale->created_at->format('d M Y, h:i A') }}</td>
                            <td><strong>Rs.{{ number_format($sale->total_amount, 2) }}</strong></td>
                            <td>Rs.{{ number_format($sale->paid_amount ?? 0, 2) }}</td>
                            <td class="text-danger"><strong>Rs.{{ number_format($sale->due_amount, 2) }}</strong></td>
                            <td>
                                @if($sale->payment_status === 'paid')
                                <span class="badge bg-success">Paid</span>
                                @elseif($sale->payment_status === 'partial')
                                <span class="badge bg-warning">Partial</span>
                                @else
                                <span class="badge bg-danger">Pending</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" 
                                        wire:click="viewSale({{ $sale->id }})"
                                        title="View">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" 
                                        wire:click="downloadInvoice({{ $sale->id }})"
                                        title="Download">
                                        <i class="bi bi-download"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" 
                                        wire:click="deleteSale({{ $sale->id }})"
                                        title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-cart-x display-4 d-block mb-2"></i>
                                No sales found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($sales->hasPages())
            <div class="card-footer">
                <div class="d-flex justify-content-center">
                    {{ $sales->links() }}
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- VIEW SALE MODAL --}}
    @if($showViewModal && $selectedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-receipt me-2"></i>Sale Details - {{ $selectedSale->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-2">Customer Information</h6>
                            <p class="mb-1"><strong>Name:</strong> {{ $selectedSale->customer->name ?? 'N/A' }}</p>
                            <p class="mb-1"><strong>Phone:</strong> {{ $selectedSale->customer->phone ?? 'N/A' }}</p>
                            <p class="mb-1"><strong>Address:</strong> {{ $selectedSale->customer->address ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-2">Sale Information</h6>
                            <p class="mb-1"><strong>Date:</strong> {{ $selectedSale->created_at->format('d M Y, h:i A') }}</p>
                            <p class="mb-1"><strong>Payment Type:</strong> {{ ucfirst($selectedSale->payment_type) }}</p>
                            <p class="mb-1"><strong>Status:</strong> 
                                @if($selectedSale->payment_status === 'paid')
                                <span class="badge bg-success">Paid</span>
                                @elseif($selectedSale->payment_status === 'partial')
                                <span class="badge bg-warning">Partial</span>
                                @else
                                <span class="badge bg-danger">Pending</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2">Sale Items</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($selectedSale->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item->product_name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>Rs.{{ number_format($item->unit_price, 2) }}</td>
                                    <td>Rs.{{ number_format($item->total_price, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Total Amount:</strong></td>
                                    <td><strong>Rs.{{ number_format($selectedSale->total_amount, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Paid Amount:</strong></td>
                                    <td><strong>Rs.{{ number_format($selectedSale->paid_amount ?? 0, 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Due Amount:</strong></td>
                                    <td class="text-danger"><strong>Rs.{{ number_format($selectedSale->due_amount, 2) }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if($selectedSale->notes)
                    <div class="mt-3">
                        <h6 class="fw-bold">Notes:</h6>
                        <p>{{ $selectedSale->notes }}</p>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Close</button>
                    <button type="button" class="btn btn-success" wire:click="downloadInvoice({{ $selectedSale->id }})">
                        <i class="bi bi-download me-1"></i>Download Invoice
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- DELETE CONFIRMATION MODAL --}}
    @if($showDeleteModal && $selectedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirm Delete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete sale <strong>{{ $selectedSale->invoice_number }}</strong>?</p>
                    <p class="text-danger"><i class="bi bi-info-circle me-1"></i>This action cannot be undone. Stock quantities will be restored.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="button" class="btn btn-danger" wire:click="confirmDelete">
                        <i class="bi bi-trash me-1"></i>Delete Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('showToast', (event) => {
            const type = event.type || 'info';
            const message = event.message || 'Action completed';
            
            // You can integrate with your toast notification system here
            alert(message);
        });
    });
</script>
@endpush
</div>


