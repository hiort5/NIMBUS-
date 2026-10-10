/**
 * Nimbus Smart Restaurant - Core Interactive Utilities
 */
window.Nimbus = {
    // Toast Notification System
    toast: function(message, type = 'info', duration = 3000) {
        let container = document.getElementById('nimbus-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'nimbus-toast-container';
            container.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:10px;pointer-events:none;';
            document.body.appendChild(container);
        }

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠️',
            info: 'ℹ️'
        };

        const bgColors = {
            success: '#059669',
            error: '#dc2626',
            warning: '#d97706',
            info: '#0f172a'
        };

        const toast = document.createElement('div');
        toast.className = 'nimbus-toast-item';
        toast.style.cssText = `
            background:${bgColors[type] || bgColors.info};
            color:#fff;
            padding:12px 20px;
            border-radius:12px;
            box-shadow:0 10px 25px rgba(0,0,0,0.2);
            font-size:14px;
            font-weight:600;
            display:flex;
            align-items:center;
            gap:10px;
            pointer-events:auto;
            transform:translateX(50px);
            opacity:0;
            transition:all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        `;

        toast.innerHTML = `<span>${icons[type] || '•'}</span> <span>${message}</span>`;
        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        });

        setTimeout(() => {
            toast.style.transform = 'translateX(50px)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    // Audio Notification Chime for Kitchen
    playChime: function() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.12); // A5
            
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            
            osc.connect(gain);
            gain.connect(ctx.destination);
            
            osc.start();
            osc.stop(ctx.currentTime + 0.45);
        } catch(e) {
            console.warn('Audio chime unsupported or blocked');
        }
    },

    // Image Upload Preview Helper
    setupImagePreview: function(inputId, previewImgId, containerId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewImgId);
        const container = containerId ? document.getElementById(containerId) : null;
        
        if (!input || !preview) return;

        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    if (container) container.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    },

    // Live Menu Search and Category Filtering
    setupMenuFilter: function(searchInputId = 'menuSearch', chipSelector = '.cat-chip', itemSelector = '.food-item-col') {
        const searchInput = document.getElementById(searchInputId);
        const chips = document.querySelectorAll(chipSelector);
        const items = document.querySelectorAll(itemSelector);

        let activeCategory = 'all';
        let activeQuery = '';

        function filterItems() {
            let visibleCount = 0;
            items.forEach(item => {
                const itemCat = (item.dataset.category || '').toLowerCase();
                const itemSubCat = (item.dataset.subcategory || '').toLowerCase();
                const itemName = (item.dataset.name || '').toLowerCase();

                const matchesCat = (activeCategory === 'all') || 
                                   (itemCat === activeCategory) || 
                                   (itemSubCat === activeCategory);
                const matchesQuery = !activeQuery || itemName.includes(activeQuery);

                if (matchesCat && matchesQuery) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Handle Section headers
            document.querySelectorAll('.category-section-title').forEach(section => {
                const secCat = (section.dataset.category || '').toLowerCase();
                if (activeCategory !== 'all' && secCat !== activeCategory) {
                    section.style.display = 'none';
                } else {
                    section.style.display = '';
                }
            });

            const emptyMsg = document.getElementById('noResultsMessage');
            if (emptyMsg) {
                emptyMsg.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                activeQuery = this.value.trim().toLowerCase();
                filterItems();
            });
        }

        chips.forEach(chip => {
            chip.addEventListener('click', function() {
                chips.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                activeCategory = (this.dataset.filter || 'all').toLowerCase();
                filterItems();
            });
        });
    }
};
