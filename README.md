# OWP Forum
> The Office of Water Programs (OWP) at Sacramento State is a global authority in water and wastewater treatment training and research.

![OWP Logo](frontend/src/assets/img/svg/owp-logo-horizontal-2color.svg)

This is a web-based forum application being developed as our senior project for the Office of Water Programs.
The platform is designed to bridge the gap between communication and learning. It allows students and
professionals to interact, share knowledge, and support each other in gaining valuable information related to 
water programs and initiatives.

## Getting Started

Follow these instructions to get a local copy of the project up and running.

### Prerequisites

Before you begin, ensure you have the following installed:
* [Node.js and npm](https://nodejs.org/)
* [PHP](https://www.php.net/downloads.php)
* [Composer](https://getcomposer.org/)

## ⚙️ Setup Instructions

### 1. Frontend Setup

1. Navigate to frontend directory:
   ```bash
   cd frontend
   ```

2. Install all required npm packages:
   ```bash
   npm install
   ```

3. Run the frontend development server:
   ```bash
   npm run dev
   ```

### 2. Backend Setup

1. In a **new terminal window**, navigate to the backend directory from the project root:
   ```bash
   cd backend
   ```
2. Install the required PHP dependencies using Composer:
   ```bash
   composer require slim/slim slim/psr7 vlucas/phpdotenv
   ```
3. **Configure Environment Variables:** The backend needs credentials to run.
   * First, copy the example `.env.example` file to create your own local configuration file:
   ```bash
   cp .env.example .env
   ```
   * Next, open the new `.env` file.
   * Fill in the required parameters (database credentials, application secrets) with your correct development values.

## 👥 Contributors

- Maksim Shkrabak
- Daniel Ivanilov
- Jeffrey Sardella
- Oleksii Andriienko
- Egor Strakhov
- Ruzanna Shomakhova