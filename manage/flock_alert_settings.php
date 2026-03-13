<?php require_once __DIR__ . '/../lib/auth_check_admin.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALPR Alert Settings - RedFive Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .toggle-checkbox:checked {
            right: 0;
            border-color: #22c55e;
        }

        .toggle-checkbox:checked+.toggle-label {
            background-color: #22c55e;
        }

        .toggle-checkbox {
            right: 1.25rem;
            transition: all 0.3s;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .toast {
            animation: slideIn 0.3s ease-out, fadeOut 0.3s ease-in 2.7s;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }

            to {
                opacity: 0;
            }
        }
    </style>
</head>

<body class="bg-gray-900 text-gray-100 min-h-screen">
    <!-- Header -->
    <nav class="bg-gray-800 border-b border-gray-700 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="../index.php" class="text-gray-400 hover:text-white mr-4">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <i class="fas fa-camera text-blue-400 text-xl mr-3"></i>
                    <h1 class="text-xl font-bold text-white">Flock ALPR Alert Settings</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span id="lastSaved" class="text-sm text-gray-400"></span>
                    <button onclick="saveSettings()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-save mr-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-4 py-8">
        <!-- Loading State -->
        <div id="loadingState" class="text-center py-20">
            <i class="fas fa-spinner fa-spin text-4xl text-blue-400 mb-4"></i>
            <p class="text-gray-400">Loading alert settings...</p>
        </div>

        <!-- Error State -->
        <div id="errorState" class="hidden text-center py-20">
            <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
            <p class="text-red-300" id="errorMessage">Failed to load settings</p>
            <button onclick="loadSettings()" class="mt-4 bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-redo mr-1"></i> Retry
            </button>
        </div>

        <!-- Main Content (hidden until loaded) -->
        <div id="mainContent" class="hidden space-y-8">

            <!-- Global Settings Card -->
            <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-white">
                            <i class="fas fa-cogs text-gray-400 mr-2"></i>Global Settings
                        </h2>
                        <p class="text-sm text-gray-400 mt-1">Controls that apply to all Flock ALPR alerts</p>
                    </div>
                </div>
                <div class="p-6 space-y-5">
                    <!-- Send All Override -->
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-sm font-medium text-gray-200">Send ALL alerts to CAD</label>
                            <p class="text-xs text-gray-400 mt-0.5">Override: ignores per-type settings and sends everything</p>
                        </div>
                        <div class="relative inline-block w-11 align-middle select-none">
                            <input type="checkbox" id="sendAllToCad"
                                class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-2 border-gray-500 appearance-none cursor-pointer top-0.5"
                                onchange="markDirty()">
                            <label for="sendAllToCad"
                                class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-600 cursor-pointer"></label>
                        </div>
                    </div>

                    <!-- Log Filtered -->
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-sm font-medium text-gray-200">Log filtered (skipped) alerts</label>
                            <p class="text-xs text-gray-400 mt-0.5">Still log alerts to the debug log even when not sent to CAD</p>
                        </div>
                        <div class="relative inline-block w-11 align-middle select-none">
                            <input type="checkbox" id="logFilteredAlerts"
                                class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-2 border-gray-500 appearance-none cursor-pointer top-0.5"
                                onchange="markDirty()">
                            <label for="logFilteredAlerts"
                                class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-600 cursor-pointer"></label>
                        </div>
                    </div>

                    <!-- Min Plate Confidence -->
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-sm font-medium text-gray-200">Minimum plate confidence</label>
                            <p class="text-xs text-gray-400 mt-0.5">Skip alerts below this OCR confidence % (0 = no minimum)</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input type="number" id="minPlateConfidence" min="0" max="100" step="5"
                                class="w-20 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-sm text-white text-center focus:outline-none focus:ring-2 focus:ring-blue-500"
                                onchange="markDirty()">
                            <span class="text-gray-400 text-sm">%</span>
                        </div>
                    </div>

                    <!-- Default for New Types -->
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-sm font-medium text-gray-200">Auto-enable new alert types</label>
                            <p class="text-xs text-gray-400 mt-0.5">When an unknown alert type arrives, automatically enable it</p>
                        </div>
                        <div class="relative inline-block w-11 align-middle select-none">
                            <input type="checkbox" id="defaultNewTypeEnabled"
                                class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-2 border-gray-500 appearance-none cursor-pointer top-0.5"
                                onchange="markDirty()">
                            <label for="defaultNewTypeEnabled"
                                class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-600 cursor-pointer"></label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Types Card -->
            <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-white">
                            <i class="fas fa-list-check text-blue-400 mr-2"></i>Alert Types
                        </h2>
                        <p class="text-sm text-gray-400 mt-1">Choose which alert types create CAD call records</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="enableAll()" class="text-xs bg-green-700 hover:bg-green-600 text-white px-3 py-1.5 rounded-lg transition-colors">
                            <i class="fas fa-check-double mr-1"></i>Enable All
                        </button>
                        <button onclick="disableAll()" class="text-xs bg-red-700 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg transition-colors">
                            <i class="fas fa-times mr-1"></i>Disable All
                        </button>
                    </div>
                </div>
                <div id="alertTypesList" class="divide-y divide-gray-700">
                    <!-- Alert type rows will be rendered here by JS -->
                </div>
            </div>

            <!-- Stats / Info Card -->
            <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-700">
                    <h2 class="text-lg font-semibold text-white">
                        <i class="fas fa-info-circle text-gray-400 mr-2"></i>Info
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-700/50 rounded-lg p-4 text-center">
                            <div id="statTotal" class="text-2xl font-bold text-white">-</div>
                            <div class="text-xs text-gray-400 mt-1">Total Alert Types</div>
                        </div>
                        <div class="bg-gray-700/50 rounded-lg p-4 text-center">
                            <div id="statEnabled" class="text-2xl font-bold text-green-400">-</div>
                            <div class="text-xs text-gray-400 mt-1">Sending to CAD</div>
                        </div>
                        <div class="bg-gray-700/50 rounded-lg p-4 text-center">
                            <div id="statDisabled" class="text-2xl font-bold text-red-400">-</div>
                            <div class="text-xs text-gray-400 mt-1">Filtered Out</div>
                        </div>
                    </div>
                    <div id="lastUpdatedInfo" class="mt-4 text-sm text-gray-400 text-center"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <!-- Unsaved Changes Warning -->
    <div id="unsavedBanner" class="hidden fixed bottom-0 inset-x-0 bg-yellow-600/90 backdrop-blur-sm text-white py-3 px-4 text-center text-sm z-40">
        <i class="fas fa-exclamation-circle mr-1"></i>
        You have unsaved changes.
        <button onclick="saveSettings()" class="ml-3 bg-white text-yellow-700 px-3 py-1 rounded font-medium text-xs hover:bg-yellow-50">
            Save Now
        </button>
    </div>

    <script>
        const API_URL = '../api/flock_alert_settings.php';
        let currentSettings = null;
        let isDirty = false;

        // ── Load settings from API ──
        async function loadSettings() {
            document.getElementById('loadingState').classList.remove('hidden');
            document.getElementById('errorState').classList.add('hidden');
            document.getElementById('mainContent').classList.add('hidden');

            try {
                const resp = await fetch(API_URL, {
                    credentials: 'same-origin'
                });
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                currentSettings = await resp.json();

                renderGlobalSettings();
                renderAlertTypes();
                updateStats();
                showLastUpdated();

                document.getElementById('loadingState').classList.add('hidden');
                document.getElementById('mainContent').classList.remove('hidden');
                isDirty = false;
                document.getElementById('unsavedBanner').classList.add('hidden');
            } catch (err) {
                document.getElementById('loadingState').classList.add('hidden');
                document.getElementById('errorMessage').textContent = err.message;
                document.getElementById('errorState').classList.remove('hidden');
            }
        }

        // ── Render global settings into the form ──
        function renderGlobalSettings() {
            const gs = currentSettings.global_settings;
            document.getElementById('sendAllToCad').checked = gs.send_all_to_cad;
            document.getElementById('logFilteredAlerts').checked = gs.log_filtered_alerts;
            document.getElementById('minPlateConfidence').value = gs.min_plate_confidence;
            document.getElementById('defaultNewTypeEnabled').checked = gs.default_new_type_enabled;
        }

        // ── Render alert type rows ──
        function renderAlertTypes() {
            const container = document.getElementById('alertTypesList');
            container.innerHTML = '';

            const types = currentSettings.alert_types;
            const sortedKeys = Object.keys(types).sort((a, b) => a.localeCompare(b));

            sortedKeys.forEach(name => {
                const t = types[name];
                const row = document.createElement('div');
                row.className = 'px-6 py-4 flex items-center justify-between hover:bg-gray-700/30 transition-colors fade-in';
                row.dataset.alertType = name;

                const statusIcon = t.send_to_cad ?
                    '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-900/40 text-green-400"><i class="fas fa-broadcast-tower text-sm"></i></span>' :
                    '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-700 text-gray-500"><i class="fas fa-ban text-sm"></i></span>';

                row.innerHTML = `
                    <div class="flex items-center space-x-4">
                        ${statusIcon}
                        <div>
                            <div class="text-sm font-medium text-white">${escapeHtml(name)}</div>
                            <div class="text-xs text-gray-400">${escapeHtml(t.description || '')}</div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-6">
                        <div class="text-right">
                            <label class="text-xs text-gray-400 block mb-1">Send to CAD</label>
                            <div class="relative inline-block w-11 align-middle select-none">
                                <input type="checkbox" id="cad_${cssId(name)}"
                                    ${t.send_to_cad ? 'checked' : ''}
                                    class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-2 border-gray-500 appearance-none cursor-pointer top-0.5"
                                    onchange="toggleAlertType('${escapeJs(name)}', this.checked)">
                                <label for="cad_${cssId(name)}"
                                    class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-600 cursor-pointer"></label>
                            </div>
                        </div>
                    </div>
                `;

                container.appendChild(row);
            });
        }

        // ── Toggle an individual alert type ──
        function toggleAlertType(name, enabled) {
            currentSettings.alert_types[name].send_to_cad = enabled;
            currentSettings.alert_types[name].enabled = enabled;
            renderAlertTypes();
            updateStats();
            markDirty();
        }

        // ── Enable/Disable all ──
        function enableAll() {
            Object.keys(currentSettings.alert_types).forEach(name => {
                currentSettings.alert_types[name].send_to_cad = true;
                currentSettings.alert_types[name].enabled = true;
            });
            renderAlertTypes();
            updateStats();
            markDirty();
        }

        function disableAll() {
            Object.keys(currentSettings.alert_types).forEach(name => {
                currentSettings.alert_types[name].send_to_cad = false;
                currentSettings.alert_types[name].enabled = false;
            });
            renderAlertTypes();
            updateStats();
            markDirty();
        }

        // ── Save settings to API ──
        async function saveSettings() {
            // Collect global settings from form
            const globalSettings = {
                send_all_to_cad: document.getElementById('sendAllToCad').checked,
                log_filtered_alerts: document.getElementById('logFilteredAlerts').checked,
                min_plate_confidence: parseFloat(document.getElementById('minPlateConfidence').value) || 0,
                default_new_type_enabled: document.getElementById('defaultNewTypeEnabled').checked
            };

            const payload = {
                alert_types: currentSettings.alert_types,
                global_settings: globalSettings
            };

            try {
                const resp = await fetch(API_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                const result = await resp.json();

                if (result.success) {
                    currentSettings = result.settings;
                    isDirty = false;
                    document.getElementById('unsavedBanner').classList.add('hidden');
                    showLastUpdated();
                    showToast('Settings saved successfully', 'success');
                } else {
                    throw new Error(result.error || 'Save failed');
                }
            } catch (err) {
                showToast('Failed to save: ' + err.message, 'error');
            }
        }

        // ── Update stat counters ──
        function updateStats() {
            const types = currentSettings.alert_types;
            const total = Object.keys(types).length;
            const enabled = Object.values(types).filter(t => t.send_to_cad).length;

            document.getElementById('statTotal').textContent = total;
            document.getElementById('statEnabled').textContent = enabled;
            document.getElementById('statDisabled').textContent = total - enabled;
        }

        // ── Show last-updated timestamp ──
        function showLastUpdated() {
            const el = document.getElementById('lastUpdatedInfo');
            if (currentSettings.last_updated) {
                el.textContent = `Last updated: ${currentSettings.last_updated} by ${currentSettings.updated_by || 'unknown'}`;
            } else {
                el.textContent = 'Settings have not been modified yet';
            }
        }

        // ── Dirty state tracking ──
        function markDirty() {
            isDirty = true;
            document.getElementById('unsavedBanner').classList.remove('hidden');
        }

        // ── Toast notifications ──
        function showToast(message, type) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            const bgClass = type === 'success' ? 'bg-green-600' : 'bg-red-600';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

            toast.className = `toast ${bgClass} text-white px-4 py-3 rounded-lg shadow-lg flex items-center space-x-2 text-sm`;
            toast.innerHTML = `<i class="fas ${icon}"></i><span>${escapeHtml(message)}</span>`;
            container.appendChild(toast);

            setTimeout(() => toast.remove(), 3000);
        }

        // ── Helpers ──
        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function escapeJs(str) {
            return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        }

        function cssId(str) {
            return str.replace(/[^a-zA-Z0-9]/g, '_');
        }

        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Load settings on page ready
        loadSettings();
    </script>
</body>

</html>