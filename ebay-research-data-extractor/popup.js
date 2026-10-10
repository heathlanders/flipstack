/**
 * Popup script for eBay Research Extension - Full Version
 */

let currentExtraction = null;
let extractionHistory = [];

// Initialize popup when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeTabs();
    loadExtractionData();
    loadExtractionHistory();
    loadFlipStackSettings();
});

/**
 * Initialize tab navigation
 */
function initializeTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabName = button.dataset.tab;
            
            // Update button states
            tabButtons.forEach(b => b.classList.remove('active'));
            button.classList.add('active');
            
            // Update content visibility
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === `${tabName}-tab`) {
                    content.classList.add('active');
                }
            });
            
            // Load data for specific tabs
            if (tabName === 'history') {
                loadExtractionHistory();
            }
        });
    });
}

/**
 * Load latest extraction data from background script
 */
function loadExtractionData() {
    chrome.runtime.sendMessage({ type: 'GET_EXTRACTION_DATA' }, function(response) {
        if (response && response.success && response.data) {
            currentExtraction = response.data;
            displayExtractionResults(response.data);
        } else {
            console.log('No extraction data available');
            showWaitingState();
        }
    });
}

/**
 * Load extraction history from background script
 */
function loadExtractionHistory() {
    chrome.runtime.sendMessage({ type: 'GET_EXTRACTION_HISTORY' }, function(response) {
        if (response && response.success && response.history) {
            extractionHistory = response.history;
            displayExtractionHistory(response.history);
        } else {
            displayEmptyHistory();
        }
    });
}

/**
 * Display extraction results in the popup
 */
function displayExtractionResults(data) {
    // Hide waiting state, show success state
    document.getElementById('waiting-state').classList.add('hidden');
    document.getElementById('success-state').classList.remove('hidden');
    
    // Update success message
    const pageType = data.page_type.charAt(0).toUpperCase() + data.page_type.slice(1);
    document.getElementById('success-title').textContent = `${pageType} Extraction Complete!`;
    document.getElementById('success-subtitle').textContent = 
        `Found ${data.total_listings} listings • ${new Date(data.extraction_time).toLocaleTimeString()}`;
    
    // Update basic stats
    document.getElementById('total-listings').textContent = data.total_listings;
    
    const priceCount = data.price_analysis ? data.price_analysis.count : 0;
    document.getElementById('with-prices').textContent = priceCount;
    
    // Show price analysis if available
    if (data.price_analysis && data.price_analysis.count > 0) {
        displayPriceAnalysis(data.price_analysis);
    }
    
    // Show market insights if available
    if (data.market_insights && data.market_insights.length > 0) {
        displayMarketInsights(data.market_insights);
    }
    
    // Show sample listings
    if (data.listings && data.listings.length > 0) {
        displaySampleListings(data.listings);
    }
}

/**
 * Display price analysis section
 */
function displayPriceAnalysis(priceAnalysis) {
    document.getElementById('price-analysis-section').classList.remove('hidden');
    
    document.getElementById('avg-price').textContent = `$${priceAnalysis.average.toFixed(2)}`;
    document.getElementById('median-price').textContent = `$${priceAnalysis.median.toFixed(2)}`;
    document.getElementById('price-range').textContent = `$${priceAnalysis.min} - $${priceAnalysis.max}`;
    document.getElementById('sample-size').textContent = `${priceAnalysis.count} listings`;
}

/**
 * Display market insights section
 */
function displayMarketInsights(insights) {
    document.getElementById('insights-section').classList.remove('hidden');
    
    const container = document.getElementById('insights-container');
    container.innerHTML = '';
    
    insights.forEach(insight => {
        const insightEl = document.createElement('div');
        insightEl.className = `insight-item ${insight.type}`;
        insightEl.innerHTML = `
            <div style="font-weight: bold; margin-bottom: 2px;">${insight.title}</div>
            <div>${insight.message}</div>
        `;
        container.appendChild(insightEl);
    });
}

/**
 * Display sample listings
 */
function displaySampleListings(listings) {
    document.getElementById('listings-section').classList.remove('hidden');
    
    const container = document.getElementById('listings-container');
    container.innerHTML = '';
    
    // Show first 5 listings
    listings.slice(0, 5).forEach((listing, index) => {
        const listingEl = document.createElement('div');
        listingEl.className = 'listing-item';
        
        listingEl.innerHTML = `
            <div class="listing-title">${index + 1}. ${listing.title || 'No title'}</div>
            <div class="listing-details">
                <span class="listing-price">${listing.price || 'No price'}</span>
                <span class="listing-condition">${listing.condition || ''}</span>
            </div>
        `;
        
        container.appendChild(listingEl);
    });
    
    // Add "more listings" indicator if applicable
    if (listings.length > 5) {
        const moreEl = document.createElement('div');
        moreEl.className = 'listing-item';
        moreEl.style.fontStyle = 'italic';
        moreEl.style.textAlign = 'center';
        moreEl.style.color = '#6b7280';
        moreEl.textContent = `... and ${listings.length - 5} more listings`;
        container.appendChild(moreEl);
    }
}

/**
 * Display extraction history
 */
function displayExtractionHistory(history) {
    const container = document.getElementById('history-container');
    const emptyState = document.getElementById('empty-history');
    
    if (!history || history.length === 0) {
        displayEmptyHistory();
        return;
    }
    
    emptyState.classList.add('hidden');
    container.innerHTML = '';
    
    history.forEach((extraction, index) => {
        const historyItem = document.createElement('div');
        historyItem.className = 'history-item';
        
        const extractionDate = new Date(extraction.extraction_time);
        const pageType = extraction.page_type.charAt(0).toUpperCase() + extraction.page_type.slice(1);
        const priceCount = extraction.price_analysis ? extraction.price_analysis.count : 0;
        
        historyItem.innerHTML = `
            <div class="history-header">
                <div style="font-weight: bold; color: #111827;">
                    ${pageType} - ${extraction.total_listings} listings
                </div>
                <div style="font-size: 11px; color: #6b7280;">
                    ${extractionDate.toLocaleDateString()} ${extractionDate.toLocaleTimeString()}
                </div>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">
                ${priceCount} with prices • ${extraction.market_insights?.length || 0} insights
            </div>
            <div class="history-url">${extraction.url}</div>
        `;
        
        // Click to load this extraction
        historyItem.addEventListener('click', () => {
            currentExtraction = extraction;
            displayExtractionResults(extraction);
            
            // Switch to results tab
            document.querySelector('[data-tab="results"]').click();
        });
        
        container.appendChild(historyItem);
    });
}

/**
 * Display empty history state
 */
function displayEmptyHistory() {
    document.getElementById('history-container').innerHTML = '';
    document.getElementById('empty-history').classList.remove('hidden');
}

/**
 * Show waiting state (no extraction data)
 */
function showWaitingState() {
    document.getElementById('waiting-state').classList.remove('hidden');
    document.getElementById('success-state').classList.add('hidden');
    document.getElementById('price-analysis-section').classList.add('hidden');
    document.getElementById('insights-section').classList.add('hidden');
    document.getElementById('listings-section').classList.add('hidden');
}

/**
 * Clear all extraction results
 */
function clearResults() {
    chrome.runtime.sendMessage({ type: 'CLEAR_EXTRACTION_DATA' }, function(response) {
        if (response && response.success) {
            currentExtraction = null;
            extractionHistory = [];
            showWaitingState();
            displayEmptyHistory();
            console.log('✅ Results cleared');
        }
    });
}

/**
 * Refresh extraction data
 */
function refreshData() {
    loadExtractionData();
    loadExtractionHistory();
    console.log('🔄 Data refreshed');
}

/**
 * Export extraction data
 */
function exportData() {
    const includeHistory = document.getElementById('export-history').checked;
    const includeDetailed = document.getElementById('export-detailed').checked;
    const exportCurrent = document.getElementById('export-current').checked;
    
    chrome.runtime.sendMessage({ 
        type: 'EXPORT_EXTRACTION_DATA',
        includeHistory: includeHistory
    }, function(response) {
        if (response && response.success && response.data) {
            let exportData = response.data;
            
            // Remove detailed listings if not requested
            if (!includeDetailed) {
                exportData.extractions = exportData.extractions.map(extraction => {
                    const cleaned = { ...extraction };
                    if (cleaned.listings) {
                        cleaned.listings = cleaned.listings.slice(0, 10); // Keep only first 10
                        cleaned.listings_note = `First 10 of ${extraction.listings.length} listings`;
                    }
                    return cleaned;
                });
            }
            
            // Create download
            const blob = new Blob([JSON.stringify(exportData, null, 2)], 
                { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const filename = `ebay-extraction-${timestamp}.json`;
            
            // Create temporary download link
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            console.log('📁 Data exported:', filename);
            
            // Show success feedback
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = '✅ Downloaded!';
            button.style.background = '#10b981';
            
            setTimeout(() => {
                button.textContent = originalText;
                button.style.background = '';
            }, 2000);
        }
    });
}

/**
 * Listen for background script updates
 */
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.type === 'EXTRACTION_UPDATED') {
        // Refresh the popup data if it's currently open
        loadExtractionData();
        loadExtractionHistory();
    }
});

/**
 * Load FlipStack settings into form
 */
function loadFlipStackSettings() {
    chrome.storage.sync.get(['flipstack_url', 'flipstack_api_token'], function(config) {
        if (chrome.runtime.lastError) {
            console.error('Failed to load FlipStack settings:', chrome.runtime.lastError.message);
            return;
        }
        const urlField = document.getElementById('flipstack-url');
        const tokenField = document.getElementById('flipstack-token');
        if (urlField) {
            urlField.value = config.flipstack_url || 'https://flipstack.app';
        }
        if (tokenField && config.flipstack_api_token) {
            tokenField.value = config.flipstack_api_token;
        }
    });
}

/**
 * Save FlipStack settings
 */
function saveFlipStackSettings() {
    const url = document.getElementById('flipstack-url').value.trim();
    const token = document.getElementById('flipstack-token').value.trim();

    chrome.storage.sync.set({
        flipstack_url: url,
        flipstack_api_token: token
    }, function() {
        const status = document.getElementById('flipstack-status');
        if (chrome.runtime.lastError) {
            console.error('Failed to save FlipStack settings:', chrome.runtime.lastError.message);
            status.style.display = 'block';
            status.style.color = '#dc2626';
            status.textContent = 'Save failed: ' + chrome.runtime.lastError.message;
            return;
        }
        console.log('FlipStack settings saved successfully');
        status.style.display = 'block';
        status.style.color = '#059669';
        status.textContent = 'Settings saved.';
        setTimeout(() => { status.style.display = 'none'; }, 3000);
    });
}

/**
 * Test FlipStack connection
 */
async function testFlipStackConnection() {
    const url = document.getElementById('flipstack-url').value.trim();
    const token = document.getElementById('flipstack-token').value.trim();
    const status = document.getElementById('flipstack-status');

    if (!url || !token) {
        status.style.display = 'block';
        status.style.color = '#dc2626';
        status.textContent = 'Please enter URL and token first.';
        return;
    }

    status.style.display = 'block';
    status.style.color = '#6b7280';
    status.textContent = 'Testing connection...';

    try {
        const response = await fetch(`${url.replace(/\/+$/, '')}/api/user`, {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });

        if (response.ok) {
            const data = await response.json();
            status.style.color = '#059669';
            status.textContent = `Connected as ${data.data?.name || 'user'}.`;
        } else {
            status.style.color = '#dc2626';
            status.textContent = `Error ${response.status}: Authentication failed.`;
        }
    } catch (error) {
        status.style.color = '#dc2626';
        status.textContent = `Connection failed: ${error.message}`;
    }
}

// Load FlipStack settings is called inside DOMContentLoaded above

// Auto-refresh data every 5 seconds when popup is open
setInterval(() => {
    if (document.visibilityState === 'visible') {
        loadExtractionData();
    }
}, 5000);
