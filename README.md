# 📇 Dynamic QR Digital Business Card & Analytics System

An enterprise-ready, dynamic digital business card (vCard) platform that generates scannable QR codes, tracks real-time scan analytics, and lets users download standard contact files (.vcf) directly into mobile devices.

---

## 📸 Project Screenshots

<p align="center">
  <img src="docs/images/dashboard-preview.png" alt="Admin Analytics Dashboard" width="800">
</p>

<p align="center" style="display: flex; justify-content: center; gap: 20px;">
  <img src="docs/images/builder-preview.png" alt="vCard Studio Builder" width="48%">
  <img src="docs/images/mobile-card-preview.png" alt="Mobile vCard View" width="48%">
</p>

---

## 🚀 Key Features

- **Dynamic QR Code Engine**:
  - Automatically resolves scannable links via URL slugs.
  - Custom styling presets: Classic Black, DuitNow Pink (`#ED008C`), and Navy Blue with high-contrast finder patterns to ensure quick camera recognition.
  - Dynamic redirect architecture allowing card information to be updated without needing to re-print physical QR codes.

- **Mobile-First Digital Card**:
  - Responsive, modern mobile card layout built with Tailwind CSS.
  - Zero-redirect server pre-rendering for instantaneous card display upon scanning.
  - One-tap interactions: **Direct Phone Call**, **WhatsApp Chat**, **Email**, and **Google Maps Navigation**.
  - One-tap **"Save to Contacts"** downloading a standardized `.vcf` (vCard 3.0) file compatible with iOS and Android.

- **Administrative Dashboard & Analytics**:
  - Live KPI metrics tracking total registered cards, scan counts, and most active cards with compact large-number formatting (`1.01 M`, `1.5 K`).
  - Searchable staff card directory with inline high-resolution QR previews and universal clipboard link copying.
  - PIN-protected admin authentication and password reset workflow with transactional email integration (Resend API).

- **Self-Service Card Editing**:
  - OTP verification system allowing employees to update their contact information securely.
  - QR design lock mechanism on edit mode to maintain corporate brand consistency.

---

## 🛠️ Tech Stack & Architecture

- **Backend**: PHP 8 (Vanilla OOP & Procedural, PDO)
- **Database**: MySQL / MariaDB (Structured with foreign keys, indexes, and automated timestamps)
- **Frontend**: HTML5, Tailwind CSS, Vanilla JavaScript (ES6+)
- **Libraries**:
  - `qr-code-styling` (Custom vector & canvas QR generation)
  - `cropperjs` (Client-side profile picture crop and adjustment)
  - `lucide-icons` (UI vector iconography)
  - `intl-tel-input` (International phone formatting)
- **APIs & Services**: Resend REST API (Transactional emails)

---

## 📂 Project Structure

```text
├── assets/
│   ├── css/           # Custom UI styles
│   └── js/
│       ├── builder.js # Card creation & QR styling engine
│       ├── card.js    # Mobile card renderer & interactions
│       └── vcard-helper.js # vCard 3.0 export & maps utilities
├── c/
│   └── index.php      # Dynamic QR resolver & scan tracker
├── uploads/           # User avatar storage
├── api.php            # RESTful JSON API handling CRUD & authentication
├── card.html          # Mobile card template
├── config.php         # DB connections, sessions, and email helpers
├── dashboard.php      # Admin analytics & management portal
├── index.html         # Card builder & self-service edit interface
├── schema.sql         # Clean database schema (ready for deployment)
└── upload.php         # Image upload handler
```

---

## ⚙️ Local Setup Instructions

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/your-username/vcard-app.git
   ```

2. **Configure Web Server**:
   - Move the project directory to your web root (e.g., `C:/xampp/htdocs/` or Linux `/var/www/html/`).

3. **Database Setup**:
   - Open phpMyAdmin (or your MySQL client).
   - Create a database named `vcard_app`.
   - Import `schema.sql`.

4. **Environment Configuration**:
   - In `config.php`, configure your database credentials and optional transactional email key:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'vcard_app');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('EMAIL_API_KEY', 'your_resend_api_key_here'); // Optional
     ```

5. **Run the App**:
   - Access the Builder: `http://localhost/vcard-app/`
   - Access Admin Dashboard: `http://localhost/vcard-app/dashboard.php` (Default PIN: `1234`)
