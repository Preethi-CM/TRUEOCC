# True Occupation (TrueOcc) 🚀

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Version](https://img.shields.io/badge/version-1.0.6-green.svg)
![Platform](https://img.shields.io/badge/platform-Web%20%7C%20PWA%20%7C%20Android-orange.svg)

**True Occupation** is an AI-powered career readiness and job portal platform designed to bridge the gap between education and employment. It features advanced proctoring, AI-driven interview simulations, and a premium learning ecosystem.

---

## 🌟 Key Features

### 🤖 AI Mock Interviews
*   Real-time proctoring with head-pose detection and facial analysis.
*   Instant feedback warnings for "Look at camera" or "Multiple faces detected".
*   Automated scoring based on performance.

### 📝 Aptitude & Skills Assessment
*   Comprehensive testing modules for different career paths.
*   Personalized job recommendations based on skill-gap analysis.
*   Progress tracking and historical results dashboard.

### 📚 Premium Learning Hub
*   Curated library of career development books.
*   In-app PDF viewer for seamless reading.
*   Integrated QR-based subscription system.

### 🏢 Employer Verification
*   Secure document upload system for company verification.
*   Dedicated dashboard for job posting and application management.
*   Admin-led manual verification workflow.

### 📱 PWA & Mobile Ready
*   Fully responsive design with app-like bottom navigation on mobile.
*   Service Worker integration for offline support and fast loading.
*   Ready for Google Play Store deployment via Trusted Web Activity (TWA).

---

## 🛠️ Technology Stack
*   **Frontend:** HTML5, CSS3 (Vanilla), JavaScript (ES6+)
*   **Backend:** PHP 8.x
*   **Database:** MySQL
*   **AI/ML:** MediaPipe (Face Mesh) for proctoring
*   **Architecture:** RESTful API with JSON responses

---

## 🚀 Quick Start (Local Setup)

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/Preethi-CM/TRUEOCC.git
    ```
2.  **Move to XAMPP:**
    Place the folder in your `C:\xampp\htdocs\` directory.
3.  **Database Setup:**
    *   Open phpMyAdmin.
    *   Create a database named `true_occupation`.
    *   Import `database/schema.sql`.
4.  **Configuration:**
    *   Update `backend/includes/config.php` with your database credentials.
5.  **Run:**
    *   Access the app at `http://localhost/trueocc`.

---

## 📄 Privacy Policy
A mandatory Privacy Policy for Play Store submission is included at `frontend/pages/privacy.html`.

---

## 🤝 Contributing
Contributions are welcome! Please open an issue or submit a pull request for any improvements.

---

## 📜 License
This project is licensed under the MIT License.
