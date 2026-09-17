/**
 * GEORX — Supabase Realtime Inventory & Notification Synchronizer
 * 
 * Listens to postgres_changes on:
 *   - pharmacy_medicine (stock, price, availability changes)
 *   - medicines (name, generic, brand additions/edits)
 *   - pharmacies (active status, verification changes)
 *   - notifications (user private notification events)
 * 
 * Dynamically updates UI stock counts, price tags, availability badges,
 * notification badges, and displays toast alerts without requiring page refreshes.
 */

(function () {
    'use strict';

    // 1. Extract Supabase credentials from meta tags or window object
    const supabaseUrl = document.querySelector('meta[name="supabase-url"]')?.content || window.GEORX_SUPABASE_URL;
    const supabaseAnonKey = document.querySelector('meta[name="supabase-anon-key"]')?.content || window.GEORX_SUPABASE_ANON_KEY;

    if (!supabaseUrl || !supabaseAnonKey) {
        console.warn('[GEORX Realtime] Supabase credentials missing. Realtime updates disabled.');
        return;
    }

    if (typeof supabase === 'undefined') {
        console.warn('[GEORX Realtime] Supabase JS SDK not loaded. Load https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2 before this script.');
        return;
    }

    // 2. Initialize Supabase Client
    const client = supabase.createClient(supabaseUrl, supabaseAnonKey);
    console.log('[GEORX Realtime] Subscribing to Supabase Realtime inventory channels...');

    // 3. Subscribe to Public Realtime Inventory Channel
    const inventoryChannel = client.channel('georx-realtime-inventory')
        .on(
            'postgres_changes',
            { event: '*', schema: 'public', table: 'pharmacy_medicine' },
            (payload) => {
                console.log('[GEORX Realtime] Inventory Event:', payload.eventType, payload);
                handleInventoryChange(payload);
            }
        )
        .on(
            'postgres_changes',
            { event: '*', schema: 'public', table: 'medicines' },
            (payload) => {
                console.log('[GEORX Realtime] Medicine Event:', payload.eventType, payload);
                handleMedicineChange(payload);
            }
        )
        .on(
            'postgres_changes',
            { event: '*', schema: 'public', table: 'pharmacies' },
            (payload) => {
                console.log('[GEORX Realtime] Pharmacy Event:', payload.eventType, payload);
                handlePharmacyChange(payload);
            }
        )
        .subscribe((status, err) => {
            if (status === 'SUBSCRIBED') {
                console.log('✅ [GEORX Realtime] Connected & Listening to Live Inventory Updates.');
                showConnectionStatus(true);
            } else if (status === 'CLOSED' || status === 'CHANNEL_ERROR') {
                console.error('[GEORX Realtime] Subscription error:', err);
                showConnectionStatus(false);
            }
        });

    // 4. Subscribe to User Private Notifications Channel (if authenticated user)
    const userIdMeta = document.querySelector('meta[name="user-id"]')?.content || window.GEORX_USER_ID;
    if (userIdMeta) {
        client.channel(`user-notifications-${userIdMeta}`)
            .on(
                'postgres_changes',
                { event: 'INSERT', schema: 'public', table: 'notifications', filter: `notifiable_id=eq.${userIdMeta}` },
                (payload) => {
                    console.log('[GEORX Realtime] User Notification Event:', payload);
                    handleUserNotification(payload);
                }
            )
            .subscribe();
    }

    /**
     * Handle user private notification events
     */
    function handleUserNotification(payload) {
        if (payload.new && payload.new.data) {
            try {
                const data = typeof payload.new.data === 'string' ? JSON.parse(payload.new.data) : payload.new.data;
                if (data.type === 'stock_alert') {
                    showToast(`Restock Alert: ${data.message || 'Medicine back in stock!'}`, 'success');
                    
                    // Increment unread notification badges in header
                    const badgeEls = document.querySelectorAll('.notification-unread-badge, #unread-notif-count');
                    badgeEls.forEach(el => {
                        let current = parseInt(el.textContent) || 0;
                        el.textContent = current + 1;
                        el.classList.remove('hidden');
                    });
                }
            } catch (e) {
                console.error('[GEORX Realtime] Failed parsing notification data', e);
            }
        }
    }

    /**
     * Handle updates on the pharmacy_medicine table
     */
    function handleInventoryChange(payload) {
        const { eventType, new: newRow, old: oldRow } = payload;
        const targetId = newRow?.id || oldRow?.id;
        const medId = newRow?.medicine_id || oldRow?.medicine_id;
        const pharmacyId = newRow?.pharmacy_id || oldRow?.pharmacy_id;

        // 1. Update Stock Badges
        const stockElements = document.querySelectorAll(`[data-inventory-id="${targetId}"], [data-med-stock="${medId}"][data-pharmacy="${pharmacyId}"]`);
        stockElements.forEach(el => {
            if (newRow) {
                const qty = newRow.quantity_on_hand;
                el.textContent = `${qty} in stock`;
                
                // Toggle availability classes
                if (qty <= 0 || !newRow.is_available) {
                    el.classList.add('bg-red-100', 'text-red-800');
                    el.classList.remove('bg-green-100', 'text-green-800');
                    el.textContent = 'Out of Stock';
                } else {
                    el.classList.add('bg-green-100', 'text-green-800');
                    el.classList.remove('bg-red-100', 'text-red-800');
                }
            }
        });

        // 2. Update Price Displays
        if (newRow && newRow.selling_price !== undefined) {
            const priceElements = document.querySelectorAll(`[data-inventory-price="${targetId}"]`);
            priceElements.forEach(el => {
                const price = parseFloat(newRow.selling_price || 0).toFixed(2);
                el.textContent = `₱${price}`;
                el.classList.add('animate-pulse');
                setTimeout(() => el.classList.remove('animate-pulse'), 2000);
            });
        }
    }

    /**
     * Handle updates on the medicines table
     */
    function handleMedicineChange(payload) {
        if (payload.eventType === 'INSERT') {
            showToast(`New Medicine Added: ${payload.new.brand_name || payload.new.generic_name}`, 'info');
        }
    }

    /**
     * Handle updates on the pharmacies table
     */
    function handlePharmacyChange(payload) {
        if (payload.eventType === 'UPDATE' && payload.new) {
            const pharmacyId = payload.new.id;
            const statusElements = document.querySelectorAll(`[data-pharmacy-status="${pharmacyId}"]`);
            statusElements.forEach(el => {
                el.textContent = payload.new.is_active ? 'Open' : 'Closed';
            });
        }
    }

    /**
     * Toast notification utility
     */
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white text-sm font-medium transition-all duration-300 transform translate-y-2 ${
            type === 'success' ? 'bg-emerald-600' : type === 'warning' ? 'bg-amber-600' : 'bg-blue-600'
        }`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-4');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    /**
     * Connection status indicator
     */
    function showConnectionStatus(isConnected) {
        const indicator = document.getElementById('georx-realtime-status');
        if (indicator) {
            indicator.className = isConnected 
                ? 'inline-flex items-center px-2 py-1 text-xs font-semibold text-emerald-700 bg-emerald-100 rounded-full'
                : 'inline-flex items-center px-2 py-1 text-xs font-semibold text-rose-700 bg-rose-100 rounded-full';
            indicator.innerHTML = isConnected 
                ? '<span class="w-2 h-2 mr-1 bg-emerald-500 rounded-full animate-ping"></span> Realtime Active'
                : 'Realtime Disconnected';
        }
    }
})();
