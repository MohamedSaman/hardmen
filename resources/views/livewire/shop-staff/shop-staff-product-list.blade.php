<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-box-seam text-primary me-2"></i> Product Catalog
            </h3>
            <p class="text-muted mb-0">View product information and stock levels (read-only)</p>
        </div>
        <a href="{{ route('shop-staff.dashboard') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search by name, code, or model...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="categoryFilter" class="form-select">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="stockFilter" class="form-select">
                        <option value="all">All Stock Levels</option>
                        <option value="in_stock">In Stock (>10)</option>
                        <option value="low_stock">Low Stock (1-10)</option>
                        <option value="out_of_stock">Out of Stock</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Products Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Product</th>
                            <th>Code</th>
                            <th>Category</th>
                            <th class="text-end">Retail Price</th>
                            <th class="text-end">Wholesale Price</th>
                            <th class="text-center">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    @if($product->image)
                                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                    @else
                                    <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="bi bi-box text-muted"></i>
                                    </div>
                                    @endif
                                    <div>
                                        <span class="fw-medium">{{ $product->name }}</span>
                                        @if($product->model)
                                        <small class="d-block text-muted">{{ $product->model }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-secondary">{{ $product->code }}</span></td>
                            <td>{{ $product->category->name ?? 'N/A' }}</td>
                            <td class="text-end">Rs. {{ number_format($product->price->retail_price ?? 0, 2) }}</td>
                            <td class="text-end">Rs. {{ number_format($product->price->wholesale_price ?? 0, 2) }}</td>
                            <td class="text-center">
                                @php
                                    $stock = $product->stock->available_stock ?? 0;
                                @endphp
                                @if($stock > 10)
                                    <span class="badge bg-success">{{ $stock }}</span>
                                @elseif($stock > 0)
                                    <span class="badge bg-warning">{{ $stock }}</span>
                                @else
                                    <span class="badge bg-danger">Out of Stock</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No products found matching your criteria.
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
        {{ $products->links() }}
    </div>
</div>
