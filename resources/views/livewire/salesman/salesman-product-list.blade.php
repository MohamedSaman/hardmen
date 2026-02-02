<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-box-seam text-primary me-2"></i> Product Catalog
            </h3>
            <p class="text-muted mb-0">View available products and stock levels (Distributor Prices)</p>
        </div>
        <a href="{{ route('salesman.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by name, code, or model...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="categoryFilter" class="form-select">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Products Grid --}}
    <div class="row g-4">
        @forelse($products as $product)
        @php
            $info = $stockInfo[$product->id] ?? ['stock' => 0, 'available' => 0, 'pending' => 0];
        @endphp
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100" style="cursor: pointer;" wire:click="viewProduct({{ $product->id }})">
                @if($product->image)
                <img src="{{ asset('storage/' . $product->image) }}" class="card-img-top" alt="{{ $product->name }}" style="height: 150px; object-fit: cover;">
                @else
                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                    <i class="bi bi-box text-muted fs-1"></i>
                </div>
                @endif
                <div class="card-body">
                    <h6 class="card-title fw-bold mb-1">{{ $product->name }}</h6>
                    <p class="text-muted small mb-2">
                        <span class="badge bg-secondary">{{ $product->code }}</span>
                        @if($product->category)
                        <span class="badge bg-info">{{ $product->category->name }}</span>
                        @endif
                    </p>
                    <div class="text-center">
                        <small class="text-muted d-block">Distributor Price:</small>
                        <span class="fw-bold text-success fs-5">Rs. {{ number_format($product->price->distribute_price ?? $product->price->wholesale_price ?? 0, 2) }}</span>
                    </div>
                </div>
                <div class="card-footer bg-white border-top">
                    <div class="d-flex justify-content-between">
                        <div>
                            <small class="text-muted">Stock:</small>
                            <span class="badge {{ $info['stock'] > 0 ? 'bg-success' : 'bg-danger' }}">{{ $info['stock'] }}</span>
                        </div>
                        <div>
                            <small class="text-muted">Available:</small>
                            <span class="badge {{ $info['available'] > 0 ? 'bg-primary' : 'bg-secondary' }}">{{ $info['available'] }}</span>
                        </div>
                        @if($info['pending'] > 0)
                        <div>
                            <small class="text-muted">Pending:</small>
                            <span class="badge bg-warning">{{ $info['pending'] }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                    <p class="text-muted mb-0">No products found matching your criteria.</p>
                </div>
            </div>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $products->links() }}
    </div>

    <!-- Product Details Modal -->
    @if($showProductModal && $selectedProduct)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-box me-2"></i> Product Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeProductModal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Product Image and Status -->
                        <div class="col-md-4 text-center mb-4">
                            @if($selectedProduct->image)
                            <img src="{{ asset('storage/' . $selectedProduct->image) }}" 
                                 alt="{{ $selectedProduct->name }}" 
                                 class="img-fluid rounded mb-3" 
                                 style="max-height: 200px; object-fit: cover;">
                            @else
                            <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3" style="height: 200px;">
                                <i class="bi bi-box text-muted" style="font-size: 3rem;"></i>
                            </div>
                            @endif
                            
                            <span class="badge bg-success fs-6 mb-3">
                                <i class="bi bi-check-circle me-1"></i> Active
                            </span>
                            
                            <h4 class="fw-bold">{{ $selectedProduct->name }}</h4>
                            <p class="text-muted">
                                <i class="bi bi-upc-scan me-1"></i> {{ $selectedProduct->code }}
                            </p>
                            
                            <!-- Stock Information -->
                            @php
                                $totalStock = $selectedProduct->stock ? $selectedProduct->stock->available_stock : 0;
                                $stockInfo = $this->stockService->getAvailableStock($selectedProduct->id);
                            @endphp
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-center align-items-center mb-2">
                                    <i class="bi bi-box-seam text-primary fs-4 me-2"></i>
                                    <span class="fs-2 fw-bold">{{ $stockInfo['available'] }}</span>
                                </div>
                                <p class="text-muted small mb-0">Available Stock</p>
                                @if($stockInfo['available'] > 0)
                                    <span class="badge bg-success">Ready to sell</span>
                                @else
                                    <span class="badge bg-danger">Out of Stock</span>
                                @endif
                            </div>
                            
                            <!-- Unit Price -->
                            <div class="text-center">
                                <div class="d-flex justify-content-center align-items-center mb-2">
                                    <i class="bi bi-currency-dollar text-success fs-4 me-2"></i>
                                    <span class="fs-2 fw-bold text-success">
                                        Rs. {{ number_format($selectedProduct->price->distribute_price ?? $selectedProduct->price->wholesale_price ?? 0, 2) }}
                                    </span>
                                </div>
                                <p class="text-muted small mb-0">Distributor Price</p>
                            </div>
                        </div>
                        
                        <!-- Product Information -->
                        <div class="col-md-8">
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-info-circle me-2"></i> Product Information
                                </h6>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <p class="text-muted mb-1">Product Code</p>
                                        <p class="fw-medium mb-3">{{ $selectedProduct->code }}</p>
                                    </div>
                                    <div class="col-6">
                                        <p class="text-muted mb-1">Brand</p>
                                        <p class="fw-medium mb-3">{{ $selectedProduct->brand ?? 'N/A' }}</p>
                                    </div>
                                    <div class="col-6">
                                        <p class="text-muted mb-1">Model</p>
                                        <p class="fw-medium mb-3">{{ $selectedProduct->model ?? 'N/A' }}</p>
                                    </div>
                                    <div class="col-6">
                                        <p class="text-muted mb-1">Category</p>
                                        <p class="fw-medium mb-3">{{ $selectedProduct->category->name ?? 'N/A' }}</p>
                                    </div>
                                </div>
                                
                                @if($selectedProduct->description)
                                <div class="mb-3">
                                    <h6 class="text-primary">
                                        <i class="bi bi-journal-text me-2"></i> Description
                                    </h6>
                                    <p>{{ $selectedProduct->description }}</p>
                                </div>
                                @endif
                            </div>
                            
                            <!-- Variants Table (if variants exist) -->
                            @if($selectedProduct->variants && $selectedProduct->variants->count() > 0)
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-list-task me-2"></i> Available Variants
                                </h6>
                                
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Variant</th>
                                                <th>Distributor Price</th>
                                                <th>Available Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($selectedProduct->variants as $variant)
                                            @php
                                                $variantStockInfo = $this->stockService->getAvailableStock($selectedProduct->id, $variant->variant);
                                            @endphp
                                            <tr>
                                                <td>{{ $variant->variant }}</td>
                                                <td>Rs. {{ number_format($variant->price->distribute_price ?? $variant->price->wholesale_price ?? 0, 2) }}</td>
                                                <td>
                                                    <span class="badge {{ $variantStockInfo['available'] > 0 ? 'bg-success' : 'bg-danger' }}">
                                                        {{ $variantStockInfo['available'] }}
                                                    </span>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @endif
                            
                            <!-- Stock Information -->
                            <div class="alert alert-info">
                                <h6 class="alert-heading">
                                    <i class="bi bi-exclamation-circle me-2"></i> Stock Information
                                </h6>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="fw-bold fs-5">{{ $stockInfo['stock'] }}</div>
                                        <small class="text-muted">Total Stock</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold fs-5 text-success">{{ $stockInfo['available'] }}</div>
                                        <small class="text-muted">Available</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold fs-5 text-warning">{{ $stockInfo['pending'] }}</div>
                                        <small class="text-muted">Pending Orders</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeProductModal">
                        <i class="bi bi-x-lg me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
