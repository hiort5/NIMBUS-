<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Display System (KDS) - Nimbus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/nimbus.css" rel="stylesheet">
</head>
<body class="kds-body">

    <!-- KDS Header -->
    <header class="kds-navbar py-3">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            
            <div class="d-flex align-items-center gap-3">
                <a href="../index.php" class="nimbus-brand fs-4 text-white">
                    👨‍🍳 Nimbus KDS
                </a>
                <span class="badge bg-danger px-3 py-2 rounded-pill fw-bold text-uppercase" id="liveOrderBadge">
                    LIVE TICKETS
                </span>
            </div>

            <div class="d-flex align-items-center gap-3">
                
                <!-- Audio Alert Toggle -->
                <button type="button" id="soundToggleBtn" class="btn btn-outline-light btn-sm rounded-pill px-3 py-2 fw-semibold d-flex align-items-center gap-2">
                    <span id="soundIcon">🔔</span> <span id="soundText">Sound: ON</span>
                </button>

                <!-- Navigation -->
                <a href="table_view.php" class="btn btn-outline-warning btn-sm rounded-pill px-3 py-2 fw-semibold">
                    🍽️ View by Table
                </a>
                <a href="completed_orders.php" class="btn btn-outline-light btn-sm rounded-pill px-3 py-2 fw-semibold">
                    ✓ Completed Orders
                </a>
                <a href="../index.php" class="btn btn-dark btn-sm rounded-pill px-3 py-2">
                    Exit KDS
                </a>

            </div>

        </div>
    </header>

    <!-- Main Ticket Workspace -->
    <main class="container-fluid px-4 py-4">
        
        <!-- Status Filter Bar -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div class="d-flex gap-2" id="kdsFilterChips">
                <button class="btn btn-sm btn-light active rounded-pill px-3 fw-bold filter-btn" data-status="all">
                    All Active Orders
                </button>
                <button class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold filter-btn" data-status="pending">
                    ⏳ Pending
                </button>
                <button class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold filter-btn" data-status="preparing">
                    👨‍🍳 Preparing
                </button>
                <button class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold filter-btn" data-status="ready">
                    🍽️ Ready to Serve
                </button>
            </div>

            <div class="text-slate-400 small" style="color: #94a3b8;">
                <span id="kdsClock">--:--:--</span> • Auto-refreshes every 3 seconds
            </div>
        </div>

        <!-- Order Tickets Container -->
        <div id="orders">
            <div class="text-center py-5 text-slate-400">
                <div class="spinner-border text-warning mb-3" role="status"></div>
                <div>Loading Kitchen Display tickets...</div>
            </div>
        </div>

    </main>

    <!-- Reusable JS -->
    <script src="../assets/js/nimbus.js"></script>
    <script>
        let soundEnabled = true;
        let previousOrderIds = new Set();
        let currentFilter = 'all';

        // Sound Toggle
        const soundBtn = document.getElementById('soundToggleBtn');
        const soundIcon = document.getElementById('soundIcon');
        const soundText = document.getElementById('soundText');

        soundBtn.addEventListener('click', () => {
            soundEnabled = !soundEnabled;
            soundIcon.innerText = soundEnabled ? '🔔' : '🔕';
            soundText.innerText = soundEnabled ? 'Sound: ON' : 'Sound: OFF';
            soundBtn.classList.toggle('btn-outline-light', soundEnabled);
            soundBtn.classList.toggle('btn-outline-secondary', !soundEnabled);
        });

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('btn-light', 'active');
                    b.classList.add('btn-outline-light');
                });
                this.classList.remove('btn-outline-light');
                this.classList.add('btn-light', 'active');
                currentFilter = this.dataset.status;
                applyTicketFilter();
            });
        });

        function applyTicketFilter() {
            document.querySelectorAll('.kds-ticket-wrapper').forEach(ticket => {
                const status = (ticket.dataset.status || '').toLowerCase();
                if (currentFilter === 'all' || status === currentFilter) {
                    ticket.style.display = '';
                } else {
                    ticket.style.display = 'none';
                }
            });
        }

        // Live Clock
        function updateClock() {
            const now = new Date();
            document.getElementById('kdsClock').innerText = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Load Orders via AJAX
        async function loadKitchenOrders() {
            try {
                const response = await fetch('fetch_orders.php', { cache: 'no-store' });
                if (!response.ok) throw new Error('Network error');
                
                const html = await response.text();
                document.getElementById('orders').innerHTML = html;

                // Check for newly arrived pending orders to trigger chime
                const currentIds = new Set();
                document.querySelectorAll('.kds-ticket-wrapper').forEach(card => {
                    const id = card.dataset.orderId;
                    const status = card.dataset.status;
                    if (id) {
                        currentIds.add(id);
                        if (status === 'Pending' && !previousOrderIds.has(id) && previousOrderIds.size > 0) {
                            if (soundEnabled) Nimbus.playChime();
                            Nimbus.toast(`New Order #${id} arrived!`, 'warning');
                        }
                    }
                });
                previousOrderIds = currentIds;

                applyTicketFilter();

            } catch (error) {
                console.error('KDS Fetch error:', error);
            }
        }

        // Action Trigger via AJAX without page reload
        window.updateKdsStatus = async function(orderId, newStatus) {
            try {
                const res = await fetch(`update_status.php?id=${orderId}&status=${newStatus}&ajax=1`);
                if (res.ok) {
                    Nimbus.toast(`Order #${orderId} marked as ${newStatus}`, 'success');
                    loadKitchenOrders();
                }
            } catch(e) {
                window.location = `update_status.php?id=${orderId}&status=${newStatus}`;
            }
        };

        // Initial load & 3-second auto-poll
        loadKitchenOrders();
        setInterval(loadKitchenOrders, 3000);
    </script>
</body>
</html>
