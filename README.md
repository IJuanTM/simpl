<div align="center">

[<img src="src/public/img/svg/simpl.svg" alt="Simpl logo" width="256">](https://simpl.iwanvanderwal.nl/)

# Simpl

#### An easy-to-use PHP, HTML, Sass and TypeScript framework!

[![GitHub release](https://img.shields.io/github/v/release/IJuanTM/simpl?color=D01018&logo=data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyBpZD0iYSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB3aWR0aD0iNTEyIiBoZWlnaHQ9IjUxMiIgdmlld0JveD0iMCAwIDQ0My40MSA1MTIiPgogIDxkZWZzPgogICAgPGZpbHRlciBpZD0iYiIgZmlsdGVyVW5pdHM9InVzZXJTcGFjZU9uVXNlIj4KICAgICAgPGZlT2Zmc2V0IGR4PSIwIiBkeT0iMCIvPgogICAgICA8ZmVHYXVzc2lhbkJsdXIgcmVzdWx0PSJjIiBzdGREZXZpYXRpb249IjIiLz4KICAgICAgPGZlRmxvb2QgZmxvb2QtY29sb3I9IiMwMDAiIGZsb29kLW9wYWNpdHk9Ii4yNSIvPgogICAgICA8ZmVDb21wb3NpdGUgaW4yPSJjIiBvcGVyYXRvcj0iaW4iLz4KICAgICAgPGZlQ29tcG9zaXRlIGluPSJTb3VyY2VHcmFwaGljIi8+CiAgICA8L2ZpbHRlcj4KICAgIDxmaWx0ZXIgaWQ9ImQiIGZpbHRlclVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+CiAgICAgIDxmZU9mZnNldCBkeD0iMCIgZHk9IjAiLz4KICAgICAgPGZlR2F1c3NpYW5CbHVyIHJlc3VsdD0iZSIgc3RkRGV2aWF0aW9uPSIyIi8+CiAgICAgIDxmZUZsb29kIGZsb29kLWNvbG9yPSIjMDAwIiBmbG9vZC1vcGFjaXR5PSIuMjUiLz4KICAgICAgPGZlQ29tcG9zaXRlIGluMj0iZSIgb3BlcmF0b3I9ImluIi8+CiAgICAgIDxmZUNvbXBvc2l0ZSBpbj0iU291cmNlR3JhcGhpYyIvPgogICAgPC9maWx0ZXI+CiAgPC9kZWZzPgogIDxnIGZpbHRlcj0idXJsKCNiKSI+CiAgICA8cG9seWdvbiBwb2ludHM9IjIyMS43MSAwIDAgMTI4IDAgMzg0IDIyMS43MSA1MTIgNDQzLjQxIDM4NCA0NDMuNDEgMTI4IDIyMS43MSAwIiBmaWxsPSIjRDAxMDE4Ii8+CiAgICA8cG9seWdvbiBwb2ludHM9IjQ0My40MSAxMjggNDQzLjQxIDM4NCAyMjEuNzEgNTEyIDIyMS43MSAyNTYgNDQzLjQxIDEyOCIgZmlsbD0iI0MwMDAwOCIvPgogICAgPHBvbHlnb24gcG9pbnRzPSIyMjEuNzEgMjU2IDIyMS43MSA1MTIgMCAzODQgMCAxMjggMjIxLjcxIDI1NiIgZmlsbD0iI0UwMjAyOCIvPgogIDwvZz4KICA8ZyBmaWx0ZXI9InVybCgjZCkiPgogICAgPHBhdGgKICAgICAgZD0iTTE5Ni4zNywzMDMuMTFjMy45Miw0Ljc0LDguMzMsOC40NSwxMy4yMiwxMS4xMXMxMC40NCw0LDE2LjY3LDRjOC4yOSwwLDE0Ljk2LTIuMjksMjAtNi44OSw1LjA0LTQuNTksNy41Ni0xMC40NCw3LjU2LTE3LjU2cy0xLjYzLTEyLjUyLTQuODktMTYuNjdjLTMuMjYtNC4xNS03LjUyLTcuNTItMTIuNzgtMTAuMTEtNS4yNi0yLjU5LTEwLjg1LTQuOTItMTYuNzgtNy0zLjg1LTEuMzMtOC4xNS0zLjA3LTEyLjg5LTUuMjItNC43NC0yLjE1LTkuMjYtNC44OS0xMy41NS04LjIyLTQuMy0zLjMzLTcuODItNy40NS0xMC41Ni0xMi4zMy0yLjc0LTQuODktNC4xMS0xMC44OS00LjExLTE4LDAtNy43LDEuOTItMTQuNTksNS43OC0yMC42NywzLjg1LTYuMDcsOS4xOC0xMC44NSwxNi0xNC4zMyw2LjgxLTMuNDgsMTQuNTktNS4yMiwyMy4zMy01LjIyczE1LjgxLDEuNTksMjIuMTEsNC43OGM2LjI5LDMuMTksMTEuNjcsNy4yNiwxNi4xMSwxMi4yMiw0LjQ0LDQuOTcsNy45MiwxMC4xOSwxMC40NSwxNS42N2wtMTYuMjIsOS4zM2MtMS45My0zLjg1LTQuMzctNy42My03LjMzLTExLjMzLTIuOTctMy43LTYuNTYtNi43NC0xMC43OC05LjExLTQuMjItMi4zNy05LjM3LTMuNTYtMTUuNDQtMy41Ni04LjQ1LDAtMTQuNTksMi4wNC0xOC40NCw2LjExLTMuODUsNC4wOC01Ljc4LDguNjMtNS43OCwxMy42NywwLDQuMywxLjExLDguMjYsMy4zMywxMS44OSwyLjIyLDMuNjMsNS45Niw3LjA0LDExLjIyLDEwLjIyLDUuMjYsMy4xOSwxMi40MSw2LjMzLDIxLjQ0LDkuNDQsNC4xNSwxLjQ4LDguNTIsMy40MSwxMy4xMSw1Ljc4LDQuNTksMi4zNyw4Ljg1LDUuMzcsMTIuNzgsOSwzLjkyLDMuNjMsNy4xNSw4LDkuNjcsMTMuMTEsMi41Miw1LjExLDMuNzgsMTEuMjIsMy43OCwxOC4zM3MtMS4zLDEyLjk2LTMuODksMTguNDRjLTIuNTksNS40OC02LjE1LDEwLjE1LTEwLjY2LDE0LTQuNTIsMy44NS05LjYzLDYuODItMTUuMzMsOC44OS01LjcsMi4wNy0xMS42NywzLjExLTE3Ljg5LDMuMTEtOC40NCwwLTE2LjI2LTEuODItMjMuNDQtNS40NS03LjE5LTMuNjMtMTMuNDgtOC40NC0xOC44OS0xNC40NC01LjQxLTYtOS44Mi0xMi40MS0xMy4yMi0xOS4yMmwxNS4xMS0xMC4yMmMzLjU2LDYuMjIsNy4yOSwxMS43LDExLjIyLDE2LjQ0WiIKICAgICAgZmlsbD0iI2ZmZiIvPgogIDwvZz4KPC9zdmc+Cg==)](https://github.com/IJuanTM/simpl/releases/latest)
[![GitHub license](https://img.shields.io/github/license/IJuanTM/simpl?color=A32D2A&logo=gnu)](LICENSE)

<br>

[![PHP logo](https://img.shields.io/badge/php-8.3.26-777BB3?logo=php)](https://www.php.net/)
[![Composer logo](https://img.shields.io/badge/composer-2.8.12-89552C?logo=composer)](https://getcomposer.org/)
[![Node.js logo](https://img.shields.io/badge/node.js-24.9.0-5FA04E?logo=node.js)](https://nodejs.org/)
[![Sass logo](https://img.shields.io/badge/sass-1.93.2-CC6699?logo=sass)](https://sass-lang.com/)
[![TypeScript logo](https://img.shields.io/badge/typescript-5.9.3-3178C6?logo=typescript)](https://www.typescriptlang.org/)
[![Vite logo](https://img.shields.io/badge/vite-7.1.9-646CFF?logo=vite)](https://vite.dev/)

<br>

### [Features](#features) • [Getting Started](#getting-started) • [Download](#download) • [Roadmap](#roadmap) • [Updates](#updates) • [Credits](#credits) • [License](#license)

</div>

<br>

## Features

* Easy to use
* Quick setup
* Composer PSR-4 autoloading
* Makes use of an MVC system using PHP
* Works with both Windows and Linux
* Made to work with the latest versions of PHP, Composer, Node.js, npm and Sass
* Makes use of Vite for bundling TypeScript files and compiling Sass files, as well as live reloading
* Comes with example pages and a default landing page
* A collection of handy TypeScript functions

_Read more about Simpl [here](https://simpl.iwanvanderwal.nl/about/)._

<br>

## Getting Started

### Step 0: Requirements

Before you can start using Simpl you will need to make sure you have the following installed:

* [PHP](https://www.php.net/)
* [Composer](https://getcomposer.org/)
* [Node.js](https://nodejs.org/)
* [npm](https://www.npmjs.com/)

### Step 1: Download Simpl

Download the latest version of Simpl from [here](#download) and extract the folder. Next, copy the `src` folder to your localhost folder. For localhost management I **recommend** using [WAMP](https://www.wampserver.com/) or [XAMPP](https://www.apachefriends.org/) if you're on Windows, or plain [Apache](https://httpd.apache.org/) if you're on Linux.

Next, rename the `src` folder to the name of your project and open this folder in an IDE to your liking, I **recommend** using [PhpStorm](https://www.jetbrains.com/phpstorm/) or [Microsoft Visual Studio Code](https://code.visualstudio.com/).

### Step 2: Run composer install

Simpl makes use of PSR-4 autoloading, for this to work you will have to run `composer install` in the root folder of your project. This will install the required packages and create the `vendor` folder. It will also install the `phpdotenv` package, which is used for loading environment variables from the `.env` file.

### Step 3: Install packages

Next, a few npm packages will need to be installed. You can do this by running `npm install` in the root folder of your project, this will also run the `build` script, which will compile the default Sass and TypeScript files to the `public` folder using Vite and the `sass` package.

### Step 4: Set up your localhost

Set up a localhost for your project. If you're using WAMP or XAMPP, you can do this by creating a new virtual host. If you're using plain Apache, you will have to create a new configuration file in the `sites-available` folder and enable it using `a2ensite`. _Make sure the document root is set to the `public` folder of your project._

Now if you open your browser and go to your localhost url of this project, you should see the default landing page. If the page doesn't have any styling there is a chance there was an issue compiling the Sass files, you can try to fix this by running the `build` script again manually using `npm run build`.

### Step 5: Install add-ons (optional)

Simpl comes with a few add-ons, these are optional and can be added to Simpl by downloading them from [here](https://simpl.iwanvanderwal.nl/add-ons/) and extracting them to the `src` folder. When asked to overwrite files, click **No** and manually copy the code in the files to the existing files. Each add-on has a `README.md` file with instructions on how to install it.

### Step 6: Start coding!

Now you're all set up and ready to start coding! This is the framework in a nutshell:

#### Npm scripts

The following scrips are included in the `package.json` file:

* `dev` - Runs the `watch` and `live` scripts in parallel
* `build` - Runs the `build:scss` and `build:ts` scripts after one another
* `watch` - Runs the `watch:scss` and `watch:ts` scripts in parallel
* `build:scss` - Compiles the Sass files to the `public/css` folder using the `sass` package
* `build:ts` - Bundles the TypeScript files to the `public/js` folder using Vite
* `watch:scss` - Watches the Sass files for changes and compiles them to the `public/css` folder using the `sass` package
* `watch:ts` - Watches the TypeScript files for changes and bundles them to the `public/js` folder using Vite
* `live` - Runs a local server using `browser-sync` and watches the `public` folder for changes, as well as the `views` folder for changes, reloading the browser automatically when a change is detected

After changing the styling or TypeScript of your website you will have to run the `build` script to compile the files. This will run Vite to compile the Sass and TypeScript files and output them to the `public` folder. This can also be done automatically by running the `dev` script, which will watch the files for changes and recompile them automatically with live reloading.

_The reason Vite is not used as a server, but instead only to bundle the TypeScript files and files from packages like Font Awesome, is because Vite's server does not support CSS source maps for Sass files, which makes debugging the styling a lot harder. Also hosting the website using Vite's server makes it so that the scripts and styling are not loaded properly when run through a PHP webserver. _

#### Config

Config files for the PHP framework are located in the `app/Config` folder. Here you can find the `app.php` file, which contains the configuration for the framework.

Feel free to add your own config files here, as each `.php` file in this folder will be loaded automatically on page load.

#### Controllers, Models and Pages

In the `app` folder you can find the `Controllers`, `Models` and `Pages` folders.

* The `Controllers` folder contains an `AppController` and a `PageController`, these contain the main functions for the framework.
    - Besides these there are also the `AlertController`, `AliasController` and `SessionController`, these are used for handling alerts, aliases and sessions respectively. These are used by the main controllers. In the `AliasController` you can register aliases for urls, these can be used to create custom urls for pages, for example by default there is a `welcome` alias for the home page.
    - There is also a `LogController`, this is used for logging errors and warnings. You can use this controller to log errors and warnings in your own code. There will be stored in the `app/Logs` folder, with each different type having its own logfile.
* The `Models` folder contains different models that are used in the framework, like the `Page` and `Url` models. These are used to store data about pages and urls.
* The `Pages` folder contains a `Page` class for each view that requires PHP code. See these as specific controllers for each view. A `Page` is not required for each view, if a view doesn't require PHP code, you don't need to create a `Page` for it. By default, there is a `HomePage` class for the `home.phtml` view, as well as an `ErrorPage` class for handling errors.

Besides these there are also a couple of supporting folders like the `Enums` and `Scripts` folders. The `Enums` folder contains enums that are used in the framework. The `Scripts` folder contains scripts that are used in the framework, for example the `start.php` script, which is used to start the framework.

#### Views

You can find the HTML code in the `views` folder, here you can find the `home.phtml` file, as well as a `parts` folder containing the `header.phtml` and `footer.phtml` files.

#### Styling and TypeScript

The styling is located in the `scss` folder. Here each view has its own stylesheet, as well as stylesheets for the parts like the header and footer. In the `config` folder you can find stylesheets for things like variables, mixins and breakpoints. All of these stylesheets are imported in the `main.scss` file, which is the main stylesheet.

The TypeScript code is located in the `ts` folder. Simpl makes use of Rollup to bundle the TypeScript files, because of this you are able to create multiple TypeScript files and import them in the `main.ts` file.

#### Public

The `public` folder contains the static files like images and fonts, as well as other static files for the website. Here you can also find the `index.php` file, which is the entry point for the framework. This file loads the autoloader, environment variables and runs the main `AppController`. After running the `build` script, the compiled Sass and TypeScript files will be located in this folder under their respective `css` and `js` folders.

<br>

_If you need more information about the framework and its features, you can find the documentation [here](https://simpl.iwanvanderwal.nl/docs/) (page under construction)._

<br>

## Download

Download the latest version of Simpl from [here](https://simpl.iwanvanderwal.nl/download/latest/).

Or clone the repository using `git clone https://github.com/IJuanTM/simpl/`.

<br>

## Roadmap

- [x] Improve the form validation system
- [x] Improve page logic and add more functionality
- [x] Improve mail system
- [x] Improve the auth system
- [x] Nicer default styling
- [ ] Improve logger, make it more flexible
- [ ] Update Simpl website
- [ ] Write documentation
- [ ] Add more add-ons?

<br>

## Updates

### Version 1.0

#### Version 1.0.0 (2023-09-18)

* Initial release

#### Version 1.1.0 (2023-12-20)

* Ready for use with PHP 8.3.0
* Updated composer and npm packages
* Added remember me functionality to the auth system
* Fixed a small issue with the manifest file
* Updated the-new-css-reset
* Updated Font Awesome icons

#### Version 1.1.1 (2023-12-20)

* Quick update to PHP 8.3.1

#### Version 1.2.0 (2024-01-29)

* Changed some constants to environment variables
* Updated .htaccess file and fixed an issue with the URL builder
* Updated npm packages
* Tested with PHP 8.3.2
* Fix for error when the `Logs` directory doesn't exist

#### Version 1.3.0 (2024-06-19)

* Switched to TypeScript instead of JavaScript
* Small fixes to npm scripts
* Updated Font Awesome icons
* Newer database collation in database example file
* Updated npm packages
* Support for PHP 8.3.8

#### Version 1.4.0 (2025-10-04)

* Security improvements
* Added a start script for the application
* Added a cron job script
* Small fixes and improvements all around
* Updated npm packages
* Support for PHP 8.3.26
* Updated Font Awesome icons to version 7.0.1
* Updated the-new-css-reset to version 1.11.3
* Improvements to the auth system (improved controller and pages logic)
* Improved MailController by added async functionality for sending emails and more
* Improved form validation by generalizing the validation functions
* Added the option for multiple form alerts
* Changed Sass to use @use and @forward instead of @import for future compatibility as @import is being deprecated
* Improved Sass structure
* Added flex gap classes to the flexbox system
* Improved page controller logic (page classes now load before the top part and have extended functionality)
* Added custom route support to the PageController, these can be used to create direct endpoints without creating a page class or view for it
* Added options for aliases to pages (multiple urls for one page, or custom urls)
* Improved error handling and logging
* Moved error page logic to its own Page class
* Added a timestamp to <head> files to prevent caching issues (dynamically added in the url() method)
* Improved tsconfig.json file
* Added enums to the php code for better type safety and readability
* The auth add-on now has config options for password requirements and to enable/disable user email verification
* The project now uses Vite for bundling TypeScript files. The sass files are still compiled using the sass npm package and live reloading is still done using browser-sync.
* Entire new styling for the default Simpl pages and components

#### Version 1.5.0 (2025-12-01)

* Added support for PHP 8.5
* Updated composer and npm packages

<br>

## Credits

### Composer packages

* [PHP dotenv](https://github.com/vlucas/phpdotenv/)

### Node packages

#### Development

* [browser-sync](https://browsersync.io/)
* [npm-run-all](https://github.com/mysticatea/npm-run-all/)
* [sass](https://sass-lang.com/)
* [terser](https://terser.org/)
* [typescript](https://www.typescriptlang.org/)
* [vite](https://vite.dev/)

#### Production

* [@fortawesome/fontawesome-free](https://fontawesome.com/)
* [hamburgers](https://jonsuh.com/hamburgers/)
* [the-new-css-reset](https://elad2412.github.io/the-new-css-reset/)

### Fonts

* [IBM Plex Sans](https://fonts.google.com/specimen/IBM+Plex+Sans/)
* [Inter](https://fonts.google.com/specimen/Inter/)

<br>

## License

Simpl is licensed under the GNU General Public License v3.0.

Feel free to use, modify, and redistribute Simpl, but please give credit to the original author.
