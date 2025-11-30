# OWP Forum
> The Office of Water Programs (OWP) at Sacramento State is a global authority in water and wastewater treatment training and research.

![OWP Logo](frontend/src/assets/img/svg/owp-logo-horizontal-2color.svg)

This is a web-based forum application being developed as our senior project for the Office of Water Programs.
Its purpose is to bridge the gap between communication and learning by allowing students and
professionals to interact, share knowledge, and support each other in gaining valuable information related to 
water programs and initiatives. The platform provides a space for posts, comments, and real-time updates—supporting deeper engagement with water program training and initiatives.

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

| Layer          | Technology                         |
| -------------- | ---------------------------------- |
| **Frontend**   | Vue.js                             |
| **Backend**    | PHP Slim REST API                  |
| **Database**   | T-SQL                              |
| **Build Goal** | Cloud-deployable production system |

## <img width="20" height="20" alt="Prototype Images" src="https://github.com/user-attachments/assets/08fea9c6-d35b-4994-810e-de5143150ef0" /> Prototype Images

### <img width="20" height="20" alt="Home Page" src="https://github.com/user-attachments/assets/45f9e5dc-6d26-4be0-a425-7d5bd9d322ac" /> Home Page

| Not Logged in  | Logged in   | Logged in as Mod/Admin |
| -------------- | ----------- | ---------------------- |
| <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/a8175499-f987-4a18-9816-09f63df4611e" /> | <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/90ffdafd-37c7-4563-9c60-9ddb74fce32d" /> | <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/e6e85a41-0d94-4fad-ac80-89a5cd495219" /> |

### <img width="20" height="20" alt="image" src="https://github.com/user-attachments/assets/4a7adb12-d4d5-4f59-b016-823ef2fe0fd5" /> View a Specific Category

Similar for all roles.

<img width="800" height="800" alt="image" src="https://github.com/user-attachments/assets/1e57ccf3-c3f9-45bc-b13b-ef0f1bb7676a" />

### <img width="20" height="20" alt="image" src="https://github.com/user-attachments/assets/80cee93f-ac25-4018-8312-ef2d546521f3" /> Viewing a Specific Post 

| Not Logged in  | Logged in   | Logged in as Mod/Admin |
| -------------- | ----------- | ---------------------- |
| <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/68ad6d2c-7109-4bed-ae29-92ab6b424abb" /> | <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/61be1798-ecfe-4d17-bd17-906320ebcfea" /> | <img width="500" height="500" alt="image" src="https://github.com/user-attachments/assets/92fcee36-d467-4694-882d-10506c150d72" /> |

### <img width="20" height="20" alt="image" src="https://github.com/user-attachments/assets/4daeaf92-2a0c-4e25-b347-fc052ebff944" /> Creating a Post

| Logged in as User/Student | Logged in as Mod/Admin |
| ------------------------- | ---------------------- |
| <img width="750" height="750" alt="image" src="https://github.com/user-attachments/assets/0f07f601-cccb-4876-9cb4-170044c3174e" /> | <img width="750" height="750" alt="image" src="https://github.com/user-attachments/assets/c0804f2e-b5d6-4837-942f-19b40b1f294a" /> |

### <img width="20" height="20" alt="image" src="https://github.com/user-attachments/assets/f8dd55bc-f59f-4c34-bd29-59b16af789f9" /> User Forum Profile

Similar for all roles.

<img width="800" height="800" alt="image" src="https://github.com/user-attachments/assets/43b4f50e-e9ef-4234-b2fb-83783a1e2635" />

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

## 🙏 Acknowledgments


This project is developed as part of the CSC 190/191 Senior Project sequence at  
**California State University, Sacramento**.

We would like to thank:

- **Instructor:** Dr. Kenneth Elliot  
- **Faculty Advisor:** Prof. Harvin Singh  
- **Office of Water Programs (OWP)** for partnering with us and providing project requirements and feedback.
  
## 👥 Contributors <img width="20" height="20" alt="9c74196a9a400de1cae861afb5b16ac4" src="https://github.com/user-attachments/assets/20b5af03-5366-4dd5-aee5-8a1c6473495a" />


- Maksim Shkrabak
- Daniel Ivanilov
- Jeffrey Sardella
- Oleksii Andriienko
- Egor Strakhov
- Ruzanna Shomakhova
- Gianni Dumitru
- Gavin Kabel
