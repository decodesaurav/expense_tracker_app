document.addEventListener("DOMContentLoaded", function () {
	const timeDropdown = document.getElementById("timeDropdown");
	const totalSpendings = document.getElementById("totalSpendings");
	const totalSpendingsByCategory = document.getElementById("totalSpendingsByCategory");
	const categoryDropdown = document.getElementById("categoryExpenseFilter");

	timeDropdown.addEventListener("change", function () {
		const selectedTime = timeDropdown.value;

		// Make an AJAX request to your server
		fetch(`/dashboard/${selectedTime}`)
			.then((response) => response.json())
			.then((data) => {
				totalSpendings.innerHTML = `Nrs.${data.filteredExpense}`;
			})
			.catch((error) => {
				console.error("Error:", error);
			});
	});

	categoryDropdown.addEventListener("change", function() {
		if( categoryDropdown.value != "select-one" ) {
			const selectedCategory = categoryDropdown.value;
			fetch(`/dashboard/category/${selectedCategory}`)
				.then((response) => response.json())
				.then((data) => {
					console.log(data);
					totalSpendingsByCategory.innerHTML = `Nrs.${data.filterByCategory}`;
				})
				.catch((error) => {
					console.error("Error:", error);
			});
		} else {
			totalSpendingsByCategory.innerHTML = `Nrs. 0`;
		}
	});
});