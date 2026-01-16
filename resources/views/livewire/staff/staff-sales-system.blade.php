<div class="container-fluid py-3">
    {{-- Flash Messages --}}
    @if (session()->has('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4">
        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if (session()->has('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4">
        <i class="bi bi-exclamation-circle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

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
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Customer *</label>
                        <select class="form-select shadow-sm" wire:model.live="customerId">
                            @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ $customer->name === 'Walking Customer' ? 'selected' : '' }}>
                                {{ $customer->name }}
                                @if($customer->phone)
                                - {{ $customer->phone }}
                                @endif
                                @if($customer->name === 'Walking Customer')
                                (Default)
                                @endif
                            </option>
                            @endforeach
                        </select>

                        <div class="form-text mt-2">
                            Select an existing customer or add a new one.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Add Products Card --}}
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-1">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-semibold">
                        <i class="bi bi-search me-2 text-success"></i> Add Allocated Products
                    </h5>
                </div>
                <div class="card-body position-relative">
                    <div class="mb-3">
                        <input type="text" class="form-control shadow-sm"
                            wire:model.live="search"
                            placeholder="Search allocated products by name, code, or model...">
                    </div>

                    {{-- Search Results --}}
                    @if($search && count($products) > 0)
                    <div class="search-results mt-1 position-absolute w-100 shadow-lg" style="max-height: 300px; max-width: 96%; z-index: 1055; overflow-y: auto;">
                        @foreach($products as $product)
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-white rounded-1"
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
                            <button class="btn btn-sm btn-outline-primary"
                                wire:click="addToCart({{ json_encode($product) }})"
                                {{ $product['stock'] <= 0 ? 'disabled' : '' }}>
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                        </div>
                        @endforeach
                    </div>
                    @elseif($search)
                    <div class="text-center text-muted p-3">
                        <i class="bi bi-exclamation-circle me-1"></i> No allocated products found
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Sale Items Table --}}
    <div class="col-md-12 mb-4">
        <div class="card overflow-auto">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-cart me-2"></i>Sale Items
                </h5>
                <span class="badge bg-primary">{{ count($cart) }} items</span>
            </div>
            <div class="card-body p-0">
                @if(count($cart) > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="30">#</th>
                                <th>Product</th>
                                <th width="120">Unit Price</th>
                                <th width="150">Quantity</th>
                                <th width="120">Total</th>
                                <th width="100" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cart as $index => $item)
                            <tr wire:key="{{ $item['key'] ?? 'cart_' . $index }}">
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <div>
                                        <strong>{{ $item['name'] }}</strong>
                                        <div class="text-muted small">
                                            {{ $item['code'] }} | {{ $item['model'] }}
                                        </div>
                                        <div class="text-info small">
                                            Stock: {{ $item['stock'] }}
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-bold">
                                    <input type="number" class="form-control-sm text-primary" style="min-width:90px;"
                                        wire:change="updatePrice({{ $index }}, $event.target.value)"
                                        value="{{ $item['price'] }}" min="0" step="0.01"
                                        placeholder="0.00">
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <button class="btn btn-outline-secondary" type="button"
                                            wire:click="decrementQuantity({{ $index }})">-</button>
                                        <input type="number" class="form-control text-center"
                                            wire:change="updateQuantity({{ $index }}, $event.target.value)"
                                            value="{{ $item['quantity'] }}" min="1" max="{{ $item['stock'] }}">
                                        <button class="btn btn-outline-secondary" type="button"
                                            wire:click="incrementQuantity({{ $index }})">+</button>
                                    </div>
                                </td>
                                <td class="fw-bold">
                                    Rs.{{ number_format($item['total'], 2) }}
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-danger"
                                        wire:click="removeFromCart({{ $index }})"
                                        title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                <td class="fw-bold">Rs.{{ number_format($subtotal, 2) }}</td>
                                <td></td>
                            </tr>

                            {{-- Discount Section --}}
                            <tr>
                                <td colspan="3" class="text-end fw-bold align-middle">
                                    Discount:
                                    @if($discount > 0)
                                    <button type="button" class="text-danger p-0 ms-1 border-0 bg-opacity-0"
                                        wire:click="removeDiscount" title="Remove discount">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                    @endif
                                </td>
                                <td colspan="2">
                                    <div class="input-group input-group-sm">
                                        <input type="number"
                                            class="form-control form-control-sm text-danger"
                                            wire:model.live="discount"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00">

                                        <span class="input-group-text">
                                            {{ $discountType === 'percentage' ? '%' : 'Rs.' }}
                                        </span>

                                        <button type="button"
                                            class="btn btn-outline-secondary"
                                            wire:click="toggleDiscountType"
                                            title="Switch Discount Type">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="fw-bold text-danger">
                                    @if($discount > 0)
                                    - Rs.{{ number_format($discountAmount, 2) }}
                                    @if($discountType === 'percentage')
                                    <div class="text-muted small">({{ $discount }}%)</div>
                                    @endif
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td></td>
                            </tr>

                            {{-- Grand Total --}}
                            <tr>
                                <td colspan="4" class="text-end fw-bold fs-5">Grand Total:</td>
                                <td class="fw-bold fs-5 text-primary">Rs.{{ number_format($grandTotal, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-5">
                    <i class="bi bi-cart display-4 d-block mb-2"></i>
                    No items added yet
                </div>
                @endif
            </div>
            @if(count($cart) > 0)
            <div class="card-footer">
                <button class="btn btn-danger" wire:click="clearCart">
                    <i class="bi bi-trash me-2"></i>Clear All Items
                </button>
            </div>
            @endif
        </div>
    </div>

    {{-- Payment Section --}}
    <div class="row">
        {{-- Notes --}}
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-chat-text me-2"></i>Notes & Payment Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes (Optional)</label>
                        <textarea class="form-control" wire:model="notes" rows="3"
                            placeholder="Add any notes for this sale..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- Create Sale Button --}}
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="fw-bold fs-5">Grand Total</div>
                        <div class="fw-bold fs-5 text-primary">Rs.{{ number_format($grandTotal, 2) }}</div>
                    </div>
                    @if($dueAmount > 0)
                    <div class="mb-3">
                        <div class="text-muted small">Due Amount</div>
                        <div class="fw-bold fs-6 text-danger">Rs.{{ number_format($dueAmount, 2) }}</div>
                    </div>
                    @endif
                    <button class="btn btn-success btn-lg px-5" wire:click="validateAndCreateSale"
                        {{ count($cart) == 0 ? 'disabled' : '' }}>
                        <i class="bi bi-cart-check me-2"></i>Complete Sale
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ADD CUSTOMER MODAL --}}
    @if($showCustomerModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-plus me-2"></i>Add New Customer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeCustomerModal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name *</label>
                        <input type="text" class="form-control" wire:model="customerName" placeholder="Enter customer name">
                        @error('customerName') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Phone *</label>
                        <input type="text" class="form-control" wire:model="customerPhone" placeholder="Enter phone number">
                        @error('customerPhone') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" wire:model="customerEmail" placeholder="Enter email">
                        @error('customerEmail') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Address *</label>
                        <textarea class="form-control" wire:model="customerAddress" rows="2" placeholder="Enter address"></textarea>
                        @error('customerAddress') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Customer Type *</label>
                        <select class="form-select" wire:model="customerType">
                            <option value="retail">Retail</option>
                            <option value="wholesale">Wholesale</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeCustomerModal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="createCustomer">
                        <i class="bi bi-check-circle me-1"></i>Create Customer
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- SALE COMPLETE MODAL --}}
    @if($showSaleModal && $createdSale)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-check-circle me-2"></i>Sale Created Successfully!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-check-circle-fill text-success display-1"></i>
                        <h4 class="mt-3">Invoice #{{ $createdSale->invoice_number }}</h4>
                        <p class="text-muted">Sale has been created successfully</p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Sale Details</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td>Customer:</td>
                                    <td class="fw-semibold">{{ $createdSale->customer->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td>Total Amount:</td>
                                    <td class="fw-bold text-success">Rs. {{ number_format($createdSale->total_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Paid Amount:</td>
                                    <td>Rs. {{ number_format($createdSale->paid_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Due Amount:</td>
                                    <td class="text-danger fw-bold">Rs. {{ number_format($createdSale->due_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Payment Method:</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $createdSale->payment_type)) }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Items ({{ $createdSale->items->count() }})</h6>
                            <ul class="list-group">
                                @foreach($createdSale->items as $item)
                                <li class="list-group-item py-1 px-2 small">
                                    {{ $item->product_name }} × {{ $item->quantity }}
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" wire:click="downloadInvoice">
                        <i class="bi bi-download me-1"></i>Download Invoice
                    </button>
                    <button type="button" class="btn btn-success" wire:click="createNewSale">
                        <i class="bi bi-plus-circle me-1"></i>New Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('showToast', (event) => {
            const type = event.type || 'info';
            const message = event.message || 'Action completed';
            alert(message);
        });
    });
</script>
@endpush
