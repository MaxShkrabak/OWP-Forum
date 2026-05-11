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
| **User / Student**                 | Create posts, comment, like, report content                                     |
| **Guest (Unauthenticated)** | Read-only access — no login required                                            |

### 📝 Posting & Interaction Features

* Create discussion threads with:
  * Rich Text
  * Hyperlinks
  * Images
* Upvote / Downvote system for engagement
* Commenting system with email notifications
* Upvoting and commenting with browser notifications
* Search functionality for posts, categories, tags, and users

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

## 📸 Live Website Images
<details>
 <summary><img height="20" alt="Image" src="https://github.com/user-attachments/assets/8aa3dffd-54c1-481c-a67c-32cf698c9099" /></summary>

   <h6>╭┈➤&emsp;🏠 Browser Notifications for signed up users visible on any page for comments and upvotes by other people on your post.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/f20c3928-cf08-4721-8d5a-37460f795e97" />

   <h6>╭┈➤&emsp;🏠 Search up posts that have a matching Title, Category, Tag, Author Name, or Role. Additionally filter with the category filter.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/380fcd7c-4f5f-4a1a-bc3f-018695103a25" />

   <h6>╭┈➤&emsp;🏠 When you receive no results on a too-specific search.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/987bf5c0-bc9e-4835-9404-39069d629abf" />

   <h6>╭┈➤&emsp;🏠 Category filter to only see selected categories on the home page, limited to 5 posts per category by default. Click Category header to visit Category-specific view.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/7fdc194f-9852-49c0-bcf4-b4f2bddd137f" />

   <h6>╭┈➤&emsp;📁 Tag Filter usable within the page of Category-specific view.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/f1cc52cc-77f0-4b46-8bbd-b4f16db2e14b" />

   <h6>╭┈➤&emsp;📝 View a post. Share button to copy link, Upvote/Downvote, see view counter, and click on author to visit their profile!</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/dfe1454c-cbfa-492c-9a22-c410ee626073" />

   <h6>╭┈➤&emsp;📝 Comment section past the post, with nested replies of depth 2 at most. Sort button for comments. Click author to route to their profile.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/84364bf7-d2b6-4689-8ab9-27427e3541f9" />

   <h6>╭┈➤&emsp;🙍‍♂️ Other Users' profiles. See their statistics and posts with sort options.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/be79e624-ee6e-4c76-a08f-ebb2b6b3c693" />

   <h6>╭┈➤&emsp;🙍‍♂️ User's own profile. View your own statistics and posts with sort options.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/714d2c12-8e40-491b-b2e6-78cc5d3002e9" />

   <h6>╭┈➤&emsp;🙍‍♂️ User's own profile. View posts you liked/upvoted.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/fadf8d1d-a39b-49e4-85a1-a91b3118da1e" />

   <h6>╭┈➤&emsp;🙍‍♂️ User's own profile. Edit your profile, with editable pre-made avatars designed by Ruzanna Shomakhova and notification preferences.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/c76a68ff-ef07-48f4-a29e-a9b62bdf2c64" />
</details>

<details>
   <summary>
   <img height="20" alt="Image" src="https://github.com/user-attachments/assets/9fca0489-b74f-433f-b396-46ee83567473" /></summary>

   <h6>╭┈➤&emsp;🔑 Registration Page for new users with valid Names, SSNs, and Emails. </h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/32ec4c6c-78f5-4e15-9724-75b9a3766986" />

   <h6>╭┈➤&emsp;🔑 Login Page for returning users. Enter email to receive an email in-return to proceed further.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/0727cfee-37d9-4821-a6b2-76fc57bfa794" />

   <h6>╭┈➤&emsp;🔑 Verification Page. Enter received One-Time Password from the email to procceed as logged in.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/fde156a7-6fa1-4041-91bd-8dc831d73c02" />

   <h6>╭┈➤&emsp;🏠 Guest Users' homepage with no ability to makes posts, reports, or upvote/downvote.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/516bfb7f-378b-4a4f-9c02-d55bb53a9cc0" />

   <h6>╭┈➤&emsp;📁 Guest Users' Category-specific view.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/152f51dd-1000-4a09-9645-3d1715e76b3f" />

   <h6>╭┈➤&emsp;📁 Guest Users' Category-specific view.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/6c5be111-aaf4-4bc6-b525-fffdbe24be8c" />

   <h6>╭┈➤&emsp;📝 View posts as guest with no ability to report, upvote/downvote.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/1b7762c1-cded-4bec-b693-681476268e15" />

   <h6>╭┈➤&emsp;📝 View posts' comments with no ability to comment/reply, upvote/downvote, or report comments.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/2ef2857a-3399-4df5-9b01-c8c4321b49e9" />
</details>


<details>
 <summary><strong><img height="20" alt="Image" src="https://github.com/user-attachments/assets/f49d6806-ff8d-4dba-945b-b2482886a066"/></strong></summary>

   <h6>╭┈➤&emsp;🏠 Homepage as User with ability to create, upvote/downvote, and report posts.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/4f34b111-b7eb-4a7c-9775-dfa3638f8557" />

   <h6>╭┈➤&emsp;🏠 Create post with fields for title, category, up to 5 tags, and main content with rich text editor.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/9920cee5-2bff-4b28-9936-cfba38857c22" />

   <h6>╭┈➤&emsp;📁 Category-specific view with ability to create posts.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/de15b07a-26f1-4dee-b278-31db636537a3" />

   <h6>╭┈➤&emsp;📁 Category-specific view with ability to create posts.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/6966fa7b-1d06-431b-9807-181e72c5e292" />

   <h6>╭┈➤&emsp;📝 View others' posts with ability to upvote/downvote, report, and count toward view count.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/02655355-14a6-48ce-a96c-e05d83afe50f" />

   <h6>╭┈➤&emsp;📝 View your own post with ability to edit or delete it.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/1c196f5d-4d05-49fa-b514-85125fc9d35a" />

   <h6>╭┈➤&emsp;📝 Viewing a post with disabled comments, without the ability to comment.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/a7edc307-95d1-40a1-9776-b060dfb71d88" />

   <h6>╭┈➤&emsp;📝 Report a post that you are not the author of.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/f3ab0fa0-943f-445e-a7ab-6d457a89fbc3" />

   <h6>╭┈➤&emsp;📝 Report a comment that you are not the author of.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/6b218e1d-40bf-45a8-998b-21071bad182c" />
</details>

<details>
 <summary><img height="20" alt="Image" src="https://github.com/user-attachments/assets/fe63f747-1f23-4a4c-8ac9-95bb062ff0fe"/></summary>

   <h6>╭┈➤&emsp;🏠 Homepage as moderator with the button to view users' and students' post/comment reports.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/7b74a70d-7d69-4eba-bc0b-5a04b970282a" />

   <h6>╭┈➤&emsp;📁 Category-specific view as moderator with the button to view users' and students' post/comment reports.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/e7403e68-f257-4ec1-8069-8b6e3f00cf21" />
</details>

<details>
 <summary><img height="20" alt="Image" src="https://github.com/user-attachments/assets/c3631737-7431-4d7b-9aa0-69ba42c482d6" /></summary>

   <h6>╭┈➤&emsp;🏠 Homepage with the ability to pin posts to the top of their categories.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/e0583159-3ba1-4d5a-89d8-7c4dafa5496b" />

   <h6>╭┈➤&emsp;🏠 Homepage with the ability to create posts using Announcements category, official tag, and disable comments on the post.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/4417ab71-0315-4365-b1e5-75c635268730" />

   <h6>╭┈➤&emsp;🏠 Homepage with ability to view reports by users and students. Sort reports, route to them (scroll to comment for comment reports), or resolve.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/3c30b363-cf07-4d7b-aeb3-999245eae9ba" />

   <h6>╭┈➤&emsp;📝 View post with ability to edit or delete any post, no matter if you are the author or not.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/9c592539-c905-4d30-a628-3e107ad428bb" />

   <h6>╭┈➤&emsp;📝 View comments with ability to edit or delete and comments, no matter if you are the author or not.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/4b57de4a-9a81-4202-92d4-e83da2d84c8c" />
</details>

<details>
 <summary><img height="20" alt="Image" src="https://github.com/user-attachments/assets/4d8d7bb2-3c62-4bf0-8f61-68a7e908608b" /></summary>

   <h6>╭┈➤&emsp;🏠 Homepage with button to route to the Admin Panel.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/4c4b1eeb-c601-444f-9f92-c1306d0d98cf" />

   <h6>╭┈➤&emsp;📁 Category-specific view with button to route to the Admin Panel.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/3c6e25da-f82f-44cc-b02a-ff479906ee8c" />

   <h6>╭┈➤&emsp;🛡️ Admin Panel with ability to view/search and sort users, and modify their roles and ban status.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/800970ef-280d-4f46-a49d-3ee90bd02ab5" />

   <h6>╭┈➤&emsp;🛡️ Admin Panel to manage category names, who it is useable by, who it is visible to, and delete them.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/f869a0e7-4795-4188-abd2-896f0e9fc696" />

   <h6>╭┈➤&emsp;🛡️ Admin panel form to create a category.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/8332a466-e72c-4fa5-973b-3d70af8fed8e" />

   <h6>╭┈➤&emsp;🛡️ Admin Panel to manage tag names, who it is useable by, and delete them.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/cbe095fa-407b-4d8c-8ab0-2e24845e77dd" />

   <h6>╭┈➤&emsp;🛡️ Admin panel form to create a tag.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/963c2298-b551-4464-96db-f2a7cb19ce9e" />

   <h6>╭┈➤&emsp;🛡️ Admin panel to manage report tag names and delete them. Alternative way to view users' and students' reports with more information.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/f3c968a5-ebe4-4e10-9899-62a57a79fb70" />

   <h6>╭┈➤&emsp;🛡️ Admin panel form to create a report tag.</h6>
   <img width="900" alt="Image" src="https://github.com/user-attachments/assets/2be6283e-994b-436e-81dc-b513cd44415f" />
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
![ERD](./docs/images/erd.png)
---

## Getting Started

Follow these instructions to get a local copy of the project up and running.

### Prerequisites

Before you begin, ensure you have the following installed. The versions listed are what the project has been developed and tested against — other versions may work but are not guaranteed.

* [Node.js and npm](https://nodejs.org/) - **Node.js v24.14.0**, **npm 11.9.0**

* [PHP](https://www.php.net/downloads.php) - **PHP 8.3**, Thread-Safe / VS16 x64 ZIP, tested with **PHP 8.3.27**

* [Composer](https://getcomposer.org/) — **Composer 2.8.12**

* [PHP SQL Server Drivers](https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server?view=sql-server-ver17)

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

4. Click or type the Local link:
   ```bash
   ➜ Local:  http://localhost:5173/
   ```

### 2. Backend Setup
1. Download the PHP **Thread-Safe / VS16 x64 ZIP** and extract it to a folder on your computer, such as `C:\php-8.3.27-Win32-vs16-x64`. Add the extracted PHP folder (the folder containing `php.exe`) to the system **Path** environment variable.
2. From the **SQLSRV5.13.1_Windows.zip** file you downloaded, copy the thread-safe x64 drivers **php_pdo_sqlsrv_83_ts_x64.dll** and **php_sqlsrv_83_ts_x64.dll**, or equivalent drivers for the version of PHP you downloaded, and paste them into the **ext** file inside your PHP installation.
3. Make a copy of **php.ini-development** inside your PHP installation folder and rename the copy to **php.ini**.
4. Inside **php.ini**, remove the **;** from in front of **extension=openssl** and **extension=mbstring**, then paste the following SQL Server extensions below or their equivalents under **;zend_extension=opcache**.
   ```bash
   extension=php_sqlsrv_83_ts_x64.dll
   extension=php_pdo_sqlsrv_83_ts_x64.dll
   ```
5. Copy the path of the **ext** folder inside your PHP installation folder and paste it inside the double quotes for `extension_dir` in **php.ini**, then save **php.ini**.
   ```bash
   ; On windows:
   extension_dir = "C:\php-8.3.27-Win32-vs16-x64\ext"
   ```
6. In a **new terminal window**, navigate to the backend directory from the project root:
   ```bash
   cd backend
   ```
7. Install the required PHP dependencies using Composer:
   ```bash
   composer install
   ```
8. **Configuring Your Environment Variables:** The backend needs credentials to run.
   * First, copy the example `.env.example` file to create your own local configuration file:
   ```bash
   cp .env.example .env
   ```
   * Next, open the new `.env` file.
   * Fill in the required parameters (database credentials, application secrets) with your correct development values.
   * **Setting up your emailing service:** This requires at the least these three variables configured with your emailing service in order to generate emails for comments and OTPs.
   ```bash
   EMAIL_API_KEY="Email-API-Key"
   EMAIL_FROM_ADDRESS="some.email@outlook.com"
   EMAIL_FROM_NAME="EmailName"
   ```
   * **Using One-Time Passcode:** For development purposes, set `GLOBAL_OTP` to any 6-digit passcode to bypass generating and emailing OTPs. Otherwise, generating OTPs requires having the email service *already configured* and setting `GLOBAL_OTP` to anything non-6-digit.
   ```bash
   GLOBAL_OTP="" # OTP generation with emailing enabled
   ```
   * **Connecting your Azure Storage:** In order to store images from posts and comments, you need to configure your `AZURE_STORAGE_CONNECTION_STRING` and `AZURE_STORAGE_ACCOUNT_NAME`.
    ```bash
   AZURE_STORAGE_CONNECTION_STRING="YourConnectionString"
   AZURE_STORAGE_ACCOUNT_NAME="YourAccountName"
   ```
   * **Setting your secret Hashing Key:** This key can be anything you want and will be used to generate various tokens.
    ```bash
   HMAC_KEY="some-very-secure-key"
   ```
   * **Connecting your Database:** These variables will tell the applications where to store and look for data.
   ```bash
   DB_SERVER="your-sql-server-host"
   DB_DATABASE="your-database-name"
   DB_USER="your-username"
   DB_PASS="your-password"
   ```
   * **Additional configurations:** The `COMMENT_EMAIL_COOLDOWN_MINUTES` can be set to configure how long between comments it takes, in minutes, to send emails to the author.
   ```bash
   COMMENT_EMAIL_COOLDOWN_MINUTES="10" # 10 minute cooldown
   ```

9. **After creating your .env file and configuring environment variables:** Run your backend **Local** Development Server while in the <em>/backend</em> folder.
   ```bash
   php -S localhost:8080 -t public
   ```

### 3. Database Migrations

The database schema is managed through versioned SQL migration scripts in [backend/database/migrations/](backend/database/migrations/). A runner script at [backend/database/migrate.php](backend/database/migrate.php) applies any migrations that haven't been run yet and records them in a `Forum_SchemaVersions` tracking table, so it is safe to run repeatedly.

**Before running migrations:**

1. Make sure your SQL Server instance is running and that the target database already exists (the runner does **not** create the database itself, only the tables inside it).
2. Confirm your `backend/.env` file has the correct values for at least:
   ```bash
   DB_SERVER=your-sql-server-host
   DB_DATABASE=your-database-name
   DB_USER=your-username
   DB_PASS=your-password
   ```
3. Ensure `composer install` has been run so `vendor/autoload.php` exists.

**Run the migrations** from the backend directory:

```bash
cd backend
php database/migrate.php
```

Expected output looks like:

```
== Database migrations ==
Server: <server> | DB: <database>
Scanning: .../backend/database/migrations

APPLY 001_user_roles.sql ... OK
APPLY 002_users_and_auth.sql ... OK
...
Done. Applied: N, Skipped: 0
```

On subsequent runs, any already-applied scripts will be listed as `SKIP` and only new migration files will be executed. If a migration fails, the script rolls back that batch and exits with a non-zero status so the database is left in a consistent state.

**Adding a new migration:**

1. Create a new file in [backend/database/migrations/](backend/database/migrations/) using the next sequential prefix (e.g., `010_my_change.sql`).
2. Write T-SQL compatible with Microsoft SQL Server; use `GO` on its own line to separate batches if needed.
3. Commit the file along with the code change that depends on it, and run `php database/migrate.php` locally to verify.

---

## 🧪 Testing

The project uses automated testing on both the frontend and backend to ensure correctness of components, API endpoints, and multi-component feature flows.

### Frontend Testing

The frontend is tested with **[Vitest](https://vitest.dev/)**. Tests live under [frontend/tests/](frontend/tests/) and are organized into:

- [frontend/tests/components/](frontend/tests/components/) - Unit tests for individual Vue components (admin, forum, user).
- [frontend/tests/views/](frontend/tests/views/) - Tests for full page-level views (auth, forum, terms gate, etc.).
- [frontend/tests/integration/](frontend/tests/integration/) - Integration tests that mount multiple components together with mocked API/router/store layers to verify complete feature flows (e.g., UserProfile, CommentSection).

**Run the frontend tests:**

```bash
cd frontend
npm install
npx vitest run                 # run all tests
npx vitest                     # run in watch mode
npx vitest run path/to/file    # run a specific test file
```

### Backend Testing

The backend is tested with **[PHPUnit](https://phpunit.de/)**. Tests live under [backend/tests/](backend/tests/) and are split into:

- [backend/tests/](backend/tests/) — Controller-level unit tests (Auth, User, Post, Comment, Report, Admin, Terms).
- [backend/tests/Integration/](backend/tests/Integration/) — Integration tests that exercise full request/response flows through the Slim app (e.g., creating and fetching posts, commenting on posts, user profile pages, notification settings).

**Run the backend tests:**

```bash
cd backend
composer install
./vendor/bin/phpunit tests                   # run all tests
./vendor/bin/phpunit tests/NameOfTest.php    # run a specific test file
./vendor/bin/phpunit tests/Integration       # run only integration tests
```

### Tested On

The test suites have been verified against the following environment:

| Component | Version |
| :--- | :--- |
| **OS** | Windows 11 Pro |
| **Node.js** | v24.14.0 |
| **npm** | 11.9.0 |
| **PHP** | 8.3.27 (ZTS, VS16, x64) |
| **Composer** | 2.8.12 |
| **PHPUnit** | ^12.5 |

Other versions may work but have not been validated. If you hit environment-related test failures, try matching the versions above.

---

## 🚀 Deployment

The app can be deployed to [Render](https://render.com) as a single Docker web service that builds the Vue frontend and serves both the SPA and the PHP API from one Apache container.

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running
- A [Docker Hub](https://hub.docker.com) account
- A [Render](https://render.com) account
- An externally accessible Microsoft SQL Server (e.g. Azure SQL) with migrations already applied
- A [Brevo](https://www.brevo.com) account with a verified sender email and an API key

### Deployment Files

The following files are required locally but are excluded from version control via `.gitignore`:

| File | Purpose |
| :--- | :--- |
| `Dockerfile` | Builds the Vue SPA and PHP/Apache image with MSSQL drivers |
| `backend/public/.htaccess` | Routes `/api/*` to PHP and everything else to the Vue SPA |
| `.dockerignore` | Excludes `vendor/`, `node_modules/`, `.env` from the Docker build context |

### Steps

**1. Build the Docker image** from the project root:
```bash
docker build -t YOURDOCKERHUBUSERNAME/owp-forum .
```

**2. Push to Docker Hub:**
```bash
docker push YOURDOCKERHUBUSERNAME/owp-forum
```

**3. Create a Render Web Service:**
- Go to Render → **New → Web Service**
- Choose **"Deploy an existing image"**
- Image URL: `YOURDOCKERHUBUSERNAME/owp-forum`

**4. Set environment variables** in Render's dashboard:

| Key | Value |
| :--- | :--- |
| `APP_ENV` | `production` |
| `DB_SERVER` | `your-sql-server.database.windows.net,1433` |
| `DB_DATABASE` | `your-database-name` |
| `DB_USER` | `your-db-username` |
| `DB_PASS` | `your-db-password` |
| `HMAC_KEY` | any long random secret string |
| `AZURE_STORAGE_ACCOUNT_NAME` | from Azure Portal |
| `AZURE_STORAGE_CONNECTION_STRING` | from Azure Portal |
| `EMAIL_API_KEY` | your Brevo API key |
| `EMAIL_FROM_ADDRESS` | your verified Brevo sender email |
| `EMAIL_FROM_NAME` | `OWP Forum` |
| `EMAIL_SANDBOX` | `false` |
| `COMMENT_EMAIL_COOLDOWN_MINUTES` | `10` |
| `GLOBAL_OTP` | leave blank (uses real OTP emails) |

**5. Configure external services:**

- **Azure SQL firewall**: Enable _"Allow Azure services and resources to access this server"_ in the Azure Portal under your SQL Server → Networking. This allows Render's servers to reach the database.
- **Brevo authorized IPs**: Go to Brevo → Settings → Authorized IPs and add Render's outbound IP. The IP will appear in your Render logs if Brevo rejects a request with a 401 error.

**6. Deploy**: Click **Create Web Service**. On subsequent updates, rebuild and push the image then click **Manual Deploy → Deploy latest image** in Render.

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

- **Instructor:** Dr. Kenneth Elliott
- **Lab Advisor:** Prof. Harvin Singh & Maryam Siddique
- **Office of Water Programs (OWP)** for partnering with us and providing project requirements and feedback.

---

## 👥 Meet the Team

<p align="center">
  <img width="175" height="110" alt="Bug Busters Logo" src="https://github.com/user-attachments/assets/e83ab8a5-90ef-4d55-8795-6aac07eb77b2" />
</p>

<div align="center">
<table>
  <tr>
    <td align="center" width="25%">
      <a href="https://github.com/MaxShkrabak">
        <img src="https://github.com/MaxShkrabak.png" width="100px" alt="Maksim Shkrabak"/><br />
        <sub><b>Maksim Shkrabak</b></sub>
      </a><br />
      <sub>Developer</sub><br />
      <sub>shkrabakmaksim@gmail.com</sub><br><br>
      </td>
    <td align="center" width="25%">
      <a href="https://github.com/DanielIvanilov">
        <img src="https://github.com/DanielIvanilov.png" width="100px" alt="Daniel Ivanilov"/><br />
        <sub><b>Daniel Ivanilov</b></sub>
      </a><br />
      <sub>Developer</sub><br />
      <sub>ivanilovdaniel@gmail.com</sub><br><br>
    </td>
    <td align="center" width="25%">
      <a href="https://github.com/JeffreySardella">
        <img src="https://github.com/JeffreySardella.png" width="100px" alt="Jeffrey Sardella"/><br />
        <sub><b>Jeffrey Sardella</b></sub>
      </a><br />
      <sub>Developer</sub><br />
      <sub>sardellajeffrey123@gmail.com</sub><br><br>
    </td>
    <td align="center" width="25%">
      <a href="https://github.com/Zamazinga">
        <img src="https://github.com/Zamazinga.png" width="100px" alt="Oleksii Andriienko"/><br />
        <sub><b>Oleksii Andriienko</b></sub>
      </a><br />
      <sub>Developer</sub><br />
      <sub>oandriienko@csus.edu</sub><br><br>
    </td>
  </tr>

  <tr>
    <td align="center" width="25%">
      <br> <a href="https://github.com/Sillor">
        <img src="https://github.com/Sillor.png" width="100px" alt="Egor Strakhov"/><br />
        <sub><b>Egor Strakhov</b></sub>
      </a><br />
      <sub>Developer</sub><br />
      <sub>estrakhov@csus.edu</sub>
    </td>
    <td align="center" width="25%">
      <br>
      <a href="https://github.com/Scander3">
        <img src="https://github.com/Scander3.png" width="100px" alt="Ruzanna Shomakhova"/><br />
        <sub><b>Ruzanna Shomakhova</b></sub>
      </a><br />
      <sub>Developer</sub><br />
      <sub>rshomakhova@csus.edu</sub>
    </td>
    <td align="center" width="25%">
      <br>
      <a href="https://github.com/GSD1453">
        <img src="https://github.com/GSD1453.png" width="100px" alt="Gianni Dumitru"/><br />
        <sub><b>Gianni Dumitru</b></sub>
      </a><br />
      <sub>Developer</sub><br />
      <sub>gsdumitru@gmail.com</sub>
    </td>
    <td align="center" width="25%">
      <br>
      <a href="https://github.com/gavinkabelcsus">
        <img src="https://github.com/gavinkabelcsus.png" width="100px" alt="Gavin Kabel"/><br />
        <sub><b>Gavin Kabel</b></sub>
      </a><br />
      <sub>Developer</sub><br />
      <sub>gbkabel@yahoo.com</sub>
    </td>
  </tr>
</table>
</div>
