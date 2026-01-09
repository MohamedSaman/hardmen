<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="fw-bold text-dark mb-2">
                        <i class="bi bi-file-earmark-text text-success me-2"></i> Create Quotation
                    </h3>
                    <p class="text-muted">Quickly create professional quotations for customers</p>
                </div>
            </div>
        </div>
    </div>

    

    <div class="row">
        {{-- Customer Information --}}
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-1">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-semibold">
                        <i class="bi bi-person me-2 text-primary"></i> Customer Information
                    </h5>
                    <button class="btn btn-sm btn-primary" wire:click="openCustomerModal">
                        <i class="bi bi-plus-circle me-1"></i> Add New Customer
                    </button>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        {{-- Select Customer (No default) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Customer *</label>
                            <select class="form-select shadow-sm" wire:model.live="customerId">
                                <option value="">-- Select a Customer --</option>
                                @foreach($customers as $customer)
                                    <option value="">-- Select a Customer --</option>
                                    <option value="{{ $customer->id }}">
                                        {{ $customer->name }}
                                        @if($customer->phone)
                                            - {{ $customer->phone }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text mt-2">
                                Select an existing customer or add a new one.
                            </div>
                        </div>

                        {{-- Valid Until --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Valid Until *</label>
                            <input type="date" class="form-control shadow-sm" wire:model="validUntil">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Add Products --}}
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-1">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-semibold">
                        <i class="bi bi-search me-2 text-success"></i> Add Products
                    </h5>
                </div>

                <div class="card-body">
                    {{-- Search Field --}}
                    <div class="mb-3">
                        <input type="text" class="form-control shadow-sm"
                            wire:model.live="search"
                            placeholder="Search by product name, code, or model...">
                    </div>

                    {{-- Search Results --}}
                    @if($search && count($searchResults) > 0)
                        <div class="search-results border rounded bg-white shadow-sm" style="max-height: 300px; overflow-y: auto;">
                            @foreach($searchResults as $product)
                                <div class="p-3 border-bottom d-flex justify-content-between align-items-center"
                                    wire:key="product-{{ $product['id'] }}">
                                    <div>
                                        <h6 class="mb-1 fw-semibold">{{ $product['name'] }}</h6>
                                        <p class="text-muted small mb-0">
                                            Code: {{ $product['code'] }} | Model: {{ $product['model'] }}
                                        </p>
                                        <p class="text-success small mb-0">
                                            Rs.{{ number_format($product['price'], 2) }} | Stock: {{ $product['stock'] }}
                                        </p>
                                    </div>
                                    <button class="btn btn-sm btn-success" wire:click="addToCart({{ json_encode($product) }})">
                                        <i class="bi bi-plus-circle"></i> Add
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @elseif($search && count($searchResults) == 0)
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i> No products found
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Quotation Items --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-1">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-semibold">
                        <i class="bi bi-cart me-2 text-info"></i> Quotation Items
                        <span class="badge bg-info ms-2">{{ count($cart) }} Items</span>
                    </h5>
                </div>

                <div class="card-body">
                    @if(count($cart) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Product</th>
                                        <th width="120">Unit Price</th>
                                        <th width="100">Quantity</th>
                                        <th width="120">Discount/Unit</th>
                                        <th width="120">Total</th>
                                        <th width="60" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cart as $index => $item)
                                        <tr wire:key="cart-{{ $index }}">
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <strong>{{ $item['name'] }}</strong>
                                                <br>
                                                <small class="text-muted">{{ $item['code'] }}</small>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm" step="0.01" 
                                                    value="{{ number_format($item['price'], 2, '.', '') }}"
                                                    wire:change="updateUnitPrice({{ $index }}, $event.target.value)">
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <button class="btn btn-outline-secondary" wire:click="decrementQuantity({{ $index }})">
                                                        <i class="bi bi-dash"></i>
                                                    </button>
                                                    <input type="number" class="form-control text-center" value="{{ $item['quantity'] }}"
                                                        wire:change="updateQuantity({{ $index }}, $event.target.value)">
                                                    <button class="btn btn-outline-secondary" wire:click="incrementQuantity({{ $index }})">
                                                        <i class="bi bi-plus"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm" step="0.01"
                                                    value="{{ number_format($item['discount'], 2, '.', '') }}"
                                                    wire:change="updateDiscount({{ $index }}, $event.target.value)">
                                            </td>
                                            <td>
                                                <strong class="text-success">Rs.{{ number_format($item['total'], 2) }}</strong>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-danger" wire:click="removeFromCart({{ $index }})">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="row mt-4 ms-auto" style="width: 350px;">
                            <div class="d-flex justify-content-between border-top pt-3 mb-2">
                                <strong>Subtotal:</strong>
                                <strong>Rs.{{ number_format($subtotal, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-3">
                                <strong>Item Discounts:</strong>
                                <strong class="text-danger">-Rs.{{ number_format($totalDiscount, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mt-3 mb-2">
                                <strong>After Item Discounts:</strong>
                                <strong>Rs.{{ number_format($subtotalAfterItemDiscounts, 2) }}</strong>
                            </div>

                            {{-- Additional Discount --}}
                            <div class="row g-2 my-3">
                                <div class="col-6">
                                    <select class="form-select form-select-sm" wire:model.live="additionalDiscountType">
                                        <option value="fixed">Fixed</option>
                                        <option value="percentage">Percentage</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control form-control-sm" step="0.01"
                                        wire:model.live="additionalDiscount"
                                        placeholder="Additional Discount">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between border-top pt-3 mb-2">
                                <strong>Additional Discount:</strong>
                                <strong class="text-danger">-Rs.{{ number_format($additionalDiscountAmount, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-3">
                                <h5 class="mb-0">Grand Total:</h5>
                                <h5 class="mb-0 text-success">Rs.{{ number_format($grandTotal, 2) }}</h5>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-danger me-2" wire:click="clearCart">
                                <i class="bi bi-trash me-1"></i> Clear All Items
                            </button>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-cart-x display-3 text-muted mb-3"></i>
                            <p class="text-muted">No items added yet. Search and add products above.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Terms & Conditions --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-1">
                <div class="card-header">
                    <h5 class="card-title mb-0 fw-semibold">
                        <i class="bi bi-file-text me-2 text-warning"></i> Terms & Conditions
                    </h5>
                </div>

                <div class="card-body">
                    <textarea class="form-control" rows="5" wire:model="termsConditions" placeholder="Add terms and conditions..."></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Notes --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-1">
                <div class="card-header">
                    <h5 class="card-title mb-0 fw-semibold">
                        <i class="bi bi-sticky me-2 text-secondary"></i> Notes
                    </h5>
                </div>

                <div class="card-body">
                    <textarea class="form-control" rows="3" wire:model="notes" placeholder="Add any additional notes..."></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Action Buttons --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-secondary" wire:click="createNewQuotation">
                    <i class="bi bi-arrow-clockwise me-1"></i> Reset
                </button>
                <button class="btn btn-success btn-lg" wire:click="createQuotation">
                    <i class="bi bi-check-circle me-1"></i> Create Quotation
                </button>
            </div>
        </div>
    </div>

    {{-- Quotation Modal --}}
    @if($showQuotationModal && $createdQuotation)
        <div class="modal show d-block" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-check-circle me-2"></i> Quotation Created Successfully
                        </h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            Quotation <strong>#{{ $createdQuotation->quotation_number }}</strong> has been created successfully!
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <p><strong>Customer:</strong> {{ $createdQuotation->customer_name }}</p>
                                <p><strong>Quotation Date:</strong> {{ $createdQuotation->quotation_date->format('d-m-Y') }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Valid Until:</strong> {{ $createdQuotation->valid_until->format('d-m-Y') }}</p>
                                <p><strong>Total Amount:</strong> <span class="text-success fw-bold">Rs.{{ number_format($createdQuotation->total_amount, 2) }}</span></p>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            The quotation is ready for download or printing.
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="closeModal">Close</button>
                        <button class="btn btn-info" wire:click="downloadQuotation">
                            <i class="bi bi-download me-1"></i> Download PDF
                        </button>
                        <button class="btn btn-primary" wire:click="printQuotation">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                        <button class="btn btn-success" wire:click="createNewQuotation">
                            <i class="bi bi-plus-circle me-1"></i> Create Another
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Customer Modal --}}
    @if($showCustomerModal)
        <div class="modal show d-block" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-person-plus me-2"></i> Add New Customer
                        </h5>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeCustomerModal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Customer Name *</label>
                                <input type="text" class="form-control" wire:model="customerName" placeholder="Enter customer name">
                                @error('customerName') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone</label>
                                <input type="text" class="form-control" wire:model="customerPhone" placeholder="Phone number">
                                @error('customerPhone') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" class="form-control" wire:model="customerEmail" placeholder="Email address">
                                @error('customerEmail') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Address *</label>
                                <input type="text" class="form-control" wire:model="customerAddress" placeholder="Customer address">
                                @error('customerAddress') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Customer Type *</label>
                                <select class="form-select" wire:model="customerType">
                                    <option value="retail">Retail</option>
                                    <option value="wholesale">Wholesale</option>
                                    <option value="business">Business</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Business Name</label>
                                <input type="text" class="form-control" wire:model="businessName" placeholder="Business name (optional)">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="closeCustomerModal">Cancel</button>
                        <button class="btn btn-primary" wire:click="createCustomer">
                            <i class="bi bi-check-circle me-1"></i> Create Customer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

