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
                <div class="col-md-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="text-sm text-muted fw-medium">Show</label>
                        <select wire:model.live="perPage" class="form-select form-select-sm" style="width: 80px;">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                        </select>
                        <span class="text-sm text-muted">entries</span>
                    </div>
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
                                    <button wire:click="openApproveModal({{ $sale->id }})" class="btn btn-sm btn-success">
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
            <div class="card-footer bg-light">
                <div class="d-flex justify-content-center">
                    {{ $sales->links('livewire.custom-pagination') }}
                </div>
            </div>
        </div>
    </div>

    
    {{-- Details Modal --}}
    @if($showDetailsModal && $selectedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-receipt me-2"></i>Invoice Preview - {{ $selectedSale->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeDetailsModal"></button>
                </div>
                <div class="modal-body p-4" style="background: #f8f9fa;">
                    {{-- Printable Invoice --}}
                    <div id="printableInvoice" class="receipt-container" style="background: white; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 8px;">
                        {{-- Header --}}
                        <div class="text-center mb-4">
                            <h4 class="fw-bold mb-2">HARDMEN (PVT) LTD</h4>
                            <p class="text-muted mb-1">TOOLS WITH POWER</p>
                            <p class="text-muted small">421/2, Doomala, thihariya, Kalagodithena.</p>
                            <p class="text-muted small">TEL: (077) 9752950, EMAIL: Hardmenlanka@gmail.com</p>
                        </div>

                        <hr class="my-3">

                        {{-- Customer & Invoice Info --}}
                        <div class="row mb-4">
                            <div class="col-6">
                                <p class="mb-1"><strong>Name:</strong> {{ $selectedSale->customer->name ?? 'N/A' }}</p>
                                <p class="mb-1"><strong>Phone:</strong> {{ $selectedSale->customer->phone ?? 'N/A' }}</p>
                                <p class="mb-1"><strong>Address:</strong> {{ $selectedSale->customer->address ?? 'N/A' }}</p>
                                <p class="mb-0"><strong>Salesman:</strong> {{ $selectedSale->user->name ?? 'N/A' }}</p>
                            </div>
                            <div class="col-6 text-end">
                                <p class="mb-1"><strong>Invoice Number:</strong> {{ $selectedSale->invoice_number }}</p>
                                <p class="mb-1"><strong>Date:</strong> {{ $selectedSale->created_at->format('m/d/Y h:i A') }}</p>
                                <p class="mb-0"><strong>Payment Status:</strong> <span class="badge bg-success">Paid</span></p>
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- Items Table --}}
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 5%">#</th>
                                        <th style="width: 15%">Code</th>
                                        <th style="width: 35%">Item</th>
                                        <th class="text-end" style="width: 15%">Price</th>
                                        <th class="text-center" style="width: 10%">Qty</th>
                                        <th class="text-end" style="width: 20%">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($selectedSale->items as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $item->product_code ?? 'N/A' }}</td>
                                        <td>{{ $item->product_name }}</td>
                                        <td class="text-end">Rs.{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end"><strong>Rs.{{ number_format($item->total, 2) }}</strong></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <hr class="my-3">

                        {{-- Summary --}}
                        <div class="row">
                            <div class="col-6">
                                {{-- Empty column --}}
                            </div>
                            <div class="col-6">
                                <table class="table table-sm table-borderless">
                                    <tbody>
                                        <tr>
                                            <td class="text-end"><strong>Subtotal:</strong></td>
                                            <td class="text-end">Rs.{{ number_format($selectedSale->subtotal, 2) }}</td>
                                        </tr>
                                        @if($selectedSale->discount_amount > 0)
                                        <tr>
                                            <td class="text-end text-danger"><strong>Discount:</strong></td>
                                            <td class="text-end text-danger">- Rs.{{ number_format($selectedSale->discount_amount, 2) }}</td>
                                        </tr>
                                        @endif
                                        <tr class="table-primary">
                                            <td class="text-end fw-bold">Grand Total:</td>
                                            <td class="text-end fw-bold">Rs.{{ number_format($selectedSale->total_amount, 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- Signatures --}}
                        <div class="row mt-5 pt-4 text-center">
                            <div class="col-6">
                                <p class="mb-4" style="border-top: 1px dotted #000; padding-top: 40px;">Authorized Signature</p>
                            </div>
                            <div class="col-6">
                                <p class="mb-4" style="border-top: 1px dotted #000; padding-top: 40px;">Customer Signature</p>
                            </div>
                        </div>

                        <div class="text-center mt-4 text-muted small">
                            <p>Thank you for your business!</p>
                            <p>www.hardmen.lk | info@hardmen.lk</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" wire:click="closeDetailsModal">
                        <i class="bi bi-x-circle me-2"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="printInvoice()">
                        <i class="bi bi-printer me-2"></i>Print
                    </button>
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

    {{-- Approve Confirmation Modal --}}
    @if($showApproveModal && $selectedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Approve Sale</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeApproveModal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Approving this sale will reduce the stock quantities.
                    </div>
                    <p>You are about to approve sale <strong>{{ $selectedSale->invoice_number }}</strong>.</p>
                    <div class="bg-light rounded p-3">
                        <p class="mb-1"><strong>Customer:</strong> {{ $selectedSale->customer->name ?? 'N/A' }}</p>
                        <p class="mb-1"><strong>Total Amount:</strong> <span class="text-primary fw-bold">Rs. {{ number_format($selectedSale->total_amount, 2) }}</span></p>
                        <p class="mb-0"><strong>Items:</strong> {{ $selectedSale->items->count() }} products</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeApproveModal">Cancel</button>
                    <button wire:click="approveSale" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Confirm Approval
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
    function printInvoice() {
        const printEl = document.getElementById('printableInvoice');
        if (!printEl) { 
            alert('Invoice not found. Please try again.');
            return; 
        }

        // Clone the content to avoid modifying the original
        let content = printEl.cloneNode(true);
        
        // Remove any buttons or interactive elements from print
        content.querySelectorAll('button, .no-print, .modal-footer').forEach(el => el.remove());

        // Get the HTML string
        let htmlContent = content.innerHTML;

        // Open a new window
        const printWindow = window.open('', '_blank', 'width=900,height=700');
        
        if (!printWindow) {
            alert('Popup blocked. Please allow pop-ups for this site.');
            return;
        }

        // Complete HTML document with print styles
        const fullHtml = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Invoice - HARDMEN (PVT) LTD</title>
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    body {
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        font-size: 12px;
                        line-height: 1.4;
                        color: #000;
                    }
                    .receipt-container {
                        padding: 20px;
                        max-width: 800px;
                        margin: 0 auto;
                        background: white;
                    }
                    h4 {
                        font-size: 18px;
                        font-weight: bold;
                        margin-bottom: 5px;
                    }
                    .text-center { text-align: center; }
                    .text-end { text-align: right; }
                    .text-muted { color: #666; }
                    .small { font-size: 11px; }
                    .mb-1 { margin-bottom: 4px; }
                    .mb-2 { margin-bottom: 8px; }
                    .mb-4 { margin-bottom: 16px; }
                    .my-3 { margin: 12px 0; }
                    .my-4 { margin: 16px 0; }
                    .row { display: flex; }
                    .col-6 { flex: 0 0 50%; padding-right: 10px; }
                    .col-6:last-child { padding-right: 0; padding-left: 10px; }
                    
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 10px;
                    }
                    th, td {
                        padding: 6px;
                        text-align: left;
                        border: 1px solid #ddd;
                    }
                    th {
                        background-color: #f5f5f5;
                        font-weight: bold;
                    }
                    .text-end { text-align: right; }
                    .text-center { text-align: center; }
                    .fw-bold { font-weight: bold; }
                    .badge {
                        background-color: #28a745;
                        color: white;
                        padding: 2px 6px;
                        border-radius: 3px;
                        font-size: 10px;
                    }
                    hr { border: none; border-top: 1px solid #000; margin: 12px 0; }
                    .table-light th { background-color: #f5f5f5; }
                    .table-primary { background-color: #e7f3ff; }
                    
                    p { margin-bottom: 4px; }
                    strong { font-weight: bold; }
                    
                    @media print {
                        body { margin: 0; padding: 0; }
                        .receipt-container { padding: 10px; }
                    }
                </style>
            </head>
            <body>
                ${htmlContent}
            </body>
            </html>
        `;

        // Write the content
        try {
            printWindow.document.open();
            printWindow.document.write(fullHtml);
            printWindow.document.close();
        } catch(e) {
            alert('Failed to prepare print: ' + e.message);
        }
        
        // Focus the print window
        printWindow.focus();
        
        // Trigger print after a short delay to ensure content is rendered
        setTimeout(() => {
            printWindow.print();
        }, 250);
    }
</script>
@endpush
