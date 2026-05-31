	</div> <!-- End main-content -->
	
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script src="/hms/public/assets/js/app.js"></script>
	<script>
		// Auto-scroll sidebar to active menu item
		document.addEventListener('DOMContentLoaded', function() {
			const sidebar = document.querySelector('.sidebar');
			if (!sidebar) return;
			
			// Find active link (could be in main menu or submenu)
			const activeLink = sidebar.querySelector('.nav-link.active');
			
			if (activeLink) {
				// Scroll to active link with smooth behavior, centered in view
				setTimeout(function() {
					activeLink.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
				}, 200);
			}
			
			// Auto-scroll when submenu expands (using Bootstrap collapse events)
			const collapseTriggers = sidebar.querySelectorAll('[data-bs-toggle="collapse"]');
			collapseTriggers.forEach(function(trigger) {
				const targetId = trigger.getAttribute('data-bs-target');
				if (targetId) {
					const submenu = document.querySelector(targetId);
					if (submenu) {
						// Listen for when collapse is shown
						submenu.addEventListener('shown.bs.collapse', function() {
							const activeSubLink = submenu.querySelector('.nav-link.active');
							if (activeSubLink) {
								setTimeout(function() {
									activeSubLink.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
								}, 100);
							}
						});
						
						// Also check on click if submenu is already expanded
						trigger.addEventListener('click', function() {
							setTimeout(function() {
								if (submenu.classList.contains('show')) {
									const activeSubLink = submenu.querySelector('.nav-link.active');
									if (activeSubLink) {
										activeSubLink.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
									}
								}
							}, 300);
						});
					}
				}
			});
		});
	</script>
</body>
</html>

