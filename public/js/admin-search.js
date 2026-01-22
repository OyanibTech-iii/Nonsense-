/**
 * Admin Search Utility
 * Provides reusable search functionality for admin tables
 */

class AdminSearch {
    constructor(options = {}) {
        this.searchInputId = options.searchInputId;
        this.tableRowClass = options.tableRowClass;
        this.emptyStateSelector = options.emptyStateSelector || 'tbody tr[colspan]';
        this.searchDelay = options.searchDelay || 300;
        this.minSearchLength = options.minSearchLength || 0;
        
        this.init();
    }
    
    init() {
        if (!this.searchInputId || !this.tableRowClass) {
            console.error('AdminSearch: searchInputId and tableRowClass are required');
            return;
        }
        
        const searchInput = document.getElementById(this.searchInputId);
        if (!searchInput) {
            console.error(`AdminSearch: Search input with id "${this.searchInputId}" not found`);
            return;
        }
        
        // Add debounced search
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.performSearch(e.target.value);
            }, this.searchDelay);
        });
        
        // Add clear search functionality
        this.addClearButton(searchInput);
    }
    
    performSearch(searchTerm) {
        const rows = document.querySelectorAll(`.${this.tableRowClass}`);
        const normalizedSearchTerm = searchTerm.toLowerCase().trim();
        
        let visibleCount = 0;
        
        rows.forEach(row => {
            if (normalizedSearchTerm.length < this.minSearchLength) {
                // Show all rows if search term is too short
                row.style.display = '';
                visibleCount++;
            } else {
                const text = row.textContent.toLowerCase();
                if (text.includes(normalizedSearchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            }
        });
        
        // Handle empty state
        this.handleEmptyState(visibleCount);
        
        // Dispatch custom event for other components to listen to
        document.dispatchEvent(new CustomEvent('adminSearchPerformed', {
            detail: {
                searchTerm: normalizedSearchTerm,
                visibleCount: visibleCount,
                totalCount: rows.length
            }
        }));
    }
    
    handleEmptyState(visibleCount) {
        const emptyStateRow = document.querySelector(this.emptyStateSelector);
        if (emptyStateRow) {
            emptyStateRow.style.display = visibleCount === 0 ? '' : 'none';
        }
    }
    
    addClearButton(searchInput) {
        // Create clear button
        const clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.innerHTML = '<ion-icon name="close-circle-outline" class="w-4 h-4"></ion-icon>';
        clearButton.className = 'absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors duration-200 z-10';
        clearButton.style.display = 'none';
        
        // Add to parent container
        const parentContainer = searchInput.parentElement;
        if (parentContainer.style.position !== 'relative') {
            parentContainer.style.position = 'relative';
        }
        parentContainer.appendChild(clearButton);
        
        // Adjust search input padding to make room for clear button
        const currentPaddingRight = searchInput.style.paddingRight || '16px';
        searchInput.style.paddingRight = '40px'; // Make room for the clear button
        
        // Show/hide clear button based on input value
        searchInput.addEventListener('input', (e) => {
            clearButton.style.display = e.target.value ? 'block' : 'none';
        });
        
        // Clear search when button is clicked
        clearButton.addEventListener('click', () => {
            searchInput.value = '';
            clearButton.style.display = 'none';
            this.performSearch('');
            searchInput.focus();
        });
    }
    
    // Static method to initialize multiple search instances
    static initMultiple(configs) {
        return configs.map(config => new AdminSearch(config));
    }
}

// Auto-initialize common admin searches if elements exist
document.addEventListener('DOMContentLoaded', function() {
    const searchConfigs = [
        {
            searchInputId: 'user-search',
            tableRowClass: 'user-row',
            emptyStateSelector: 'tbody tr[colspan]'
        },
        {
            searchInputId: 'product-search',
            tableRowClass: 'product-row',
            emptyStateSelector: 'tbody tr[colspan]'
        },
        {
            searchInputId: 'stock-search',
            tableRowClass: 'stock-row',
            emptyStateSelector: 'tbody tr[colspan]'
        }
    ];
    
    // Only initialize searches that have the required elements
    searchConfigs.forEach(config => {
        const searchInput = document.getElementById(config.searchInputId);
        const tableRows = document.querySelectorAll(`.${config.tableRowClass}`);
        
        if (searchInput && tableRows.length > 0) {
            new AdminSearch(config);
        }
    });
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdminSearch;
}
