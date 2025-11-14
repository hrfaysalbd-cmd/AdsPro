jQuery(document).ready(function($) {
    'use strict';

    const $dashboard = $('#adcp-analytics-dashboard');
    if (!$dashboard.length) {
        return; // Not on the analytics page
    }

    const $loader = $('#adcp-loader');
    const $content = $('#adcp-analytics-content');
    const $filterButtons = $('.adcp-filters .button');
    const ctx = document.getElementById('adcp-main-chart');
    let mainChart;

    /**
     * Fetch data from our analytics endpoint.
     */
    async function fetchData(days = 30) {
        $loader.show();
        $content.hide();
        $filterButtons.removeClass('button-primary');
        $(`.adcp-filters .button[data-days="${days}"]`).addClass('button-primary');

        try {
            const response = await fetch(`${adcpAnalytics.rest_url}?days=${days}`, {
                headers: {
                    'X-WP-Nonce': adcpAnalytics.nonce
                }
            });
            
            if (!response.ok) {
                throw new Error('Analytics API request failed.');
            }
            
            const data = await response.json();
            renderDashboard(data);

        } catch (error) {
            console.error('Failed to load analytics:', error);
            $loader.hide();
            $content.html('<p>Error loading analytics data.</p>').show();
        }
    }

    /**
     * Render all components of the dashboard.
     */
    function renderDashboard(data) {
        renderGlobalStats(data.global_stats);
        renderTimeSeriesChart(data.timeseries);
        renderTopCampaigns(data.top_campaigns);
        
        $loader.hide();
        $content.show();
    }

    /**
     * Populate the 4 global stat boxes.
     */
    function renderGlobalStats(stats) {
        $('#stat-impressions').text(stats.impressions.toLocaleString());
        $('#stat-clicks').text(stats.clicks.toLocaleString());
        $('#stat-uniques').text(stats.uniques.toLocaleString());
        $('#stat-ctr').text(stats.ctr + '%');
    }

    /**
     * Render the main time-series line chart.
     */
    function renderTimeSeriesChart(timeseries) {
        const labels = timeseries.map(item => item.event_date);
        const impressions = timeseries.map(item => item.impressions);
        const clicks = timeseries.map(item => item.clicks);

        if (mainChart) {
            mainChart.destroy();
        }

        mainChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Impressions',
                        data: impressions,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        fill: true,
                        tension: 0.1
                    },
                    {
                        label: 'Clicks',
                        data: clicks,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        fill: true,
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Campaign Performance'
                    }
                }
            }
        });
    }

    /**
     * Populate the top campaigns table.
     */
    function renderTopCampaigns(campaigns) {
        const $tbody = $('#adcp-top-campaigns-body');
        $tbody.empty();

        if (campaigns.length === 0) {
            $tbody.html('<tr><td colspan="4">No campaign data for this period.</td></tr>');
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

    /**
     * Bind click events to filter buttons.
     */
    $filterButtons.on('click', function(e) {
        e.preventDefault();
        const days = $(this).data('days');
        fetchData(days);
    });

    // Initial load
    fetchData(30);
});