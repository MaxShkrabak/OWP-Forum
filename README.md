# OWP Forum
> The Office of Water Programs (OWP) at Sacramento State is a global authority in water and wastewater treatment training and research.

![OWP Logo](frontend/src/assets/img/svg/owp-logo-horizontal-2color.svg)

This is a web-based forum application being developed as our senior project for the Office of Water Programs.
The platform is designed to bridge the gap between communication and learning. It allows students and
professionals to interact, share knowledge, and support each other in gaining valuable information related to 
water programs and initiatives.

## 🚀 Core Features

### 🔐 Authentication & User Access

* Email-based login system
* One-time passcode (OTP) sent through mail server
* Secure account registration requiring:

  * First & last name
  * Email (used as username)
  * Last 4 digits of SSN (identity verification)

### 🧩 Role-Based Permissions

| Role                        | Permissions                                                                     |
| --------------------------- | ------------------------------------------------------------------------------- |
| **Admin**                   | Assign roles, post with official tagging, view all reports, full system control |
| **Moderator**               | Edit and recategorize posts, remove content, view reports                       |
| **Student**                 | Create posts, comment, like, report content                                     |
| **Guest (Unauthenticated)** | Read-only access — no login required                                            |

### 📝 Posting & Interaction Features

* Create discussion threads with:

  * Text
  * Images
  * Video support
* Upvote / Downvote system for engagement
* Commenting system with email notifications
* Search functionality for posts, topics, and tags

### ♿ Accessibility & Design

* Developed to meet **WCAG accessibility standards**
* Fully responsive UI — mobile, tablet, and desktop supported

### 🗄 Technology Stack

| Layer          | Technology                         |
| -------------- | ---------------------------------- |
| **Frontend**   | Vue.js                             |
| **Backend**    | PHP Slim REST API                  |
| **Database**   | T-SQL                              |
| **Build Goal** | Cloud-deployable production system |

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

## 📅 Timeline
This timeline tracks our project's progress across CSC 190 and CSC 191

## Phase I: 🍂 Fall 2025 (CSC 190)

| Sprint | Key Features / Goals | Status |
| ------ | ------------------- | ------ |
| **01** | Developed and finalized Figma Prototypes, established project scope in the **Project Charter**, and defined initial requirements/tech stack. | **DONE** |
| **02** | Set up **Git/Jira environment**, implemented the **Login/Register UI**, connected Authentication to the database backend, and built base site components (Headers/Footer). | **DONE** |
| **03** | Implemented **Authentication Logic**, developed **Create Post Page**, initiated backend for Tags/Categories, and built the **User Profile Page** for settings. | **DONE** |
| **04** | Fixed issues with **Create Post Page**, implemented functional **Category View**, updated **Homepage** to display posts, and signed the Project Charter. | **DONE** |

## Phase II: 🌷 Spring 2026 (CSC 191)

| Sprint | Key Features / Goals | Status |
| ------ | ------------------- | ------ |
| **05** | Adjustments to **Homepage** layout, convert the **Create Post Page into a Modal**, create the **Post View Page**, implement **Commenting**, and enable user **Post Reporting** functionality.| **TODO** |
| **06** | Implement **Admin Panel** for role assignment, user **Notifications**, author **Post Editing Privileges**, and Admin/Mod post moderation (Move/Delete posts). | **TODO** |
| **07** | Work on **Bug Fixes**, perform full-site **Testing**, and make necessary final adjustments based on testing results. | **TODO** |
| **08** | Final application preparation and official environment **Deployment** for production. | **TODO** |

## 👥 Contributors <img width="20" height="20" alt="9c74196a9a400de1cae861afb5b16ac4" src="https://github.com/user-attachments/assets/20b5af03-5366-4dd5-aee5-8a1c6473495a" />


- Maksim Shkrabak
- Daniel Ivanilov
- Jeffrey Sardella
- Oleksii Andriienko
- Egor Strakhov
- Ruzanna Shomakhova
- Gianni Dumitru
- Gavin Kabel
