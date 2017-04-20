(function($) {
	$(document).ready(function() {
		// Only show database templates when "create database" is selected
		var tmplField = $('#createDatabaseTemplate'),
			createField = $('#createDatabase input'),
			toggleFn = function() {tmplField.toggle(createField.is(':checked'));};

		toggleFn();
		createField.click(toggleFn);
	});
}(jQuery));