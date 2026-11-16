<?php
require_once __DIR__ . '/config.php';
$isAuth = isAdminAuthenticated();
$resetToken = isset($_GET['token']) ? trim($_GET['token']) : '';
$isResetMode = (isset($_GET['action']) && $_GET['action'] === 'reset_pin' && !empty($resetToken));
if ($isResetMode) { $isAuth = false; }
$adminEmail = getAdminEmail();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Company vCard Dashboard & Scan Analytics</title>
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- QR Code Styling (for thumbnail generation) -->
  <script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.6.0-rc.1/lib/qr-code-styling.js"></script>
<script>
  // Determine the correct base URL for QR codes (use LAN IP if running on localhost)
  (function() {
    const protocol = "<?php echo (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; ?>";
    const host = "<?php
      $host = $_SERVER['HTTP_HOST'] ?? '';
      if (stripos($host, 'localhost') !== false || stripos($host, '127.0.0.1') !== false) {
        $host = gethostbyname(gethostname());
      }
      echo $host;
    ?>";
    window.BASE_URL = `${protocol}://${host}`;
  })();
</script>
  <!-- Custom Styles -->
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .btn-preview-qr canvas {
      width: 100% !important;
      height: 100% !important;
      display: block;
      border-radius: 0.375rem;
    }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans min-h-screen">

  <?php if (!$isAuth): ?>

    <?php if ($isResetMode): ?>
    <!-- ==================== 1A. RESET PIN SCREEN ==================== -->
    <div class="min-h-screen flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl p-8 max-w-sm w-full shadow-2xl border border-slate-200 flex flex-col items-center text-center">
        <div class="w-16 h-16 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4 shadow-sm">
          <i data-lucide="key" class="w-8 h-8"></i>
        </div>
        
        <h1 class="text-xl font-black text-slate-900">Set New Admin PIN</h1>
        <p class="text-xs text-slate-500 mt-1">Enter your new administrator PIN to regain access to the dashboard.</p>

        <form id="resetPinForm" class="w-full mt-6 flex flex-col gap-3">
          <input type="hidden" id="resetToken" value="<?= htmlspecialchars($resetToken) ?>">
          
          <div>
            <label class="block text-[11px] font-bold text-slate-600 text-left mb-1 uppercase tracking-wider">New PIN (Min 4 digits)</label>
            <input type="password" id="inputResetNewPin" minlength="4" maxlength="12" placeholder="Enter new PIN" required autofocus
              class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-center font-mono font-bold text-base tracking-wider">
          </div>

          <div>
            <label class="block text-[11px] font-bold text-slate-600 text-left mb-1 uppercase tracking-wider">Confirm New PIN</label>
            <input type="password" id="inputResetConfirmPin" minlength="4" maxlength="12" placeholder="Repeat new PIN" required
              class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-center font-mono font-bold text-base tracking-wider">
            <p id="resetPinErrorMsg" class="text-xs text-rose-500 font-semibold mt-1.5 hidden"></p>
            <p id="resetPinSuccessMsg" class="text-xs text-emerald-600 font-semibold mt-1.5 hidden"></p>
          </div>

          <button type="submit" id="btnSubmitResetPin"
            class="w-full py-3 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm shadow-md shadow-blue-500/25 transition cursor-pointer flex items-center justify-center gap-2 mt-1">
            <span>Save PIN & Enter Dashboard</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
          </button>
        </form>

       <div class="mt-6 pt-4 border-t border-slate-100 w-full flex items-center justify-center text-xs text-slate-400">
          <a href="dashboard.php" class="text-blue-600 hover:underline">Back to Login</a>
        </div>
      </div>
    </div> 

    <script>
      document.getElementById('resetPinForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const token = document.getElementById('resetToken').value.trim();
        const newPin = document.getElementById('inputResetNewPin').value.trim();
        const confirmPin = document.getElementById('inputResetConfirmPin').value.trim();
        const errEl = document.getElementById('resetPinErrorMsg');
        const successEl = document.getElementById('resetPinSuccessMsg');
        const btn = document.getElementById('btnSubmitResetPin');

        errEl.classList.add('hidden');
        successEl.classList.add('hidden');

        if (newPin.length < 4) {
          errEl.textContent = 'PIN must be at least 4 digits/characters.';
          errEl.classList.remove('hidden');
          return;
        }

        if (newPin !== confirmPin) {
          errEl.textContent = 'PINs do not match. Please re-type.';
          errEl.classList.remove('hidden');
          return;
        }

        try {
          btn.disabled = true;
          btn.innerHTML = `<span>Saving PIN...</span>`;

          const res = await fetch('api.php?action=reset_pin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token, new_pin: newPin })
          });
          const data = await res.json();

          if (data.success) {
            successEl.textContent = '✓ ' + data.message + ' Redirecting...';
            successEl.classList.remove('hidden');
            setTimeout(() => {
              window.location.href = 'dashboard.php';
            }, 1200);
          } else {
            errEl.textContent = data.message || 'Reset failed.';
            errEl.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = `<span>Save PIN & Enter Dashboard</span><i data-lucide="arrow-right" class="w-4 h-4"></i>`;
            if (window.lucide) lucide.createIcons();
          }
        } catch (err) {
          errEl.textContent = 'Server connection error.';
          errEl.classList.remove('hidden');
          btn.disabled = false;
          btn.innerHTML = `<span>Save PIN & Enter Dashboard</span><i data-lucide="arrow-right" class="w-4 h-4"></i>`;
          if (window.lucide) lucide.createIcons();
        }
      });
      if (window.lucide) lucide.createIcons();
    </script>

    <?php else: ?>
    <!-- ==================== 1B. PIN LOGIN SCREEN ==================== -->
    <div class="min-h-screen flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl p-8 max-w-sm w-full shadow-2xl border border-slate-200 flex flex-col items-center text-center">
        <div class="w-16 h-16 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4 shadow-sm">
          <i data-lucide="lock" class="w-8 h-8"></i>
        </div>
        
        <h1 class="text-xl font-black text-slate-900">Admin Dashboard</h1>
        <p class="text-xs text-slate-500 mt-1">Enter master PIN to access staff vCards & scan statistics.</p>

        <form id="pinLoginForm" class="w-full mt-6 flex flex-col gap-3">
          <div>
            <input type="password" id="inputPin" maxlength="12" placeholder="Enter PIN (e.g. 1234)" autofocus
              class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-center font-mono font-bold text-lg tracking-widest">
            <p id="pinErrorMsg" class="text-xs text-rose-500 font-semibold mt-1.5 hidden"></p>
          </div>

          <button type="submit" id="btnSubmitPin"
            class="w-full py-3 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm shadow-md shadow-blue-500/25 transition cursor-pointer flex items-center justify-center gap-2">
            <span>Unlock Dashboard</span>
            <i data-lucide="arrow-right" class="w-4 h-4"></i>
          </button>
        </form>

        <div class="mt-4 flex items-center justify-between w-full text-xs text-slate-400 pt-3 border-t border-slate-100">
          <span>Default PIN: <strong class="font-mono text-slate-600 font-bold">1234</strong></span>
          <button type="button" id="btnForgotPin" class="text-xs text-blue-600 hover:underline font-semibold cursor-pointer">
            Forgot PIN?
          </button>
        </div>
      </div>
    </div>

    <!-- Forgot PIN Modal -->
    <div id="forgotPinModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
      <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-sm w-full shadow-2xl border border-slate-200 flex flex-col items-center text-center modal-animate">
        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3 shadow-xs">
          <i data-lucide="mail" class="w-6 h-6"></i>
        </div>

        <h3 class="text-base font-bold text-slate-900">Forgot Admin PIN</h3>
        <p class="text-xs text-slate-500 mt-1">A password reset link will be sent to the administrator email address.</p>

        <form id="forgotPinForm" class="w-full mt-4 flex flex-col gap-3">
          <div class="text-left">
            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Admin Email</label>
            <input type="email" id="forgotAdminEmail" value="<?= htmlspecialchars($adminEmail) ?>" readonly
              class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 font-medium text-slate-700 cursor-not-allowed">
          </div>

          <div id="forgotPinStatus" class="p-3 rounded-xl text-xs font-medium text-left hidden"></div>

          <button type="submit" id="btnSendResetLink"
            class="w-full py-2.5 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md shadow-blue-500/25 transition cursor-pointer flex items-center justify-center gap-1.5 mt-1">
            <i data-lucide="send" class="w-3.5 h-3.5"></i>
            <span>Send Reset Link</span>
          </button>

          <button type="button" id="btnCancelForgotPin"
            class="w-full py-2 px-4 rounded-xl text-slate-500 hover:bg-slate-100 font-semibold text-xs transition cursor-pointer">
            Cancel
          </button>
        </form>
      </div>
    </div>

    <script>
      // Login Form
      document.getElementById('pinLoginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const pin = document.getElementById('inputPin').value.trim();
        const errEl = document.getElementById('pinErrorMsg');
        errEl.classList.add('hidden');

        if (!pin) return;

        try {
          const res = await fetch('api.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pin })
          });
          const data = await res.json();
          if (data.success) {
            window.location.reload();
          } else {
            errEl.textContent = data.message || 'Incorrect PIN';
            errEl.classList.remove('hidden');
          }
        } catch (err) {
          errEl.textContent = 'Server error. Please try again.';
          errEl.classList.remove('hidden');
        }
      });

      // Forgot PIN Modal Handlers
      const forgotModal = document.getElementById('forgotPinModal');
      const btnForgotPin = document.getElementById('btnForgotPin');
      const btnCancelForgotPin = document.getElementById('btnCancelForgotPin');
      const forgotPinForm = document.getElementById('forgotPinForm');
      const forgotStatus = document.getElementById('forgotPinStatus');
      const btnSendResetLink = document.getElementById('btnSendResetLink');

      btnForgotPin.addEventListener('click', () => {
        forgotStatus.classList.add('hidden');
        forgotModal.classList.remove('hidden');
        forgotModal.classList.add('flex');
      });

      btnCancelForgotPin.addEventListener('click', () => {
        forgotModal.classList.add('hidden');
        forgotModal.classList.remove('flex');
      });

      forgotPinForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        forgotStatus.classList.add('hidden');
        btnSendResetLink.disabled = true;
        btnSendResetLink.innerHTML = `<span>Sending Link...</span>`;

        try {
          const res = await fetch('api.php?action=forgot_pin', {
            method: 'POST'
          });
          const data = await res.json();

          if (data.success) {
            forgotStatus.className = 'p-3 rounded-xl text-xs font-medium text-left bg-emerald-50 text-emerald-800 border border-emerald-200 block';
            // Security: never show or use any reset_url from the response.
            // The link is ONLY delivered by email. The admin must check their inbox.
            forgotStatus.innerHTML = `
              <p class="font-bold">Reset Link Sent!</p>
              <p class="mt-1">An email has been sent to <strong>${data.email}</strong> with a reset link valid for 30 minutes.</p>
              <p class="mt-2 text-emerald-700 text-[11px]">If you do not receive it within a few minutes, ask your system administrator to check <code>data/reset_log.txt</code> on the server.</p>
            `;
          } else {
            forgotStatus.className = 'p-3 rounded-xl text-xs font-medium text-left bg-rose-50 text-rose-800 border border-rose-200 block';
            forgotStatus.textContent = data.message || 'Failed to send reset link.';
          }
        } catch (err) {
          forgotStatus.className = 'p-3 rounded-xl text-xs font-medium text-left bg-rose-50 text-rose-800 border border-rose-200 block';
          forgotStatus.textContent = 'Server connection error.';
        } finally {
          btnSendResetLink.disabled = false;
          btnSendResetLink.innerHTML = `<i data-lucide="send" class="w-3.5 h-3.5"></i><span>Send Reset Link</span>`;
          if (window.lucide) lucide.createIcons();
        }
      });

      if (window.lucide) lucide.createIcons();
    </script>
    <?php endif; ?>

  <?php else: ?>
  <!-- ==================== 2. ADMIN DASHBOARD SCREEN ==================== -->
  
  <!-- Navigation Header -->
  <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center shadow-md font-black text-lg">
          <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
        </div>
        <div>
          <h1 class="text-base sm:text-lg font-bold text-slate-900 leading-none">vCard Staff Dashboard</h1>
          <p class="text-xs text-slate-500 mt-0.5">Live Scan Tracking & Card Management</p>
        </div>
      </div>

      <div class="flex items-center gap-2.5">
        <a href="index.html?admin=1" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md shadow-blue-500/20 transition">
          <i data-lucide="plus" class="w-4 h-4"></i>
          <span>Create New QR</span>
        </a>

        <button type="button" id="btnOpenChangePin" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition" title="Change Admin PIN">
          <i data-lucide="key-round" class="w-4 h-4"></i>
        </button>

        <button type="button" id="btnLogout" class="p-2 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-600 transition" title="Logout">
          <i data-lucide="log-out" class="w-4 h-4"></i>
        </button>
      </div>
    </div>
  </header>

  <!-- Dashboard Main View -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col gap-6">

    <!-- KPI Summary Tiles -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      
      <!-- Total Cards -->
      <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Staff Cards</p>
          <h3 id="statTotalCards" class="text-2xl font-black text-slate-900 mt-1">-</h3>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shadow-xs">
          <i data-lucide="users" class="w-6 h-6"></i>
        </div>
      </div>

      <!-- Total Scans -->
      <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Scans Recorded</p>
          <h3 id="statTotalScans" class="text-2xl font-black text-emerald-600 mt-1">-</h3>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shadow-xs">
          <i data-lucide="scan" class="w-6 h-6"></i>
        </div>
      </div>

      <!-- Most Scanned Card -->
      <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Most Active Card</p>
          <h3 id="statTopCard" class="text-lg font-black text-slate-900 mt-1 truncate max-w-[180px]">-</h3>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center shadow-xs">
          <i data-lucide="trophy" class="w-6 h-6"></i>
        </div>
      </div>
     
    </div>

    <!-- Table Container & Search Filter -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
      
      <!-- Table Header & Live Search Bar -->
      <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
          <h2 class="text-base font-bold text-slate-900">Registered vCards</h2>
          <p class="text-xs text-slate-500">Edit details anytime, download print files, and monitor live scans.</p>
        </div>

        <div class="relative w-full sm:w-72">
          <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
          <input type="text" id="tableSearch" placeholder="Search by name or company..."
            class="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-medium">
        </div>
      </div>

      <!-- Data Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400">
              <th class="py-3.5 px-5">QR Code</th>
              <th class="py-3.5 px-5">Employee / Profile</th>
              <th class="py-3.5 px-5">Dynamic Link</th>
              <th class="py-3.5 px-5 text-center">Live Scans</th>
              <th class="py-3.5 px-5">Created Date</th>
              <th class="py-3.5 px-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="cardTableBody" class="divide-y divide-slate-100 text-sm">
            <!-- Dynamic rows will be inserted here -->
            <tr>
              <td colspan="6" class="py-12 text-center text-slate-400 text-xs">
                Loading cards...
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>

  </main>

  <!-- ==================== MODALS ==================== -->

  <!-- 1. Enlarge QR Code Modal -->
  <div id="qrModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 cropper-modal-overlay">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl modal-animate flex flex-col items-center text-center gap-4">
      <div class="w-full flex items-center justify-between pb-2 border-b border-slate-100">
        <h3 id="modalQrTitle" class="text-sm font-bold text-slate-900">QR Code Preview</h3>
        <button type="button" id="btnCloseQrModal" class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center transition">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <div id="modalQrContainer" class="p-3 bg-white rounded-2xl shadow-inner border border-slate-100 min-w-[220px] min-h-[220px] flex items-center justify-center">
        <!-- Enlarged QR renders here -->
      </div>

      <div class="grid grid-cols-2 gap-2 w-full mt-2">
        <button id="modalBtnDownloadPNG" type="button" class="py-2.5 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm transition">
          <i data-lucide="download" class="w-3.5 h-3.5"></i>
          <span>Download PNG</span>
        </button>
        <button id="modalBtnDownloadSVG" type="button" class="py-2.5 px-3 rounded-xl bg-white hover:bg-slate-100 text-slate-800 border border-slate-200 font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm transition">
          <i data-lucide="file-code" class="w-3.5 h-3.5"></i>
          <span>Download SVG</span>
        </button>
      </div>
    </div>
  </div>

  <!-- 2. Change Admin PIN Modal -->
  <div id="pinModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 cropper-modal-overlay">
    <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl modal-animate flex flex-col gap-4">
      <div class="flex items-center justify-between pb-2 border-b border-slate-100">
        <h3 class="text-sm font-bold text-slate-900">Change Admin PIN</h3>
        <button type="button" id="btnClosePinModal" class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center transition">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>

      <form id="changePinForm" class="flex flex-col gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1 uppercase tracking-wider">Current PIN</label>
          <input type="password" id="inputOldPin" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 font-mono">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1 uppercase tracking-wider">New PIN (Min 4 digits)</label>
          <input type="password" id="inputNewPin" required minlength="4" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 font-mono">
        </div>
        
        <p id="changePinMsg" class="text-xs font-semibold hidden"></p>

        <div class="flex items-center justify-end gap-2 mt-2 pt-2 border-t border-slate-100">
          <button type="button" id="btnCancelChangePin" class="px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition">Cancel</button>
          <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition">Save New PIN</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Dashboard JavaScript Logic -->
  <script>
    let allCards = [];
    let currentModalQrInstance = null;
    let currentModalCard = null;

    // Compact number formatter: 1010000 → 1.01M, 1500 → 1.5K, etc.
    function formatNum(n) {
      n = Number(n) || 0;
      if (n >= 1_000_000) return (n / 1_000_000).toFixed(2).replace(/\.?0+$/, '') + ' M';
      if (n >= 1_000)     return (n / 1_000).toFixed(1).replace(/\.0$/, '')    + ' K';
      return n.toString();
    }

    // 1. Fetch Cards and Render Table
    async function loadCards() {
      try {
        const res = await fetch('api.php?action=list');
        const data = await res.json();
        if (data.success) {
          allCards = data.cards || [];
          document.getElementById('statTotalCards').textContent = formatNum(data.stats.total_cards);
          document.getElementById('statTotalScans').textContent = formatNum(data.stats.total_scans);
          document.getElementById('statTopCard').textContent = data.stats.top_card;
          renderTable(allCards);
        generateThumbnails();
        } else {
          if (res.status === 401) {
            window.location.reload();
            return;
          }
          document.getElementById('cardTableBody').innerHTML = `
            <tr><td colspan="6" class="py-12 text-center text-rose-500 text-xs">${data.message || 'Failed to load'}</td></tr>
          `;
        }
      } catch (err) {
        document.getElementById('cardTableBody').innerHTML = `
          <tr><td colspan="6" class="py-12 text-center text-rose-500 text-xs">Error connecting to server</td></tr>
        `;
      }
    }

    // 2. Render Table Rows
    function renderTable(cards) {
      const tbody = document.getElementById('cardTableBody');
      if (!cards || cards.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="6" class="py-12 text-center text-slate-400 text-xs">
              No staff vCards created yet. Click <strong>"Create New QR"</strong> above to make your first card!
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = cards.map(c => {
        const fullName = `${c.first_name || ''} ${c.last_name || ''}`.trim();
        const baseUrl = window.BASE_URL || window.location.origin;
        const shortUrl = `${baseUrl}${window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'))}/c/?id=${c.slug}`;
        const createdDate = c.created_at ? c.created_at.split(' ')[0] : '-';

        return `
          <tr class="hover:bg-slate-50/80 transition group" data-card-id="${c.id}">
            <!-- QR Thumbnail -->
            <td class="py-3.5 px-5">
              <button type="button" class="btn-preview-qr w-12 h-12 p-1 bg-white rounded-xl shadow-xs border border-slate-200 hover:scale-105 transition flex items-center justify-center overflow-hidden" data-slug="${c.slug}" title="View QR Code">
                <div id="thumb_${c.slug}" class="w-full h-full flex items-center justify-center">
                  <i data-lucide="qr-code" class="w-6 h-6 text-slate-600"></i>
                </div>
              </button>
            </td>

            <!-- Employee Details -->
            <td class="py-3.5 px-5">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center overflow-hidden shrink-0 border border-slate-200">
                  ${c.photo_url ? `<img src="${c.photo_url}" class="w-full h-full object-cover">` : `<span class="text-xs font-bold text-slate-600">${(c.first_name?.[0] || '') + (c.last_name?.[0] || '')}</span>`}
                </div>
                <div>
                  <p class="font-bold text-slate-900">${fullName}</p>
                  <p class="text-xs text-slate-500">${c.job_title || ''} ${c.company ? '• ' + c.company : ''}</p>
                </div>
              </div>
            </td>

            <!-- Short Link -->
            <td class="py-3.5 px-5">
              <div class="flex items-center gap-1.5">
                <span class="font-mono text-xs text-blue-600 truncate max-w-[140px]">${c.slug}</span>
                <button type="button" class="btn-copy-short-url p-1 rounded hover:bg-slate-200 text-slate-400 hover:text-slate-700 transition" data-url="${shortUrl}" title="Copy Link">
                  <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                </button>
                <a href="${shortUrl}" target="_blank" class="p-1 rounded hover:bg-blue-50 text-blue-600 transition" title="Open Card">
                  <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                </a>
              </div>
            </td>

            <!-- Scan Count Badge -->
            <td class="py-3.5 px-5 text-center">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold ${c.scans > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                <span>${c.scans || 0} scans</span>
              </span>
            </td>

            <!-- Created Date -->
            <td class="py-3.5 px-5 text-xs text-slate-500">
              ${createdDate}
            </td>

            <!-- Actions -->
            <td class="py-3.5 px-5 text-right">
              <div class="flex items-center justify-end gap-1.5">
                <!-- Method 1: Edit Card in Studio (Admin Mode) -->
                <a href="index.html?edit=${c.slug}&admin=1" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-700 text-xs font-bold transition flex items-center gap-1" title="Edit Card Details">
                  <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                  <span>Edit</span>
                </a>

                <button type="button" class="btn-delete-card p-1.5 rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-600 transition" data-id="${c.id}" data-name="${fullName}" title="Delete Card">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join('');

      if (window.lucide) lucide.createIcons();
      attachTableEventListeners();
      generateThumbnails(cards);
    }

    // Universal Clipboard Copy Helper (works on both HTTPS and plain HTTP over local LAN IP)
    function copyToClipboard(text) {
      if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
      }
      return new Promise((resolve, reject) => {
        try {
          const textarea = document.createElement('textarea');
          textarea.value = text;
          textarea.style.position = 'fixed';
          textarea.style.left = '-9999px';
          textarea.style.top = '-9999px';
          textarea.style.opacity = '0';
          document.body.appendChild(textarea);
          textarea.focus();
          textarea.select();
          const ok = document.execCommand('copy');
          document.body.removeChild(textarea);
          ok ? resolve() : reject(new Error('Copy command failed'));
        } catch (err) {
          reject(err);
        }
      });
    }

    // 3. Attach Dynamic Listeners to Table Actions
    function attachTableEventListeners() {
      // Copy URL
      document.querySelectorAll('.btn-copy-short-url').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const url = btn.getAttribute('data-url');
          if (!url) return;

          copyToClipboard(url).then(() => {
            const original = btn.innerHTML;
            btn.innerHTML = `<i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i>`;
            if (window.lucide) lucide.createIcons();
            setTimeout(() => {
              btn.innerHTML = original;
              if (window.lucide) lucide.createIcons();
            }, 1800);
          }).catch(() => {
            prompt('Copy card link:', url);
          });
        });
      });

      // Preview QR Modal
      document.querySelectorAll('.btn-preview-qr').forEach(btn => {
        btn.addEventListener('click', () => {
          const slug = btn.getAttribute('data-slug');
          const card = allCards.find(c => c.slug === slug);
          if (card) openQrModal(card);
        });
      });

      // Delete Card
      document.querySelectorAll('.btn-delete-card').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = btn.getAttribute('data-id');
          const name = btn.getAttribute('data-name');
          if (confirm(`Are you sure you want to delete ${name}'s digital card?`)) {
            try {
              const res = await fetch('api.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
              });
              const data = await res.json();
              if (data.success) {
                loadCards();
              } else {
                alert(data.message || 'Delete failed');
              }
            } catch (err) {
              alert('Server error');
            }
          }
        });
      });
    }

    // 4b. Generate QR thumbnails for dashboard rows
    function generateThumbnails(cardsToRender) {
      const cards = cardsToRender || allCards;
      if (!Array.isArray(cards)) return;
      cards.forEach(card => {
        const slug = card.slug;
        const container = document.getElementById(`thumb_${slug}`);
        if (!container) return;
        // Clear previous icon or canvas
        container.innerHTML = '';
        const baseUrl = (window.BASE_URL || window.location.origin).replace(/\/+$/, '');
        const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
        const shortUrl = `${baseUrl}${currentPath}/c/?id=${encodeURIComponent(slug)}`;
        let qrDotColor = (card.qr_color && card.qr_color !== 'match-theme') ? card.qr_color : '#000000';
        let dotType = card.qr_pattern || 'square';
        let cornerType = (card.qr_corner_style === 'square') ? 'square' : 'extra-rounded';

        try {
          // Render at 100x100 resolution so canvas has ample pixels to render curves/dots cleanly, then CSS scales to 40x40
          const thumbQr = new QRCodeStyling({
            width: 100,
            height: 100,
            type: 'canvas',
            data: shortUrl,
            dotsOptions: { color: qrDotColor, type: dotType },
            cornersSquareOptions: { color: qrDotColor, type: cornerType },
            cornersDotOptions: { color: qrDotColor, type: 'dot' },
            backgroundOptions: { color: '#ffffff' }
          });
          thumbQr.append(container);
        } catch (e) {
          console.error('Thumbnail generation error for', slug, e);
        }
      });
    }

    function openQrModal(card) {
      currentModalCard = card;
      const modal = document.getElementById('qrModal');
      const container = document.getElementById('modalQrContainer');
      const fullName = `${card.first_name || ''} ${card.last_name || ''}`.trim();
      document.getElementById('modalQrTitle').textContent = `${fullName}'s QR Code`;

      const baseUrl = window.BASE_URL || window.location.origin;
      const shortUrl = `${baseUrl}${window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'))}/c/?id=${card.slug}`;

      let qrDotColor = (card.qr_color && card.qr_color !== 'match-theme') ? card.qr_color : '#000000';
      let dotType = card.qr_pattern || 'square';
      let cornerType = (card.qr_corner_style === 'square') ? 'square' : 'extra-rounded';

      container.innerHTML = '';
      currentModalQrInstance = new QRCodeStyling({
        width: 220,
        height: 220,
        type: 'canvas',
        data: shortUrl,
        dotsOptions: { color: qrDotColor, type: dotType },
        cornersSquareOptions: { color: qrDotColor, type: cornerType },
        cornersDotOptions: { color: qrDotColor, type: 'dot' },
        backgroundOptions: { color: '#ffffff' }
      });
      currentModalQrInstance.append(container);

      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    document.getElementById('btnCloseQrModal').addEventListener('click', () => {
      document.getElementById('qrModal').classList.add('hidden');
      document.getElementById('qrModal').classList.remove('flex');
    });

    document.getElementById('modalBtnDownloadPNG').addEventListener('click', () => {
      if (currentModalQrInstance && currentModalCard) {
        currentModalQrInstance.download({ name: `${currentModalCard.slug}_qr`, extension: 'png' });
      }
    });

    document.getElementById('modalBtnDownloadSVG').addEventListener('click', () => {
      if (currentModalQrInstance && currentModalCard) {
        currentModalQrInstance.download({ name: `${currentModalCard.slug}_qr`, extension: 'svg' });
      }
    });

    // 5. Change PIN Handlers
    const pinModal = document.getElementById('pinModal');
    document.getElementById('btnOpenChangePin').addEventListener('click', () => {
      pinModal.classList.remove('hidden');
      pinModal.classList.add('flex');
    });
    document.getElementById('btnClosePinModal').addEventListener('click', () => {
      pinModal.classList.add('hidden');
      pinModal.classList.remove('flex');
    });
    document.getElementById('btnCancelChangePin').addEventListener('click', () => {
      pinModal.classList.add('hidden');
      pinModal.classList.remove('flex');
    });

    document.getElementById('changePinForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const oldPin = document.getElementById('inputOldPin').value.trim();
      const newPin = document.getElementById('inputNewPin').value.trim();
      const msgEl = document.getElementById('changePinMsg');
      msgEl.classList.add('hidden');

      try {
        const res = await fetch('api.php?action=change_pin', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ old_pin: oldPin, new_pin: newPin })
        });
        const data = await res.json();
        if (data.success) {
          msgEl.textContent = '✓ ' + data.message;
          msgEl.className = 'text-xs font-semibold text-emerald-600 block';
          setTimeout(() => {
            pinModal.classList.add('hidden');
            pinModal.classList.remove('flex');
            document.getElementById('changePinForm').reset();
            msgEl.classList.add('hidden');
          }, 1500);
        } else {
          msgEl.textContent = data.message || 'Failed';
          msgEl.className = 'text-xs font-semibold text-rose-500 block';
        }
      } catch (err) {
        msgEl.textContent = 'Server error';
        msgEl.className = 'text-xs font-semibold text-rose-500 block';
      }
    });

    // 6. Logout
    document.getElementById('btnLogout').addEventListener('click', async () => {
      await fetch('api.php?action=logout');
      window.location.reload();
    });

    // 7. Live Table Search Filter
    document.getElementById('tableSearch').addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase().trim();
      if (!q) {
        renderTable(allCards);
        return;
      }
      const filtered = allCards.filter(c => {
        const name = `${c.first_name || ''} ${c.last_name || ''}`.toLowerCase();
        const comp = (c.company || '').toLowerCase();
        const title = (c.job_title || '').toLowerCase();
        const slug = (c.slug || '').toLowerCase();
        return name.includes(q) || comp.includes(q) || title.includes(q) || slug.includes(q);
      });
      renderTable(filtered);
    });

    async function detectServerLanIp() {
      try {
        const hostname = window.location.hostname;
        if (hostname === 'localhost' || hostname === '127.0.0.1') {
          const res = await fetch('api.php?action=get_server_ip');
          const data = await res.json();
          if (data.success && data.ip && data.ip !== '127.0.0.1') {
            const protocol = window.location.protocol;
            window.BASE_URL = `${protocol}//${data.ip}`;
            generateThumbnails();
          }
        }
      } catch (e) {}
    }

    // Initial Load
    if (window.lucide) lucide.createIcons();
    loadCards().then(() => detectServerLanIp());
  </script>
  <?php endif; ?>

</body>
</html>
