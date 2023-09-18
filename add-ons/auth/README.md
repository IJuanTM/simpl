## Auth

### Description

#### About

This add-on adds a user system to your Simpl project. It includes code for logging in, logging out, registering, verifing your account and a contact form.

It also includes a user profile page, which can be used to display user information. It also comes with a form to edit your profile. As well as a page to change your password.

There is also a way to reset your password if you forget it. It includes a page to enter your email address to send a reset link to. And a page to enter a new password after clicking on the reset link in the email.

Furthermore, it includes an admin system for managing users' accounts. It includes code for viewing, editing and deleting users. It also includes code for adding and removing roles from users.

It also includes code for changing the navigation bar based on if the user is logged in and to add the added pages from this add-on to the menu.

#### Controllers

In the `UserController.php` file you can find the code for the user system. The functions here are used in mutiple different pages in the add-on. The `MailController.php` file contains the code for sending emails. The `FormController.php` file contains the code for a form alert that is displayed to show the user information about the form submission.

#### Pages

In the `pages` folder you can find the code that goes along with the views for logging in, registering, verifying your account, the contact form, the user profile page, the change password page, the forgot password page, the reset password page and the admin system.

In these pages form submissions are handled and the user is redirected to the correct page based on the form submission. Each page contains its own functions that go along with the views. For example, the `login.phtml` file contains a `login()` function that handles the login form submission.

#### Views

In the `views` folder you can find pages for all of the pages that are used in the add-on. These pages contain forms for logging in, registering, etc. In the `parts` folder you can find the code for changing the navigation bar based on if the user is logged in. Here you can also find a `mails` folder with mail templates used by the framework, as well as a `users` folder with subpages for the admin system.

#### Styling

In the `scss` folder you can find the styling for the add-on. Teh styling for the forms can be found in the `components/form` file, and stying for the users table in the `components/table` file. In the `views` folder you can find the styling for the profile and users pages.

#### Database

Also included is code for a database connection to go along with the user system. It uses PDO to connect to the database. It has functions to execute queries, fetch single rows and fetch multiple rows of data. It also has a function to fetch the amount of returned rows of a query. Furthermore, it has a function to bind parameters to a query and a function to escape strings to prevent SQL injections.

It also comes with an example sql file to create a database with a user table and a user role table for quick setup together with this add-on.

The database connection values are stored in the `config/database.php` as constants. By default, the database connection values are set to connect to a local database using the root user, don't forget to change these values to your own database connection values and add your own database user instead of root for security reasons.

#### JavaScript

Lastly, this add-on also adds functionality to the visibility toggle icon of the password field in a login input field and a warning when the user has Caps Lock on when typing in the password field. It also has code to show a counter for the max length of textareas. There is also code for disabling the save button when no changes have been made to the form or when the required checkbox to confirm the changes has not been checked. And lastly, it has code for disabling the submit button when the form is being submitted.

### Requirements

* Icons add-on

_Before installing this add-on, make sure you have installed and setup either MySQL (tested with version **8.1.0**) or MariaDB (tested with version **11.1.2**)._

### Installation

The easiest way to install this add-on is to just extract it in your Simpl project's folder. You will be prompted to overwrite some files, choose the option to _**skip**_ these files. Then manually copy the code in these files to your project's files.

Alternatively, you can follow these steps:

1. Copy the contents of the `.env` file to your project's `.env` file
2. Copy the contents of the `app/Config` folder to your Simpl projects's `app/Config` folder
3. Copy the contents of the `app/Controllers` folder to your Simpl projects's `app/Controllers` folder
4. Copy the `app/Database` folder to the `app` folder of your Simpl project's folder
5. Copy the `app/Pages` folder to the `app` folder of your Simpl project's folder
6. Copy the `js/input.js` file to the `js` folder of your Simpl project's folder
7. Copy the code from the `js/main.js` file to the `js/main.js` file of your project
8. Copy the `scss/components` folder to the `scss` folder in your project folder
9. Copy the contents of the `scss/views` folder to your project's `scss/views` folder
10. Copy the imports from the `scss/main.scss` file to your project's `scss/main.scss` file
11. Copy the contents of the `views/parts/header.phtml` file to your project's `views/parts/header.phtml` file
12. Recommended is to use the example sql file to create a database with a user table and a user role table, as this is what the add-on uses by default. You can find the example sql file in the `sql` folder of this add-on. If you want to use your own database, you can change the database connection values in the `config/database.php` file.
