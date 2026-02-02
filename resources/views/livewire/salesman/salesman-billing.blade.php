<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-cart-plus text-primary me-2"></i> Create Sales Order
            </h3>
            <p class="text-muted mb-0">Create orders for customers (requires admin approval)</p>
        </div>
        <a href="{{ route('salesman.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <div class="row g-4">
        {{-- Product Selection --}}
        <div class="col-lg-7">
            {{-- Customer Selection --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person me-2"></i>Select Customer</h6>
                    <select wire:model.live="customerId" class="form-select">
                        <option value="">-- Select Customer --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} - {{ $customer->phone ?? 'N/A' }}</option>
                        @endforeach
                    </select>
                    @if($selectedCustomer)
                    <div class="mt-2 p-2 bg-light rounded">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            {{ $selectedCustomer->address ?? 'No address' }} | Phone: {{ $selectedCustomer->phone ?? 'N/A' }}
                        </small>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Product Search --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search products by name or code...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select wire:model.live="priceType" class="form-select">
                                <option value="retail">Retail Price</option>
                                <option value="wholesale">Wholesale Price</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-4">Product</th>
                                    <th>Price</th>
                                    <th>Available</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $product)
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-medium">{{ $product['name'] }}</div>
                                        <small class="text-muted">{{ $product['code'] }}</small>
                                    </td>
                                    <td>Rs. {{ number_format($product['price'], 2) }}</td>
                                    <td>
                                        <span class="badge {{ $product['available'] > 0 ? 'bg-success' : 'bg-danger' }}">
                                            {{ $product['available'] }}
                                        </span>
                                        @if($product['pending'] > 0)
                                        <small class="text-warning d-block">{{ $product['pending'] }} pending</small>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <button wire:click="addToCart({{ json_encode($product) }})" 
                                            class="btn btn-sm btn-primary"
                                            {{ $product['available'] <= 0 ? 'disabled' : '' }}>
                                            <i class="bi bi-plus"></i> Add
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No products found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cart --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                <div class="card-header bg-primary text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-cart3 me-2"></i>Cart ({{ count($cart) }} items)</h5>
                        @if(count($cart) > 0)
                        <button wire:click="clearCart" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-trash"></i> Clear
                        </button>
                        @endif
                    </div>
                </div>
                <div class="card-body p-0">
                    @if(count($cart) > 0)
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-3">Product</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cart as $index => $item)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-medium small">{{ $item['name'] }}</div>
                                        <small class="text-muted">Rs. {{ number_format($item['price'], 2) }}</small>
                                    </td>
                                    <td>
                                        <input type="number" wire:change="updateQuantity({{ $index }}, $event.target.value)" 
                                            value="{{ $item['quantity'] }}" min="1" max="{{ $item['available'] }}"
                                            class="form-control form-control-sm" style="width: 60px;">
                                    </td>
                                    <td class="fw-semibold">Rs. {{ number_format($item['total'], 2) }}</td>
                                    <td>
                                        <button wire:click="removeFromCart({{ $index }})" class="btn btn-sm btn-link text-danger p-0">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-cart-x fs-1 d-block mb-2"></i>
                        Cart is empty
                    </div>
                    @endif
                </div>

                @if(count($cart) > 0)
                <div class="card-footer bg-white">
                    {{-- Additional Discount --}}
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small text-muted">Additional Discount</label>
                            <input type="number" wire:model.live="additionalDiscount" class="form-control form-control-sm" min="0">
                        </div>
                        <div class="col-6">
                            <label class="small text-muted">Type</label>
                            <select wire:model.live="additionalDiscountType" class="form-select form-select-sm">
                                <option value="fixed">Fixed (Rs.)</option>
                                <option value="percentage">Percentage (%)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="mb-3">
                        <label class="small text-muted">Order Notes</label>
                        <textarea wire:model="notes" class="form-control form-control-sm" rows="2" placeholder="Any notes..."></textarea>
                    </div>

                    {{-- Totals --}}
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal:</span>
                            <span>Rs. {{ number_format($this->subtotal, 2) }}</span>
                        </div>
                        @if($this->totalDiscount > 0)
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Item Discounts:</span>
                            <span>- Rs. {{ number_format($this->totalDiscount, 2) }}</span>
                        </div>
                        @endif
                        @if($this->additionalDiscountAmount > 0)
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Additional Discount:</span>
                            <span>- Rs. {{ number_format($this->additionalDiscountAmount, 2) }}</span>
                        </div>
                        @endif
                        <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2">
                            <span>Grand Total:</span>
                            <span class="text-primary">Rs. {{ number_format($this->grandTotal, 2) }}</span>
                        </div>
                    </div>

                    <button wire:click="createSale" class="btn btn-success w-100 mt-3 py-2" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="createSale">
                            <i class="bi bi-check-circle me-2"></i> Create Order (Pending Approval)
                        </span>
                        <span wire:loading wire:target="createSale">
                            <span class="spinner-border spinner-border-sm me-2"></span> Creating...
                        </span>
                    </button>
                    <small class="text-muted d-block text-center mt-2">
                        <i class="bi bi-info-circle me-1"></i>Order will be sent to admin for approval
                    </small>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
