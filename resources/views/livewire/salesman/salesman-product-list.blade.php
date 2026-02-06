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
    <div class="row g-3">
        @forelse($products as $product)
        @php
            $info = $stockInfo[$product->id] ?? ['stock' => 0, 'available' => 0, 'pending' => 0];
            $imageUrl = $product->image ? asset('storage/' . $product->image) : null;
        @endphp
        
        {{-- Product Card - 5 per row --}}
        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
            <div class="card border-0 shadow-sm h-100 product-card" style="cursor: pointer; transition: all 0.3s ease;" wire:click="viewProduct({{ $product->id }})">
                {{-- Product Image --}}
                <div style="height: 180px; overflow: hidden; background-color: #f8f9fa;" class="position-relative">
                    @if($imageUrl)
                        <img src="{{ $imageUrl }}" class="w-100 h-100" alt="{{ $product->name }}" style="object-fit: cover; object-position: center;" onerror="this.parentElement.querySelector('.box-icon').style.display='flex';">
                    @endif
                    <div class="box-icon w-100 h-100 d-flex align-items-center justify-content-center" style="{{ $imageUrl ? 'display: none;' : '' }}">
                        <i class="bi bi-box text-muted" style="font-size: 3rem;"></i>
                    </div>
                    @if($product->variant_id)
                        <span class="badge bg-primary position-absolute" style="top: 10px; right: 10px;">Variant</span>
                    @endif
                </div>
                
                {{-- Product Info --}}
                <div class="card-body pb-2">
                    <h6 class="card-title fw-bold mb-2" style="font-size: 13px; line-height: 1.4;">{{ $product->name }}</h6>
                    @if($product->variant_id && $product->variant)
                        <p class="mb-2" style="font-size: 12px;">
                            <span class="badge bg-purple">{{ $product->variant->variant_name ?? 'Variant' }}</span>
                        </p>
                    @endif
                    <div class="mb-2">
                        <span class="badge bg-secondary" style="font-size: 10px;">{{ $product->code }}</span>
                        @if($product->category)
                            <span class="badge bg-info" style="font-size: 10px;">{{ $product->category->name }}</span>
                        @endif
                    </div>
                    <div class="text-center py-2 border-top border-bottom">
                        <small class="text-muted d-block" style="font-size: 11px;">Distributor Price</small>
                        <span class="fw-bold text-success" style="font-size: 16px;">Rs. {{ number_format($product->price->distributor_price ?? $product->price->wholesale_price ?? 0, 2) }}</span>
                    </div>
                </div>
                
                {{-- Stock Info Footer --}}
                <div class="card-footer bg-white border-top p-2">
                    <div class="row g-2 text-center" style="font-size: 11px;">
                        <div class="col-4">
                            <small class="text-muted d-block">Stock</small>
                            <span class="badge {{ $info['stock'] > 0 ? 'bg-success' : 'bg-danger' }} w-100">{{ $info['stock'] }}</span>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Available</small>
                            <span class="badge {{ $info['available'] > 0 ? 'bg-primary' : 'bg-secondary' }} w-100">{{ $info['available'] }}</span>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Pending</small>
                            <span class="badge {{ $info['pending'] > 0 ? 'bg-warning' : 'bg-light' }} w-100">{{ $info['pending'] }}</span>
                        </div>
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

    <style>
        /* 5 columns per row for large screens */
        @media (min-width: 1200px) {
            .col-xl-2-4 {
                flex: 0 0 auto;
                width: 20%;
            }
        }

        .product-card {
            transition: all 0.3s ease;
        }

        .product-card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15) !important;
            transform: translateY(-4px);
        }

        .badge.bg-purple {
            background-color: #667eea !important;
        }
    </style>

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
                            
                            <h4 class="fw-bold">{{ $selectedProduct->name }}</h4>
                            <p class="text-muted">
                                <i class="bi bi-upc-scan me-1"></i> {{ $selectedProduct->code }}
                            </p>

                            <!-- Stock Summary -->
                            @php
                                $productStockInfo = $this->stockService->getAvailableStock($selectedProduct->id);
                                $totalStock = $selectedProduct->stock ? $selectedProduct->stock->available_stock : 0;
                                $damagedStock = $selectedProduct->stock ? $selectedProduct->stock->damage_stock : 0;
                            @endphp
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body text-center p-2">
                                            <i class="bi bi-box text-primary fs-4"></i>
                                            <div class="fw-bold fs-5">{{ $totalStock }}</div>
                                            <small class="text-muted">Available Stock</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body text-center p-2">
                                            <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                                            <div class="fw-bold fs-5">{{ $damagedStock }}</div>
                                            <small class="text-muted">Damaged Stock</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Status Badge -->
                            @if($productStockInfo['available'] > 0)
                                <div class="alert alert-success d-flex align-items-center mb-3">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <span><strong>Available</strong> - Ready to sell</span>
                                </div>
                            @else
                                <div class="alert alert-danger d-flex align-items-center mb-3">
                                    <i class="bi bi-x-circle me-2"></i>
                                    <span><strong>Out of Stock</strong></span>
                                </div>
                            @endif
                            
                            <!-- Unit Price -->
                            <div class="card border-success mb-3">
                                <div class="card-body text-center">
                                    <div class="text-muted small">Distributor Price</div>
                                    <div class="fw-bold text-success" style="font-size: 24px;">
                                        Rs. {{ number_format($selectedProduct->price->distributor_price ?? $selectedProduct->price->wholesale_price ?? 0, 2) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product Information -->
                        <div class="col-md-8">
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-info-circle me-2"></i> Product Information
                                </h6>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <p class="text-muted mb-1 small">Product Code</p>
                                        <p class="fw-medium mb-0">{{ $selectedProduct->code }}</p>
                                    </div>
                                    <div class="col-6">
                                        <p class="text-muted mb-1 small">Brand</p>
                                        <p class="fw-medium mb-0">
                                            @if($selectedProduct->brand)
                                                {{ $selectedProduct->brand->brand_name ?? 'N/A' }}
                                            @else
                                                N/A
                                            @endif
                                        </p>
                                    </div>
                                    <div class="col-6">
                                        <p class="text-muted mb-1 small">Model</p>
                                        <p class="fw-medium mb-0">{{ $selectedProduct->model ?? 'N/A' }}</p>
                                    </div>
                                    <div class="col-6">
                                        <p class="text-muted mb-1 small">Category</p>
                                        <p class="fw-medium mb-0">{{ $selectedProduct->category->name ?? 'N/A' }}</p>
                                    </div>
                                </div>
                                
                                @if($selectedProduct->description)
                                <div class="mt-3 pt-3 border-top">
                                    <h6 class="text-primary mb-2">
                                        <i class="bi bi-journal-text me-2"></i> Description
                                    </h6>
                                    <p class="text-muted" style="font-size: 13px;">{{ $selectedProduct->description }}</p>
                                </div>
                                @endif
                            </div>
                            
                            <!-- Variants Table (if variants exist) -->
                            @php
                                // Get all products with the same code (these are the variants)
                                $variants = \App\Models\ProductDetail::where('code', $selectedProduct->code)
                                    ->with(['price', 'stock', 'variant'])
                                    ->orderBy('variant_id')
                                    ->get();
                            @endphp
                            @if($variants && $variants->count() > 1)
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-list-task me-2"></i> Variant Prices & Stock
                                </h6>
                                
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0" style="font-size: 12px;">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="width: 12%;">VALUE</th>
                                                <th class="text-center" style="width: 12%;">COST</th>
                                                <th class="text-center" style="width: 14%;">WHOLESALE</th>
                                                <th class="text-center" style="width: 14%;">DISTRIBUTOR</th>
                                                <th class="text-center" style="width: 12%;">RETAIL</th>
                                                <th class="text-center" style="width: 10%;">AVAILABLE</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($variants as $variant)
                                            @php
                                                $variantStock = \App\Models\ProductStock::where('product_id', $variant->id)->first();
                                                $variantAvailable = $variantStock ? $variantStock->available_stock : 0;
                                            @endphp
                                            <tr>
                                                <td class="text-center">
                                                    @if($variant->variant_id)
                                                        {{ $variant->variant?->variant_name ?? 'Variant' }}
                                                    @else
                                                        Size
                                                    @endif
                                                </td>
                                                <td class="text-center">Rs.{{ number_format($variant->price?->supplier_price ?? 0, 2) }}</td>
                                                <td class="text-center">Rs.{{ number_format($variant->price?->wholesale_price ?? 0, 2) }}</td>
                                                <td class="text-center"><strong>Rs.{{ number_format($variant->price?->distributor_price ?? 0, 2) }}</strong></td>
                                                <td class="text-center">Rs.{{ number_format($variant->price?->retail_price ?? 0, 2) }}</td>
                                                <td class="text-center">
                                                    <span class="badge {{ $variantAvailable > 0 ? 'bg-success' : 'bg-secondary' }}">{{ $variantAvailable }}</span>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @endif
                            
                            <!-- Stock Information -->
                            <div class="alert alert-info mb-0">
                                <h6 class="alert-heading mb-3">
                                    <i class="bi bi-exclamation-circle me-2"></i> Stock Information
                                </h6>
                                @php
                                    $modalStockInfo = $this->stockService->getAvailableStock($selectedProduct->id);
                                @endphp
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="fw-bold fs-5 text-primary">{{ $modalStockInfo['stock'] }}</div>
                                        <small class="text-muted">Total Stock</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold fs-5 text-success">{{ $modalStockInfo['available'] }}</div>
                                        <small class="text-muted">Available</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold fs-5 text-warning">{{ $modalStockInfo['pending'] }}</div>
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
