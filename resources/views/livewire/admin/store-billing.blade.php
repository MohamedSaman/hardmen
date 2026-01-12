<div class="pos-container">
    {{-- Opening Cash Modal --}}
    @if($showOpeningCashModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.8);" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-3 border-0 shadow-lg">
                <div class="modal-header text-white rounded-top" style="background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%);">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-cash-stack me-2"></i>Enter Opening Cash Amount
                    </h5>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-calendar-check" style="font-size: 3rem; color: #f58320;"></i>
                        <h5 class="mt-3 mb-1 fw-bold" style="color: #f58320;">Start New POS Session</h5>
                        <p class="text-muted">{{ now()->format('l, F d, Y') }}</p>
                    </div>
                    <div class="mb-3">
                        <label for="openingCashAmount" class="form-label fw-semibold" style="color:#f58320;">
                            Opening Cash Amount (Rs.) *
                        </label>
                        <input type="number" class="form-control form-control-lg text-center fw-bold"
                            id="openingCashAmount" wire:model="openingCashAmount" step="0.01" min="0"
                            placeholder="0.00" style="font-size: 1.5rem; border: 2px solid #f58320;" autofocus>
                        @error('openingCashAmount')
                        <div class="text-danger mt-1 small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer justify-content-center bg-light">
                    <button type="button" class="btn btn-lg text-white px-5"
                        style="background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%);"
                        wire:click="submitOpeningCash">
                        <i class="bi bi-check-circle me-2"></i>Start POS Session
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Header Section --}}
    <div class="header-section mb-3">
        <div class="d-flex justify-content-between align-items-center p-3 bg-white rounded shadow-sm border">
            <div class="d-flex align-items-center">
                <img src="{{ asset('images/HARDMEN.png') }}" alt="Logo" style="height:45px;" class="me-3">
                <div>
                    <h4 class="mb-0 fw-bold" style="color:#f58320;">HARDMEN (PVT) LTD</h4>
                    <small class="text-muted">Point of Sale System</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="badge d-flex align-items-center px-3 py-2 rounded-2 shadow-sm"
                    style="background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%); color:white; cursor: pointer;"
                    wire:click="viewCloseRegisterReport" role="button">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    <span class="fw-semibold">View Report</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main POS Layout --}}
    <div class="row g-0" style="min-height: calc(100vh - 95px);">
        
        {{-- LEFT SIDEBAR - Customer & Cart --}}
        <div class="col-md-3 bg-white border-end d-flex flex-column" style="max-height: calc(100vh - 95px);">
            {{-- Customer Section --}}
            <div class="p-3 border-bottom">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-person me-2" style="color: #f58320;"></i>Customer
                    </h6>
                    <button class="btn btn-link btn-sm p-0 text-decoration-none" style="color: #f58320;" wire:click="openCustomerModal">
                        <i class="bi bi-plus-circle me-1"></i>Add New
                    </button>
                </div>
                <select class="form-select form-select-sm" wire:model.live="customerId">
                    @foreach($customers as $customer)
                    <option value="{{ $customer->id }}">
                        {{ $customer->name }}
                        @if($customer->phone) - {{ $customer->phone }} @endif
                        @if($customer->name === 'Walking Customer') (Default) @endif
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Shopping Cart --}}
            <div class="p-3 flex-grow-1" style="overflow-y: auto;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-cart3 me-2" style="color: #f58320;"></i>Shopping Cart
                    </h6>
                    <span class="badge text-white" style="background: #f58320;">{{ count($cart) }} Items</span>
                </div>

                @if(count($cart) > 0)
                <div class="cart-items">
                    @foreach($cart as $index => $item)
                    <div class="cart-item d-flex align-items-center p-2 mb-2 bg-light rounded" wire:key="cart-{{ $item['key'] ?? $index }}">
                        <div class="cart-item-image me-2">
                            @if(isset($item['image']) && $item['image'])
                            <img src="{{ strpos($item['image'], 'http') === 0 || strpos($item['image'], 'data:') === 0 ? $item['image'] : asset('storage/' . $item['image']) }}" alt="{{ $item['name'] }}" 
                                class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                            @else
                            <img src="{{ asset('images/default-product.png') }}" alt="No Image" 
                                class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                            @endif
                        </div>
                        <div class="cart-item-details flex-grow-1" style="min-width: 0;">
                            <h6 class="mb-0 small fw-semibold text-truncate">{{ $item['name'] }}</h6>
                            <small class="text-muted">Rs. {{ number_format($item['price'], 0) }}</small>
                            <div class="d-flex align-items-center mt-1">
                                <button class="btn btn-sm btn-outline-secondary px-2 py-0" 
                                    wire:click="decrementQuantity({{ $index }})">-</button>
                                <span class="mx-2 small fw-bold">{{ $item['quantity'] }}</span>
                                <button class="btn btn-sm btn-outline-secondary px-2 py-0" 
                                    wire:click="incrementQuantity({{ $index }})">+</button>
                            </div>
                        </div>
                        <div class="cart-item-price text-end ms-2">
                            <span class="fw-bold small" style="color: #f58320;">Rs. {{ number_format($item['total'], 0) }}</span>
                            <button class="btn btn-link btn-sm text-danger p-0 d-block" 
                                wire:click="removeFromCart({{ $index }})" title="Remove">
                                <i class="bi bi-trash small"></i>
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center text-muted py-4">
                    <i class="bi bi-cart-x" style="font-size: 2rem;"></i>
                    <p class="mb-0 mt-2 small">Cart is empty</p>
                </div>
                @endif
            </div>

            {{-- Cart Summary & Complete Sale --}}
            <div class="p-3 border-top bg-light mt-auto">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Subtotal</span>
                    <span class="fw-semibold small">Rs. {{ number_format($subtotal, 2) }}</span>
                </div>
                @if($totalDiscount > 0)
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Discount</span>
                    <span class="text-danger small">- Rs. {{ number_format($totalDiscount, 2) }}</span>
                </div>
                @endif
                <div class="d-flex justify-content-between mb-3 pt-2 border-top">
                    <span class="fw-bold">Total</span>
                    <span class="fw-bold fs-5" style="color: #f58320;">Rs. {{ number_format($grandTotal, 2) }}</span>
                </div>
                <button class="btn w-100 text-white py-2 fw-bold" 
                    style="background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%);"
                    wire:click="validateAndCreateSale"
                    {{ count($cart) == 0 ? 'disabled' : '' }}>
                    <i class="bi bi-cart-check me-2"></i>Complete Sale
                </button>
            </div>
        </div>

        {{-- CENTER - Search & Products Grid --}}
        <div class="col-md-7 bg-light d-flex flex-column" style="max-height: calc(100vh - 95px);">
            {{-- Search Bar --}}
            <div class="p-3 bg-white border-bottom">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" 
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by product name, code, or model...">
                    @if($search)
                    <button class="btn btn-outline-secondary" type="button" wire:click="$set('search', '')">
                        <i class="bi bi-x"></i>
                    </button>
                    @endif
                </div>
            </div>

            {{-- Products Grid --}}
            <div class="p-3 flex-grow-1" style="overflow-y: auto;">
                <div class="row g-2">
                    @forelse($products as $product)
                    <div class="col-6 col-lg-4 col-xl-3">
                        <div class="card h-100 product-card border-0 shadow-sm" 
                            style="cursor: pointer; transition: all 0.2s;"
                            wire:click="addToCart({{ json_encode($product) }})"
                            onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 15px rgba(245,131,32,0.3)'"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 .125rem .25rem rgba(0,0,0,.075)'">
                            <div class="card-body p-2 text-center">
                                @if(isset($product['image']) && $product['image'])
                                <img src="{{ strpos($product['image'], 'http') === 0 || strpos($product['image'], 'data:') === 0 ? $product['image'] : asset('storage/' . $product['image']) }}" 
                                    alt="{{ $product['name'] }}"
                                    class="img-fluid rounded mb-2"
                                    style="height: 70px; width: 100%; object-fit: cover;">
                                @else
                                <div class="bg-light rounded mb-2 d-flex align-items-center justify-content-center"
                                    style="height: 70px;">
                                    <i class="bi bi-image text-muted" style="font-size: 1.5rem;"></i>
                                </div>
                                @endif
                                <h6 class="card-title mb-1 small fw-semibold text-truncate" title="{{ $product['name'] }}">{{ $product['name'] }}</h6>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold small" style="color: #f58320;">Rs. {{ number_format($product['price'], 0) }}</span>
                                    <span class="badge bg-success" style="font-size: 0.65rem;">IN STOCK</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-box-seam" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="text-muted mt-2">No products found</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- RIGHT SIDEBAR - Categories --}}
        <div class="col-md-2 bg-white border-start d-flex flex-column" style="max-height: calc(100vh - 95px);">
            {{-- Categories --}}
            <div class="p-3 flex-grow-1" style="overflow-y: auto;">
                <h6 class="mb-3 fw-bold">
                    <i class="bi bi-grid me-2" style="color: #f58320;"></i>Categories
                </h6>
                
                {{-- All Products Button --}}
                <button class="btn w-100 mb-2 text-start d-flex justify-content-between align-items-center {{ !$selectedCategory ? 'text-white' : 'btn-light' }}"
                    style="{{ !$selectedCategory ? 'background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%);' : '' }}"
                    wire:click="showAllProducts">
                    <span><i class="bi bi-collection me-2"></i>All Products</span>
                    <i class="bi bi-chevron-right"></i>
                </button>

                {{-- Category List --}}
                @foreach($categories as $category)
                <button class="btn w-100 mb-2 text-start d-flex justify-content-between align-items-center {{ $selectedCategory == $category->id ? 'text-white' : 'btn-light' }}"
                    style="{{ $selectedCategory == $category->id ? 'background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%);' : '' }}"
                    wire:click="selectCategory({{ $category->id }})">
                    <span>{{ $category->category_name }}</span>
                </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Payment Modal --}}
    @if($showPaymentModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.7);" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%);">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-credit-card me-2"></i>Payment Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closePaymentModal"></button>
                </div>
                <div class="modal-body p-4">
                    {{-- Order Summary --}}
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Subtotal:</span>
                                <span class="fw-semibold">Rs. {{ number_format($subtotal, 2) }}</span>
                            </div>
                            @if($totalDiscount > 0)
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Discount:</span>
                                <span class="text-danger">- Rs. {{ number_format($totalDiscount, 2) }}</span>
                            </div>
                            @endif
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="fw-bold fs-5">Total Amount:</span>
                                <span class="fw-bold fs-4" style="color: #f58320;">Rs. {{ number_format($grandTotal, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Payment Method Selection --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold mb-3" style="color: #f58320;">
                            <i class="bi bi-wallet2 me-2"></i>Select Payment Method
                        </label>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="payment-option {{ $paymentMethod == 'cash' ? 'selected' : '' }}" 
                                    wire:click="$set('paymentMethod', 'cash')"
                                    style="cursor: pointer; padding: 20px; border: 2px solid {{ $paymentMethod == 'cash' ? '#f58320' : '#dee2e6' }}; border-radius: 10px; transition: all 0.3s; background: {{ $paymentMethod == 'cash' ? '#fff5ed' : 'white' }};">
                                    <div class="text-center">
                                        <i class="bi bi-cash-stack" style="font-size: 2.5rem; color: {{ $paymentMethod == 'cash' ? '#f58320' : '#6c757d' }};"></i>
                                        <h6 class="mt-2 mb-0 fw-semibold" style="color: {{ $paymentMethod == 'cash' ? '#f58320' : '#495057' }};">Cash</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="payment-option {{ $paymentMethod == 'card' ? 'selected' : '' }}" 
                                    wire:click="$set('paymentMethod', 'card')"
                                    style="cursor: pointer; padding: 20px; border: 2px solid {{ $paymentMethod == 'card' ? '#f58320' : '#dee2e6' }}; border-radius: 10px; transition: all 0.3s; background: {{ $paymentMethod == 'card' ? '#fff5ed' : 'white' }};">
                                    <div class="text-center">
                                        <i class="bi bi-credit-card-2-front" style="font-size: 2.5rem; color: {{ $paymentMethod == 'card' ? '#f58320' : '#6c757d' }};"></i>
                                        <h6 class="mt-2 mb-0 fw-semibold" style="color: {{ $paymentMethod == 'card' ? '#f58320' : '#495057' }};">Card</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="payment-option {{ $paymentMethod == 'bank_transfer' ? 'selected' : '' }}" 
                                    wire:click="$set('paymentMethod', 'bank_transfer')"
                                    style="cursor: pointer; padding: 20px; border: 2px solid {{ $paymentMethod == 'bank_transfer' ? '#f58320' : '#dee2e6' }}; border-radius: 10px; transition: all 0.3s; background: {{ $paymentMethod == 'bank_transfer' ? '#fff5ed' : 'white' }};">
                                    <div class="text-center">
                                        <i class="bi bi-bank" style="font-size: 2.5rem; color: {{ $paymentMethod == 'bank_transfer' ? '#f58320' : '#6c757d' }};"></i>
                                        <h6 class="mt-2 mb-0 fw-semibold" style="color: {{ $paymentMethod == 'bank_transfer' ? '#f58320' : '#495057' }};">Bank Transfer</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="payment-option {{ $paymentMethod == 'cheque' ? 'selected' : '' }}" 
                                    wire:click="$set('paymentMethod', 'cheque')"
                                    style="cursor: pointer; padding: 20px; border: 2px solid {{ $paymentMethod == 'cheque' ? '#f58320' : '#dee2e6' }}; border-radius: 10px; transition: all 0.3s; background: {{ $paymentMethod == 'cheque' ? '#fff5ed' : 'white' }};">
                                    <div class="text-center">
                                        <i class="bi bi-check2-square" style="font-size: 2.5rem; color: {{ $paymentMethod == 'cheque' ? '#f58320' : '#6c757d' }};"></i>
                                        <h6 class="mt-2 mb-0 fw-semibold" style="color: {{ $paymentMethod == 'cheque' ? '#f58320' : '#495057' }};">Cheque</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @error('paymentMethod')
                        <div class="text-danger mt-2 small">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Payment Amount Input (for cash with change calculation) --}}
                    @if($paymentMethod == 'cash')
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color: #f58320;">Amount Received</label>
                            <input type="number" class="form-control form-control-lg" 
                                wire:model.live="amountReceived" 
                                step="0.01" 
                                min="{{ $grandTotal }}"
                                placeholder="Enter amount"
                                style="border: 2px solid #f58320;">
                            @error('amountReceived')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-success">Change to Return</label>
                            <input type="text" class="form-control form-control-lg text-success fw-bold" 
                                value="Rs. {{ number_format(max(0, ($amountReceived ?? 0) - $grandTotal), 2) }}" 
                                readonly
                                style="border: 2px solid #28a745; background-color: #f0fff4;">
                        </div>
                    </div>
                    @endif

                    {{-- Payment Notes --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="color: #f58320;">
                            <i class="bi bi-pencil-square me-1"></i>Payment Notes (Optional)
                        </label>
                        <textarea class="form-control" wire:model="paymentNotes" rows="2" 
                            placeholder="Add any payment notes or reference..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary px-4" wire:click="closePaymentModal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn text-white px-4 py-2 fw-bold" 
                        style="background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%);"
                        wire:click="completeSaleWithPayment">
                        <i class="bi bi-check-circle me-2"></i>Complete Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Add Customer Modal --}}
    @if($showCustomerModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%);">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-plus me-2"></i>Add New Customer
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeCustomerModal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#f58320;">Name *</label>
                            <input type="text" class="form-control" wire:model="customerName" placeholder="Enter customer name">
                            @error('customerName') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#f58320;">Phone *</label>
                            <input type="text" class="form-control" wire:model="customerPhone" placeholder="Enter phone number">
                            @error('customerPhone') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#f58320;">Email</label>
                            <input type="email" class="form-control" wire:model="customerEmail" placeholder="Enter email address">
                            @error('customerEmail') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#f58320;">Customer Type *</label>
                            <select class="form-select" wire:model="customerType">
                                <option value="retail">Retail</option>
                                <option value="wholesale">Wholesale</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="color:#f58320;">Address *</label>
                            <textarea class="form-control" wire:model="customerAddress" rows="2" placeholder="Enter address"></textarea>
                            @error('customerAddress') <span class="text-danger small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeCustomerModal">Cancel</button>
                    <button type="button" class="btn text-white" style="background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%);" wire:click="createCustomer">
                        <i class="bi bi-check-circle me-2"></i>Create Customer
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Sale Preview Modal --}}
    @if($showSaleModal && $createdSale)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content rounded-0">
                <div class="modal-header text-white rounded-0" style="background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%);">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-cart-check me-2"></i>
                        Sale Completed Successfully! - {{ $createdSale->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="createNewSale"></button>
                </div>

                <div class="modal-body p-0">
                    <div class="sale-preview p-4" id="saleReceiptPrintContent">
                        {{-- Screen Only Header (visible on screen, hidden on print) --}}
                        <div class="screen-only-header mb-4">
                            <div class="text-center border-bottom pb-3">
                                <img src="{{ asset('images/HARDMEN.png') }}" alt="Logo" style="max-height:50px;" class="mb-2">
                                <h5 class="fw-bold mb-1">HARDMEN (PVT) LTD</h5>
                                <p class="text-muted small mb-0">TOOLS WITH POWER</p>
                                <p class="text-muted small mb-0">421/2, Doolmala, Thihariya, Kalagedihena</p>
                            </div>
                        </div>

                        {{-- Invoice Header --}}
                        <div class="row mb-4">
                            <div class="col-6">
                                <p class="mb-1"><strong>Customer:</strong> {{ $createdSale->customer->name }}</p>
                                <p class="mb-0 small text-muted">{{ $createdSale->customer->phone ?? 'N/A' }}</p>
                            </div>
                            <div class="col-6 text-end">
                                <p class="mb-1"><strong>Invoice #:</strong> {{ $createdSale->invoice_number }}</p>
                                <p class="mb-0 small text-muted">{{ $createdSale->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>

                        {{-- Items Table --}}
                        <div class="table-responsive mb-3">
                            <table class="table table-sm invoice-table">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 5%;">#</th>
                                        <th style="width: 45%;">Item</th>
                                        <th class="text-center" style="width: 15%;">Qty</th>
                                        <th class="text-end" style="width: 18%;">Price</th>
                                        <th class="text-end" style="width: 17%;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($createdSale->items as $index => $item)
                                    <tr>
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td>{{ $item->product_name }}</td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end">Rs.{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-end">Rs.{{ number_format($item->total, 2) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">No items found</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr class="totals-row">
                                        <td colspan="4" class="text-end">Subtotal:</td>
                                        <td class="text-end">Rs.{{ number_format($createdSale->items->sum('total'), 2) }}</td>
                                    </tr>
                                    <tr class="grand-total">
                                        <td colspan="4" class="text-end fw-bold">Grand Total</td>
                                        <td class="text-end fw-bold">Rs.{{ number_format($createdSale->total_amount, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- Footer Note --}}
                        <div class="invoice-footer mt-4 border-top pt-3">
                            <p class="small text-muted mb-1"><strong>Thank you for your purchase!</strong></p>
                            <p class="small text-muted mb-0">This is a computer generated invoice. No signature required.</p>
                        </div>
                    </div>
                </div>

                {{-- Footer Buttons --}}
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-secondary me-2" wire:click="createNewSale">
                        <i class="bi bi-x-circle me-2"></i>Close
                    </button>
                    <button type="button" class="btn btn-outline-primary me-2" wire:click="printSaleReceipt">
                        <i class="bi bi-printer me-2"></i>Print
                    </button>
                    <button type="button" class="btn btn-success" wire:click="downloadInvoice">
                        <i class="bi bi-download me-2"></i>Download Invoice
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Close Register Modal --}}
    @if($showCloseRegisterModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #f58320 0%, #d16d0e 100%); color: white;">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-x-circle me-2"></i>CLOSE REGISTER ({{ date('d/m/Y H:i') }})
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="cancelCloseRegister"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm">
                        <tbody>
                            <tr><td>Cash in hand:</td><td class="text-end">Rs.{{ number_format($sessionSummary['opening_cash'] ?? 0, 2) }}</td></tr>
                            <tr><td>Cash Sales (POS):</td><td class="text-end">Rs.{{ number_format($sessionSummary['pos_cash_sales'] ?? 0, 2) }}</td></tr>
                            <tr><td>Total POS Sales:</td><td class="text-end fw-bold">Rs.{{ number_format($sessionSummary['total_pos_sales'] ?? 0, 2) }}</td></tr>
                            <tr><td>Expenses:</td><td class="text-end">Rs.{{ number_format($sessionSummary['expenses'] ?? 0, 2) }}</td></tr>
                            <tr><td>Refunds:</td><td class="text-end">Rs.{{ number_format($sessionSummary['refunds'] ?? 0, 2) }}</td></tr>
                            <tr class="table-success"><td class="fw-bold">Total Cash in Hand:</td><td class="text-end fw-bold">Rs.{{ number_format($sessionSummary['expected_cash'] ?? 0, 2) }}</td></tr>
                        </tbody>
                    </table>
                    <div class="mb-3">
                        <label class="form-label"><strong>Note:</strong></label>
                        <textarea class="form-control" rows="2" wire:model="closeRegisterNotes" placeholder="Add any notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeRegisterAndRedirect">
                        <i class="bi bi-x-circle me-1"></i>Close Register
                    </button>
                    <button type="button" class="btn btn-info" wire:click="downloadCloseRegisterReport">
                        <i class="bi bi-download me-1"></i>Download
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('styles')
<style>
    .pos-container {
        background-color: #f8f9fa;
        padding: 15px;
    }
    
    .cart-items::-webkit-scrollbar,
    .col-md-3::-webkit-scrollbar,
    .col-md-6::-webkit-scrollbar {
        width: 5px;
    }
    
    .cart-items::-webkit-scrollbar-thumb,
    .col-md-3::-webkit-scrollbar-thumb,
    .col-md-6::-webkit-scrollbar-thumb {
        background: #f58320;
        border-radius: 5px;
    }
    
    kbd {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #6c757d;
        font-size: 0.7rem;
        padding: 2px 5px;
    }
    
    .product-card {
        border-radius: 8px;
    }
    
    .product-card:active {
        transform: scale(0.98);
    }

    /* Payment Modal Animations */
    .payment-option {
        transition: all 0.3s ease;
    }
    
    .payment-option:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(245, 131, 32, 0.3);
        border-color: #f58320 !important;
    }
    
    .payment-option.selected {
        animation: pulse 0.5s ease;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .modal.show {
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Hide print headers on screen */
    .screen-only-header {
        display: block;
    }

    /* Invoice Table Styles */
    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem;
    }

    .invoice-table th {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 8px;
        font-weight: 600;
        text-align: center;
    }

    .invoice-table td {
        border: 1px solid #dee2e6;
        padding: 6px 8px;
    }

    .invoice-table .totals-row td {
        border-top: 2px solid #000;
        font-weight: 600;
        padding: 8px;
    }

    .invoice-table .grand-total td {
        border-top: 3px double #000;
        font-size: 1.1rem;
        background-color: #f0f0f0;
        font-weight: bold;
    }

    /* Print styles for sale receipt */
    @media print {
        body * {
            visibility: hidden;
        }

        #saleReceiptPrintContent,
        #saleReceiptPrintContent * {
            visibility: visible !important;
        }

        #saleReceiptPrintContent {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 15mm !important;
            background: white !important;
            color: black !important;
        }

        /* Hide screen elements */
        .modal-header,
        .modal-footer,
        .btn,
        .badge {
            display: none !important;
            visibility: hidden !important;
        }

        /* Invoice table styles for print */
        .invoice-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin: 15px 0 !important;
            font-size: 12px !important;
        }

        .invoice-table th {
            background-color: #f8f9fa !important;
            border: 1px solid #000 !important;
            padding: 8px !important;
            font-weight: bold !important;
            text-align: center !important;
        }

        .invoice-table td {
            border: 1px solid #000 !important;
            padding: 6px 8px !important;
        }

        .invoice-table .totals-row td {
            border-top: 2px solid #000 !important;
            font-weight: bold !important;
            padding: 8px !important;
        }

        .invoice-table .grand-total td {
            border-top: 3px double #000 !important;
            font-size: 14px !important;
            background-color: #f0f0f0 !important;
        }

        /* Page setup */
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        body {
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
        }
    }

    @media screen {
        .print-only-header {
            display: none !important;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+N - Focus search for new sale
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                document.querySelector('input[wire\\:model\\.live\\.debounce\\.300ms="search"]')?.focus();
            }
            // Ctrl+F - Focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[wire\\:model\\.live\\.debounce\\.300ms="search"]')?.focus();
            }
            // F10 - Complete sale
            if (e.key === 'F10') {
                e.preventDefault();
                Livewire.dispatch('validateAndCreateSale');
            }
        });
        
        // Listen for sale completion
        Livewire.on('saleSaved', function() {
            // Clean up any modals
            setTimeout(() => {
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.remove();
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
            }, 100);
        });
    });
</script>
@endpush
