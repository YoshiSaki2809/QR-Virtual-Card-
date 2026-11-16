/**
 * Builder Application Logic (builder.js)
 * Automatically generates dynamic vCard QR codes with auto-slug,
 * intl-tel-input country flags, Cropper.js photo adjustment,
 * DD-MM-YYYY birth date format, and QRCodeStyling custom rendering.
 */
let qrCode = null;
let currentQrColor = '#000000';

// Date format helper functions
function formatDobDisplay(isoDate) {
  if (!isoDate) return '';
  if (/^\d{2}-\d{2}-\d{4}$/.test(isoDate)) return isoDate;
  const parts = isoDate.split('-');
  if (parts.length === 3 && parts[0].length === 4) {
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
  }
  return isoDate;
}

function parseDobToInput(dobStr) {
  if (!dobStr) return '';
  if (/^\d{4}-\d{2}-\d{2}$/.test(dobStr)) return dobStr;
  const parts = dobStr.split('-');
  if (parts.length === 3 && parts[2].length === 4) {
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
  }
  return dobStr;
}

document.addEventListener('DOMContentLoaded', () => {
  // --- Form & Inputs ---
  const form = document.getElementById('vcardForm');
  const phoneInputField = document.getElementById('phoneNumber');
  const customColorPicker = document.getElementById('customColorPicker');
  const customColorHex = document.getElementById('customColorHex');
  const customColorSecondary = document.getElementById('customColorSecondary');
  const customColorSecondaryHex = document.getElementById('customColorSecondaryHex');
  const customBaseUrlInput = document.getElementById('customBaseUrl');
  const swatches = document.querySelectorAll('.color-swatch');

  // --- Card Identifiers & Admin Edit Mode ---
  const cardIdInput = document.getElementById('cardId');
  const editModeNotice = document.getElementById('editModeNotice');
  const editingCardName = document.getElementById('editingCardName');
  const btnGenerateQR = document.getElementById('btnGenerateQR');
  const btnGenerateQRText = document.getElementById('btnGenerateQRText');
  const btnFormSubmit = document.getElementById('btnFormSubmit');
  const btnFormSubmitText = document.getElementById('btnFormSubmitText');

  // --- Photo Upload & Crop Elements ---
  const photoFileInput = document.getElementById('photoFileInput');
  const photoUrlInput = document.getElementById('photoUrl');
  const btnRemovePhoto = document.getElementById('btnRemovePhoto');
  const btnAdjustCrop = document.getElementById('btnAdjustCrop');
  const uploadAvatarImg = document.getElementById('uploadAvatarImg');
  const uploadAvatarInitials = document.getElementById('uploadAvatarInitials');
  const uploadStatusText = document.getElementById('uploadStatusText');
  const prevAvatarImg = document.getElementById('prevAvatarImg');

  // --- Cropper Modal Elements ---
  const cropModal = document.getElementById('cropModal');
  const cropperImage = document.getElementById('cropperImage');
  const cropZoomSlider = document.getElementById('cropZoomSlider');
  const zoomLevelText = document.getElementById('zoomLevelText');
  const btnRotateLeft = document.getElementById('btnRotateLeft');
  const btnRotateRight = document.getElementById('btnRotateRight');
  const btnResetCrop = document.getElementById('btnResetCrop');
  const btnCancelCrop = document.getElementById('btnCancelCrop');
  const btnCancelCropX = document.getElementById('btnCancelCropX');
  const btnApplyCrop = document.getElementById('btnApplyCrop');
  
  let cropperInstance = null;
  let originalImageSrc = null;

  // --- Tab & Panel Elements ---
  const tabBtnPreview = document.getElementById('tabBtnPreview');
  const tabBtnQR = document.getElementById('tabBtnQR');
  const panelPreview = document.getElementById('panelPreview');
  const panelQR = document.getElementById('panelQR');
  const headerBtnShowQR = document.getElementById('headerBtnShowQR');
  const adminNavButtons = document.getElementById('adminNavButtons');

  // --- Success Overlay Elements ---
  const successOverlay = document.getElementById('successOverlay');
  const successQrContainer = document.getElementById('successQrContainer');
  const successCardName = document.getElementById('successCardName');
  const successBtnDownloadPNG = document.getElementById('successBtnDownloadPNG');
  const successBtnDownloadSVG = document.getElementById('successBtnDownloadSVG');
  const successBtnNewCard = document.getElementById('successBtnNewCard');
  const successBtnViewCard = document.getElementById('successBtnViewCard');
  const successBtnExit = document.getElementById('successBtnExit');
  const btnExitSuccessOverlayX = document.getElementById('btnExitSuccessOverlayX');
  const editTokenInput = document.getElementById('editToken');
  const editingModeBadge = document.getElementById('editingModeBadge');
  let successQrInstance = null;

  // --- OTP Self-Service Elements ---
  const btnOpenEditModal = document.getElementById('btnOpenEditModal');
  const otpModal = document.getElementById('otpModal');
  const btnCancelOtpModalX = document.getElementById('btnCancelOtpModalX');
  const otpStep1 = document.getElementById('otpStep1');
  const otpStep2 = document.getElementById('otpStep2');
  const formRequestOtp = document.getElementById('formRequestOtp');
  const inputOtpEmail = document.getElementById('inputOtpEmail');
  const btnSubmitRequestOtp = document.getElementById('btnSubmitRequestOtp');
  const otpRequestStatus = document.getElementById('otpRequestStatus');
  const formVerifyOtp = document.getElementById('formVerifyOtp');
  const inputOtpDigits = document.getElementById('inputOtpDigits');
  const btnSubmitVerifyOtp = document.getElementById('btnSubmitVerifyOtp');
  const otpVerifyStatus = document.getElementById('otpVerifyStatus');
  const otpTargetEmailText = document.getElementById('otpTargetEmailText');
  const btnOtpBackToStep1 = document.getElementById('btnOtpBackToStep1');
  const btnResendOtp = document.getElementById('btnResendOtp');

  // Admin Navigation Handling:
  // ONLY show 'Back to Dashboard' when url contains ?admin=1 AND user is authenticated as Admin.
  // For normal users visiting index.html, it remains strictly hidden.
  const urlParamsInit = new URLSearchParams(window.location.search);
  const isAdminParam = urlParamsInit.get('admin') === '1';
  let isAdminMode = isAdminParam;

  async function checkAdminStatus() {
    if (!isAdminParam) {
      if (adminNavButtons) {
        adminNavButtons.classList.add('hidden');
        adminNavButtons.classList.remove('flex');
      }
      return;
    }

    try {
      const res = await fetch('api.php?action=check_auth');
      const data = await res.json();
      if (data && data.authenticated) {
        isAdminMode = true;
        if (adminNavButtons) {
          adminNavButtons.classList.remove('hidden');
          adminNavButtons.classList.add('flex');
        }
      } else {
        isAdminMode = false;
        if (adminNavButtons) {
          adminNavButtons.classList.add('hidden');
          adminNavButtons.classList.remove('flex');
        }
      }
    } catch (e) {
      if (adminNavButtons) {
        adminNavButtons.classList.add('hidden');
        adminNavButtons.classList.remove('flex');
      }
    }
  }

  if (isAdminParam && adminNavButtons) {
    adminNavButtons.classList.remove('hidden');
    adminNavButtons.classList.add('flex');
  } else if (adminNavButtons) {
    adminNavButtons.classList.add('hidden');
    adminNavButtons.classList.remove('flex');
  }
  checkAdminStatus();

  // --- Preview Elements ---
  const prevBanner = document.getElementById('prevBanner');
  const prevAvatarBg = document.getElementById('prevAvatarBg');
  const prevInitials = document.getElementById('prevInitials');
  const prevFullName = document.getElementById('prevFullName');
  const prevJobTitle = document.getElementById('prevJobTitle');
  const prevCompanyBadge = document.getElementById('prevCompanyBadge');
  const prevCompanyName = document.getElementById('prevCompanyName');
  const prevDob = document.getElementById('prevDob');
  const prevPhoneType = document.getElementById('prevPhoneType');
  const prevPhoneText = document.getElementById('prevPhoneText');
  const prevEmailText = document.getElementById('prevEmailText');
  const prevWebsiteText = document.getElementById('prevWebsiteText');
  const prevAddressText = document.getElementById('prevAddressText');

  // --- QR Output Elements ---
  const qrCanvasContainer = document.getElementById('qrCanvasContainer');
  const qrCardName = document.getElementById('qrCardName');
  const txtDirectLink = document.getElementById('txtDirectLink');
  const btnCopyLink = document.getElementById('btnCopyLink');
  const btnOpenCard = document.getElementById('btnOpenCard');
  const btnDownloadPNG = document.getElementById('btnDownloadPNG');
  const btnDownloadSVG = document.getElementById('btnDownloadSVG');
  const qrColorButtons = document.querySelectorAll('.qr-color-btn');
  const lblCurrentQrColor = document.getElementById('lblCurrentQrColor');
  const qrStyleSelect = document.getElementById('qrStyleSelect');

  let qrCodeInstance = null;
  let selectedQrColor = '#000000'; // Default Classic Black

  // 1. Tab Switching Logic (Mobile Preview vs QR Code)
  function switchRightPanel(tab) {
    if (tab === 'qr') {
      panelPreview.classList.add('hidden');
      panelQR.classList.remove('hidden');

      tabBtnQR.className = 'flex-1 py-2 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition bg-white text-slate-900 shadow-sm cursor-pointer';
      tabBtnPreview.className = 'flex-1 py-2 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition text-slate-600 hover:text-slate-900 cursor-pointer';
    } else {
      panelQR.classList.add('hidden');
      panelPreview.classList.remove('hidden');

      tabBtnPreview.className = 'flex-1 py-2 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition bg-white text-slate-900 shadow-sm cursor-pointer';
      tabBtnQR.className = 'flex-1 py-2 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 transition text-slate-600 hover:text-slate-900 cursor-pointer';
    }
  }

  tabBtnPreview.addEventListener('click', () => switchRightPanel('preview'));
  tabBtnQR.addEventListener('click', () => switchRightPanel('qr'));
  if (headerBtnShowQR) {
    headerBtnShowQR.addEventListener('click', () => switchRightPanel('qr'));
  }
  // Set QR tab as default on page load
  switchRightPanel('qr');

  // --- Toast Notification Logic ---
  let toastTimer = null;
  function showToast(title = 'QR Saved Successfully! 🎉', message = 'Your dynamic vCard is saved in the database.') {
    const toast = document.getElementById('saveToast');
    const toastTitle = document.getElementById('toastTitle');
    const toastMsg = document.getElementById('toastMessage');
    if (!toast) return;
    if (toastTitle) toastTitle.textContent = title;
    if (toastMsg) toastMsg.textContent = message;

    toast.classList.remove('-translate-y-6', 'opacity-0', 'pointer-events-none');
    toast.classList.add('translate-y-0', 'opacity-100');

    if (window.lucide) lucide.createIcons();

    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      hideToast();
    }, 4500);
  }

  function hideToast() {
    const toast = document.getElementById('saveToast');
    if (!toast) return;
    toast.classList.add('-translate-y-6', 'opacity-0', 'pointer-events-none');
    toast.classList.remove('translate-y-0', 'opacity-100');
  }
  window.showToast = showToast;
  window.hideToast = hideToast;

  // 1b. OTP Verification Handlers (Self-Service Editing)
  function openOtpModal(prefillEmail = '') {
    const modal = otpModal || document.getElementById('otpModal');
    if (!modal) return;
    if (prefillEmail && inputOtpEmail) {
      inputOtpEmail.value = prefillEmail;
    }
    if (otpStep1) otpStep1.classList.remove('hidden');
    if (otpStep2) otpStep2.classList.add('hidden');
    if (otpRequestStatus) {
      otpRequestStatus.classList.add('hidden');
      otpRequestStatus.textContent = '';
    }
    if (otpVerifyStatus) {
      otpVerifyStatus.classList.add('hidden');
      otpVerifyStatus.textContent = '';
    }
    if (inputOtpDigits) inputOtpDigits.value = '';

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.style.display = 'flex';

    if (inputOtpEmail && !prefillEmail) {
      setTimeout(() => inputOtpEmail.focus(), 50);
    }
    if (window.lucide) lucide.createIcons();
  }

  function closeOtpModal() {
    const modal = otpModal || document.getElementById('otpModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.style.display = 'none';
  }

  // Expose globally so button clicks and links always work
  window.openOtpModal = openOtpModal;
  window.closeOtpModal = closeOtpModal;

  if (btnOpenEditModal) {
    btnOpenEditModal.addEventListener('click', (e) => {
      e.preventDefault();
      openOtpModal();
    });
  }

  if (btnCancelOtpModalX) {
    btnCancelOtpModalX.addEventListener('click', (e) => {
      e.preventDefault();
      closeOtpModal();
    });
  }

  // Click outside modal content to close
  if (otpModal) {
    otpModal.addEventListener('click', (e) => {
      if (e.target === otpModal) {
        closeOtpModal();
      }
    });
  }

  // ESC key to close modal
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && otpModal && otpModal.style.display !== 'none') {
      closeOtpModal();
    }
  });

  // Step 1: Request OTP
  if (formRequestOtp) {
    formRequestOtp.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = inputOtpEmail.value.trim();
      if (!email) return;

      btnSubmitRequestOtp.disabled = true;
      btnSubmitRequestOtp.innerHTML = `<span>Sending Code...</span>`;
      otpRequestStatus.classList.add('hidden');

      try {
        const res = await fetch('api.php?action=request_otp', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email })
        });
        const data = await res.json();

        if (data.success) {
          if (otpTargetEmailText) otpTargetEmailText.textContent = data.email || email;
          otpStep1.classList.add('hidden');
          otpStep2.classList.remove('hidden');
          if (inputOtpDigits) {
            inputOtpDigits.value = '';
            inputOtpDigits.focus();
          }
          if (otpVerifyStatus) {
            otpVerifyStatus.className = 'p-3 rounded-xl text-xs font-medium text-left bg-emerald-50 text-emerald-800 border border-emerald-200 block';
            otpVerifyStatus.textContent = '✓ ' + (data.message || 'Verification code sent!');
            otpVerifyStatus.classList.remove('hidden');
          }
        } else {
          otpRequestStatus.className = 'p-3 rounded-xl text-xs font-medium text-left bg-rose-50 text-rose-800 border border-rose-200 block';
          otpRequestStatus.textContent = data.message || 'Could not send verification code.';
          otpRequestStatus.classList.remove('hidden');
        }
      } catch (err) {
        otpRequestStatus.className = 'p-3 rounded-xl text-xs font-medium text-left bg-rose-50 text-rose-800 border border-rose-200 block';
        otpRequestStatus.textContent = 'Server connection error. Please try again.';
        otpRequestStatus.classList.remove('hidden');
      } finally {
        btnSubmitRequestOtp.disabled = false;
        btnSubmitRequestOtp.innerHTML = `<i data-lucide="send" class="w-3.5 h-3.5"></i><span>Send Verification Code</span>`;
        if (window.lucide) lucide.createIcons();
      }
    });
  }

  // Step 2: Verify OTP
  if (formVerifyOtp) {
    formVerifyOtp.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = inputOtpEmail.value.trim();
      const otp = inputOtpDigits.value.trim();
      if (!email || !otp) return;

      btnSubmitVerifyOtp.disabled = true;
      btnSubmitVerifyOtp.innerHTML = `<span>Verifying Code...</span>`;
      otpVerifyStatus.classList.add('hidden');

      try {
        const res = await fetch('api.php?action=verify_otp', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, otp })
        });
        const data = await res.json();

        if (data.success) {
          otpVerifyStatus.className = 'p-3 rounded-xl text-xs font-medium text-left bg-emerald-50 text-emerald-800 border border-emerald-200 block';
          otpVerifyStatus.textContent = '✓ ' + (data.message || 'Verification successful! Loading card...');
          otpVerifyStatus.classList.remove('hidden');

          setTimeout(() => {
            window.location.href = `index.html?edit=${encodeURIComponent(data.slug)}&token=${encodeURIComponent(data.edit_token)}`;
          }, 700);
        } else {
          otpVerifyStatus.className = 'p-3 rounded-xl text-xs font-medium text-left bg-rose-50 text-rose-800 border border-rose-200 block';
          otpVerifyStatus.textContent = data.message || 'Verification failed. Please check your code.';
          otpVerifyStatus.classList.remove('hidden');
          btnSubmitVerifyOtp.disabled = false;
          btnSubmitVerifyOtp.innerHTML = `<i data-lucide="shield-check" class="w-4 h-4"></i><span>Verify & Unlock Card</span>`;
          if (window.lucide) lucide.createIcons();
        }
      } catch (err) {
        otpVerifyStatus.className = 'p-3 rounded-xl text-xs font-medium text-left bg-rose-50 text-rose-800 border border-rose-200 block';
        otpVerifyStatus.textContent = 'Server connection error. Please try again.';
        otpVerifyStatus.classList.remove('hidden');
        btnSubmitVerifyOtp.disabled = false;
        btnSubmitVerifyOtp.innerHTML = `<i data-lucide="shield-check" class="w-4 h-4"></i><span>Verify & Unlock Card</span>`;
        if (window.lucide) lucide.createIcons();
      }
    });
  }

  // Switch back to Step 1
  if (btnOtpBackToStep1) {
    btnOtpBackToStep1.addEventListener('click', () => {
      otpStep2.classList.add('hidden');
      otpStep1.classList.remove('hidden');
      if (inputOtpEmail) inputOtpEmail.focus();
    });
  }

  // Resend code trigger
  if (btnResendOtp) {
    btnResendOtp.addEventListener('click', () => {
      if (formRequestOtp) {
        formRequestOtp.dispatchEvent(new Event('submit'));
      }
    });
  }

  // 2. Initialize intl-tel-input with Country Flags (Malaysia +60 default)
  const iti = window.intlTelInput(phoneInputField, {
    initialCountry: 'my',
    preferredCountries: ['my', 'sg', 'id', 'th', 'ph', 'gb', 'us', 'au'],
    separateDialCode: true,
    utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/utils.js'
  });

  // 3. Set default Auto-detected Base URL (set silently; input is hidden from users)
  let defaultCardBaseUrl = '';
  if (window.location.protocol === 'file:') {
    const fullPath = window.location.pathname.replace(/\\/g, '/');
    const htdocsIndex = fullPath.indexOf('htdocs/');
    if (htdocsIndex !== -1) {
      const relFromHtdocs = fullPath.substring(htdocsIndex + 'htdocs/'.length);
      const folderPath = relFromHtdocs.substring(0, relFromHtdocs.lastIndexOf('/'));
      defaultCardBaseUrl = `http://localhost/${folderPath}/card.html`;
    } else {
      defaultCardBaseUrl = 'http://localhost/ADHOC/QR%20VCard/card.html';
    }
  } else {
    const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    defaultCardBaseUrl = `${window.location.origin}${currentPath}/card.html`;
  }
  // Set the hidden input to the default. detectServerLanIp() may override this with the LAN IP.
  customBaseUrlInput.value = defaultCardBaseUrl;

  // QR Pattern Style Change Event
  if (qrStyleSelect) {
    qrStyleSelect.addEventListener('change', () => {
      generateQRCode();
    });
  }

  // 4. Theme Color Handlers (Card Colors)
  function updateThemeColors(primary, secondary) {
    document.documentElement.style.setProperty('--preview-primary', primary);
    document.documentElement.style.setProperty('--preview-secondary', secondary);
    customColorPicker.value = primary;
    customColorHex.value = primary.toUpperCase();
    customColorSecondary.value = secondary;
    customColorSecondaryHex.value = secondary.toUpperCase();
  }

  // Initialize Default Theme Colors (Inputs start empty for a clean new-user experience)
  updateThemeColors('#2563eb', '#1d4ed8');

  swatches.forEach(swatch => {
    swatch.addEventListener('click', () => {
      swatches.forEach(s => s.classList.remove('active'));
      swatch.classList.add('active');
      const primary = swatch.getAttribute('data-color');
      const secondary = swatch.getAttribute('data-secondary') || primary;
      updateThemeColors(primary, secondary);
      updateLivePreview();
    });
  });

  customColorPicker.addEventListener('input', (e) => {
    swatches.forEach(s => s.classList.remove('active'));
    customColorHex.value = e.target.value.toUpperCase();
    updateThemeColors(e.target.value, customColorSecondary.value);
    updateLivePreview();
  });

  customColorHex.addEventListener('change', (e) => {
    let val = e.target.value;
    if (!val.startsWith('#')) val = '#' + val;
    if (/^#[0-9A-F]{6}$/i.test(val)) {
      customColorPicker.value = val;
      updateThemeColors(val, customColorSecondary.value);
      updateLivePreview();
    }
  });

  customColorSecondary.addEventListener('input', (e) => {
    swatches.forEach(s => s.classList.remove('active'));
    customColorSecondaryHex.value = e.target.value.toUpperCase();
    updateThemeColors(customColorPicker.value, e.target.value);
    updateLivePreview();
  });

  customColorSecondaryHex.addEventListener('change', (e) => {
    let val = e.target.value;
    if (!val.startsWith('#')) val = '#' + val;
    if (/^#[0-9A-F]{6}$/i.test(val)) {
      customColorSecondary.value = val;
      updateThemeColors(customColorPicker.value, val);
      updateLivePreview();
    }
  });

  // 6. QR Code Color Selector Logic (Black vs DuitNow Pink vs Navy)
  qrColorButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      qrColorButtons.forEach(b => {
        b.classList.remove('active', 'bg-slate-900', 'text-white', 'border-slate-900');
        b.classList.add('bg-white', 'text-slate-700', 'border-slate-200');
      });
      btn.classList.add('active', 'bg-slate-900', 'text-white', 'border-slate-900');
      btn.classList.remove('bg-white', 'text-slate-700', 'border-slate-200');

      selectedQrColor = btn.getAttribute('data-qr-color') || '#000000';
      lblCurrentQrColor.textContent = selectedQrColor.toUpperCase();
      generateQRCode();
    });
  });

  // 7. Profile Photo Cropping & Scaling (Cropper.js)
  function openCropper(imageSrc) {
    originalImageSrc = imageSrc;
    cropperImage.src = imageSrc;
    cropModal.classList.remove('hidden');
    cropModal.classList.add('flex');

    if (cropperInstance) {
      cropperInstance.destroy();
    }

    cropZoomSlider.value = 1;
    zoomLevelText.textContent = '1.0x';

    cropperInstance = new Cropper(cropperImage, {
      aspectRatio: 1,
      viewMode: 1,
      dragMode: 'move',
      autoCropArea: 0.85,
      restore: false,
      guides: true,
      center: true,
      highlight: false,
      cropBoxMovable: true,
      cropBoxResizable: true,
      toggleDragModeOnDblclick: false,
      ready() {},
      zoom(e) {
        const ratio = Math.min(3, Math.max(0.2, e.detail.ratio));
        cropZoomSlider.value = ratio.toFixed(2);
        zoomLevelText.textContent = ratio.toFixed(1) + 'x';
      }
    });

    if (window.lucide) lucide.createIcons();
  }

  function closeCropper() {
    cropModal.classList.add('hidden');
    cropModal.classList.remove('flex');
    if (cropperInstance) {
      cropperInstance.destroy();
      cropperInstance = null;
    }
  }

  btnCancelCrop.addEventListener('click', closeCropper);
  btnCancelCropX.addEventListener('click', closeCropper);

  cropZoomSlider.addEventListener('input', (e) => {
    if (cropperInstance) {
      const zoomVal = parseFloat(e.target.value);
      cropperInstance.zoomTo(zoomVal);
      zoomLevelText.textContent = zoomVal.toFixed(1) + 'x';
    }
  });

  btnRotateLeft.addEventListener('click', () => {
    if (cropperInstance) cropperInstance.rotate(-90);
  });

  btnRotateRight.addEventListener('click', () => {
    if (cropperInstance) cropperInstance.rotate(90);
  });

  btnResetCrop.addEventListener('click', () => {
    if (cropperInstance) {
      cropperInstance.reset();
      cropZoomSlider.value = 1;
      zoomLevelText.textContent = '1.0x';
    }
  });

  // When user selects a file from disk
  photoFileInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
      alert('Photo must be less than 5MB');
      photoFileInput.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
      openCropper(event.target.result);
    };
    reader.readAsDataURL(file);
  });

  // Re-adjust crop on current photo
  btnAdjustCrop.addEventListener('click', () => {
    if (originalImageSrc) {
      openCropper(originalImageSrc);
    }
  });

  // When user confirms crop in modal
  btnApplyCrop.addEventListener('click', async () => {
    if (!cropperInstance) return;

    const croppedCanvas = cropperInstance.getCroppedCanvas({
      width: 400,
      height: 400,
      imageSmoothingEnabled: true,
      imageSmoothingQuality: 'high'
    });

    closeCropper();

    uploadStatusText.textContent = 'Saving...';
    uploadStatusText.className = 'text-[11px] text-blue-600 font-medium';

    const croppedDataUrl = croppedCanvas.toDataURL('image/jpeg', 0.88);
    uploadAvatarImg.src = croppedDataUrl;
    uploadAvatarImg.classList.remove('hidden');
    uploadAvatarInitials.classList.add('hidden');
    prevAvatarImg.src = croppedDataUrl;
    prevAvatarImg.classList.remove('hidden');
    prevInitials.classList.add('hidden');

    croppedCanvas.toBlob(async (blob) => {
      if (!blob) return;

      try {
        const formData = new FormData();
        formData.append('photo', blob, 'avatar.jpg');

        const res = await fetch('upload.php', {
          method: 'POST',
          body: formData
        });
        const result = await res.json();
        if (result.success && result.url) {
          photoUrlInput.value = result.url;
          uploadStatusText.textContent = '✓ Saved Face';
          uploadStatusText.className = 'text-[11px] text-emerald-600 font-medium';
          btnRemovePhoto.classList.remove('hidden');
          btnRemovePhoto.classList.add('inline-flex');
          btnAdjustCrop.classList.remove('hidden');
          btnAdjustCrop.classList.add('inline-flex');
          updateLivePreview();
          generateQRCode();
        } else {
          throw new Error(result.message || 'Upload failed');
        }
      } catch (err) {
        console.warn('Server upload unavailable, using client-side cropped image:', err);
        photoUrlInput.value = croppedDataUrl;
        uploadStatusText.textContent = '✓ Ready (Local)';
        uploadStatusText.className = 'text-[11px] text-emerald-600 font-medium';
        btnRemovePhoto.classList.remove('hidden');
        btnRemovePhoto.classList.add('inline-flex');
        btnAdjustCrop.classList.remove('hidden');
        btnAdjustCrop.classList.add('inline-flex');
        updateLivePreview();
        generateQRCode();
      }
    }, 'image/jpeg', 0.88);
  });

  // Remove Photo
  btnRemovePhoto.addEventListener('click', () => {
    photoFileInput.value = '';
    photoUrlInput.value = '';
    originalImageSrc = null;
    uploadStatusText.textContent = '';
    btnRemovePhoto.classList.add('hidden');
    btnRemovePhoto.classList.remove('inline-flex');
    btnAdjustCrop.classList.add('hidden');
    btnAdjustCrop.classList.remove('inline-flex');
    updateLivePreview();
    generateQRCode();
  });

  // 8. Gather form data object (formats DOB as DD-MM-YYYY)
  function getFormData() {
    let fullPhone = '';
    const rawNumber = phoneInputField.value.trim();
    if (rawNumber) {
      if (iti.isValidNumber && iti.isValidNumber()) {
        fullPhone = iti.getNumber();
      } else {
        const countryData = iti.getSelectedCountryData();
        const dialCode = countryData.dialCode ? `+${countryData.dialCode} ` : '';
        fullPhone = `${dialCode}${rawNumber}`;
      }
    }

    const rawDob = document.getElementById('dob').value;
    const formattedDob = formatDobDisplay(rawDob);

    return {
      firstName: document.getElementById('firstName').value.trim(),
      lastName: document.getElementById('lastName').value.trim(),
      photoUrl: photoUrlInput.value.trim(),
      company: document.getElementById('company').value.trim(),
      jobTitle: document.getElementById('jobTitle').value.trim(),
      dob: formattedDob,
      phoneType: document.getElementById('phoneType').value,
      phone: fullPhone,
      email: document.getElementById('email').value.trim(),
      website: document.getElementById('website').value.trim(),
      address: document.getElementById('address').value.trim(),
      city: document.getElementById('city').value.trim(),
      state: document.getElementById('state').value.trim(),
      postalCode: document.getElementById('postalCode').value.trim(),
      country: document.getElementById('country').value.trim(),
      themeColor: customColorPicker.value,
      secondaryColor: customColorSecondary.value
    };
  }

  // 9. Live Mobile Preview Updater
  function updateLivePreview() {
    const data = getFormData();
    const fullName = `${data.firstName} ${data.lastName}`.trim() || 'Your Name';
    const initials = ((data.firstName?.[0] || '') + (data.lastName?.[0] || '')).toUpperCase() || 'VC';

    prevFullName.textContent = fullName;
    prevJobTitle.textContent = data.jobTitle || 'Job Title';

    if (data.photoUrl) {
      prevAvatarImg.src = data.photoUrl;
      prevAvatarImg.classList.remove('hidden');
      prevInitials.classList.add('hidden');

      uploadAvatarImg.src = data.photoUrl;
      uploadAvatarImg.classList.remove('hidden');
      uploadAvatarInitials.classList.add('hidden');
    } else {
      prevAvatarImg.classList.add('hidden');
      prevInitials.textContent = initials;
      prevInitials.classList.remove('hidden');

      uploadAvatarImg.classList.add('hidden');
      uploadAvatarInitials.textContent = initials;
      uploadAvatarInitials.classList.remove('hidden');
    }

    if (data.company) {
      prevCompanyName.textContent = data.company;
      prevCompanyBadge.classList.remove('hidden');
    } else {
      prevCompanyBadge.classList.add('hidden');
    }

    if (data.dob) {
      prevDob.querySelector('span').textContent = data.dob;
      prevDob.classList.remove('hidden');
    } else {
      prevDob.classList.add('hidden');
    }

    prevPhoneType.textContent = `${data.phoneType || 'Phone'} Phone`;
    prevPhoneText.textContent = data.phone || 'No phone entered';
    prevEmailText.textContent = data.email || 'No email entered';
    prevWebsiteText.textContent = data.website || 'No website entered';

    const fullAddress = [data.address, data.city, data.state, data.postalCode, data.country]
      .filter(Boolean).join(', ');
    prevAddressText.textContent = fullAddress || 'No address entered';
  }

  // Attach live listeners to form inputs
  const allInputs = form.querySelectorAll('input, select');
  allInputs.forEach(input => {
    input.addEventListener('input', () => {
      updateLivePreview();
    });
    input.addEventListener('change', () => {
      updateLivePreview();
    });
  });
  phoneInputField.addEventListener('countrychange', updateLivePreview);

  // 10. QR Code Generator Function (Always Dynamic)
  function generateQRCode() {
    const rawData = getFormData();
    let finalUrl = '';

    const currentBase = customBaseUrlInput.value.trim() || defaultCardBaseUrl;

    // Use current card slug if editing, or auto-generate from name
    let slug = (cardIdInput && cardIdInput.dataset.currentSlug) ? cardIdInput.dataset.currentSlug : '';
    if (!slug) {
      slug = ((rawData.firstName || '') + '-' + (rawData.lastName || '')).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'card';
    }

    let appBase = currentBase;
    const cardHtmlIdx = appBase.indexOf('/card.html');
    if (cardHtmlIdx !== -1) {
      appBase = appBase.substring(0, cardHtmlIdx);
    } else {
      const lastSlash = appBase.lastIndexOf('/');
      if (lastSlash !== -1) appBase = appBase.substring(0, lastSlash);
    }

    finalUrl = `${appBase}/c/?id=${encodeURIComponent(slug)}`;

    // Update Links
    txtDirectLink.value = finalUrl;
    btnOpenCard.href = finalUrl;
    qrCardName.textContent = `${rawData.firstName} ${rawData.lastName}`.trim() || 'vCard';

    // Determine Colors
    let qrDotColor = selectedQrColor || '#000000';
    let qrCornerColor = qrDotColor;

    // User Selected QR Pattern Style
    const selectedStyle = qrStyleSelect ? qrStyleSelect.value : 'rounded';
    let patternType = 'rounded';
    let cornerSquareType = 'extra-rounded';
    let cornerDotType = 'dot';

    if (selectedStyle === 'square') {
      patternType = 'square';
      cornerSquareType = 'square';
      cornerDotType = 'square';
    } else if (selectedStyle === 'dots') {
      patternType = 'dots';
      // extra-rounded preserves the 7x7 outer finder frame so phone cameras can lock on
      cornerSquareType = 'extra-rounded';
      cornerDotType = 'dot';
    } else if (selectedStyle === 'rounded') {
      patternType = 'rounded';
      cornerSquareType = 'extra-rounded';
      cornerDotType = 'dot';
    }

    // Configure QRCodeStyling
    const qrOptions = {
      width: 220,
      height: 220,
      type: 'canvas',
      data: finalUrl,
      image: '',
      qrOptions: {
        errorCorrectionLevel: 'M'
      },
      dotsOptions: {
        color: qrDotColor,
        type: patternType
      },
      cornersSquareOptions: {
        color: qrCornerColor,
        type: cornerSquareType
      },
      cornersDotOptions: {
        color: qrCornerColor,
        type: cornerDotType
      },
      backgroundOptions: {
        color: '#ffffff'
      },
      imageOptions: {
        crossOrigin: 'anonymous',
        margin: 6
      }
    };

    qrCanvasContainer.innerHTML = '';
    qrCodeInstance = new QRCodeStyling(qrOptions);
    qrCodeInstance.append(qrCanvasContainer);
  }

  // 11. Form Submit: Save to SQLite (Always Dynamic, Auto-slug)
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = getFormData();
    const existingId = cardIdInput.value ? parseInt(cardIdInput.value) : null;
    const currentSlug = cardIdInput.dataset.currentSlug || '';

    const qrStyle = qrStyleSelect ? qrStyleSelect.value : 'rounded';

    const payload = {
      id: existingId,
      slug: currentSlug,
      edit_token: (editTokenInput ? editTokenInput.value : ''),
      isDynamic: 1,
      firstName: formData.firstName,
      lastName: formData.lastName,
      company: formData.company,
      jobTitle: formData.jobTitle,
      dob: formData.dob,
      phoneType: formData.phoneType,
      phone: formData.phone,
      email: formData.email,
      website: formData.website,
      address: formData.address,
      city: formData.city,
      state: formData.state,
      postalCode: formData.postalCode,
      country: formData.country,
      photoUrl: formData.photoUrl,
      themeColor: formData.themeColor,
      secondaryColor: formData.secondaryColor,
      qrColor: selectedQrColor,
      qrPattern: qrStyle,
      qrCornerStyle: (qrStyle === 'square') ? 'square' : 'extra-rounded'
    };

    try {
      btnGenerateQR.disabled = true;
      if (btnFormSubmit) btnFormSubmit.disabled = true;
      if (btnGenerateQRText) btnGenerateQRText.textContent = 'Saving vCard...';
      if (btnFormSubmitText) btnFormSubmitText.textContent = 'Saving vCard...';

      const res = await fetch('api.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await res.json();

      if (result.success) {
        cardIdInput.value = result.id;
        cardIdInput.dataset.currentSlug = result.slug;

        // Show toast notification that QR code is saved to database
        showToast('QR Saved Successfully! 🎉', 'Your digital card & dynamic QR code have been saved.');

        // If admin was editing
        if (existingId && isAdminMode) {
          window.history.replaceState({}, '', `?edit=${encodeURIComponent(result.slug)}&admin=1`);
          showEditNotice(formData.firstName + ' ' + formData.lastName, false);
          generateQRCode();
          switchRightPanel('qr');
        } else {
          // Self-service edit or new creation: show completion screen
          const tokenVal = editTokenInput ? editTokenInput.value : '';
          if (tokenVal) {
            window.history.replaceState({}, '', `?edit=${encodeURIComponent(result.slug)}&token=${encodeURIComponent(tokenVal)}`);
            showEditNotice(formData.firstName + ' ' + formData.lastName, true);
          }
          generateQRCode();
          showSuccessOverlay(formData, result.slug);
        }
      } else {
        alert('Save failed: ' + (result.message || 'Unknown error'));
      }
    } catch (err) {
      console.error('Error saving dynamic card:', err);
      alert('Server error while saving card.');
    } finally {
      btnGenerateQR.disabled = false;
      if (btnFormSubmit) btnFormSubmit.disabled = false;
      const btnLabel = cardIdInput.value ? 'Save Changes to Dynamic Card' : 'Generate vCard QR Code';
      if (btnGenerateQRText) btnGenerateQRText.textContent = btnLabel;
      if (btnFormSubmitText) btnFormSubmitText.textContent = btnLabel;
    }
  });

  // 12. Download Handlers
  btnDownloadPNG.addEventListener('click', () => {
    if (!qrCodeInstance) generateQRCode();
    const data = getFormData();
    const name = `${data.firstName}_${data.lastName}_vcard_qr`.toLowerCase().replace(/[^a-z0-9]/g, '_');
    qrCodeInstance.download({ name: name || 'vcard_qr', extension: 'png' });
  });

  btnDownloadSVG.addEventListener('click', () => {
    if (!qrCodeInstance) generateQRCode();
    const data = getFormData();
    const name = `${data.firstName}_${data.lastName}_vcard_qr`.toLowerCase().replace(/[^a-z0-9]/g, '_');
    qrCodeInstance.download({ name: name || 'vcard_qr', extension: 'svg' });
  });

  // 12b. Success Overlay — shown to customers after first-time card save
  function showSuccessOverlay(formData, slug) {
    const fullName = `${formData.firstName} ${formData.lastName}`.trim() || 'Your Card';
    if (successCardName) successCardName.textContent = fullName;

    // Build QR URL using the same logic as generateQRCode
    const currentBase = customBaseUrlInput.value.trim() || defaultCardBaseUrl;
    let appBase = currentBase;
    const cardHtmlIdx = appBase.indexOf('/card.html');
    if (cardHtmlIdx !== -1) {
      appBase = appBase.substring(0, cardHtmlIdx);
    } else {
      const lastSlash = appBase.lastIndexOf('/');
      if (lastSlash !== -1) appBase = appBase.substring(0, lastSlash);
    }
    const finalUrl = `${appBase}/c/?id=${encodeURIComponent(slug)}`;

    // QR colors
    let qrDotColor = selectedQrColor || '#000000';
    let qrCornerColor = qrDotColor;
    const selectedStyle = qrStyleSelect ? qrStyleSelect.value : 'rounded';
    let patternType = 'rounded', cornerSquareType = 'extra-rounded', cornerDotType = 'dot';
    if (selectedStyle === 'square') { patternType = 'square'; cornerSquareType = 'square'; cornerDotType = 'square'; }
    else if (selectedStyle === 'dots') { patternType = 'dots'; cornerSquareType = 'extra-rounded'; cornerDotType = 'dot'; }

    // Generate QR in overlay
    if (successQrContainer) {
      successQrContainer.innerHTML = '';
      successQrInstance = new QRCodeStyling({
        width: 220,
        height: 220,
        type: 'canvas',
        data: finalUrl,
        qrOptions: {
          errorCorrectionLevel: 'M'
        },
        dotsOptions: { color: qrDotColor, type: patternType },
        cornersSquareOptions: { color: qrCornerColor, type: cornerSquareType },
        cornersDotOptions: { color: qrCornerColor, type: cornerDotType },
        backgroundOptions: { color: '#ffffff' }
      });
      successQrInstance.append(successQrContainer);
    }

    // Show overlay
    if (successBtnViewCard) {
      successBtnViewCard.href = finalUrl;
    }
    successOverlay.classList.remove('hidden');
    successOverlay.classList.add('flex');
    successOverlay.style.display = 'flex';
    if (window.lucide) lucide.createIcons();
  }

  function closeSuccessOverlay() {
    const overlay = successOverlay || document.getElementById('successOverlay');
    if (!overlay) return;
    overlay.style.display = 'none';
    overlay.classList.add('hidden');
    overlay.classList.remove('flex');
    switchRightPanel('qr');
  }
  window.closeSuccessOverlay = closeSuccessOverlay;

  // Success overlay exit / close button bindings
  if (btnExitSuccessOverlayX) {
    btnExitSuccessOverlayX.addEventListener('click', (e) => {
      e.preventDefault();
      closeSuccessOverlay();
    });
  }

  if (successBtnExit) {
    successBtnExit.addEventListener('click', (e) => {
      e.preventDefault();
      closeSuccessOverlay();
    });
  }

  // Success overlay download buttons
  if (successBtnDownloadPNG) {
    successBtnDownloadPNG.addEventListener('click', () => {
      if (successQrInstance) {
        const data = getFormData();
        const name = `${data.firstName}_${data.lastName}_vcard_qr`.toLowerCase().replace(/[^a-z0-9]/g, '_');
        successQrInstance.download({ name: name || 'vcard_qr', extension: 'png' });
      }
    });
  }
  if (successBtnDownloadSVG) {
    successBtnDownloadSVG.addEventListener('click', () => {
      if (successQrInstance) {
        const data = getFormData();
        const name = `${data.firstName}_${data.lastName}_vcard_qr`.toLowerCase().replace(/[^a-z0-9]/g, '_');
        successQrInstance.download({ name: name || 'vcard_qr', extension: 'svg' });
      }
    });
  }
  // "Create Another Card" button — reset form and dismiss overlay
  if (successBtnNewCard) {
    successBtnNewCard.addEventListener('click', () => {
      closeSuccessOverlay();
      form.reset();
      cardIdInput.value = '';
      cardIdInput.dataset.currentSlug = '';
      if (editTokenInput) editTokenInput.value = '';
      if (uploadAvatarImg) { uploadAvatarImg.src = ''; uploadAvatarImg.classList.add('hidden'); }
      if (uploadAvatarInitials) uploadAvatarInitials.classList.remove('hidden');
      if (photoUrlInput) photoUrlInput.value = '';
      if (btnRemovePhoto) { btnRemovePhoto.classList.add('hidden'); btnRemovePhoto.classList.remove('inline-flex'); }
      if (btnAdjustCrop) { btnAdjustCrop.classList.add('hidden'); btnAdjustCrop.classList.remove('inline-flex'); }
      if (editModeNotice) editModeNotice.classList.add('hidden');
      if (btnGenerateQRText) btnGenerateQRText.textContent = 'Generate vCard QR Code';
      if (btnFormSubmitText) btnFormSubmitText.textContent = 'Generate vCard QR Code';
      // Restore QR customise controls (they get locked during edit mode)
      const qrCustomizeSection = document.getElementById('qrCustomizeSection');
      const qrCustomizeLocked  = document.getElementById('qrCustomizeLocked');
      if (qrCustomizeSection && qrCustomizeLocked) {
        qrCustomizeSection.querySelectorAll(':scope > div:not(#qrCustomizeLocked)').forEach(el => el.classList.remove('hidden'));
        qrCustomizeLocked.classList.add('hidden');
      }
      switchRightPanel('qr');
      updateLivePreview();
      generateQRCode();
      window.history.replaceState({}, '', window.location.pathname);
    });
  }

  // 13. Copy Link Handler
  btnCopyLink.addEventListener('click', () => {
    const link = txtDirectLink.value;
    if (!link) return;
    navigator.clipboard.writeText(link).then(() => {
      const originalHtml = btnCopyLink.innerHTML;
      btnCopyLink.innerHTML = `<i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i>`;
      if (window.lucide) lucide.createIcons();
      setTimeout(() => {
        btnCopyLink.innerHTML = originalHtml;
        if (window.lucide) lucide.createIcons();
      }, 2000);
    });
  });

  customBaseUrlInput.addEventListener('change', () => {
    generateQRCode();
  });

  // 14. Check URL Parameters for Edit Mode (?edit=slug&token=...)
  async function checkUrlParamsAndLoadCard() {
    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('action') || '';
    const emailParam = urlParams.get('email') || '';

    // If navigated from card with action=edit, pop up OTP modal directly
    if (action === 'edit') {
      openOtpModal(emailParam);
    }

    const editSlug = urlParams.get('edit') || urlParams.get('slug');
    const token = urlParams.get('token') || '';

    if (!editSlug) return;

    if (token && editTokenInput) {
      editTokenInput.value = token;
    }

    try {
      const res = await fetch(`api.php?action=get&slug=${encodeURIComponent(editSlug)}`);
      const data = await res.json();
      if (!data.success || !data.card) {
        console.warn('Card to edit not found:', editSlug);
        return;
      }

      const card = data.card;
      cardIdInput.value = card.id;
      cardIdInput.dataset.currentSlug = card.slug;

      // Populate form fields
      document.getElementById('firstName').value = card.first_name || '';
      document.getElementById('lastName').value = card.last_name || '';
      document.getElementById('company').value = card.company || '';
      document.getElementById('jobTitle').value = card.job_title || '';
      document.getElementById('dob').value = parseDobToInput(card.dob || '');
      document.getElementById('phoneType').value = card.phone_type || 'Mobile';
      if (card.phone) {
        phoneInputField.value = card.phone;
        if (iti && iti.setNumber) iti.setNumber(card.phone);
      }
      document.getElementById('email').value = card.email || '';
      document.getElementById('website').value = card.website || '';
      document.getElementById('address').value = card.address || '';
      document.getElementById('city').value = card.city || '';
      document.getElementById('state').value = card.state || '';
      document.getElementById('postalCode').value = card.postal_code || '';
      document.getElementById('country').value = card.country || '';

      // Photo
      if (card.photo_url) {
        photoUrlInput.value = card.photo_url;
        uploadAvatarImg.src = card.photo_url;
        uploadAvatarImg.classList.remove('hidden');
        uploadAvatarInitials.classList.add('hidden');
        btnRemovePhoto.classList.remove('hidden');
        btnRemovePhoto.classList.add('inline-flex');
        btnAdjustCrop.classList.remove('hidden');
        btnAdjustCrop.classList.add('inline-flex');
      }

      // Theme Colors
      if (card.theme_color) {
        updateThemeColors(card.theme_color, card.secondary_color || card.theme_color);
      }

      // QR Color
      if (card.qr_color) {
        selectedQrColor = card.qr_color;
        qrColorButtons.forEach(btn => {
          if (btn.getAttribute('data-qr-color') === card.qr_color) {
            btn.classList.add('active', 'bg-slate-900', 'text-white', 'border-slate-900');
            btn.classList.remove('bg-white', 'text-slate-700', 'border-slate-200');
          } else {
            btn.classList.remove('active', 'bg-slate-900', 'text-white', 'border-slate-900');
            btn.classList.add('bg-white', 'text-slate-700', 'border-slate-200');
          }
        });
        lblCurrentQrColor.textContent = (selectedQrColor || '#000000').toUpperCase();
      }

      // QR Pattern
      if (card.qr_pattern && qrStyleSelect) {
        qrStyleSelect.value = card.qr_pattern;
      }

      // Show Edit Notice
      const isSelfService = Boolean(token);
      showEditNotice((card.first_name || '') + ' ' + (card.last_name || ''), isSelfService);

      updateLivePreview();
      generateQRCode();

    } catch (err) {
      console.error('Failed to load card for editing:', err);
    }
  }

  function showEditNotice(fullName, isSelfService = false) {
    if (editModeNotice) {
      editModeNotice.classList.remove('hidden');
      if (editingCardName) editingCardName.textContent = fullName.trim() || 'Staff Card';
      if (editingModeBadge) {
        editingModeBadge.textContent = isSelfService ? 'Editing Your Card:' : 'Admin Editing:';
      }
      if (isSelfService) {
        editModeNotice.className = 'p-4 rounded-2xl bg-blue-50 border border-blue-200 flex items-center justify-between';
      } else {
        editModeNotice.className = 'p-4 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-between';
      }
    }

    // Lock QR colour & pattern — these are set at creation only
    const qrCustomizeSection = document.getElementById('qrCustomizeSection');
    const qrCustomizeLocked  = document.getElementById('qrCustomizeLocked');
    if (qrCustomizeSection && qrCustomizeLocked) {
      // Hide the colour buttons + pattern dropdown
      qrCustomizeSection.querySelectorAll(':scope > div:not(#qrCustomizeLocked)').forEach(el => el.classList.add('hidden'));
      // Show the lock notice
      qrCustomizeLocked.classList.remove('hidden');
    }

    const btnLabel = 'Save Changes to Dynamic Card';
    if (btnGenerateQRText) {
      btnGenerateQRText.textContent = btnLabel;
    }
    if (btnFormSubmitText) {
      btnFormSubmitText.textContent = btnLabel;
    }
  }

  // 15. Auto-detect & silently apply the best QR destination URL for phone scanning
  // - If already accessed via LAN IP (e.g. 10.x.x.x), use the current origin — no change needed.
  // - If accessed via localhost/127.0.0.1, silently fetch the server's LAN IP and auto-apply it
  //   so QR codes scan correctly from any phone on the same Wi-Fi network.
  // Users never see or interact with this — it just works.
  async function detectServerLanIp() {
    try {
      const hostname = window.location.hostname;
      const isLocalAccess = (hostname === 'localhost' || hostname === '127.0.0.1');

      if (!isLocalAccess) {
        // Already accessed via IP/domain — current URL is fine for phones, no change needed.
        return;
      }

      // We're on localhost — need to swap to the real LAN IP for QR codes
      const res = await fetch('api.php?action=get_server_ip');
      const data = await res.json();

      if (data.success && data.ip && data.ip !== '127.0.0.1' && data.suggested_url) {
        // Silently apply the LAN IP — no button, no banner, no user action
        customBaseUrlInput.value = data.suggested_url;
        generateQRCode();
      }
    } catch (e) {
      // Ignore silently — localhost URL will still work on the same machine
    }
  }

  // Initial Run
  updateLivePreview();
  generateQRCode();
  checkUrlParamsAndLoadCard();
  detectServerLanIp();

  if (window.lucide) {
    lucide.createIcons();
  }
});
