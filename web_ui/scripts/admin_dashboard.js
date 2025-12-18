// Global variables to store chart instances
let trendsChart = null;
let violationTypeChart = null;
let dailyChart = null;
let currentSelectedYear = new Date().getFullYear();
let currentSelectedMonth = new Date().getMonth() + 1; // 1-12

document.querySelector('.hamburger-menu').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('active');
});

// Close sidebar when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const hamburger = document.querySelector('.hamburger-menu');
    
    if (!sidebar.contains(event.target) && !hamburger.contains(event.target) && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
    }
});

// Make menu items clickable
document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', function() {
        document.querySelectorAll('.menu-item').forEach(el => {
            el.classList.remove('active');
        });
        this.classList.add('active');
    });
});

// Add event listeners for Export Report buttons
document.querySelector('.export-btn')?.addEventListener('click', function() {
    alert('Export functionality will be implemented here');
});

// Function to initialize the print button
function initializePrintButton() {
    const printBtn = document.querySelector('.print-btn');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            // Prepare the page for printing
            prepareForPrint();
            // Trigger browser print dialog
            window.print();
            // Restore original page state after print dialog closes
            setTimeout(restoreAfterPrint, 1000);
        });
    }
}

// Function to prepare the page for printing
function prepareForPrint() {
    // Store the current state to restore later
    document.body.setAttribute('data-original-class', document.body.className);
    
    // Add a print-mode class to the body
    document.body.classList.add('print-mode');
    
    // Hide elements not needed for printing
    document.querySelectorAll('.no-print').forEach(el => {
        el.setAttribute('data-display', el.style.display);
        el.style.display = 'none';
    });
}

// Function to restore the page after printing
function restoreAfterPrint() {
    // Restore original body class
    const originalClass = document.body.getAttribute('data-original-class');
    document.body.className = originalClass || '';
    document.body.removeAttribute('data-original-class');
    document.body.classList.remove('print-mode');
    
    // Restore hidden elements
    document.querySelectorAll('.no-print').forEach(el => {
        el.style.display = el.getAttribute('data-display') || '';
        el.removeAttribute('data-display');
    });
}

// Function to initialize the violation type chart
function initializeViolationTypeChart() {
    const violationChartEl = document.getElementById('violations-by-type-chart');
    
    if (!violationChartEl) return;
    
    try {
        const canvas = document.getElementById('violation-type-chart');
        if (canvas) {
            // Get violation data from PHP data attributes
            const violationData = {
                dangerousDriving: parseInt(violationChartEl.dataset.dangerous || 0),
                vehicleCondition: parseInt(violationChartEl.dataset.vehicle || 0),
                parking: parseInt(violationChartEl.dataset.parking || 0),
                licensing: parseInt(violationChartEl.dataset.licensing || 0),
                safety: parseInt(violationChartEl.dataset.safety || 0)
            };
            
            console.log('Violation data:', violationData); // Debug info
            
            // Create the violations chart
            violationTypeChart = new Chart(
                canvas,
                {
                    type: 'bar',
                    data: {
                        labels: ['Dangerous Driving', 'Vehicle Condition', 'Parking', 'Licensing', 'Safety'],
                        datasets: [{
                            label: 'Number of Violations',
                            data: [
                                violationData.dangerousDriving,
                                violationData.vehicleCondition,
                                violationData.parking,
                                violationData.licensing,
                                violationData.safety
                            ],
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 206, 86, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(153, 102, 255, 0.7)',
                            ],
                            borderColor: [
                                'rgb(255, 99, 132)',
                                'rgb(54, 162, 235)',
                                'rgb(255, 206, 86)',
                                'rgb(75, 192, 192)',
                                'rgb(153, 102, 255)',
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.raw + ' violations';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        },
                        animation: {
                            duration: 1500,
                            easing: 'easeOutQuart'
                        }
                    }
                }
            );
            console.log('Violation chart initialized successfully');
        } else {
            console.log('Violation chart canvas not found');
        }
    } catch (e) {
        console.error('Error initializing violation chart:', e);
    }
}

// Function to update the monthly trends chart based on selected year
function updateMonthlyTrends(selectedYear) {
    const trendChartEl = document.getElementById('monthly-trends-chart');
    
    if (!trendChartEl) return;
    
    try {
        // Parse all years data from the data attribute
        const allYearsData = JSON.parse(trendChartEl.dataset.years || '{}');
        
        // Get data for the selected year or default to zeros
        const yearData = allYearsData[selectedYear] || Array(12).fill(0);
        
        console.log('Monthly data for year', selectedYear + ':', yearData); // Debug info
        
        // If chart already exists, update its data
        if (trendsChart) {
            trendsChart.data.datasets[0].data = yearData;
            trendsChart.update();
        } else {
            // Create a new chart if it doesn't exist
            const canvas = document.getElementById('trends-chart');
            if (canvas) {
                trendsChart = new Chart(
                    canvas,
                    {
                        type: 'line',
                        data: {
                            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                            datasets: [{
                                label: 'Violations',
                                data: yearData,
                                borderColor: 'rgb(78, 84, 232)',
                                backgroundColor: 'rgba(78, 84, 232, 0.1)',
                                tension: 0.3,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.raw + ' violations';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            },
                            animation: {
                                duration: 1500,
                                easing: 'easeOutQuart'
                            }
                        }
                    }
                );
                console.log('Trends chart initialized successfully');
            } else {
                console.log('Trends chart canvas not found');
            }
        }
    } catch (e) {
        console.error('Error updating monthly trends chart:', e);
    }
}

// Function to update the daily activity chart based on selected month and year
function updateDailyActivityChart(selectedYear, selectedMonth) {
    const dailyChartEl = document.getElementById('daily-activity-chart');
    
    if (!dailyChartEl) return;
    
    try {
        // Parse all daily data from the data attribute
        const allDailyData = JSON.parse(dailyChartEl.dataset.dailyViolations || '{}');
        
        // Get data for the selected year and month or default to zeros
        const yearData = allDailyData[selectedYear] || {};
        const monthData = yearData[selectedMonth] || [];
        
        // Generate day labels based on the month length
        const daysInMonth = new Date(selectedYear, selectedMonth, 0).getDate();
        const dayLabels = Array.from({length: daysInMonth}, (_, i) => (i + 1).toString());
        
        // Ensure data array matches the number of days in the month
        const normalizedData = monthData.length === daysInMonth ? 
            monthData : Array(daysInMonth).fill(0).map((_, i) => monthData[i] || 0);
        
        console.log(`Daily data for ${selectedYear}-${selectedMonth}:`, normalizedData);
        
        // If chart already exists, update its data
        if (dailyChart) {
            dailyChart.data.labels = dayLabels;
            dailyChart.data.datasets[0].data = normalizedData;
            dailyChart.update();
        } else {
            // Create a new chart if it doesn't exist
            const canvas = document.getElementById('daily-chart');
            if (canvas) {
                dailyChart = new Chart(
                    canvas,
                    {
                        type: 'bar',
                        data: {
                            labels: dayLabels,
                            datasets: [{
                                label: 'Daily Violations',
                                data: normalizedData,
                                backgroundColor: 'rgba(255, 159, 64, 0.7)',
                                borderColor: 'rgb(255, 159, 64)',
                                borderWidth: 1,
                                barPercentage: 0.95,
                                categoryPercentage: 0.95
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        title: function(tooltipItems) {
                                            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                            const day = tooltipItems[0].label;
                                            return `${day} ${monthNames[selectedMonth-1]}, ${selectedYear}`;
                                        },
                                        label: function(context) {
                                            return context.raw + (context.raw === 1 ? ' violation' : ' violations');
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        maxRotation: 0,
                                        autoSkip: true,
                                        maxTicksLimit: 15
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            },
                            animation: {
                                duration: 1000,
                                easing: 'easeOutQuart'
                            }
                        }
                    }
                );
                console.log('Daily activity chart initialized successfully');
            } else {
                console.log('Daily chart canvas not found');
            }
        }
    } catch (e) {
        console.error('Error updating daily activity chart:', e);
    }
}

// Main initialization function
function initializeCharts() {
    console.log('Initializing charts...');
    
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded properly');
        return;
    }
    
    // Initialize the violation type chart
    initializeViolationTypeChart();
    
    // Initialize the trends chart with year filter
    const yearFilter = document.getElementById('year-filter');
    if (yearFilter) {
        console.log('Year filter found with value:', yearFilter.value);
        // Store the current selected year globally
        currentSelectedYear = parseInt(yearFilter.value);
        
        // Set up initial chart with selected year
        updateMonthlyTrends(currentSelectedYear);
        
        // Add event listener for year selection changes
        yearFilter.addEventListener('change', function() {
            console.log('Year changed to:', this.value);
            currentSelectedYear = parseInt(this.value);
            updateMonthlyTrends(currentSelectedYear);
            
            // Also update the daily chart when the year changes
            updateDailyActivityChart(currentSelectedYear, currentSelectedMonth);
        });
    } else {
        console.log('Year filter not found, using default year');
        // Try to get the trends chart element to extract available years
        const trendChartEl = document.getElementById('monthly-trends-chart');
        if (trendChartEl && trendChartEl.dataset.availableYears) {
            try {
                const availableYears = JSON.parse(trendChartEl.dataset.availableYears || '[]');
                if (availableYears.length > 0) {
                    currentSelectedYear = parseInt(availableYears[0]);
                    updateMonthlyTrends(currentSelectedYear);
                } else {
                    currentSelectedYear = new Date().getFullYear();
                    updateMonthlyTrends(currentSelectedYear);
                }
            } catch (e) {
                console.error('Error parsing available years:', e);
                currentSelectedYear = new Date().getFullYear();
                updateMonthlyTrends(currentSelectedYear);
            }
        }
    }
    
    // Initialize the daily activity chart with month filter
    const monthFilter = document.getElementById('month-filter');
    if (monthFilter) {
        // Set default month to current month
        monthFilter.value = currentSelectedMonth;
        
        // Set up initial chart with selected month and year
        updateDailyActivityChart(currentSelectedYear, currentSelectedMonth);
        
        // Add event listener for month selection changes
        monthFilter.addEventListener('change', function() {
            currentSelectedMonth = parseInt(this.value);
            console.log('Month changed to:', currentSelectedMonth);
            updateDailyActivityChart(currentSelectedYear, currentSelectedMonth);
        });
    } else {
        // If no month filter is found, still try to initialize the chart
        updateDailyActivityChart(currentSelectedYear, currentSelectedMonth);
    }
}

// Call the initialization function when the page loads
window.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        initializeCharts();
        initializePrintButton(); // Initialize the print button
    }, 300);
});