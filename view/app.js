const { createApp } = Vue;

createApp({
	data() {
		return {
			alerts: [],
			loading: false,
			error: null,
			searchTerm: '',
			filterStatus: '',
			filterEmergencyType: '',
			dateRange: 'all',
			sortColumn: 'dtCreatedDateTime',
			sortDirection: 'desc',
			currentPage: 1,
			perPage: 25,
			showModal: false,
			selectedAlert: null,

			columns: [
				{ key: 'sCadStatus', label: 'Status' },
				{ key: 'sEmergencyType', label: 'Type' },
				{ key: 'sStreetAddress', label: 'Location' },
				{ key: 'sCfsNumber', label: 'CFS #' },
				{ key: 'sContactFullName', label: 'Contact' },
				{ key: 'dtCreatedDateTime', label: 'Created' },
			],
		};
	},

	computed: {
		totalRecords() {
			return this.alerts.length;
		},

		emergencyTypes() {
			const types = [
				...new Set(
					this.alerts
						.map((alert) => alert.sEmergencyType)
						.filter((type) => type)
				),
			];
			return types.sort();
		},

		hasFilters() {
			return (
				this.searchTerm ||
				this.filterStatus ||
				this.filterEmergencyType ||
				this.dateRange !== 'all'
			);
		},

		filteredAlerts() {
			let filtered = [...this.alerts];

			// Search filter
			if (this.searchTerm) {
				const searchLower = this.searchTerm.toLowerCase();
				filtered = filtered.filter(
					(alert) =>
						(alert.sContactFullName &&
							alert.sContactFullName.toLowerCase().includes(searchLower)) ||
						(alert.sStreetAddress &&
							alert.sStreetAddress.toLowerCase().includes(searchLower)) ||
						(alert.sCity && alert.sCity.toLowerCase().includes(searchLower)) ||
						(alert.sDescription &&
							alert.sDescription.toLowerCase().includes(searchLower)) ||
						(alert.sCfsNumber &&
							alert.sCfsNumber.toLowerCase().includes(searchLower)) ||
						(alert.sEmergencyType &&
							alert.sEmergencyType.toLowerCase().includes(searchLower))
				);
			}

			// Status filter
			if (this.filterStatus) {
				filtered = filtered.filter(
					(alert) => alert.sCadStatus === this.filterStatus
				);
			}

			// Emergency type filter
			if (this.filterEmergencyType) {
				filtered = filtered.filter(
					(alert) => alert.sEmergencyType === this.filterEmergencyType
				);
			}

			// Date range filter
			if (this.dateRange !== 'all') {
				const now = new Date();
				const startDate = new Date();

				switch (this.dateRange) {
					case 'today':
						startDate.setHours(0, 0, 0, 0);
						break;
					case 'week':
						startDate.setDate(now.getDate() - 7);
						break;
					case 'month':
						startDate.setMonth(now.getMonth() - 1);
						break;
				}

				filtered = filtered.filter((alert) => {
					if (!alert.dtCreatedDateTime) return false;
					const alertDate = new Date(alert.dtCreatedDateTime);
					return alertDate >= startDate;
				});
			}

			// Sort
			filtered.sort((a, b) => {
				let aVal = a[this.sortColumn];
				let bVal = b[this.sortColumn];

				// Handle null/undefined values
				if (aVal === null || aVal === undefined) aVal = '';
				if (bVal === null || bVal === undefined) bVal = '';

				// Handle dates
				if (this.sortColumn.includes('DateTime')) {
					aVal = new Date(aVal || 0);
					bVal = new Date(bVal || 0);
				}

				// Sort logic
				if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
				if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
				return 0;
			});

			return filtered;
		},

		paginatedAlerts() {
			const start = (this.currentPage - 1) * this.perPage;
			const end = start + this.perPage;
			return this.filteredAlerts.slice(start, end);
		},

		totalPages() {
			return Math.ceil(this.filteredAlerts.length / this.perPage);
		},

		visiblePages() {
			const pages = [];
			const total = this.totalPages;
			const current = this.currentPage;

			if (total <= 7) {
				for (let i = 1; i <= total; i++) {
					pages.push(i);
				}
			} else {
				pages.push(1);

				if (current <= 4) {
					for (let i = 2; i <= 5; i++) {
						pages.push(i);
					}
					pages.push('...');
					pages.push(total);
				} else if (current >= total - 3) {
					pages.push('...');
					for (let i = total - 4; i <= total; i++) {
						pages.push(i);
					}
				} else {
					pages.push('...');
					for (let i = current - 1; i <= current + 1; i++) {
						pages.push(i);
					}
					pages.push('...');
					pages.push(total);
				}
			}

			return pages.filter(
				(page) =>
					page !== '...' || pages.indexOf(page) === pages.lastIndexOf(page)
			);
		},
	},

	watch: {
		// Reset to first page when filters change
		searchTerm() {
			this.currentPage = 1;
		},
		filterStatus() {
			this.currentPage = 1;
		},
		filterEmergencyType() {
			this.currentPage = 1;
		},
		dateRange() {
			this.currentPage = 1;
		},
	},

	methods: {
		async fetchAlerts() {
			this.loading = true;
			this.error = null;

			try {
				const response = await fetch('../api/getAlerts.php');

				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}

				const result = await response.json();

				if (result.error) {
					throw new Error(result.error);
				}

				this.alerts = result.data || [];
			} catch (error) {
				console.error('Error fetching alerts:', error);
				this.error = `Failed to load alerts: ${error.message}`;
			} finally {
				this.loading = false;
			}
		},

		refreshData() {
			this.fetchAlerts();
		},

		sortBy(column) {
			if (this.sortColumn === column) {
				this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
			} else {
				this.sortColumn = column;
				this.sortDirection = 'asc';
			}
		},

		clearFilters() {
			this.searchTerm = '';
			this.filterStatus = '';
			this.filterEmergencyType = '';
			this.dateRange = 'all';
			this.currentPage = 1;
		},

		// Pagination methods
		previousPage() {
			if (this.currentPage > 1) {
				this.currentPage--;
			}
		},

		nextPage() {
			if (this.currentPage < this.totalPages) {
				this.currentPage++;
			}
		},

		goToPage(page) {
			if (page !== '...' && page >= 1 && page <= this.totalPages) {
				this.currentPage = page;
			}
		},

		// Modal methods
		viewDetails(alert) {
			this.selectedAlert = alert;
			this.showModal = true;
		},

		closeModal() {
			this.showModal = false;
			this.selectedAlert = null;
		},

		viewMap(alert) {
			if (alert.iLatitude && alert.iLongitude) {
				const mapUrl = `https://www.openstreetmap.org/search?query=${alert.iLatitude}%2C${alert.iLongitude}#map=16/${alert.iLatitude}/${alert.iLongitude}`;
				window.open(mapUrl, '_blank');
			}
		},

		// Utility methods
		getStatusBadgeClass(status) {
			const classes =
				'px-2 inline-flex text-xs leading-5 font-semibold rounded-full ';
			switch (status) {
				case 'POSTED':
					return classes + 'bg-green-100 text-green-800';
				case 'FAILED':
					return classes + 'bg-red-100 text-red-800';
				case 'PENDING':
				default:
					return classes + 'bg-yellow-100 text-yellow-800';
			}
		},

		formatAddress(alert) {
			const parts = [];

			if (alert.sStreetAddress) {
				parts.push(alert.sStreetAddress);
			}

			if (alert.sCity) {
				parts.push(alert.sCity);
			}

			if (alert.sState) {
				parts.push(alert.sState);
			}

			return parts.join(', ') || 'No address';
		},

		formatPhoneNumber(phone) {
			if (!phone) return null;

			// Remove all non-digits
			const cleaned = phone.replace(/\D/g, '');

			// Format as (XXX) XXX-XXXX
			if (cleaned.length === 10) {
				return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(
					6
				)}`;
			}

			return phone; // Return original if not 10 digits
		},

		formatDate(dateString) {
			if (!dateString) return 'N/A';

			try {
				// Database timestamps are stored as UTC, but JavaScript Date constructor
				// interprets them as local time. We need to explicitly treat them as UTC.
				let date;

				// If the dateString doesn't have timezone info, append 'Z' to treat as UTC
				if (dateString.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/)) {
					// Format: "2025-10-01 16:05:39" - treat as UTC
					date = new Date(dateString.replace(' ', 'T') + 'Z');
				} else {
					// Already has timezone info or different format
					date = new Date(dateString);
				}

				// Convert to Eastern Time (handles both EST and EDT automatically)
				const formatted = date.toLocaleString('en-US', {
					year: 'numeric',
					month: 'short',
					day: 'numeric',
					hour: '2-digit',
					minute: '2-digit',
					second: '2-digit',
					hour12: true,
					timeZone: 'America/New_York', // Eastern Time Zone
				});

				// Add timezone indicator
				const isDST = this.isDaylightSaving(date);
				const timezone = isDST ? 'EDT' : 'EST';
				return `${formatted} ${timezone}`;
			} catch (error) {
				console.warn('Date formatting error:', error, 'for date:', dateString);
				return dateString;
			}
		},

		// Helper method to determine if daylight saving time is in effect
		isDaylightSaving(date) {
			const year = date.getFullYear();

			// DST in the US typically runs from 2nd Sunday in March to 1st Sunday in November
			// This is a simplified check - for production you might want a more robust solution
			const march = new Date(year, 2, 1); // March 1st
			const november = new Date(year, 10, 1); // November 1st

			// Get the second Sunday in March
			const dstStart = new Date(year, 2, 14 - march.getDay());

			// Get the first Sunday in November
			const dstEnd = new Date(year, 10, 7 - november.getDay());

			return date >= dstStart && date < dstEnd;
		},

		// Add a method to show relative time (e.g., "2 minutes ago")
		formatRelativeTime(dateString) {
			if (!dateString) return 'N/A';

			try {
				// Use the same UTC parsing logic as formatDate
				let date;
				if (dateString.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d+)?$/)) {
					// Format: "2025-10-01 16:05:39" - treat as UTC
					date = new Date(dateString.replace(' ', 'T') + 'Z');
				} else {
					// Already has timezone info or different format
					date = new Date(dateString);
				}

				const now = new Date();
				const diffMs = now - date;
				const diffMinutes = Math.floor(diffMs / (1000 * 60));
				const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
				const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

				if (diffMinutes < 1) {
					return 'Just now';
				} else if (diffMinutes < 60) {
					return `${diffMinutes} min${diffMinutes !== 1 ? 's' : ''} ago`;
				} else if (diffHours < 24) {
					return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
				} else if (diffDays < 7) {
					return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
				} else {
					// For older entries, show the formatted date
					return this.formatDate(dateString);
				}
			} catch (error) {
				return this.formatDate(dateString);
			}
		},

		// Check if an alert is recent (within last 10 minutes)
		isRecentAlert(dateString) {
			if (!dateString) return false;

			try {
				// Use the same UTC parsing logic as formatDate
				let date;
				if (dateString.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/)) {
					// Format: "2025-10-01 16:05:39" - treat as UTC
					date = new Date(dateString.replace(' ', 'T') + 'Z');
				} else {
					// Already has timezone info or different format
					date = new Date(dateString);
				}

				const now = new Date();
				const diffMs = now - date;
				const diffMinutes = Math.floor(diffMs / (1000 * 60));
				return diffMinutes <= 10;
			} catch (error) {
				return false;
			}
		},
	},

	// Keyboard shortcuts
	mounted() {
		this.fetchAlerts();

		// Add keyboard shortcuts
		document.addEventListener('keydown', (e) => {
			// ESC to close modal
			if (e.key === 'Escape' && this.showModal) {
				this.closeModal();
			}

			// Ctrl/Cmd + R to refresh
			if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
				e.preventDefault();
				this.refreshData();
			}
		});

		// Auto-refresh every 30 seconds
		setInterval(() => {
			if (!this.showModal) {
				this.fetchAlerts();
			}
		}, 30000);
	},
}).mount('#app');
