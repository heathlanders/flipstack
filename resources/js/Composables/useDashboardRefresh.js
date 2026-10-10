 import { ref, onMounted, onUnmounted } from 'vue'

/**
 * Reusable composable for dashboard auto-refresh
 * @param {string} dashboardType - owner, buyer, lister, fulfillment
 * @param {number} interval - refresh interval in milliseconds (default: 60000)
 */
export function useDashboardRefresh(dashboardType, interval = 60000) {
    const isRefreshing = ref(false)
    const lastRefresh = ref(new Date())
    const refreshInterval = ref(null)
    const refreshEnabled = ref(true)
    
    // Reactive data that will be updated
    const liveData = ref({
        businessMetrics: {},
        alerts: {},
        platformHealth: {},
        metrics: {},
        recentActivity: []
    })

    const refreshData = async () => {
        if (isRefreshing.value) return
        
        try {
            isRefreshing.value = true
            
            const response = await fetch(`/api/dashboard/live-data/${dashboardType}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                }
            })
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`)
            }
            
            const data = await response.json()
            
            // Update live data
            liveData.value = {
                ...liveData.value,
                ...data
            }
            
            lastRefresh.value = new Date()
            
            // Optional: Show toast notification for critical updates
            if (data.criticalAlerts && data.criticalAlerts.length > 0) {
                console.info(' Critical alerts updated:', data.criticalAlerts)
            }
            
        } catch (error) {
            console.error('Dashboard refresh failed:', error)
            // Optionally show user-friendly error notification
        } finally {
            isRefreshing.value = false
        }
    }

    const startRefresh = () => {
        if (refreshInterval.value) return
        
        refreshInterval.value = setInterval(() => {
            if (refreshEnabled.value && !document.hidden) {
                refreshData()
            }
        }, interval)
    }

    const stopRefresh = () => {
        if (refreshInterval.value) {
            clearInterval(refreshInterval.value)
            refreshInterval.value = null
        }
    }

    const toggleRefresh = () => {
        refreshEnabled.value = !refreshEnabled.value
        if (refreshEnabled.value) {
            startRefresh()
        } else {
            stopRefresh()
        }
    }

    // Pause refresh when tab is not visible (save resources)
    const handleVisibilityChange = () => {
        if (document.hidden) {
            stopRefresh()
        } else if (refreshEnabled.value) {
            startRefresh()
            refreshData() // Immediate refresh when returning to tab
        }
    }

    onMounted(() => {
        // Start auto-refresh
        startRefresh()
        
        // Handle tab visibility changes
        document.addEventListener('visibilitychange', handleVisibilityChange)
        
        // Initial data refresh
        setTimeout(refreshData, 1000) // Small delay to let component settle
    })

    onUnmounted(() => {
        stopRefresh()
        document.removeEventListener('visibilitychange', handleVisibilityChange)
    })

    return {
        liveData,
        isRefreshing,
        lastRefresh,
        refreshEnabled,
        refreshData,
        toggleRefresh,
        startRefresh,
        stopRefresh
    }
}