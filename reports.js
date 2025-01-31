document.addEventListener("DOMContentLoaded", function () {
    // Sales Trend Chart
    const ctx1 = document.getElementById("salesChart").getContext("2d");
    new Chart(ctx1, {
        type: "line",
        data: {
            labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul"],
            datasets: [{
                label: "Sales",
                data: [120, 190, 300, 500, 200, 300, 450],
                borderColor: "rgb(75, 192, 192)",
                tension: 0.4
            }]
        },
        options: {
            responsive: true
        }
    });

    // Revenue by Product Category Chart
    const ctx2 = document.getElementById("revenueChart").getContext("2d");
    new Chart(ctx2, {
        type: "doughnut",
        data: {
            labels: ["Toys", "Cards", "Flowers", "Gift Boxes"],
            datasets: [{
                data: [5000, 3000, 4000, 2000],
                backgroundColor: ["#ff6384", "#36a2eb", "#ffcd56", "#4bc0c0"]
            }]
        }
    });
});
