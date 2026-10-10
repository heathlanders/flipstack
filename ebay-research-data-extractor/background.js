/**
 * Background script for eBay Research Extension - Full Version
 * Includes FlipStack auto-submission support
 */

// Store extraction results
let extractionHistory = [];
let lastExtractionData = null;

// Handle messages from content script
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    console.log('Background received message:', message.type);

    if (message.type === 'EXTRACTION_COMPLETE') {
        // Store the extraction data
        lastExtractionData = message.data;

        // Add to history (keep last 10 extractions)
        extractionHistory.unshift({
            ...message.data,
            tab_id: sender.tab.id,
            tab_url: sender.tab.url
        });

        if (extractionHistory.length > 10) {
            extractionHistory = extractionHistory.slice(0, 10);
        }

        // Update extension badge with listing count
        const count = message.data.total_listings;
        chrome.action.setBadgeText({
            text: count > 99 ? '99+' : count.toString(),
            tabId: sender.tab.id
        });

        chrome.action.setBadgeBackgroundColor({
            color: '#10b981'
        });

        // Show notification for successful extraction
        if (chrome.notifications) {
            chrome.notifications.create({
                type: 'basic',
                iconUrl: 'icons/icon-48.png',
                title: 'eBay Extraction Complete!',
                message: `Found ${count} listings with price analysis`
            });
        }

        console.log('Extraction data stored:', message.data);

        // Auto-send to FlipStack if configured
        sendToFlipStack(message.data);

        sendResponse({ success: true, stored: true });
    }

    // Handle popup requesting extraction data
    if (message.type === 'GET_EXTRACTION_DATA') {
        sendResponse({
            success: true,
            data: lastExtractionData,
            history: extractionHistory
        });
    }

    // Handle popup requesting extraction history
    if (message.type === 'GET_EXTRACTION_HISTORY') {
        sendResponse({
            success: true,
            history: extractionHistory
        });
    }

    // Clear extraction data
    if (message.type === 'CLEAR_EXTRACTION_DATA') {
        lastExtractionData = null;
        extractionHistory = [];
        chrome.action.setBadgeText({ text: '' });
        sendResponse({ success: true, cleared: true });
    }

    // Export extraction data
    if (message.type === 'EXPORT_EXTRACTION_DATA') {
        const dataToExport = message.includeHistory ? extractionHistory : [lastExtractionData];
        const exportData = {
            export_time: new Date().toISOString(),
            total_extractions: dataToExport.length,
            extractions: dataToExport
        };
        sendResponse({
            success: true,
            data: exportData
        });
    }

    // Set queue item context from popup or content script
    if (message.type === 'SET_QUEUE_ITEM_CONTEXT') {
        chrome.storage.sync.set({ flipstack_queue_item_id: message.queueItemId });
        sendResponse({ success: true });
    }

    return true; // Keep message channel open
});

/**
 * Auto-send extraction data to FlipStack API
 */
async function sendToFlipStack(extractionData) {
    try {
        // Get FlipStack config and queue item ID from storage
        const config = await chrome.storage.sync.get([
            'flipstack_url',
            'flipstack_api_token',
            'flipstack_queue_item_id'
        ]);

        if (!config.flipstack_api_token || !config.flipstack_queue_item_id) {
            console.log('FlipStack auto-send skipped. Config state:', {
                hasUrl: !!config.flipstack_url,
                hasToken: !!config.flipstack_api_token,
                hasQueueItemId: !!config.flipstack_queue_item_id
            });
            return;
        }

        const baseUrl = (config.flipstack_url || 'https://flipstack.app').replace(/\/+$/, '');
        const queueItemId = config.flipstack_queue_item_id;
        const apiUrl = `${baseUrl}/api/intake-queue/${queueItemId}/extension-research`;

        console.log('Sending extraction data to FlipStack:', apiUrl);

        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${config.flipstack_api_token}`
            },
            body: JSON.stringify(extractionData)
        });

        if (response.ok) {
            const result = await response.json();
            console.log('FlipStack received data:', result);

            // Clear the queue item ID after successful send
            chrome.storage.sync.remove('flipstack_queue_item_id');

            // Update badge to show sent status
            chrome.action.setBadgeText({ text: 'SENT' });
            chrome.action.setBadgeBackgroundColor({ color: '#6366f1' });

            if (chrome.notifications) {
                chrome.notifications.create({
                    type: 'basic',
                    iconUrl: 'icons/icon-48.png',
                    title: 'Sent to FlipStack',
                    message: `Research data sent for queue item ${queueItemId.substring(0, 8)}...`
                });
            }

            // Reset badge after 5 seconds
            setTimeout(() => {
                chrome.action.setBadgeText({ text: extractionData.total_listings.toString() });
                chrome.action.setBadgeBackgroundColor({ color: '#10b981' });
            }, 5000);
        } else {
            const errorText = await response.text();
            console.error('FlipStack API error:', response.status, errorText);

            chrome.action.setBadgeText({ text: 'ERR' });
            chrome.action.setBadgeBackgroundColor({ color: '#ef4444' });

            if (chrome.notifications) {
                chrome.notifications.create({
                    type: 'basic',
                    iconUrl: 'icons/icon-48.png',
                    title: 'FlipStack Send Failed',
                    message: `Error ${response.status}: ${errorText.substring(0, 100)}`
                });
            }
        }
    } catch (error) {
        console.error('Failed to send to FlipStack:', error);

        chrome.action.setBadgeText({ text: 'ERR' });
        chrome.action.setBadgeBackgroundColor({ color: '#ef4444' });
    }
}

// Handle extension icon click (when popup is not available)
chrome.action.onClicked.addListener((tab) => {
    console.log('Extension icon clicked for tab:', tab.url);

    if (tab.url && tab.url.includes('ebay.com')) {
        chrome.scripting.executeScript({
            target: { tabId: tab.id },
            func: () => {
                if (!document.getElementById('ebay-extract-button')) {
                    if (typeof EbayResearchExtractor !== 'undefined') {
                        new EbayResearchExtractor();
                    }
                }
            }
        });
    }
});

// Clear badges when tab is updated (navigated away)
chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
    if (changeInfo.status === 'loading' && tab.url && !tab.url.includes('ebay.com')) {
        chrome.action.setBadgeText({ text: '', tabId: tabId });
    }
});

// Cleanup old extraction data periodically (every hour)
setInterval(() => {
    const oneDayAgo = Date.now() - (24 * 60 * 60 * 1000);
    extractionHistory = extractionHistory.filter(extraction => {
        const extractionTime = new Date(extraction.extraction_time).getTime();
        return extractionTime > oneDayAgo;
    });
    console.log('Cleaned old extraction data, kept:', extractionHistory.length);
}, 60 * 60 * 1000);

// Initialize
console.log('eBay Research Extension background script loaded');
console.log('Ready to receive extraction data');
