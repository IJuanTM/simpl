<div align="center">

[<img src="src/public/img/svg/simpl.svg" alt="Simpl logo" width="256">](https://simpl.iwanvanderwal.nl)

# Simpl

#### Version 1.0.0

<br>

#### An easy-to-use PHP, HTML, CSS, JavaScript and Sass framework!

[![PHP logo](https://img.shields.io/badge/php-8.2.10-777BB3?logo=php)](https://www.php.net)
[![Composer logo](https://img.shields.io/badge/composer-2.6.3-89552C?logo=composer)](https://getcomposer.org)
[![Node.js logo](https://img.shields.io/badge/node.js-20.6.1-5FA04E?logo=node.js)](https://nodejs.org)
[![npm logo](https://img.shields.io/badge/npm-10.1.0-CB0000?logo=npm)](https://www.npmjs.com)
[![Sass logo](https://img.shields.io/badge/sass-1.67.0-CF649A?logo=sass)](https://sass-lang.com)

<br>

### [Features](#features) • [Getting Started](#getting-started) • [Download](#download) • [Roadmap](#roadmap) • [Updates](#updates) • [Credits](#credits) • [License](#license)

</div>

<br>

## Features

* Easy to use
* Quick setup
* Composer PSR-4 autoloading
* Makes use of a MVC system using PHP
* Works with both Windows and Linux
* Made to work with the latest versions of PHP, Composer, Node.js, npm and Sass
* Includes a bunch of npm scripts for compiling Sass and JavaScript, as well as live-reloading
* Comes with example pages and a default landing page
* A collection of handy JavaScript functions

_Read more about Simpl [here](https://simpl.iwanvanderwal.nl/about)._

<br>

## Getting Started

### Step 0: Requirements

Before you can start using Simpl you will need to make sure you have the following installed:

* [PHP](https://www.php.net) (at least version **8.2.10**)
* [Composer](https://getcomposer.org) (at least version **2.6.3**)
* [Node.js](https://nodejs.org) (at least version **20.6.1**)
* [npm](https://www.npmjs.com) (at least version **10.1.0**)

### Step 1: Download Simpl

Download the latest version of Simpl from [here](#download) and extract the folder. Next, copy the `src` folder to your localhost folder. For localhost management I **recommend** using [WAMP](https://www.wampserver.com) or [XAMPP](https://www.apachefriends.org) if you're on Windows, or plain [Apache](https://httpd.apache.org) if you're on Linux.

Next, rename the `src` folder to the name of your project and open this folder in an IDE to your liking, I **recommend** using [PHPStorm](https://www.jetbrains.com/phpstorm) or [Microsoft Visual Studio Code](https://code.visualstudio.com).

### Step 2: Run composer install

Simpl makes use of PSR-4 autoloading, for this to work you will have to run `composer install` in the root folder of your project. This will install the required packages and create the `vendor` folder. It will also install the `phpdotenv` package, which is used for loading environment variables from the `.env` file.

### Step 3: Install packages

Next, a few npm packages will need to be installed. You can do this by running `npm install` in the root folder of your project, this will also run the `build` script, which will compile the default Sass and JavaScript files.

### Step 4: Go to your localhost

Now if you open your browser and go to your localhost url of this project, you should see the default landing page. If the page doesn't have any styling there is a chance there was an issue compiling the Sass files, you can try to fix this by running the `build` script again manually using `npm run build`.

### Step 5: Install add-ons (optional)

Simpl comes with a few add-ons, these are optional and can be added to Simpl by downloading them from [here](https://simpl.iwanvanderwal.nl/add-ons) and extracting them to the `src` folder. When asked to overwrite files, click **No** and manually copy the code in the files to the existing files. Each add-on has a `README.md` file with instructions on how to install it.

### Step 6: Start coding!

Now you're all set up and ready to start coding! This is the framework in a nutshell:

#### Npm scripts

The following scrips are included in the `package.json` file:

* `build:sass`: Compiles the Sass files
* `build:js`: Bundles the `main.js` file using Rollup and minifies it using the Terser plugin
* `build`: Runs both build commands
* `watch:sass`: Watches the Sass files for changes and recompiles them
* `watch:js`: Watches the `main.js` file for changes and rebundles it and minifies it
* `watch`: Runs both watch commands
* `live`: Runs the browser sync server to automatically reload the page when a file is changed
* `dev`: Runs the live server and watches the files for changes

After changing the styling or JavaScript of your website you will have to run the `build` script to compile the files. This will compile the Sass and JavaScript files and save them to the `public` folder. This can also be done automatically by running the `watch` script.

To make your website automatically reload when a file is changed you can run the `live` script. This will start the browser sync server and automatically reload the page when a file is changed. _Important: Before running the live-reload script you will have to change the url in the script, it's located in the `package.json` file. Change this url to your localhost._

Alternatively you can run the `dev` script, this will start the live server and watch the files for changes.

#### Config

Config files for the PHP framework are located in the `app/Config` folder. Here you can find the `app.php` file, which contains the configuration for the framework.

Feel free to add your own config files here as each `.php` file in this folder will be loaded automatically on page load.

#### Controllers, Models and Pages

In the `app` folder you can find the `Controllers`, `Models` and `Pages` folders.

The `Controllers` folder contains an `AppController` and a `PageController` by default, these contain the main functions for the framework.

The `Models` folder contains a `PageModel` by default, this contains an `obj` and a `url` property, these hold the current `Page` object if it exists and the current page name, subpages and parameters of the url. These can be accessed at all times in any view by using `$this->`.

The `Pages` folder contains a `Page` class for each view that requires PHP code. See these as specific controllers for each view. A `Page` is not required for each view, if a view doesn't require PHP code, you don't need to create a `Page` for it.

There is also a `LogController` in the `Controllers` folder, this is used for logging errors and warnings. You can use this controller to log errors and warnings in your own code. There will be stored in the `app/Logs` folder.

#### Views

You can find the HTML code in the `views` folder, here you can find the `home.phtml` file, as well as a `parts` folder containg the `header.phtml` and `footer.phtml` files.

#### Styling and JavaScript

The styling is located in the `scss` folder. Here each view has its own stylesheet, as well as stylesheets for the parts like the header and footer. In the `config` folder you can find stylesheets for things like variables, mixins and breakpoints. All of these stylesheets are imported in the `main.scss` file, which is the main stylesheet.

The JavaScript code is located in the `js` folder. Simpl makes use of Rollup to bundle the JavaScript files, because of this you are able to create multiple JavaScript files and import them in the `main.js` file.

#### Public

The `public` folder contains the compiled Sass and JavaScript files, these are the files that are used in the website. Here you can also find things like images and fonts.

<br>

_If you need more information about the framework and its features, you can find the documentation [here](https://simpl.iwanvanderwal.nl/docs) (page under construction)._

<br>

## Download

Download the latest version of Simpl from [here](https://simpl.iwanvanderwal.nl/download/latest).

Or clone the repository using `git clone https://github.com/IJuanTM/simpl`.

<br>

## Roadmap

- [ ] Make Simpl website
- [ ] Write documentation
- [ ] Add more add-ons
- [ ] Improve the form validation system

<br>

## Updates

### Version 1.0

#### Version 1.0.0 (2023-09-18)

* Initial release

<br>

## Credits

### Composer packages

* [PHP dotenv (5.5.0)](https://github.com/vlucas/phpdotenv)

### Node packages

* [npm](https://www.npmjs.com)
* [npm-run-all](https://www.npmjs.com/package/npm-run-all)
* [sass](https://sass-lang.com)
* [rollup](https://rollupjs.org)
* [@rollup/plugin-commonjs](https://www.npmjs.com/package/@rollup/plugin-commonjs)
* [@rollup/plugin-node-resolve](https://www.npmjs.com/package/@rollup/plugin-node-resolve)
* [@rollup/plugin-terser](https://www.npmjs.com/package/@rollup/plugin-terser)
* [browser-sync](https://www.browsersync.io)

### Font

* [Jost](https://fonts.google.com/specimen/Jost)

### Extra

* [Hamburgers (1.2.0)](https://github.com/jonsuh/hamburgers)
* [The New CCS Reset (1.9.0)](https://github.com/elad2412/the-new-css-reset)
* [Font Awesome _Free_ (6.4.2)](https://fontawesome.com)

<br>

## License

Simpl is licensed under the GNU General Public License v3.0.

Feel free to use, modify, and redistribute Simpl, but please give credit to the original author.
