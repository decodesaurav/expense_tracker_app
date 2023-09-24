// public/js/chart.js

document.addEventListener("DOMContentLoaded", function () {
    // Fetch data for the pie chart and timeline chart
    fetch('/get-expense-data') // Replace with the actual URL
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function (data) {
            var pieChartLabels = data.pieChart.map(item => item.category);
            var pieChartData = data.pieChart.map(item => item.total_amount);

            var timelineChartLabels = data.timeline.labels;
            var timelineChartData = data.timeline.data;

            var pieChartCtx = document.getElementById('expensePieChart').getContext('2d');
            var timelineChartCtx = document.getElementById('expenseTimelineChart').getContext('2d');

            // Create the pie chart
            new Chart(pieChartCtx, {
                type: 'pie',
                data: {
                    labels: pieChartLabels,
                    datasets: [{
                        data: pieChartData,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)'
                        ]
                    }]
                }
            });

            // Create the timeline chart
            new Chart(timelineChartCtx, {
                type: 'line',
                data: {
                    labels: timelineChartLabels,
                    datasets: [{
                        label: 'Expenses Over Time',
                        data: timelineChartData,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        fill: true
                    }]
                }
            });
        })
        .catch(function (error) {
            console.error('Error fetching data:', error);
        });
});
