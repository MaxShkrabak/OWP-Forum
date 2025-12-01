# OWP Forum
> The Office of Water Programs (OWP) at Sacramento State is a global authority in water and wastewater treatment training and research.

![OWP Logo](frontend/src/assets/img/svg/owp-logo-horizontal-2color.svg)

This is a web-based forum application being developed as our senior project for the Office of Water Programs.
Its purpose is to bridge the gap between communication and learning by allowing students and
professionals to interact, share knowledge, and support each other in gaining valuable information related to 
water programs and initiatives. The platform provides a space for posts, comments, and real-time updates—supporting deeper engagement with water program training and initiatives.

---

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
* Styled with Sac State colors and branding

### 🗄 Technology Stack

| Layer | Technology |
| :--- | :--- |
| **Frontend** | ![Vue.js](https://img.shields.io/badge/Vue.js-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white) |
| **Backend** | ![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white) ![Slim Framework](https://img.shields.io/badge/Slim%20Framework-000000?style=for-the-badge&logo=slim&logoColor=white) |
| **Database** | ![Microsoft SQL Server](https://img.shields.io/badge/MSSQL-CC2927?style=for-the-badge&logo=microsoft-sql-server&logoColor=white) |
| **Build Goal** | Cloud-deployable production system |

---

## <img width="20" height="20" alt="Prototype Images" src="https://github.com/user-attachments/assets/08fea9c6-d35b-4994-810e-de5143150ef0" /> Prototype Images

<details>
 <summary><img width="20" height="20" alt="Home Page" src="https://github.com/user-attachments/assets/45f9e5dc-6d26-4be0-a425-7d5bd9d322ac" /> Home Page</summary>

| Not Logged in  | Logged in   | Logged in as Mod/Admin |
| -------------- | ----------- | ---------------------- |
| <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/a8175499-f987-4a18-9816-09f63df4611e" /> | <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/90ffdafd-37c7-4563-9c60-9ddb74fce32d" /> | <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/e6e85a41-0d94-4fad-ac80-89a5cd495219" /> |
</details>

<details>
 <summary><img width="20" height="20" alt="image" src="https://github.com/user-attachments/assets/4a7adb12-d4d5-4f59-b016-823ef2fe0fd5" /> View a Specific Category</summary>

Similar for all roles.

<img width="800" height="800" alt="image" src="https://github.com/user-attachments/assets/1e57ccf3-c3f9-45bc-b13b-ef0f1bb7676a" />
</details>

<details>
 <summary><img width="20" height="20" alt="image" src="https://github.com/user-attachments/assets/80cee93f-ac25-4018-8312-ef2d546521f3" /> Viewing a Specific Post</summary> 

| Not Logged in  | Logged in   | Logged in as Mod/Admin |
| -------------- | ----------- | ---------------------- |
| <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/68ad6d2c-7109-4bed-ae29-92ab6b424abb" /> | <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/61be1798-ecfe-4d17-bd17-906320ebcfea" /> | <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/92fcee36-d467-4694-882d-10506c150d72" /> |

### <img width="20" height="20" alt="image" src="https://github.com/user-attachments/assets/4daeaf92-2a0c-4e25-b347-fc052ebff944" /> Creating a Post

| Logged in as User/Student | Logged in as Mod/Admin |
| ------------------------- | ---------------------- |
| <img width="750" height="750" alt="image" src="https://github.com/user-attachments/assets/0f07f601-cccb-4876-9cb4-170044c3174e" /> | <img width="750" height="750" alt="image" src="https://github.com/user-attachments/assets/c0804f2e-b5d6-4837-942f-19b40b1f294a" /> |

</details>

<details>
 <summary><img width="20" height="20" alt="image" src="https://github.com/user-attachments/assets/f8dd55bc-f59f-4c34-bd29-59b16af789f9" /> User Forum Profile</summary>

Similar for all roles.

<img width="800" height="800" alt="image" src="https://github.com/user-attachments/assets/43b4f50e-e9ef-4234-b2fb-83783a1e2635" />
</details>

---

## 📸 Actual Website Images

Screenshots of the live, implemented OWP Forum application.

<details>
 <summary>🏠 Home Page</summary>

| Not Logged in  | Logged in   | Logged in as Mod/Admin |
| -------------- | ----------- | ---------------------- |
| <img width="500" height="500" alt="Home Page - Not Logged In" src="./docs/images/home-not-logged-in.png" /> | <img width="500" height="500" alt="Home Page - Logged In" src="./docs/images/home-logged-in.png" /> | <img width="500" height="500" alt="Home Page - Mod/Admin" src="./docs/images/home-mod-admin.png" /> |
</details>

<details>
 <summary>📂 Category View</summary>

Similar for all roles.

<img width="800" height="800" alt="Category View" src="./docs/images/category-view.png" />
</details>

<details>
 <summary>👤 User Profile</summary>

Similar for all roles.

<img width="800" height="800" alt="User Profile" src="./docs/images/user-profile.png" />
</details>

---
## 🗂️ Entity Relationship Diagram (ERD)

The Entity Relationship Diagram (ERD) below outlines the full database structure behind the OWP Forum.  
It shows how core components—such as users, posts, comments, tags, categories, roles, permissions, and reporting—connect to form a secure and scalable forum ecosystem.

Key highlights of the database design:

- **User & Authentication System:**  
  Includes users, sessions, OTP codes, roles, and permissions to support secure login and role-based access control.

- **Forum Content Structure:**  
  Posts link to authors, categories, tags, attached media, and user interactions such as likes and comments.

- **Moderation & Reporting:**  
  Users can report posts or comments, which tie into report categories and moderation workflows.

- **Flexible Tagging & Categorization:**  
  Many-to-many relationships allow posts to have multiple tags, enabling advanced filtering and search.

This structure ensures data integrity while supporting all major features of the platform, including posting, comment threads, moderation tools, and user identity verification.

### 📘 ERD Diagram
![ERD](./erd.jpg)
---

## Getting Started

Follow these instructions to get a local copy of the project up and running.

### Prerequisites

Before you begin, ensure you have the following installed:
* [Node.js and npm](https://nodejs.org/)
  
* [PHP](https://www.php.net/downloads.php)
  thread-safe preferred version: 8.3
  
* [Composer](https://getcomposer.org/)
  
* [PHP SQL Server Drivers](https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server?view=sql-server-ver17)
  PHP SQL server drivers from Microsoft & ODBC driver

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
1. Extract PHP to the desired directory and create a new **system environmental variable** for the path to the PHP installation.
2. From the **SQLSRV512.ZIP** file you downloaded, copy the drivers **php_pdo_sqlsrv_83_ts_x64.dll** & **php_sqlsrv_83_ts_x64.dll** or equivalent drivers for the version of PHP you downloaded and paste them into the **ext** file inside your PHP installation
3. Make a copy of the file **php.ini-development** inside your PHP installation, paste it inside your PHP installation, and rename it to **php.ini**.
4. Inside **php.ini**, remove the **;** from in front of **extension=openssl** and paste the follwing extensions below or their equivalents under **;zend_extension=opcache**.
   ```bash
   extension=php_sqlsrv_83_ts_x64.dll
   extension=php_pdo_sqlsrv_83_ts_x64.dll
   ```
6. Copy the path of the **ext** file, inside your PHP installation file, and paste it inside the double quotes of the extension directory inside php.ini then save php.ini.
   ```bash
   ; On windows:
   extension_dir = "paste directory here"
   ```
7. In a **new terminal window**, navigate to the backend directory from the project root:
   ```bash
   cd backend
   ```
8. Install the required PHP dependencies using Composer:
   ```bash
   composer require slim/slim slim/psr7 vlucas/phpdotenv
   ```
9. **Ensure You Have Configured Your Environment Variables:** The backend needs credentials to run.
   * First, copy the example `.env.example` file to create your own local configuration file:
   ```bash
   cp .env.example .env
   ```
   * Next, open the new `.env` file.
   * Fill in the required parameters (database credentials, application secrets) with your correct development values.

---

## 📅 Timeline
This timeline tracks our project's progress across CSC 190 and CSC 191

### Phase I: 🍂 Fall 2025 (CSC 190)

| Sprint | Key Features / Goals | Status |
| ------ | ------------------- | ------ |
| **01** | Developed and finalized Figma Prototypes, established project scope in the **Project Charter**, and defined initial requirements/tech stack. | **DONE** |
| **02** | Set up **Git/Jira environment**, implemented the **Login/Register UI**, connected Authentication to the database backend, and built base site components (Headers/Footer). | **DONE** |
| **03** | Implemented **Authentication Logic**, developed **Create Post Page**, initiated backend for Tags/Categories, and built the **User Profile Page** for settings. | **DONE** |
| **04** | Fixed issues with **Create Post Page**, implemented functional **Category View**, updated **Homepage** to display posts, and signed the Project Charter. | **DONE** |

### Phase II: 🌷 Spring 2026 (CSC 191)

| Sprint | Key Features / Goals | Status |
| ------ | ------------------- | ------ |
| **05** | Adjustments to **Homepage** layout, convert the **Create Post Page into a Modal**, create the **Post View Page**, implement **Commenting**, and enable user **Post Reporting** functionality.| **TODO** |
| **06** | Implement **Admin Panel** for role assignment, user **Notifications**, author **Post Editing Privileges**, and Admin/Mod post moderation (Move/Delete posts). | **TODO** |
| **07** | Work on **Bug Fixes**, perform full-site **Testing**, and make necessary final adjustments based on testing results. | **TODO** |
| **08** | Final application preparation and official environment **Deployment** for production. | **TODO** |

---

## 🧪 Testing 
TBD

---

## Deployment
TBD

---

## 🧑‍💻 Contributing
This guide details the steps and standards required for contributing code to the OWP Forum project. All contributions must be linked to a story or subtask in Jira (using format **BB-123**).
### 1. Branching
All development work must be performed in a dedicated feature branch

1. **Pull the latest changes** from the development branch (`dev`):
   ```bash
   git checkout dev
   git pull origin dev
   ```
2. **Create your branch** directly from `dev`. The branch name ***must*** correspond to your assigned Jira story key:
   ```bash
   git checkout -b BB-123-your-story-description
   ```
   > *Example Branch Name: `BB-123-implement-login-otp-logic`*

### 2. Committing
Each commit must address one specific change and clearly linked to a relevant Jira subtask.

1. **Stage your changes:**
   ```bash
   git add .
   ```
2. **Commit Message Format:** The message must begin with the related Jira **subtask** key, followed by a clear explanation of the work done:
   ```bash
   git commit -m "BB-23: Add sign-in button to login page"
   ```

### 3. Pull Request & Review
Once you're done working on your story, you will submit your code for peer review.

1. **Push your branch** to the repository:
   ```bash
   git push origin BB-123-your-story-description
   ```
2. **Create a Pull Request** on GitHub to the **`dev`** branch.
3. **Peer Review:** The PR must receive **at least one approval** from one of the team members before being merged.
4. **Cleanup:** After the PR is merged into `dev`, the original feature branch **must be deleted**.

---

## 🙏 Acknowledgments

This project is developed as part of the CSC 190/191 Senior Project sequence at  
**California State University, Sacramento**.

We would like to thank:

- **Instructor:** Dr. Kenneth Elliot  
- **Lab Advisor:** Prof. Harvin Singh  
- **Office of Water Programs (OWP)** for partnering with us and providing project requirements and feedback.

---

## 👥 Contributors

- Maksim Shkrabak
- Daniel Ivanilov
- Jeffrey Sardella
- Oleksii Andriienko
- Egor Strakhov
- Ruzanna Shomakhova
- Gianni Dumitru
- Gavin Kabel

---

<p align="center">
 <img width="100" height="95" alt="Bug Busters Logo" src="https://github.com/user-attachments/assets/e83ab8a5-90ef-4d55-8795-6aac07eb77b2" />
 <br>
 <strong>Copyright © 2025 OWP Forum | Team Bug Busters</strong>
</p>
