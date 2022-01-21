docReady(function () {

	/**
	 * Hide body scrollbar menu opens
	 */
	var menuToggle = document.querySelector('#main-nav');
	menuToggle.addEventListener('shown.bs.collapse', function () {
		document.body.classList.add('overflow-hidden');
	})
	menuToggle.addEventListener('hide.bs.collapse', function () {
		document.body.classList.remove('overflow-hidden');
	})

	/**
	 * Resize function:
	 * - sets the top of the mobile menu so it sits just below the header
	 * - sets the main margin to be under the header
	 *
	 */
	function onResize() {
		var headerHeight = document.querySelector('header').offsetHeight;

		document.querySelector('#main-nav').style.top = headerHeight + 'px';
		// if using a sticky header, you can turn on the items below.
		// document.querySelector('main').style.marginTop = headerHeight + 'px';
		// create a anchor offset var to use in css
		// document.body.style.setProperty('--anchor-offset', headerHeight + 'px');
	};
	window.addEventListener("resize", onResize);
	window.addEventListener('load', onResize);

});


