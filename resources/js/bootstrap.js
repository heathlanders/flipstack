import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Configure Laravel Echo for real-time broadcasting
 * 
 * The application supports multiple broadcasting drivers:
 * - Pusher: For cloud-hosted WebSocket connections
 * - Redis/Soketi: For self-hosted WebSocket connections
 * 
 * Set VITE_BROADCAST_DRIVER in .env to configure:
 * - 'pusher' for Pusher/Ably
 * - 'redis' or 'soketi' for self-hosted options
 */

const broadcastDriver = import.meta.env.VITE_BROADCAST_DRIVER || 'pusher';

// Initialize Echo based on the configured driver
if (broadcastDriver === 'pusher' || broadcastDriver === 'ably') {
    // Pusher/Ably configuration
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
        wsHost: import.meta.env.VITE_PUSHER_HOST,
        wsPort: import.meta.env.VITE_PUSHER_PORT || 443,
        wssPort: import.meta.env.VITE_PUSHER_PORT || 443,
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        // Enable logging in development
        ...(import.meta.env.DEV && { 
            enableLogging: true,
        }),
    });
} else if (broadcastDriver === 'soketi' || broadcastDriver === 'redis') {
    // Soketi/Redis (self-hosted) configuration
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY || 'app-key',
        wsHost: import.meta.env.VITE_PUSHER_HOST || window.location.hostname,
        wsPort: import.meta.env.VITE_PUSHER_PORT || 6001,
        wssPort: import.meta.env.VITE_PUSHER_PORT || 6001,
        forceTLS: false,
        encrypted: false,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
        // Enable logging in development
        ...(import.meta.env.DEV && { 
            enableLogging: true,
        }),
    });
} else if (broadcastDriver === 'reverb') {
    // Laravel Reverb configuration
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
        wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

/**
 * Helper function to check if Echo is properly configured
 */
window.isEchoConfigured = () => {
    return typeof window.Echo !== 'undefined' && window.Echo.connector;
};

/**
 * Helper function to safely subscribe to a channel
 * Returns null if Echo is not configured
 */
window.safeChannel = (channelName, isPrivate = false) => {
    if (!window.isEchoConfigured()) {
        console.warn('Laravel Echo is not configured. Real-time features are disabled.');
        return null;
    }
    return isPrivate ? window.Echo.private(channelName) : window.Echo.channel(channelName);
};

/**
 * Global connection state management
 */
window.echoConnectionState = {
    connected: false,
    connecting: false,
    error: null,
};

// Track connection state if Echo is configured
if (window.Echo?.connector?.pusher) {
    const pusher = window.Echo.connector.pusher;
    
    pusher.connection.bind('connected', () => {
        window.echoConnectionState.connected = true;
        window.echoConnectionState.connecting = false;
        window.echoConnectionState.error = null;
        console.log('WebSocket connected');
    });
    
    pusher.connection.bind('connecting', () => {
        window.echoConnectionState.connected = false;
        window.echoConnectionState.connecting = true;
    });
    
    pusher.connection.bind('disconnected', () => {
        window.echoConnectionState.connected = false;
        window.echoConnectionState.connecting = false;
    });
    
    pusher.connection.bind('error', (error) => {
        window.echoConnectionState.error = error;
        console.error('WebSocket error:', error);
    });
    
    pusher.connection.bind('failed', () => {
        window.echoConnectionState.connected = false;
        window.echoConnectionState.connecting = false;
        window.echoConnectionState.error = 'Connection failed';
    });
}

/**
 * Debug helper - log all Echo events in development
 */
if (import.meta.env.DEV && window.Echo) {
    window.Echo.connector.pusher.connection.bind('state_change', (states) => {
        console.log('Echo state change:', states.previous, '->', states.current);
    });
}
