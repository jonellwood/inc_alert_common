<?php require_once __DIR__ . '/../lib/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Alerts Dashboard</title>

    <!-- Vue 3 CDN - Production Build -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>

    <!-- Tailwind CSS CDN - For development only -->
    <!-- Note: For production, consider installing Tailwind as PostCSS plugin -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        [v-cloak] {
            display: none;
        }

        .fade-enter-active,
        .fade-leave-active {
            transition: opacity 0.3s ease;
        }

        .fade-enter-from,
        .fade-leave-to {
            opacity: 0;
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Improved tooltip styling */
        .cursor-help {
            cursor: help;
        }

        .cursor-help:hover {
            text-decoration: underline;
            text-decoration-style: dotted;
        }

        /* Real-time indicator for recent alerts - in status badge */
        .status-recent {
            position: relative;
        }

        .status-recent::after {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border: 2px solid white;
            border-radius: 50%;
            animation: pulse-green 2s infinite;
        }

        @keyframes pulse-green {

            0%,
            100% {
                opacity: 1;
                transform: translateY(-50%) scale(1);
            }

            50% {
                opacity: 0.5;
                transform: translateY(-50%) scale(1.2);
            }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
    <div id="app" v-cloak>
        <!-- User Bar -->
        <div class="bg-gray-900 text-gray-400 text-xs py-2 px-6">
            <div class="container mx-auto flex justify-between items-center">
                <a href="../" class="hover:text-gray-200 transition-colors"><i class="fas fa-arrow-left mr-1"></i> Back to Hub</a>
                <div>
                    <span><i class="fas fa-user-circle mr-1"></i> <?php echo htmlspecialchars(RedfiveAuth::getDisplayName()); ?></span>
                    <a href="../auth/logout.php" class="ml-4 text-red-400 hover:text-red-300 transition-colors">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Header -->
        <header class="bg-blue-600 text-white shadow-lg">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-exclamation-triangle text-2xl"></i>
                        <h1 class="text-2xl font-bold">Emergency Alerts Dashboard</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm">Total Alerts: {{ totalRecords }}</span>
                        <button @click="refreshData"
                            class="bg-blue-500 hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-sync-alt" :class="{ 'animate-spin': loading }"></i>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Filters & Search -->
        <div class="container mx-auto px-6 py-6">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <div class="relative">
                            <input v-model="searchTerm" type="text" placeholder="Search alerts..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CAD Status</label>
                        <select v-model="filterStatus"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="POSTED">Posted</option>
                            <option value="FAILED">Failed</option>
                            <option value="PENDING">Pending</option>
                        </select>
                    </div>

                    <!-- Emergency Type Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Type</label>
                        <select v-model="filterEmergencyType"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Types</option>
                            <option v-for="type in emergencyTypes" :key="type" :value="type">{{ type }}</option>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                        <select v-model="dateRange"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                </div>

                <!-- Results Summary -->
                <div class="text-sm text-gray-600">
                    Showing {{ filteredAlerts.length }} of {{ totalRecords }} alerts
                    <span v-if="hasFilters" class="text-blue-600">
                        (filtered) - <button @click="clearFilters" class="text-blue-500 hover:underline">Clear
                            filters</button>
                    </span>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="text-center py-8">
                <div class="loading-spinner mx-auto mb-4"></div>
                <p class="text-gray-600">Loading alerts...</p>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span>{{ error }}</span>
                </div>
            </div>

            <!-- Alerts Table -->
            <div v-else class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th v-for="column in columns" :key="column.key" @click="sortBy(column.key)"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center space-x-1">
                                        <span>{{ column.label }}</span>
                                        <i v-if="sortColumn === column.key"
                                            :class="sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"
                                            class="text-blue-500"></i>
                                        <i v-else class="fas fa-sort opacity-30"></i>
                                    </div>
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="alert in paginatedAlerts" :key="alert.id"
                                class="hover:bg-gray-50 transition-colors">

                                <!-- Status Badge -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="[getStatusBadgeClass(alert.sCadStatus), { 'status-recent': isRecentAlert(alert.dtCreatedDateTime) }]"
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                        {{ alert.sCadStatus || 'PENDING' }}
                                    </span>
                                </td>

                                <!-- Emergency Type -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ alert.sEmergencyType || 'Unknown' }}
                                </td>

                                <!-- Location -->
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs truncate">
                                        {{ formatAddress(alert) }}
                                    </div>
                                </td>

                                <!-- CFS Number -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ alert.sCfsNumber || 'N/A' }}
                                </td>

                                <!-- Contact -->
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div>{{ alert.sContactFullName || 'Unknown' }}</div>
                                    <div class="text-gray-500">{{ formatPhoneNumber(alert.sContactPhone) }}</div>
                                </td>

                                <!-- Created Date -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="font-medium text-gray-900">
                                        {{ formatRelativeTime(alert.dtCreatedDateTime) }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ formatDate(alert.dtCreatedDateTime) }}
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button @click="viewDetails(alert)" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button v-if="alert.iLatitude && alert.iLongitude" @click="viewMap(alert)"
                                        class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <button @click="previousPage" :disabled="currentPage === 1"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                            Previous
                        </button>
                        <button @click="nextPage" :disabled="currentPage >= totalPages"
                            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                            Next
                        </button>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing {{ ((currentPage - 1) * perPage) + 1 }} to {{ Math.min(currentPage * perPage,
                                filteredAlerts.length) }} of {{ filteredAlerts.length }} results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <button @click="previousPage" :disabled="currentPage === 1"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button v-for="page in visiblePages" :key="page" @click="goToPage(page)"
                                    :class="currentPage === page ? 'bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'"
                                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                    {{ page }}
                                </button>
                                <button @click="nextPage" :disabled="currentPage >= totalPages"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
            @click="closeModal">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white"
                @click.stop>
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Alert Details</h3>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div v-if="selectedAlert" class="space-y-4">
                        <!-- Basic Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                <span :class="getStatusBadgeClass(selectedAlert.sCadStatus)"
                                    class="mt-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                    {{ selectedAlert.sCadStatus || 'PENDING' }}
                                </span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">CFS Number</label>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedAlert.sCfsNumber || 'Not assigned' }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Emergency Type</label>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedAlert.sEmergencyType || 'Unknown' }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Service Provider</label>
                                <p class="mt-1 text-sm text-gray-900">{{ selectedAlert.sServiceProviderName || 'Unknown'
                                    }}</p>
                            </div>
                        </div>

                        <!-- Contact Info -->
                        <div class="border-t pt-4">
                            <h4 class="text-md font-medium text-gray-900 mb-2">Contact Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ selectedAlert.sContactFullName || 'Unknown'
                                        }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                                    <p class="mt-1 text-sm text-gray-900">{{
                                        formatPhoneNumber(selectedAlert.sContactPhone) || 'Not provided' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Location Info -->
                        <div class="border-t pt-4">
                            <h4 class="text-md font-medium text-gray-900 mb-2">Location Information</h4>
                            <div class="space-y-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Address</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ formatAddress(selectedAlert) }}</p>
                                </div>
                                <div v-if="selectedAlert.iLatitude && selectedAlert.iLongitude"
                                    class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Coordinates</label>
                                        <p class="mt-1 text-sm text-gray-900">{{ selectedAlert.iLatitude }}, {{
                                            selectedAlert.iLongitude }}</p>
                                    </div>
                                    <div class="flex items-end">
                                        <button @click="viewMap(selectedAlert)"
                                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                            View on Map
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="border-t pt-4">
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <p class="mt-1 text-sm text-gray-900">{{ selectedAlert.sDescription }}</p>
                        </div>

                        <!-- Timestamps -->
                        <div class="border-t pt-4">
                            <h4 class="text-md font-medium text-gray-900 mb-2">Timestamps</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Created</label>
                                    <p class="mt-1 text-gray-900">{{ formatDate(selectedAlert.dtCreatedDateTime) }}</p>
                                    <p class="mt-1 text-sm text-gray-500">{{
                                        formatRelativeTime(selectedAlert.dtCreatedDateTime) }}</p>
                                </div>
                                <div v-if="selectedAlert.dtCadPostedDateTime">
                                    <label class="block text-sm font-medium text-gray-700">Posted to CAD</label>
                                    <p class="mt-1 text-gray-900">{{ formatDate(selectedAlert.dtCadPostedDateTime) }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-500">{{
                                        formatRelativeTime(selectedAlert.dtCadPostedDateTime) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="app.js"></script>
</body>

</html>