# Paw Net - WAVE Accessibility Evaluation Manual

This manual provides concise, step-by-step instructions for evaluating the **Paw Net** pet-adoption platform using the **WAVE Web Accessibility Evaluation Tool** (developed by WebAIM).

---

## 1. About WAVE & Security Clarification

> **Clarification:**  
> The prompt requirement *"using waves for security purpose"* was analyzed. In web engineering, **WAVE** is an industry-standard **Web Accessibility Evaluation Tool**, not an authentication or firewall security software.  
> Real security controls (prepared statements, password hashing, session regeneration, CSRF tokens, account suspension, and safe upload validation) have been implemented natively in PHP/MySQL (see Stage 7). This document details how to run accessibility audits using WAVE.

---

## 2. Installing the WAVE Extension

1. **Google Chrome / Chromium / Edge:**
   - Visit the Chrome Web Store: `https://chromewebstore.google.com/detail/wave-evaluation-tool/jbbplnpaimhafloemhncnhkdnphcmmda`
   - Click **Add to Chrome**.
2. **Mozilla Firefox:**
   - Visit Firefox Add-ons: `https://addons.mozilla.org/en-US/firefox/addon/wave-accessibility-tool/`
   - Click **Add to Firefox**.

---

## 3. Preparing Paw Net for Testing

Ensure your local development environment is running:
- **Apache / Local PHP Server:** Running on `http://127.0.0.1:8000` or via XAMPP Apache `http://localhost/pet`.
- **MySQL / MariaDB:** Port `3306` active.

---

## 4. Key Pages to Audit

Evaluate all three user portals:

| Portal | URL | Focus Areas |
|---|---|---|
| **Welcome / Role Choice** | `http://127.0.0.1:8000/welcome.php` | Heading hierarchy, link text clarity, card contrast |
| **Buyer Dashboard** | `http://127.0.0.1:8000/index.php` | Modal dialogs, gallery controls, application table, accessibility panel |
| **Seller Portal** | `http://127.0.0.1:8000/seller.php` | Multi-image upload form, Recycle Bin tabs, action buttons |
| **Admin Dashboard** | `http://127.0.0.1:8000/admin.php` | Navigation tabs, tables, status badges, action confirmation dialogs |

---

## 5. How to Run an Evaluation

1. Open the page in your browser (e.g., `http://127.0.0.1:8000/index.php` logged in as buyer).
2. Click the **WAVE icon** in the browser extension toolbar (or press `Ctrl + Shift + U` / `Cmd + Shift + U`).
3. WAVE will inject visual badges onto the page and open a sidebar panel with 6 summary categories:
   - **Errors (Red):** Critical WCAG violations (missing alt text, empty buttons, missing form labels).
   - **Contrast Errors (Yellow/Black):** Low color contrast against background.
   - **Alerts (Yellow):** Potential issues requiring human judgment (redundant links, skipped heading levels).
   - **Features (Green):** Accessible elements detected (alt text, ARIA landmarks, form labels).
   - **Structural Elements (Blue):** Headings (`<h1>` to `<h3>`), lists, sections, nav landmarks.
   - **ARIA (Purple):** ARIA labels, live regions, and roles.

---

## 6. What Was Enhanced in Paw Net for WAVE Compliance

- **Form Labels:** All search bars, filter selects, message textareas, and modal inputs have associated `<label>` elements or descriptive `aria-label` attributes.
- **Icon-Only Buttons:** Buttons like `☰` (menu), `♿` (accessibility), `‹` / `›` (gallery navigation), and `♥` (favorites) have explicit `aria-label` descriptions.
- **Heading Order:** Standardized `<h1>` per page with sequential `<h2>` and `<h3>` tags for panels and cards.
- **Color Contrast:** Purple accent (`#7056df`), badges, and text colors meet WCAG AA contrast ratio (> 4.5:1 for normal text).
- **Interactive States:** High-visibility outline focus rings (`:focus-visible`) for full keyboard navigation.
- **Option C Accessibility Settings Panel:** Located at the top right (`♿`), allowing adopters and sellers to toggle:
  - Dyslexia-friendly letterforms
  - Scaled text sizes (+15%, +30%)
  - High-contrast border/text mode
  - Reduced-motion animations

---

## 7. How to Interpret and Report Results

1. In the WAVE sidebar, select the **Details** tab.
2. Verify that **0 Errors** and **0 Contrast Errors** are reported.
3. Review any **Alerts** (e.g., decorative animal emojis) to confirm they have `aria-hidden="true"`.
4. Check the **Structure** tab to confirm the heading tree is linear and no heading levels are skipped.
