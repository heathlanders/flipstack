/**
 * eBay Research Extension - Updated for Sold Research Data
 * Extracts comprehensive sold data from eBay research tables
 * Integrates with FlipStack for automatic data submission
 */

// FlipStack page: listen for context event and store credentials, then exit early
(function () {
    const host = window.location.hostname;
    const isFlipStack = host === 'flipstack.app' || host.endsWith('.flipstack.app') || host === 'localhost';
    if (!isFlipStack) return;

    window.addEventListener('flipstack-extension-context', (e) => {
        const { token, queueItemId, flipstackUrl } = e.detail || {};
        if (typeof chrome !== 'undefined' && chrome.storage) {
            const data = {};
            if (token) data.flipstack_api_token = token;
            if (queueItemId) data.flipstack_queue_item_id = String(queueItemId);
            if (flipstackUrl) data.flipstack_url = flipstackUrl;
            chrome.storage.sync.set(data, () => {
                console.log('FlipStack extension context saved', data);
            });
        }
    });

    console.log('FlipStack extension context listener registered');
})();

// Only initialize eBay extractor on eBay pages
if (window.location.hostname.includes('ebay.')) {

class EbayResearchExtractor {
    constructor() {
        this.isInitialized = false;
        this.extractedData = null;
        this.flipstackQueueItemId = null;

        // Initialize when page is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initialize());
        } else {
            this.initialize();
        }
    }

    /**
     * Initialize the extension
     */
    initialize() {
        if (this.isInitialized) return;

        try {
            // Check if this is a supported eBay page
            if (!this.isSupportedPage()) {
                console.log('Not a supported eBay page');
                return;
            }

            // Parse FlipStack queue item ID from URL hash
            this.parseFlipStackContext();

            console.log('eBay Research Extension initialized');

            // Inject the extraction button
            this.injectExtractionButton();

            this.isInitialized = true;

            // If we have a FlipStack queue item context, update button styling
            if (this.flipstackQueueItemId) {
                console.log('FlipStack queue item detected:', this.flipstackQueueItemId);
                this.updateButtonForFlipStack();
            } else if (typeof chrome !== 'undefined' && chrome.storage) {
                chrome.storage.sync.get(['flipstack_queue_item_id'], (result) => {
                    if (result.flipstack_queue_item_id) {
                        this.flipstackQueueItemId = result.flipstack_queue_item_id;
                        this.updateButtonForFlipStack();
                    }
                });
            }

        } catch (error) {
            console.error('Extension initialization failed:', error);
        }
    }

    /**
     * Parse FlipStack context from URL hash fragment
     */
    parseFlipStackContext() {
        const hash = window.location.hash;
        const queueMatch = hash.match(/flipstack-queueitem=([a-zA-Z0-9_-]+)/i);
        const tokenMatch = hash.match(/token=([^&]+)/);

        if (queueMatch) {
            this.flipstackQueueItemId = queueMatch[1];
            if (typeof chrome !== 'undefined' && chrome.storage) {
                const data = { flipstack_queue_item_id: queueMatch[1] };
                if (tokenMatch) {
                    data.flipstack_api_token = decodeURIComponent(tokenMatch[1]);
                }
                chrome.storage.sync.set(data);
            }
            // Strip hash from URL to keep it clean
            history.replaceState(null, '', window.location.href.replace(/#.*$/, ''));
        }
    }

    /**
     * Update button to show auto-extract mode
     */
    updateButtonForFlipStack() {
        const buttonText = document.getElementById('extract-button-text');
        if (buttonText) {
            buttonText.textContent = 'Extract & Send to FlipStack';
        }
        const button = document.getElementById('ebay-extract-button');
        if (button) {
            button.style.setProperty('background', 'linear-gradient(135deg, #7c3aed, #6d28d9)', 'important');
        }
    }

    /**
     * Check if current page is supported for extraction
     */
    isSupportedPage() {
        const url = window.location.href;
        const pathname = window.location.pathname;

        // eBay research pages
        if (pathname.includes('/sh/research')) return true;

        // eBay search results (sold listings)
        if (pathname.includes('/sch/') && url.includes('LH_Sold=1')) return true;

        // Regular search results
        if (pathname.includes('/sch/')) return true;

        return false;
    }

    /**
     * Inject the extraction button
     */
    injectExtractionButton() {
        // Remove existing button if present
        const existingButton = document.getElementById('ebay-extract-button');
        if (existingButton) {
            existingButton.remove();
        }

        // Create extraction button
        const button = document.createElement('button');
        button.id = 'ebay-extract-button';
        button.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M13 3h4c1.1 0 2 .9 2 2v4h-2V5h-4V3zm-2 18H7c-1.1 0-2-.9-2-2v-4h2v4h4v2zM9 3H7c-1.1 0-2 .9-2 2v2h2V5h2V3zm8 18v-2h2v-2h2v4c0 1.1-.9 2-2 2h-2zm-9-8h8l-2.5-2.5L12 12l-1.5 1.5L8 11z"/>
            </svg>
            <span id="extract-button-text">Extract Sold Data</span>
        `;

        // Ultra-strong positioning to override eBay CSS
        button.style.cssText = `
            position: fixed !important;
            top: 20px !important;
            right: 20px !important;
            z-index: 2147483647 !important;
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
            border: none !important;
            padding: 12px 20px !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            cursor: pointer !important;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3) !important;
            transition: all 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            min-width: 180px !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            transform: none !important;
            margin: 0 !important;
        `;

        // Hover effects
        button.addEventListener('mouseenter', () => {
            button.style.setProperty('transform', 'translateY(-2px)', 'important');
            button.style.setProperty('box-shadow', '0 12px 40px rgba(0,0,0,0.4)', 'important');
        });

        button.addEventListener('mouseleave', () => {
            button.style.setProperty('transform', 'translateY(0)', 'important');
            button.style.setProperty('box-shadow', '0 8px 32px rgba(0,0,0,0.3)', 'important');
        });

        // Click handler
        button.addEventListener('click', () => {
            this.performAllPagesExtraction();
        });

        // Add to page
        document.body.appendChild(button);
    }

    /**
     * Extract all pages with pagination support
     */
    async performAllPagesExtraction() {
        const button = document.getElementById('ebay-extract-button');
        const buttonText = document.getElementById('extract-button-text');

        try {
            console.log('Starting eBay sold data extraction (all pages)...');

            // Update button to loading state
            button.style.setProperty('background', 'linear-gradient(135deg, #f59e0b, #d97706)', 'important');
            buttonText.textContent = 'Extracting page 1...';

            // Add loading spinner
            const spinner = button.querySelector('svg');
            spinner.style.animation = 'spin 1s linear infinite';

            // Wait for initial content
            await this.waitForElements(['.research-table-row', '.s-item', '.srp-item'], 3000);

            const pageType = this.detectPageType();
            console.log('Page type detected:', pageType);

            let allListings = [];
            let currentPage = 1;

            if (pageType === 'research') {
                // Extract with pagination for research pages
                while (true) {
                    buttonText.textContent = `Extracting page ${currentPage}...`;
                    const listings = await this.extractResearchTableData();
                    allListings = allListings.concat(listings);

                    // Try to go to next page
                    const hasNext = await this.goToNextResearchPage();
                    if (!hasNext) break;

                    currentPage++;
                    // Wait for table to re-render
                    await this.waitForTableUpdate();
                }
            } else if (pageType === 'search_sold') {
                allListings = await this.extractSoldListings();
            } else if (pageType === 'search_active') {
                allListings = await this.extractActiveListings();
            }

            // Process and analyze results
            const results = await this.processExtractionResults(pageType, allListings);

            console.log('Extraction complete!', results);

            // Update button to success state
            button.style.setProperty('background', 'linear-gradient(135deg, #10b981, #059669)', 'important');
            buttonText.textContent = `${allListings.length} found! (${currentPage} page${currentPage > 1 ? 's' : ''})`;
            spinner.style.animation = '';

            // Show detailed results popup
            this.showDetailedResults(results);

            // Send to background script for popup access and FlipStack submission
            if (typeof chrome !== 'undefined' && chrome.runtime) {
                chrome.runtime.sendMessage({
                    type: 'EXTRACTION_COMPLETE',
                    data: results
                }, function(response) {
                    if (chrome.runtime.lastError) {
                        console.error('Failed to send extraction to background:', chrome.runtime.lastError.message);
                        return;
                    }
                    if (response && response.success) {
                        console.log('Extraction data sent to background script successfully');
                    } else {
                        console.warn('Background script did not acknowledge extraction data');
                    }
                });
            }

        } catch (error) {
            console.error('Extraction failed:', error);

            button.style.setProperty('background', 'linear-gradient(135deg, #ef4444, #dc2626)', 'important');
            buttonText.textContent = 'Failed - click to retry';

            const spinner = button.querySelector('svg');
            spinner.style.animation = '';
        }

        // Reset button after 10 seconds
        setTimeout(() => {
            button.style.setProperty('background', 'linear-gradient(135deg, #10b981, #059669)', 'important');
            buttonText.textContent = 'Extract Sold Data';
        }, 10000);
    }

    /**
     * Navigate to next page in research table (SPA-style pagination)
     */
    async goToNextResearchPage() {
        // Look for pagination next button
        const nextButtons = document.querySelectorAll(
            '.pagination__next, [data-testid="pagination-next"], .research-pagination__next, button[aria-label="Next page"], .ebay-pagination__next'
        );

        for (const btn of nextButtons) {
            if (btn && !btn.disabled && !btn.classList.contains('disabled')) {
                btn.click();
                return true;
            }
        }

        // Also check for numbered pagination links
        const paginationLinks = document.querySelectorAll('.pagination__item, .research-pagination__item');
        let foundCurrent = false;
        for (const link of paginationLinks) {
            if (foundCurrent) {
                link.click();
                return true;
            }
            if (link.classList.contains('active') || link.getAttribute('aria-current') === 'page') {
                foundCurrent = true;
            }
        }

        return false;
    }

    /**
     * Wait for table to update after pagination click
     */
    async waitForTableUpdate() {
        return new Promise((resolve) => {
            // Use MutationObserver to detect table re-render
            const tableContainer = document.querySelector('.research-table, .srp-results, .s-results');
            if (!tableContainer) {
                // Fallback: just wait 2s
                setTimeout(resolve, 2000);
                return;
            }

            let resolved = false;
            const observer = new MutationObserver(() => {
                if (!resolved) {
                    resolved = true;
                    observer.disconnect();
                    // Small extra delay for rendering
                    setTimeout(resolve, 500);
                }
            });

            observer.observe(tableContainer, { childList: true, subtree: true });

            // Timeout fallback
            setTimeout(() => {
                if (!resolved) {
                    resolved = true;
                    observer.disconnect();
                    resolve();
                }
            }, 5000);
        });
    }

    /**
     * Perform data extraction (kept for backward compat, delegates to all-pages)
     */
    async performExtraction() {
        return this.performAllPagesExtraction();
    }

    /**
     * Detect page type for appropriate extraction strategy
     */
    detectPageType() {
        const pathname = window.location.pathname;
        const url = window.location.href;

        if (pathname.includes('/sh/research')) {
            return 'research';
        } else if (pathname.includes('/sch/') && url.includes('LH_Sold=1')) {
            return 'search_sold';
        } else if (pathname.includes('/sch/')) {
            return 'search_active';
        }

        return 'unknown';
    }

    /**
     * Extract data from eBay research table
     */
    async extractResearchTableData() {
        console.log('Extracting sold research table data...');

        const listings = [];
        const researchRows = document.querySelectorAll('.research-table-row');

        console.log(`Found ${researchRows.length} research table rows`);

        for (let i = 0; i < Math.min(researchRows.length, 100); i++) {
            const row = researchRows[i];
            const listing = {
                type: 'research_sold',
                extraction_time: new Date().toISOString(),
                row_index: i
            };

            try {
                const thumbnailImg = row.querySelector('.research-table-row__thumbnail img');
                if (thumbnailImg && thumbnailImg.alt) {
                    listing.title = thumbnailImg.alt.trim();
                }

                const linkElement = row.querySelector('.research-table-row__link-row-anchor');
                if (linkElement && linkElement.href) {
                    listing.url = linkElement.href;
                    const itemIdMatch = linkElement.href.match(/\/itm\/(\d+)/);
                    if (itemIdMatch) {
                        listing.item_id = itemIdMatch[1];
                    }
                }

                const avgPriceCell = row.querySelector('.research-table-row__avgSoldPrice');
                if (avgPriceCell) {
                    const priceText = avgPriceCell.textContent?.trim();
                    if (priceText) {
                        listing.avg_price_raw = priceText;
                        const priceMatch = priceText.match(/\$[\d,]+\.?\d*/);
                        if (priceMatch) {
                            listing.avg_price = priceMatch[0];
                            listing.avg_price_numeric = this.parsePrice(priceMatch[0]);
                        }
                    }
                }

                const totalSoldCell = row.querySelector('.research-table-row__totalSoldCount');
                if (totalSoldCell) {
                    const soldText = totalSoldCell.textContent?.trim();
                    if (soldText && soldText !== '-') {
                        listing.total_sold = parseInt(soldText) || 0;
                    }
                }

                const totalSalesCell = row.querySelector('.research-table-row__totalSalesValue');
                if (totalSalesCell) {
                    const salesText = totalSalesCell.textContent?.trim();
                    if (salesText && salesText !== '-') {
                        listing.total_sales = salesText;
                        listing.total_sales_numeric = this.parsePrice(salesText);
                    }
                }

                const lastSoldCell = row.querySelector('.research-table-row__dateLastSold');
                if (lastSoldCell) {
                    const dateText = lastSoldCell.textContent?.trim();
                    if (dateText && dateText !== '-') {
                        listing.last_sold_date = dateText;
                        listing.last_sold_timestamp = this.parseDate(dateText);
                    }
                }

                const shippingCell = row.querySelector('.research-table-row__avgShippingCost');
                if (shippingCell) {
                    const shippingText = shippingCell.textContent?.trim();
                    if (shippingText) {
                        listing.shipping_raw = shippingText;
                        const shippingMatch = shippingText.match(/\$[\d,]+\.?\d*/);
                        if (shippingMatch) {
                            listing.shipping_cost = shippingMatch[0];
                            listing.shipping_numeric = this.parsePrice(shippingMatch[0]);
                        }
                        listing.free_shipping = shippingText.includes('Free shipping') || shippingText.includes('100%');
                    }
                }

                const bidsCell = row.querySelector('.research-table-row__bids');
                if (bidsCell) {
                    const bidsText = bidsCell.textContent?.trim();
                    if (bidsText && bidsText !== '-') {
                        listing.bids = bidsText;
                    }
                }

                if (listing.title && (listing.avg_price_numeric > 0 || listing.total_sold > 0)) {
                    listings.push(listing);
                    console.log(`${i+1}. ${listing.title} - ${listing.avg_price} (${listing.total_sold} sold)`);
                }

            } catch (error) {
                console.warn('Failed to extract research row:', error);
            }
        }

        console.log(`Extracted ${listings.length} sold research listings`);
        return listings;
    }

    /**
     * Extract sold listings data (fallback for search pages)
     */
    async extractSoldListings() {
        const listings = [];
        const elements = document.querySelectorAll('.s-item');

        for (let i = 0; i < Math.min(elements.length, 50); i++) {
            const element = elements[i];
            const listing = await this.extractListingData(element, 'sold');
            if (listing && listing.title && listing.price) {
                listings.push(listing);
            }
        }

        return listings;
    }

    /**
     * Extract active listings data (fallback for search pages)
     */
    async extractActiveListings() {
        const listings = [];
        const elements = document.querySelectorAll('.s-item');

        for (let i = 0; i < Math.min(elements.length, 50); i++) {
            const element = elements[i];
            const listing = await this.extractListingData(element, 'active');
            if (listing && listing.title && listing.price) {
                listings.push(listing);
            }
        }

        return listings;
    }

    /**
     * Extract individual listing data (for search pages)
     */
    async extractListingData(element, type) {
        try {
            const listing = {
                type: type,
                extraction_time: new Date().toISOString()
            };

            const titleSelectors = ['.s-item__title', 'h3.s-item__title', '.it-ttl a', '.x-item-title-label'];
            for (const selector of titleSelectors) {
                const titleElement = element.querySelector(selector);
                if (titleElement) {
                    listing.title = titleElement.textContent?.trim();
                    listing.url = titleElement.href;
                    break;
                }
            }

            const priceSelectors = ['.s-item__price', '.notranslate'];
            for (const selector of priceSelectors) {
                const priceElement = element.querySelector(selector);
                if (priceElement) {
                    const priceText = priceElement.textContent?.trim();
                    if (priceText && (priceText.includes('$') || priceText.includes('\u00a3') || priceText.includes('\u20ac'))) {
                        listing.price = priceText;
                        listing.price_numeric = this.parsePrice(priceText);
                        break;
                    }
                }
            }

            const conditionSelectors = ['.s-item__subtitle', '.SECONDARY_INFO'];
            for (const selector of conditionSelectors) {
                const conditionElement = element.querySelector(selector);
                if (conditionElement) {
                    const conditionText = conditionElement.textContent?.trim();
                    if (conditionText && !conditionText.includes('shipping')) {
                        listing.condition = conditionText;
                        break;
                    }
                }
            }

            return listing;
        } catch (error) {
            console.warn('Failed to extract listing:', error);
            return null;
        }
    }

    /**
     * Process and analyze extraction results
     */
    async processExtractionResults(pageType, listings) {
        const results = {
            page_type: pageType,
            url: window.location.href,
            extraction_time: new Date().toISOString(),
            total_listings: listings.length,
            listings: listings
        };

        if (pageType === 'research') {
            const prices = listings.map(l => l.avg_price_numeric).filter(p => p && p > 0);
            const totalSold = listings.map(l => l.total_sold).filter(s => s && s > 0).reduce((a, b) => a + b, 0);
            const totalSalesValue = listings.map(l => l.total_sales_numeric).filter(s => s && s > 0).reduce((a, b) => a + b, 0);

            if (prices.length > 0) {
                prices.sort((a, b) => a - b);
                results.price_analysis = {
                    count: prices.length,
                    min: Math.min(...prices),
                    max: Math.max(...prices),
                    average: prices.reduce((a, b) => a + b, 0) / prices.length,
                    median: prices[Math.floor(prices.length / 2)],
                    q1: prices[Math.floor(prices.length * 0.25)],
                    q3: prices[Math.floor(prices.length * 0.75)]
                };
            }

            results.sales_analysis = {
                total_units_sold: totalSold,
                total_sales_value: totalSalesValue,
                avg_units_per_listing: listings.length > 0 ? totalSold / listings.length : 0,
                products_with_sales: listings.filter(l => l.total_sold > 0).length
            };
        } else {
            const prices = listings.map(l => l.price_numeric).filter(p => p && p > 0);
            if (prices.length > 0) {
                prices.sort((a, b) => a - b);
                results.price_analysis = {
                    count: prices.length,
                    min: Math.min(...prices),
                    max: Math.max(...prices),
                    average: prices.reduce((a, b) => a + b, 0) / prices.length,
                    median: prices[Math.floor(prices.length / 2)],
                    q1: prices[Math.floor(prices.length * 0.25)],
                    q3: prices[Math.floor(prices.length * 0.75)]
                };
            }
        }

        results.market_insights = this.generateMarketInsights(results);

        return results;
    }

    /**
     * Generate market insights from extracted data
     */
    generateMarketInsights(results) {
        const insights = [];

        if (results.price_analysis) {
            const { min, max, average, median, count } = results.price_analysis;
            const priceSpread = max - min;
            const spreadPercent = ((priceSpread / average) * 100).toFixed(1);

            if (spreadPercent > 100) {
                insights.push({
                    type: 'warning',
                    title: 'High Price Volatility',
                    message: `Price range varies by ${spreadPercent}% - market may be volatile`
                });
            }

            if (count < 10) {
                insights.push({
                    type: 'info',
                    title: 'Limited Sample Size',
                    message: `Only ${count} listings with prices found - consider broader search`
                });
            } else {
                insights.push({
                    type: 'success',
                    title: 'Good Sample Size',
                    message: `${count} listings with reliable pricing data`
                });
            }

            const suggestedPrice = median * 1.05;
            insights.push({
                type: 'success',
                title: 'Pricing Suggestion',
                message: `Consider pricing around $${suggestedPrice.toFixed(2)} (median + 5%)`
            });
        }

        if (results.sales_analysis) {
            const { total_units_sold, products_with_sales, total_sales_value } = results.sales_analysis;

            if (total_units_sold > 100) {
                insights.push({
                    type: 'success',
                    title: 'High Sales Volume',
                    message: `${total_units_sold} total units sold - strong market demand`
                });
            }

            if (total_sales_value > 10000) {
                insights.push({
                    type: 'success',
                    title: 'Valuable Market',
                    message: `$${total_sales_value.toFixed(0)} in total sales - profitable category`
                });
            }

            if (results.total_listings > 0) {
                const salesRate = (products_with_sales / results.total_listings * 100).toFixed(1);
                insights.push({
                    type: 'info',
                    title: 'Sales Success Rate',
                    message: `${salesRate}% of products have recorded sales`
                });
            }
        }

        return insights;
    }

    /**
     * Show detailed results popup
     */
    showDetailedResults(results) {
        const existing = document.getElementById('extraction-results-popup');
        if (existing) existing.remove();

        const popup = document.createElement('div');
        popup.id = 'extraction-results-popup';
        popup.style.cssText = `
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            background: white !important;
            border: 2px solid #10b981 !important;
            border-radius: 16px !important;
            padding: 24px !important;
            max-width: 700px !important;
            max-height: 80vh !important;
            overflow-y: auto !important;
            z-index: 2147483647 !important;
            box-shadow: 0 25px 75px rgba(0,0,0,0.4) !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        `;

        let content = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #059669; font-size: 24px; font-weight: bold;">Sold Data Results</h3>
                <button id="close-results" style="background: #ef4444; color: white; border: none; border-radius: 8px; padding: 8px 12px; cursor: pointer; font-weight: bold;">X</button>
            </div>
        `;

        // FlipStack status
        if (this.flipstackQueueItemId) {
            content += `
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; color: #1e40af;">
                    <strong>FlipStack:</strong> Data will be auto-sent to queue item ${this.flipstackQueueItemId.substring(0, 8)}...
                </div>
            `;
        }

        content += `
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; color: #14532d; font-weight: 500;">
                    <div><strong style="color: #166534;">Page Type:</strong> ${results.page_type}</div>
                    <div><strong style="color: #166534;">Total Found:</strong> ${results.total_listings} listings</div>
                </div>
            </div>
        `;

        if (results.price_analysis) {
            const pa = results.price_analysis;
            content += `
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 12px 0; color: #1e3a8a; font-weight: bold;">Price Analysis</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; font-size: 14px; color: #1e40af; font-weight: 500;">
                        <div><strong style="color: #1e3a8a;">Average:</strong> $${pa.average.toFixed(2)}</div>
                        <div><strong style="color: #1e3a8a;">Median:</strong> $${pa.median.toFixed(2)}</div>
                        <div><strong style="color: #1e3a8a;">Range:</strong> $${pa.min} - $${pa.max}</div>
                    </div>
                </div>
            `;
        }

        if (results.sales_analysis) {
            const sa = results.sales_analysis;
            content += `
                <div style="background: #f0f9ff; border: 1px solid #bae6fd; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 12px 0; color: #0c4a6e; font-weight: bold;">Sales Analysis</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 14px; color: #0369a1; font-weight: 500;">
                        <div><strong style="color: #0c4a6e;">Total Units Sold:</strong> ${sa.total_units_sold}</div>
                        <div><strong style="color: #0c4a6e;">Total Sales Value:</strong> $${sa.total_sales_value.toFixed(0)}</div>
                        <div><strong style="color: #0c4a6e;">Products with Sales:</strong> ${sa.products_with_sales}</div>
                        <div><strong style="color: #0c4a6e;">Avg Units/Listing:</strong> ${sa.avg_units_per_listing.toFixed(1)}</div>
                    </div>
                </div>
            `;
        }

        if (results.market_insights && results.market_insights.length > 0) {
            content += `<div style="margin-bottom: 20px;">`;
            content += `<h4 style="margin: 0 0 12px 0; color: #1f2937; font-weight: bold;">Market Insights</h4>`;
            results.market_insights.forEach(insight => {
                const colorSets = {
                    success: {bg: '#dcfce7', border: '#bbf7d0', text: '#14532d'},
                    warning: {bg: '#fef3c7', border: '#fde68a', text: '#92400e'},
                    info: {bg: '#dbeafe', border: '#bfdbfe', text: '#1e40af'}
                };
                const colors = colorSets[insight.type];
                content += `
                    <div style="background: ${colors.bg}; border: 1px solid ${colors.border}; color: ${colors.text}; padding: 12px; border-radius: 8px; margin: 8px 0; font-weight: 500;">
                        <strong>${insight.title}:</strong> ${insight.message}
                    </div>
                `;
            });
            content += `</div>`;
        }

        content += `
            <div>
                <h4 style="margin: 0 0 12px 0; color: #1f2937; font-weight: bold;">Sample Sold Listings (First 5)</h4>
                <div style="max-height: 200px; overflow-y: auto;">
        `;

        results.listings.slice(0, 5).forEach((listing, i) => {
            content += `
                <div style="border: 1px solid #d1d5db; background: #ffffff; border-radius: 8px; padding: 12px; margin: 8px 0; font-size: 13px;">
                    <div style="font-weight: bold; color: #1f2937; margin-bottom: 4px;">
                        ${i + 1}. ${listing.title || 'No title'}
                    </div>
                    <div style="color: #059669; font-weight: bold; font-size: 16px;">
                        ${listing.avg_price || listing.price || 'No price'}
                        ${listing.total_sold ? ` - ${listing.total_sold} sold` : ''}
                        ${listing.last_sold_date ? ` - Last: ${listing.last_sold_date}` : ''}
                    </div>
                    ${listing.url ? `
                        <div style="margin-top: 8px;">
                            <a href="${listing.url}" target="_blank" style="color: #1d4ed8; text-decoration: underline; font-size: 12px; font-weight: 500;">
                                View on eBay
                            </a>
                        </div>
                    ` : ''}
                </div>
            `;
        });

        content += `</div></div>`;

        popup.innerHTML = content;
        document.body.appendChild(popup);

        document.getElementById('close-results').addEventListener('click', () => {
            popup.remove();
        });

        setTimeout(() => {
            if (popup.parentNode) popup.remove();
        }, 120000);
    }

    /**
     * Helper methods
     */
    parsePrice(priceText) {
        if (!priceText) return 0;
        const cleaned = priceText.replace(/[^0-9.,]/g, '');
        const price = parseFloat(cleaned.replace(/,/g, ''));
        return isNaN(price) ? 0 : price;
    }

    parseDate(dateString) {
        try {
            return new Date(dateString).getTime();
        } catch (error) {
            return null;
        }
    }

    normalizeCondition(condition) {
        const lower = condition.toLowerCase();
        if (lower.includes('new')) return 'New';
        if (lower.includes('like new')) return 'Like New';
        if (lower.includes('very good')) return 'Very Good';
        if (lower.includes('good')) return 'Good';
        if (lower.includes('acceptable')) return 'Acceptable';
        if (lower.includes('parts') || lower.includes('repair')) return 'For Parts';
        return 'Other';
    }

    async waitForElements(selectors, timeout = 5000) {
        return new Promise((resolve) => {
            const startTime = Date.now();
            const check = () => {
                for (const selector of selectors) {
                    if (document.querySelector(selector)) {
                        resolve(true);
                        return;
                    }
                }
                if (Date.now() - startTime > timeout) {
                    resolve(false);
                    return;
                }
                setTimeout(check, 100);
            };
            check();
        });
    }
}

// Add CSS for spinning animation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Initialize the extractor
console.log('Initializing eBay Research Extension...');
new EbayResearchExtractor();

} // end if (ebay hostname)
