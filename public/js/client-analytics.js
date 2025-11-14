jQuery(document).ready(function($) {
    'use strict';

    const $dashboard = $('#adcp-client-dashboard');
    if (!$dashboard.length || typeof adcpClient === 'undefined') {
        return; // Not on the client page or script data is missing
    }

    const $loader = $('#adcp-loader');
    const $content = $('#adcp-analytics-content');
    const ctx = document.getElementById('adcp-client-chart');
    let mainChart;
    const token = adcpClient.token;
    const apiUrl = `${adcpClient.rest_url}/client-analytics/${token}`;

    async function fetchData() {
        $loader.show();
        $content.hide();

        try {
            const response = await fetch(apiUrl, {
                headers: { 'X-WP-Nonce': adcpClient.nonce }
            });
            
            if (!response.ok) {
                const err = await response.json();
                throw new Error(err.message || 'Analytics request failed.');
            }
            
            const data = await response.json();
            renderDashboard(data);

        } catch (error) {
            console.error('Failed to load client analytics:', error);
            $loader.hide();
            $content.html('<p>Error loading analytics data: ' + error.message + '</p>').show();
        }
    }

    function renderDashboard(data) {
        renderGlobalStats(data.global_stats);
        renderTimeSeriesChart(data.timeseries);
        renderTopCampaigns(data.top_campaigns);
        
        $loader.hide();
        $content.show();
    }

    function renderGlobalStats(stats) {
        $('#stat-impressions').text(stats.impressions.toLocaleString());
        $('#stat-clicks').text(stats.clicks.toLocaleString());
        $('#stat-ctr').text(stats.ctr + '%');
    }

    function renderTimeSeriesChart(timeseries) {
        const labels = timeseries.map(item => item.event_date);
        const impressions = timeseries.map(item => item.impressions);
        const clicks = timeseries.map(item => item.clicks);

        if (mainChart) mainChart.destroy();

        mainChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Impressions',
                        data: impressions,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    },
                    {
                        label: 'Clicks',
                        data: clicks,
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1
                    }
                ]
            }
        });
    }

    function renderTopCampaigns(campaigns) {
        const $tbody = $('#adcp-top-campaigns-body');
        $tbody.empty();

        if (!campaigns || campaigns.length === 0) {
            $tbody.html('<tr><td colspan="4">No campaign data available yet.</td></tr>');
            return;
        }

        campaigns.forEach(c => {
            const impressions = parseInt(c.impressions, 10);
            const clicks = parseInt(c.clicks, 10);
            const ctr = (impressions > 0) ? ((clicks / impressions) * 100).toFixed(2) : '0.00';
            
            const row = `
                <tr>
                    <td><strong>${c.name || `(Campaign #${c.campaign_id})`}</strong></td>
                    <td>${impressions.toLocaleString()}</td>
                    <td>${clicks.toLocaleString()}</td>
                    <td>${ctr}%</td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    // Initial load
    fetchData();
});