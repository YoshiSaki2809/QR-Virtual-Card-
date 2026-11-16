/**
 * vCard Helper Library
 * Handles:
 * 1. URL payload encoding & decoding (Base64 + UTF-8 safe)
 * 2. vCard 3.0 (.vcf) format generation
 * 3. Mobile vCard (.vcf) file download trigger
 * 4. Google Maps link generation
 */

const VCardHelper = {
  /**
   * Encode card data object into a URL-safe Base64 string
   */
  encodePayload(data) {
    try {
      const jsonStr = JSON.stringify(data);
      // Encode UTF-8 characters safely to base64
      return btoa(encodeURIComponent(jsonStr).replace(/%([0-9A-F]{2})/g, function (match, p1) {
        return String.fromCharCode(parseInt(p1, 16));
      }));
    } catch (e) {
      console.error('Encoding error:', e);
      return '';
    }
  },

  /**
   * Decode card data object from a Base64 string
   */
  decodePayload(base64Str) {
    try {
      if (!base64Str) return null;
      // Clean up potential hash/query prefixes
      let cleanStr = base64Str.replace(/^[#?](data=)?/, '');
      cleanStr = decodeURIComponent(cleanStr);
      const jsonStr = decodeURIComponent(
        Array.prototype.map.call(atob(cleanStr), function (c) {
          return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join('')
      );
      return JSON.parse(jsonStr);
    } catch (e) {
      console.error('Decoding error:', e);
      return null;
    }
  },

  /**
   * Escape special characters for standard vCard text format
   */
  escapeVCardText(text) {
    if (!text) return '';
    return text
      .toString()
      .replace(/\\/g, '\\\\')
      .replace(/;/g, '\\;')
      .replace(/,/g, '\\,')
      .replace(/\n/g, '\\n');
  },

  /**
   * Generate standard RFC 2426 vCard 3.0 format string
   */
  generateVCardString(data) {
    const lines = ['BEGIN:VCARD', 'VERSION:3.0'];

    // Name (N: Family;Given;Additional;Prefix;Suffix)
    const firstName = this.escapeVCardText(data.firstName || '');
    const lastName = this.escapeVCardText(data.lastName || '');
    lines.push(`N:${lastName};${firstName};;;`);

    // Formatted Name (FN)
    const fullName = `${data.firstName || ''} ${data.lastName || ''}`.trim() || 'Contact';
    lines.push(`FN:${this.escapeVCardText(fullName)}`);

    // Corporate / Organization & Title
    if (data.company) {
      lines.push(`ORG:${this.escapeVCardText(data.company)}`);
    }
    if (data.jobTitle) {
      lines.push(`TITLE:${this.escapeVCardText(data.jobTitle)}`);
    }

// Photo (Avatar)
    if (data.photoUrl) {
      let photoUri = data.photoUrl.trim();
      if (photoUri.startsWith('data:image/jpeg;base64,')) {
        const base64Data = photoUri.replace('data:image/jpeg;base64,', '');
        lines.push(`PHOTO;TYPE=JPEG;ENCODING=b:${base64Data}`);
      } else if (photoUri.startsWith('data:image/png;base64,')) {
        const base64Data = photoUri.replace('data:image/png;base64,', '');
        lines.push(`PHOTO;TYPE=PNG;ENCODING=b:${base64Data}`);
      } else {
        if (!/^https?:\/\//i.test(photoUri) && typeof window !== 'undefined') {
          try {
            photoUri = new URL(photoUri, window.location.href).href;
          } catch (e) {
            // ignore
          }
        }
        lines.push(`PHOTO;VALUE=URI:${photoUri}`);
      }
    }

    // Date of Birth (BDAY: YYYY-MM-DD for RFC standard)
    if (data.dob) {
      let vcfDob = data.dob;
      if (/^\d{2}-\d{2}-\d{4}$/.test(vcfDob)) {
        const p = vcfDob.split('-');
        vcfDob = `${p[2]}-${p[1]}-${p[0]}`;
      }
      lines.push(`BDAY:${vcfDob}`);
    }

    // Phone Number (TEL;TYPE=...)
    if (data.phone) {
      const type = (data.phoneType || 'CELL').toUpperCase();
      lines.push(`TEL;TYPE=${type},VOICE:${data.phone}`);
    }

    // Email
    if (data.email) {
      lines.push(`EMAIL;TYPE=INTERNET,PREF:${data.email.trim()}`);
    }

    // Website / URL
    if (data.website) {
      let url = data.website.trim();
      if (!/^https?:\/\//i.test(url)) {
        url = 'https://' + url;
      }
      lines.push(`URL:${url}`);
    }

    // Address (ADR;TYPE=WORK:;;Street;City;State;PostalCode;Country)
    if (data.address || data.city || data.state || data.postalCode || data.country) {
      const street = this.escapeVCardText(data.address || '');
      const city = this.escapeVCardText(data.city || '');
      const state = this.escapeVCardText(data.state || '');
      const postalCode = this.escapeVCardText(data.postalCode || '');
      const country = this.escapeVCardText(data.country || '');
      lines.push(`ADR;TYPE=WORK:;;${street};${city};${state};${postalCode};${country}`);
    }

    // Note / Generator branding
    lines.push('NOTE:Digital Business Card generated with Custom vCard Generator');
    lines.push('REV:' + new Date().toISOString());
    lines.push('END:VCARD');

    return lines.join('\r\n');
  },

  /**
   * Triggers native phone download of .vcf file
   * Supports iOS Safari, Android Chrome, and Desktop browsers
   */
  downloadVCF(data) {
    const vcardContent = this.generateVCardString(data);
    const fullName = `${data.firstName || ''} ${data.lastName || ''}`.trim() || 'contact';
    const filename = `${fullName.toLowerCase().replace(/[^a-z0-9]/g, '_') || 'contact'}.vcf`;

    // Create UTF-8 encoded Blob with correct MIME type
    const blob = new Blob([vcardContent], { type: 'text/vcard;charset=utf-8;' });

    // iOS and modern mobile browsers handle object URLs or data URIs
    if (navigator.msSaveBlob) {
      navigator.msSaveBlob(blob, filename);
      return;
    }

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();

    setTimeout(() => {
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    }, 200);
  },

  /**
   * Build Google Maps search URL from address components
   */
  getMapUrl(data) {
    const parts = [
      data.address,
      data.city,
      data.state,
      data.postalCode,
      data.country
    ].filter(Boolean);

    if (parts.length === 0) return null;
    const query = parts.join(', ');
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
  }
};

// Export to window if in browser
if (typeof window !== 'undefined') {
  window.VCardHelper = VCardHelper;
}
