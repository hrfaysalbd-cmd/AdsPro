/**
 * AdsCampaignPro Tracker
 * v1.1.0
 */
(function() {
    'use strict';

    const API_BASE = window.adcp ? window.adcp.rest_url : '/wp-json/adcp/v1';
    const CLIENT_TOKEN = window.adcp ? window.adcp.client_token : null;
    const GDPR_ENABLED = window.adcp && window.adcp.gdpr_enabled == '1';
    
    const pageUrl = encodeURIComponent(window.location.href);
    const device = detectDevice();

    function getCookieId() {
        let cookieId = localStorage.getItem('adcp_cid');
        if (!cookieId) {
            cookieId = 'cid_' + Date.now() + '_' + Math.random().toString(36).substring(2);
            localStorage.setItem('adcp_cid', cookieId);
        }
        return cookieId;
    }

    function detectDevice() {
        const width = window.innerWidth;
        if (width <= 768) return 'mobile';
        if (width <= 1024) return 'tablet';
        return 'desktop';
    }

    function isCapped(campaign) {
        const capKey = 'adcp_cap_' + campaign.id;
        const capData = localStorage.getItem(capKey);
        if (!capData) return false;
        
        const data = JSON.parse(capData);
        const today = new Date().toISOString().split('T')[0];
        if (data.date !== today || data.count < (campaign.frequency.per_day || 1)) {
            return false;
        }
        return true; // Is Capped
    }

    function setCap(campaign) {
        const capKey = 'adcp_cap_' + campaign.id;
        const capData = localStorage.getItem(capKey);
        const today = new Date().toISOString().split('T')[0];
        let count = 1;
        if (capData) {
            const data = JSON.parse(capData);
            if (data.date === today) {
                count = data.count + 1;
            }
        }
        localStorage.setItem(capKey, JSON.stringify({ date: today, count: count }));
    }

    function buildElementFromConfig(c) {
        const el = document.createElement('div');
        el.className = 'adcp-wrapper adcp-type-' + c.type;
        el.dataset.campaignId = c.id;
        let innerHTML = '<button class="adcp-close" aria-label="Close Ad">&times;</button>';
        
        // --- NEW CONTENT WRAPPER ---
        innerHTML += '<div class="adcp-content-wrap">';

        if (c.creative.html) {
            innerHTML += c.creative.html;
        } else {
            if (c.creative.url) {
                let creative_html = '';
                if (c.creative.type.startsWith('image/')) {
                    creative_html = `<img src="${c.creative.url}" alt="${c.config.headline}" class="adcp-creative-img">`;
                    
                    // --- NEW CLICKABLE IMAGE LOGIC ---
                    if (c.config.cta_url) {
                        innerHTML += `<a href="${c.config.cta_url}" target="_blank" class="adcp-creative-img-link">${creative_html}</a>`;
                    } else {
                        innerHTML += creative_html;
                    }
                    // --- END NEW LOGIC ---

                } else if (c.creative.type.startsWith('video/')) {
                    innerHTML += `<video controls autoplay muted src="${c.creative.url}" class="adcp-creative-video"></video>`;
                }
            }
            if (c.config.headline) {
                innerHTML += `<h3 class="adcp-headline">${c.config.headline}</h3>`;
            }
            if (c.config.subtext) {
                innerHTML += `<p class="adcp-subtext">${c.config.subtext}</p>`;
            }
            if (c.config.cta_label && c.config.cta_url) {
                innerHTML += `<a href="${c.config.cta_url}" target="_blank" class="adcp-cta">${c.config.cta_label}</a>`;
            }
        }
        
        innerHTML += '</div>'; // --- END CONTENT WRAPPER ---
        el.innerHTML = innerHTML;
        
        el.querySelector('.adcp-close').addEventListener('click', () => { 
            // --- NEW REMOVAL LOGIC ---
            if (c.type === 'scroll') {
                document.body.style.paddingTop = '';
                const adminBar = document.getElementById('wpadminbar');
                if (adminBar) {
                    adminBar.style.top = '';
                }
            }
            // --- END REMOVAL LOGIC ---
            el.remove(); 
        });
        return el;
    }

    function renderCampaign(c) {
        if (isCapped(c)) return;

        const el = buildElementFromConfig(c);
        document.body.appendChild(el);
        setTimeout(() => {
            el.classList.add('adcp-visible');

            // --- NEW PUSHDOWN LOGIC ---
            if (c.type === 'scroll') {
                const barHeight = el.offsetHeight;
                document.body.style.paddingTop = barHeight + 'px';
                
                // Add pushdown to WP admin bar if it exists
                const adminBar = document.getElementById('wpadminbar');
                if (adminBar) {
                    adminBar.style.top = barHeight + 'px';
                }
            }
            // --- END PUSHDOWN LOGIC ---
        }, 50);

        // A. Observe Impression
        const io = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    sendTrack('impression', c);
                    setCap(c);
                    io.disconnect();
                }
            });
        }, { threshold: 0.5 });
        io.observe(el);

        // B. Bind Click (on CTA)
        const cta = el.querySelector('.adcp-cta');
        if (cta) {
            cta.addEventListener('click', () => { sendTrack('click', c); });
        }
        // --- NEW: Bind click on image link ---
        const imgLink = el.querySelector('.adcp-creative-img-link');
        if (imgLink) {
            imgLink.addEventListener('click', () => { sendTrack('click', c); });
        }
        
        // C. Bind Engagement (Hover)
        let hoverTimer;
        el.addEventListener('mouseenter', () => {
            hoverTimer = setTimeout(() => {
                sendTrack('engagement', c, { reason: 'hover_3s' });
            }, 3000);
        });
        el.addEventListener('mouseleave', () => { clearTimeout(hoverTimer); });
    }

    function sendTrack(eventType, campaign, meta = {}) {
        const payload = {
            campaign_id: campaign.id,
            token: CLIENT_TOKEN,
            event: eventType,
            page_url: window.location.href,
            cookie_id: getCookieId(),
            meta: meta
        };
        const data = JSON.stringify(payload);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(`${API_BASE}/track`, data);
        } else {
            fetch(`${API_BASE}/track`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: data,
                keepalive: true
            });
        }
    }

    /**
     * Fetch OVERLAY campaigns (popup, slide, scroll)
     */
    function fetchCampaigns() {
        fetch(`${API_BASE}/campaigns?url=${pageUrl}&device=${device}&token=${CLIENT_TOKEN || ''}`)
            .then(response => response.json())
            .then(data => {
                if (data.campaigns && data.campaigns.length > 0) {
                    data.campaigns.forEach(renderCampaign);
                }
            })
            .catch(error => { console.error('AdsCampaignPro Error:', error); });
    }

    /**
     * Find and attach trackers to EMBED campaigns (shortcodes)
     */
    function initEmbedTrackers() {
        const embedAds = document.querySelectorAll('.adcp-embed-wrapper');
        if (embedAds.length === 0) return;

        embedAds.forEach(el => {
            const campaignId = el.dataset.campaignId;
            if (!campaignId) return;
            
            // Create a minimal campaign object for the tracker functions
            // Get frequency cap from element, default to 1
            const freqCap = el.dataset.freqCap || 1;
            const c = { id: campaignId, frequency: { per_day: parseInt(freqCap, 10) } }; 
            
            if (isCapped(c)) {
                console.log('ADCP Embed (ID ' + c.id + '): Capped');
                return;
            }

            // A. Observe Impression
            const io = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        el.style.opacity = 1; // Make visible on impression
                        sendTrack('impression', c);
                        setCap(c); // Use the same capping logic
                        io.disconnect();
                    }
                });
            }, { threshold: 0.5 });
            io.observe(el);

            // B. Bind Click (on CTA)
            const cta = el.querySelector('.adcp-cta');
            if (cta) {
                cta.addEventListener('click', () => { sendTrack('click', c); });
            }
            // --- NEW: Bind click on image link ---
            const imgLink = el.querySelector('.adcp-creative-img-link');
            if (imgLink) {
                imgLink.addEventListener('click', () => { sendTrack('click', c); });
            }
        });
    }

    /**
     * Main execution
     */
    function runTrackers() {
        // --- NEW GDPR CHECK ---
        if (GDPR_ENABLED) {
            // This is a basic check.
            const hasConsent = document.cookie.includes('cookie_notice_accepted=true') || 
                             document.cookie.includes('complianz_policy_accepted=true') ||
                             document.cookie.includes('cookieyes-consent='); // Add more as needed
            if (!hasConsent) {
                console.log('AdsCampaignPro: GDPR consent not found. Halting trackers.');
                return; 
            }
        }
        
        // Run the embed tracker
        initEmbedTrackers();
        
        // Run the overlay tracker
        fetchCampaigns();
    }

    // Run trackers after page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runTrackers);
    } else {
        runTrackers();
    }

})();