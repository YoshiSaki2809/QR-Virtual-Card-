/**
 * Card Viewer Script (card.js)
 * Supports:
 * 1. Dynamic card loading by slug from SQLite API (api.php?action=get&slug=...)
 * 2. Static card payload decoding from URL hash/query (#data=...)
 * 3. DD-MM-YYYY birth date formatting
 * 4. Contact interaction: Call, WhatsApp, Email, Google Maps, vCard (.vcf) export, Web Share
 */

function formatDobDisplay(dateStr) {
  if (!dateStr) return '';
  if (/^\d{2}-\d{2}-\d{4}$/.test(dateStr)) return dateStr;
  const parts = dateStr.split('-');
  if (parts.length === 3 && parts[0].length === 4) {
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
  }
  return dateStr;
}

document.addEventListener('DOMContentLoaded', async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const slug = urlParams.get('slug') || urlParams.get('id');

  let cardData = null;

  // 1. Check Preloaded Server Data First (instant load when scanned from /c/)
  if (window.INITIAL_CARD_DATA && typeof window.INITIAL_CARD_DATA === 'object') {
    const c = window.INITIAL_CARD_DATA;
    cardData = {
      firstName: c.first_name,
      lastName: c.last_name,
      company: c.company,
      jobTitle: c.job_title,
      dob: formatDobDisplay(c.dob),
      phoneType: c.phone_type || 'Mobile',
      phone: c.phone,
      email: c.email,
      website: c.website,
      address: c.address,
      city: c.city,
      state: c.state,
      postalCode: c.postal_code,
      country: c.country,
      photoUrl: c.photo_url,
      themeColor: c.theme_color,
      secondaryColor: c.secondary_color
    };
  } else if (slug) {
    // 2. Fallback: Fetch Dynamic Card by Slug if opened via card.html directly
    try {
      const res = await fetch(`api.php?action=get&slug=${encodeURIComponent(slug)}`);
      const result = await res.json();
      if (result.success && result.card) {
        const c = result.card;
        cardData = {
          firstName: c.first_name,
          lastName: c.last_name,
          company: c.company,
          jobTitle: c.job_title,
          dob: formatDobDisplay(c.dob),
          phoneType: c.phone_type || 'Mobile',
          phone: c.phone,
          email: c.email,
          website: c.website,
          address: c.address,
          city: c.city,
          state: c.state,
          postalCode: c.postal_code,
          country: c.country,
          photoUrl: c.photo_url,
          themeColor: c.theme_color,
          secondaryColor: c.secondary_color
        };
      }
    } catch (err) {
      console.warn('Failed to load card from API:', err);
    }
  }

  // 3. If card is not found, show clean friendly error instead of dummy data
  if (!cardData) {
    document.body.innerHTML = `
      <div class="min-h-screen flex items-center justify-center p-4 bg-slate-100 font-sans">
        <div class="bg-white p-8 rounded-3xl shadow-xl max-w-sm w-full text-center border border-slate-200">
          <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 mx-auto flex items-center justify-center text-xl font-bold mb-3">!</div>
          <h1 class="text-lg font-black text-slate-900">Card Not Available</h1>
          <p class="text-xs text-slate-500 mt-1">This digital business card could not be loaded or has been removed.</p>
          <a href="index.html" class="inline-block mt-5 px-5 py-2.5 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition">Create a Card</a>
        </div>
      </div>
    `;
    return;
  }

  // 4. Apply Custom Colors & Themes
  const primaryColor = cardData.themeColor || '#2563eb';
  const secondaryColor = cardData.secondaryColor || primaryColor;

  document.documentElement.style.setProperty('--theme-primary', primaryColor);
  document.documentElement.style.setProperty('--theme-secondary', secondaryColor);

  // Update Page Title
  const fullName = `${cardData.firstName || ''} ${cardData.lastName || ''}`.trim() || 'Contact';
  document.title = `${fullName} - Digital Business Card`;

  // 5. Render Profile Header (Avatar / Photo)
  const initials = ((cardData.firstName?.[0] || '') + (cardData.lastName?.[0] || '')).toUpperCase() || 'VC';
  const elInitials = document.getElementById('avatarInitials');
  const elAvatarImg = document.getElementById('avatarImg');

  if (cardData.photoUrl) {
    if (elAvatarImg) {
      elAvatarImg.src = cardData.photoUrl;
      elAvatarImg.classList.remove('hidden');
      elAvatarImg.onerror = () => {
        elAvatarImg.classList.add('hidden');
        if (elInitials) elInitials.classList.remove('hidden');
      };
    }
    if (elInitials) elInitials.classList.add('hidden');
  } else {
    if (elAvatarImg) elAvatarImg.classList.add('hidden');
    if (elInitials) {
      elInitials.textContent = initials;
      elInitials.classList.remove('hidden');
    }
  }

  const elFullName = document.getElementById('cardFullName');
  if (elFullName) elFullName.textContent = fullName;

  // Job Title
  const elJobTitle = document.getElementById('cardJobTitle');
  if (elJobTitle) {
    if (cardData.jobTitle) {
      elJobTitle.textContent = cardData.jobTitle;
      elJobTitle.classList.remove('hidden');
    } else {
      elJobTitle.classList.add('hidden');
    }
  }

  // Company Name
  const elCompanyBadge = document.getElementById('cardCompanyBadge');
  const elCompanyName = document.getElementById('cardCompanyName');
  if (elCompanyBadge && elCompanyName) {
    if (cardData.company) {
      elCompanyName.textContent = cardData.company;
      elCompanyBadge.classList.remove('hidden');
      elCompanyBadge.classList.add('inline-flex');
    } else {
      elCompanyBadge.classList.add('hidden');
      elCompanyBadge.classList.remove('inline-flex');
    }
  }

  // Date of Birth (DD-MM-YYYY)
  const elDobContainer = document.getElementById('cardDobContainer');
  const elDob = document.getElementById('cardDob');
  if (elDobContainer && elDob) {
    if (cardData.dob) {
      elDob.textContent = formatDobDisplay(cardData.dob);
      elDobContainer.classList.remove('hidden');
      elDobContainer.classList.add('flex');
    } else {
      elDobContainer.classList.add('hidden');
      elDobContainer.classList.remove('flex');
    }
  }

  // 6. Render Quick Action Buttons & Details
  // Phone
  const elPhoneSection = document.getElementById('phoneSection');
  const elPhoneLabel = document.getElementById('cardPhoneLabel');
  const elPhoneText = document.getElementById('cardPhoneText');
  const elQuickCall = document.getElementById('btnQuickCall');
  const elLinkCall = document.getElementById('linkCall');
  const elLinkWhatsApp = document.getElementById('linkWhatsApp');

  if (cardData.phone) {
    const rawDigits = cardData.phone.replace(/[^0-9+]/g, '');
    const cleanWhatsApp = rawDigits.replace(/\+/g, '');

    if (elPhoneText) elPhoneText.textContent = cardData.phone;
    if (elPhoneLabel) elPhoneLabel.textContent = `${cardData.phoneType || 'Mobile'} Phone`;

    if (elPhoneSection) {
      elPhoneSection.classList.remove('hidden');
      elPhoneSection.classList.add('flex');
    }

    if (elQuickCall) {
      elQuickCall.href = `tel:${rawDigits}`;
      elQuickCall.classList.remove('opacity-40', 'pointer-events-none');
    }
    if (elLinkCall) elLinkCall.href = `tel:${rawDigits}`;
    if (elLinkWhatsApp) elLinkWhatsApp.href = `https://wa.me/${cleanWhatsApp}`;
  } else {
    if (elPhoneSection) {
      elPhoneSection.classList.add('hidden');
      elPhoneSection.classList.remove('flex');
    }
    if (elQuickCall) {
      elQuickCall.href = '#';
      elQuickCall.classList.add('opacity-40', 'pointer-events-none');
    }
  }

  // Email
  const elEmailSection = document.getElementById('emailSection');
  const elEmailText = document.getElementById('cardEmailText');
  const elQuickEmail = document.getElementById('btnQuickEmail');
  const elLinkEmail = document.getElementById('linkEmail');

  if (cardData.email) {
    if (elEmailText) elEmailText.textContent = cardData.email;
    if (elEmailSection) {
      elEmailSection.classList.remove('hidden');
      elEmailSection.classList.add('flex');
    }
    if (elQuickEmail) {
      elQuickEmail.href = `mailto:${cardData.email}`;
      elQuickEmail.classList.remove('opacity-40', 'pointer-events-none');
    }
    if (elLinkEmail) elLinkEmail.href = `mailto:${cardData.email}`;
  } else {
    if (elEmailSection) {
      elEmailSection.classList.add('hidden');
      elEmailSection.classList.remove('flex');
    }
    if (elQuickEmail) {
      elQuickEmail.href = '#';
      elQuickEmail.classList.add('opacity-40', 'pointer-events-none');
    }
  }

  // Website
  const elWebsiteSection = document.getElementById('websiteSection');
  const elWebsiteText = document.getElementById('cardWebsiteText');
  const elQuickWeb = document.getElementById('btnQuickWeb');
  const elLinkWebsite = document.getElementById('linkWebsite');

  if (cardData.website) {
    let cleanUrl = cardData.website.trim();
    if (!/^https?:\/\//i.test(cleanUrl)) {
      cleanUrl = 'https://' + cleanUrl;
    }
    if (elWebsiteText) elWebsiteText.textContent = cardData.website;
    if (elWebsiteSection) {
      elWebsiteSection.classList.remove('hidden');
      elWebsiteSection.classList.add('flex');
    }
    if (elQuickWeb) {
      elQuickWeb.href = cleanUrl;
      elQuickWeb.classList.remove('opacity-40', 'pointer-events-none');
    }
    if (elLinkWebsite) elLinkWebsite.href = cleanUrl;
  } else {
    if (elWebsiteSection) {
      elWebsiteSection.classList.add('hidden');
      elWebsiteSection.classList.remove('flex');
    }
    if (elQuickWeb) {
      elQuickWeb.href = '#';
      elQuickWeb.classList.add('opacity-40', 'pointer-events-none');
    }
  }

  // Address & Google Maps
  const elAddressSection = document.getElementById('addressSection');
  const elAddressText = document.getElementById('cardAddressText');
  const elBtnViewOnMap = document.getElementById('btnViewOnMap');

  const fullAddress = [
    cardData.address,
    cardData.city,
    cardData.state,
    cardData.postalCode,
    cardData.country
  ].filter(Boolean).join(', ');

  if (fullAddress) {
    if (elAddressText) elAddressText.textContent = fullAddress;
    if (elAddressSection) {
      elAddressSection.classList.remove('hidden');
      elAddressSection.classList.add('flex');
    }
    const mapUrl = VCardHelper.getMapUrl({ address: fullAddress });
    if (elBtnViewOnMap && mapUrl) {
      elBtnViewOnMap.href = mapUrl;
    }
  } else {
    if (elAddressSection) {
      elAddressSection.classList.add('hidden');
      elAddressSection.classList.remove('flex');
    }
  }

  // 7. "Save to Contacts" (.vcf download)
  const btnSaveContact = document.getElementById('btnSaveContact');
  if (btnSaveContact) {
    btnSaveContact.addEventListener('click', (e) => {
      e.preventDefault();
      VCardHelper.downloadVCF(cardData);
    });
  }

  // 8. Share Button (Native Web Share or Copy Link)
  const btnShareCard = document.getElementById('btnShareCard');
  if (btnShareCard) {
    btnShareCard.addEventListener('click', async () => {
      const shareUrl = window.location.href;
      if (navigator.share) {
        try {
          await navigator.share({
            title: `${fullName}'s Contact Card`,
            text: `Connect with ${fullName}`,
            url: shareUrl
          });
        } catch (err) {
          // User cancelled
        }
      } else {
        navigator.clipboard.writeText(shareUrl).then(() => {
          alert('Card link copied to clipboard!');
        }).catch(() => {
          prompt('Copy this link to share:', shareUrl);
        });
      }
    });
  }

  // 9. Edit My Card Button
  const btnEditCard = document.getElementById('btnEditCard');
  if (btnEditCard && cardData && cardData.email) {
    btnEditCard.href = `index.html?action=edit&email=${encodeURIComponent(cardData.email)}`;
  }

  // Render Lucide Icons
  if (window.lucide) {
    lucide.createIcons();
  }
});
