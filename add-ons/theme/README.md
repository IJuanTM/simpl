## Theme

### Description

This add-on adds functionality to be able to switch between different themes for your website. By default it comes with a dark theme for the default Simpl theme. You can add your own themes by adding new styling and adding it to the `themes` array in the `theme.js` file.

The default dark theme that is included also supports the `cookies` add-on and the `auth` add-on.

### Requirements

* Icons add-on

### Installation

1. Copy the `public` folder to your Simpl project's folder
2. Copy the contents of the `js/main.js` file to the `js/main.js` file of your project
3. Copy the `scss/themes` folder to the `scss` folder of your project
4. Copy the dark theme colors from the `scss/config/vars/_colors.scss` file to the `scss/config/vars/_colors.scss` file of your project
5. Copy the contents of the `scss/views/parts/header.scss` file to the `scss/views/parts/header.scss` file of your project and paste it within the `div.nav-options` selector, **underneath** the breakpoint include
6. Copy the import for the themes from the `scss/main.scss` file to the `scss/main.scss` file of your project, as the last import
7. Copy the contents of the `views/parts/header.phtml` file to the `views/parts/header.phtml` file of your project and paste it within the `div.row` tag in the `div.nav-options` tag, underneath the breakpoint include
8. Copy the contents of the `views/parts/top.phtml` file to the `views/parts/top.phtml` file of your project, **underneath** the `<noscript>` tag
