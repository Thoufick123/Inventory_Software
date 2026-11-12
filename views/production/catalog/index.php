<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-journal-bookmark me-2 text-primary"></i>Production Catalog</h2>
         </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (hasPermission('catalog', 'create')): ?>
        <a href="<?= url('production/catalog/create') ?>" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-circle-fill me-1"></i> Add Product Style
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body p-3">
        <form method="GET" action="<?= url('production/catalog') ?>" id="catalogFilterForm" class="row g-2 align-items-center">
            <!-- Search input with live search and clear button -->
            <div class="col-lg-4 col-xl-5 col-md-6">
                <label class="form-label small text-muted fw-semibold mb-1">Search Product Styles</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-primary"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           name="search" 
                           id="catalogSearchInput" 
                           class="form-control fw-semibold" 
                           placeholder="Search by style code, product name, or category..." 
                           value="<?= e($search ?? '') ?>"
                           oninput="handleCatalogSearchInput(this.value)">
                    <button class="btn btn-outline-secondary" type="button" id="btnClearSearchInput" onclick="clearCatalogSearch()" title="Clear Search" style="<?= empty($search) ? 'display:none;' : '' ?>">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Category Filter Dropdown -->
            <div class="col-lg-3 col-md-3 col-sm-6">
                <label class="form-label small text-muted fw-semibold mb-1">Category</label>
                <select name="category_id" id="filterCategoryId" class="form-select" onchange="handleCategoryFilterChange()">
                    <option value="">-- All Categories --</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($categoryId ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Subcategory Filter Dropdown -->
            <div class="col-lg-2 col-md-3 col-sm-6">
                <label class="form-label small text-muted fw-semibold mb-1">Subcategory</label>
                <select name="subcategory_id" id="filterSubcategoryId" class="form-select" onchange="liveFilterCatalog()">
                    <option value="">-- All Subcategories --</option>
                    <?php if (!empty($subcategories)): ?>
                        <?php foreach ($subcategories as $sc): ?>
                            <option value="<?= $sc['id'] ?>" data-cat-id="<?= $sc['category_id'] ?>" <?= ($subcategoryId ?? '') == $sc['id'] ? 'selected' : '' ?>><?= e($sc['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Actions (Filter & Reset) -->
            <div class="col-lg-3 col-xl-2 col-md-12 d-flex align-items-end gap-1 flex-nowrap">
                <button type="submit" class="btn btn-primary flex-grow-1 text-nowrap" title="Filter Records">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnResetCatalogFilters" onclick="resetAllCatalogFilters()" title="Reset All Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Header with Result Count -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h5 class="mb-0 fw-semibold text-dark">
        <i class="bi bi-list-ul me-2 text-primary"></i>Manufactured Garment Styles
    </h5>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-semibold" id="catalogCountBadge">
        <?= count($products) ?> Product Styles
    </span>
</div>

<!-- Products Table List View -->
<div class="card border-0 shadow-sm mb-4" id="catalogListView">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="catalogTable">
            <thead class="table-light">
                <tr class="small text-uppercase text-secondary">
                    <th style="width: 45px;" class="text-center">#</th>
                    <th>Style Code</th>
                    <th>Product Style Name</th>
                    <th>Category Hierarchy</th>
                    <th>HSN &amp; GST</th>
                    <th class="text-center">Variants</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody id="catalogTableBody">
                <tr id="emptyCatalogLiveStateTable" style="display: none;">
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-search fs-2 opacity-50 mb-2 d-block"></i>
                        <h6 class="fw-bold">No Matching Product Styles Found</h6>
                        <p class="small mb-0">Try adjusting your search keywords or choosing another category filter.</p>
                    </td>
                </tr>
                <?php if (empty($products)): ?>
                    <tr id="noProductsServerStateTable">
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-plus fs-2 text-primary opacity-75 mb-2 d-block"></i>
                            <h6 class="fw-bold text-dark">No In-House Production Styles Found</h6>
                            <p class="small mb-2">Start by adding your manufactured garment styles.</p>
                            <?php if (hasPermission('catalog', 'create')): ?>
                            <a href="<?= url('production/catalog/create') ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> Create First Style
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $idx => $p): ?>
                        <tr class="catalog-product-row"
                            data-code="<?= strtolower(e($p['code'])) ?>"
                            data-name="<?= strtolower(e($p['name'])) ?>"
                            data-category-id="<?= $p['category_id'] ?? '' ?>"
                            data-category-name="<?= strtolower(e($p['category_name'] ?? '')) ?>"
                            data-subcategory-id="<?= $p['subcategory_id'] ?? '' ?>"
                            data-subcategory-name="<?= strtolower(e($p['subcategory_name'] ?? '')) ?>"
                            data-subsub-name="<?= strtolower(e($p['sub_subcategory_name'] ?? '')) ?>"
                            data-material="<?= strtolower(e($p['material'] ?? '')) ?>"
                            data-brand="<?= strtolower(e($p['brand_name'] ?? '')) ?>">
                            <td class="text-center text-muted small fw-semibold"><?= $idx + 1 ?></td>
                            <td>
                                <span class="badge bg-dark font-monospace px-2 py-1"><?= e($p['code']) ?></span>
                                <?php if (!empty($p['qr_code_prefix'])): ?>
                                    <span class="badge bg-primary-subtle text-primary border font-monospace ms-1" title="QR Code Prefix"><?= e($p['qr_code_prefix']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= url('production/catalog/view/' . $p['id']) ?>" class="fw-bold text-dark text-decoration-none hover-primary">
                                    <?= e($p['name']) ?>
                                </a>
                                <?php if (!empty($p['material'])): ?>
                                    <div class="small text-muted"><i class="bi bi-layers me-1"></i><?= e($p['material']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small">
                                    <span class="badge bg-light text-dark border"><i class="bi bi-folder2 text-primary me-1"></i><?= e($p['category_name'] ?? 'General') ?></span>
                                    <?php if (!empty($p['subcategory_name'])): ?>
                                        <span class="text-muted"> › <?= e($p['subcategory_name']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($p['sub_subcategory_name'])): ?>
                                        <span class="text-muted"> › <?= e($p['sub_subcategory_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($p['hsn_code'])): ?>
                                    <div>
                                        <span class="badge bg-info-subtle text-info border font-monospace fw-bold">HSN <?= e($p['hsn_code']) ?></span>
                                        <span class="badge bg-secondary-subtle text-dark border ms-1"><?= number_format((float)($p['gst_percentage'] ?? 5.00), 2) ?>% GST</span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border fw-bold"><?= $p['variant_count'] ?> active</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success border">In-House</span>
                            </td>
                            <td class="text-center">
                                <div class="action-btns-group justify-content-center">
                                    <a href="<?= url('production/catalog/view/' . $p['id']) ?>" class="btn btn-action-icon btn-outline-info" title="View Full Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if (hasPermission('catalog', 'edit')): ?>
                                    <a href="<?= url('production/catalog/edit/' . $p['id']) ?>" class="btn btn-action-icon btn-outline-primary" title="Edit Style">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('catalog', 'delete')): ?>
                                    <form method="POST" action="<?= url('production/catalog/delete/' . $p['id']) ?>" class="d-inline mb-0" onsubmit="return confirm('Are you sure you want to delete product style &quot;<?= e(addslashes($p['name'])) ?>&quot;?');">
                                        <?= CSRF::field() ?>
                                        <button type="submit" class="btn btn-action-icon btn-outline-danger" title="Delete Style">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Catalog Pagination Controls Card -->
<div class="card bg-white mt-4 shadow-sm border" id="catalogPaginationFooter">
    <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="small text-muted fw-medium" id="catalogPageInfo">
                Showing 1 to 15 of <?= count($products) ?> product styles
            </span>
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted mb-0 fw-medium">Show:</label>
                <select id="catalogPageSizeSelect" class="form-select form-select-sm" style="width: auto;">
                    <option value="15" selected>15 per page</option>
                    <option value="30">30 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                    <option value="all">All</option>
                </select>
            </div>
        </div>
        <nav aria-label="Catalog Pagination">
            <ul class="pagination pagination-modern mb-0" id="catalogPaginationList">
            </ul>
        </nav>
    </div>
</div>

<style>
.hover-primary:hover {
    color: var(--bs-primary) !important;
}
</style>

<script>
let catalogPagination = null;

function handleCatalogSearchInput(val) {
    const clearBtn = document.getElementById('btnClearSearchInput');
    if (clearBtn) {
        clearBtn.style.display = val.trim() ? '' : 'none';
    }
    liveFilterCatalog();
}

function clearCatalogSearch() {
    const searchInput = document.getElementById('catalogSearchInput');
    if (searchInput) {
        searchInput.value = '';
    }
    const clearBtn = document.getElementById('btnClearSearchInput');
    if (clearBtn) {
        clearBtn.style.display = 'none';
    }
    liveFilterCatalog();
}

function resetAllCatalogFilters() {
    const searchInput = document.getElementById('catalogSearchInput');
    if (searchInput) searchInput.value = '';

    const catSelect = document.getElementById('filterCategoryId');
    if (catSelect) catSelect.value = '';

    const subcatSelect = document.getElementById('filterSubcategoryId');
    if (subcatSelect) subcatSelect.value = '';

    const clearBtn = document.getElementById('btnClearSearchInput');
    if (clearBtn) clearBtn.style.display = 'none';

    handleCategoryFilterChange();
    liveFilterCatalog();
}

function handleCategoryFilterChange() {
    const catSelect = document.getElementById('filterCategoryId');
    const subcatSelect = document.getElementById('filterSubcategoryId');
    const selectedCatId = catSelect ? catSelect.value : '';

    if (subcatSelect) {
        const options = subcatSelect.querySelectorAll('option');
        options.forEach(opt => {
            const catId = opt.getAttribute('data-cat-id');
            if (!opt.value || !selectedCatId || catId === selectedCatId) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
                if (opt.selected) subcatSelect.value = '';
            }
        });
    }

    liveFilterCatalog();
}

function liveFilterCatalog() {
    const searchInput = document.getElementById('catalogSearchInput');
    const query = searchInput ? searchInput.value.trim().toLowerCase() : '';

    const catSelect = document.getElementById('filterCategoryId');
    const selectedCatId = catSelect ? catSelect.value.trim() : '';

    const subcatSelect = document.getElementById('filterSubcategoryId');
    const selectedSubcatId = subcatSelect ? subcatSelect.value.trim() : '';

    const rows = document.querySelectorAll('.catalog-product-row');
    const totalCount = rows.length;

    const matchFn = (el) => {
        const code = el.getAttribute('data-code') || '';
        const name = el.getAttribute('data-name') || '';
        const catId = el.getAttribute('data-category-id') || '';
        const catName = el.getAttribute('data-category-name') || '';
        const subcatId = el.getAttribute('data-subcategory-id') || '';
        const subcatName = el.getAttribute('data-subcategory-name') || '';
        const subsubName = el.getAttribute('data-subsub-name') || '';
        const material = el.getAttribute('data-material') || '';
        const brand = el.getAttribute('data-brand') || '';

        const matchesQuery = !query || 
            code.includes(query) || 
            name.includes(query) || 
            catName.includes(query) || 
            subcatName.includes(query) || 
            subsubName.includes(query) || 
            material.includes(query) || 
            brand.includes(query);

        const matchesCategory = !selectedCatId || catId === selectedCatId;
        const matchesSubcategory = !selectedSubcatId || subcatId === selectedSubcatId;

        return matchesQuery && matchesCategory && matchesSubcategory;
    };

    let visibleCount = 0;
    if (catalogPagination) {
        visibleCount = catalogPagination.filter(matchFn);
    } else {
        rows.forEach(el => {
            if (matchFn(el)) {
                el.style.display = '';
                visibleCount++;
            } else {
                el.style.display = 'none';
            }
        });
        const emptyState = document.getElementById('emptyCatalogLiveStateTable');
        if (emptyState) {
            emptyState.style.display = (visibleCount === 0 && rows.length > 0) ? 'table-row' : 'none';
        }
    }

    const badge = document.getElementById('catalogCountBadge');
    if (badge) {
        if (query || selectedCatId || selectedSubcatId) {
            badge.textContent = `${visibleCount} of ${totalCount} Product Styles`;
            badge.className = 'badge bg-warning text-dark border border-warning rounded-pill px-3 py-2 fw-semibold';
        } else {
            badge.textContent = `${totalCount} Product Styles`;
            badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-semibold';
        }
    }
}

function initCatalogPagination() {
    if (typeof window.initClientPagination === 'function') {
        catalogPagination = window.initClientPagination({
            container: '#catalogTableBody',
            itemSelector: '.catalog-product-row',
            footer: '#catalogPaginationFooter',
            infoEl: '#catalogPageInfo',
            paginationList: '#catalogPaginationList',
            pageSizeSelect: '#catalogPageSizeSelect',
            emptyStateEl: '#emptyCatalogLiveStateTable',
            pageSize: 15,
            itemName: 'product styles'
        });
    }
    handleCategoryFilterChange();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCatalogPagination);
} else {
    initCatalogPagination();
}
</script>
