<div>
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-layers-fill text-success me-2"></i> Product Variant Management
            </h3>
            <p class="text-muted mb-0">Manage and organize your product variants efficiently</p>
        </div>
        <div>
            <button class="btn btn-primary" wire:click="createVariant">
                <i class="bi bi-plus-lg me-2"></i> Add Variant
            </button>
        </div>
    </div>

    <!-- Variant List Table -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            <i class="bi bi-list-ul text-primary me-2"></i> Variant List
                        </h5>
                        <p class="text-muted small mb-0">View and manage all product variants</p>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">No</th>
                                    <th>Variant Name</th>
                                    <th>Values</th>
                                    <th>Status</th>
                                    <th>Used In</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($variants->count() > 0)
                                    @foreach ($variants as $variant)
                                        <tr>
                                            <td class="ps-4">
                                                <span class="fw-medium text-dark">{{ $loop->iteration }}</span>
                                            </td>
                                            <td>
                                                <span class="fw-medium text-dark">{{ $variant->variant_name }}</span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    @foreach($variant->variant_values as $value)
                                                    <span class="badge bg-info">{{ $value }}</span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td>
                                                @if($variant->status === 'active')
                                                <span class="badge bg-success">Active</span>
                                                @else
                                                <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $variant->products_count ?? $variant->products()->count() }} products</span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="text-primary me-2 bg-opacity-0 border-0" wire:click="editVariant({{ $variant->id }})">
                                                    <i class="bi bi-pencil fs-6"></i>
                                                </button>
                                                <button class="text-danger me-2 bg-opacity-0 border-0" wire:click="confirmDelete({{ $variant->id }})">
                                                    <i class="bi bi-trash fs-6"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="alert alert-primary bg-opacity-10">
                                            <i class="bi bi-info-circle me-2"></i> No product variants found.
                                        </div>
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Variant Modal -->
    <div wire:ignore.self class="modal fade" id="createVariantModal" tabindex="-1" aria-labelledby="createVariantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-plus-circle me-2"></i> Add Variant
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="saveVariant">
                        <!-- Variant Name -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Variant Name</label>
                            <input type="text" class="form-control" wire:model="variant_name" placeholder="e.g., Size, Color, Material" required>
                            @error('variant_name')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Variant Values -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Variant Values</label>
                            <div class="row mb-3">
                                <div class="col-md-9">
                                    <input type="text" class="form-control" 
                                        wire:model="variant_value_input"
                                        wire:keydown.enter.prevent="addVariantValue"
                                        placeholder="e.g., 5, 6, 7 or Small, Medium">
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-success w-100" wire:click="addVariantValue">
                                        <i class="bi bi-plus-circle me-1"></i> Add Value
                                    </button>
                                </div>
                            </div>

                            @if(count($variant_values) > 0)
                            <div class="alert alert-light">
                                <strong>Added Values ({{ count($variant_values) }}):</strong>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($variant_values as $value)
                                    <span class="badge bg-primary fs-6 px-3 py-2">
                                        {{ $value }}
                                        <button type="button" class="btn-close btn-close-white ms-2" 
                                            style="font-size: 0.7rem;"
                                            wire:click="removeVariantValue('{{ $value }}')"
                                            aria-label="Remove"></button>
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            @else
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i> No values added yet.
                            </div>
                            @endif

                            @error('variant_values')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" wire:model="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            @error('status')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Create Variant
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Variant Modal -->
    <div wire:ignore.self class="modal fade" id="editVariantModal" tabindex="-1" aria-labelledby="editVariantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-pencil-square me-2"></i> Edit Variant
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="updateVariant">
                        <!-- Variant Name -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Variant Name</label>
                            <input type="text" class="form-control" wire:model.defer="editVariantName" placeholder="e.g., Size, Color, Material" required>
                            @error('editVariantName')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Variant Values -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Variant Values</label>
                            <div class="row mb-3">
                                <div class="col-md-9">
                                    <input type="text" class="form-control" 
                                        wire:model="editVariantValueInput"
                                        wire:keydown.enter.prevent="addEditVariantValue"
                                        placeholder="e.g., 5, 6, 7 or Small, Medium">
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-success w-100" wire:click="addEditVariantValue">
                                        <i class="bi bi-plus-circle me-1"></i> Add Value
                                    </button>
                                </div>
                            </div>

                            @if(count($editVariantValues) > 0)
                            <div class="alert alert-light">
                                <strong>Added Values ({{ count($editVariantValues) }}):</strong>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($editVariantValues as $value)
                                    <span class="badge bg-primary fs-6 px-3 py-2">
                                        {{ $value }}
                                        <button type="button" class="btn-close btn-close-white ms-2" 
                                            style="font-size: 0.7rem;"
                                            wire:click="removeEditVariantValue('{{ $value }}')"
                                            aria-label="Remove"></button>
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            @else
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i> No values added yet.
                            </div>
                            @endif

                            @error('editVariantValues')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" wire:model.defer="editStatus" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            @error('editStatus')
                            <span class="text-danger small">* {{ $message }}</span>
                            @enderror
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Update Variant
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('livewire:navigated', function() {
            // Show create modal
            Livewire.on('create-variant', function() {
                const modal = new bootstrap.Modal(document.getElementById('createVariantModal'));
                modal.show();
            });

            // Show edit modal
            Livewire.on('edit-variant', function() {
                const modal = new bootstrap.Modal(document.getElementById('editVariantModal'));
                modal.show();
            });

            // Confirm delete
            Livewire.on('confirm-delete', function() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'You will not be able to recover this variant!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Livewire.dispatch('confirmDelete');
                    }
                });
            });
        });
    </script>
    @endpush
</div>
