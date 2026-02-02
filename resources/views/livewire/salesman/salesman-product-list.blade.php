<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-box-seam text-primary me-2"></i> Product Catalog
            </h3>
            <p class="text-muted mb-0">View available products and stock levels (read-only)</p>
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
            <div class="card border-0 shadow-sm h-100">
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
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted d-block">Retail:</small>
                            <span class="fw-bold text-primary">Rs. {{ number_format($product->price->retail_price ?? 0, 2) }}</span>
                        </div>
                        <div>
                            <small class="text-muted d-block">Wholesale:</small>
                            <span class="fw-bold text-success">Rs. {{ number_format($product->price->wholesale_price ?? 0, 2) }}</span>
                        </div>
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
</div>
