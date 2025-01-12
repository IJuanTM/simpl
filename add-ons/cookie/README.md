## Cookie

### Description

This add-on adds a cookie alert to the website. The alert is displayed when the user visits the website and as long as the cookies has not been accepted. By clicking on the "Ok" button, the cookies are accepted and the alert is no longer displayed. Makes use of LocalStorage to store the cookies acceptance.

### Installation

1. Copy the `ts/cookie.ts` file to the `ts` folder of your Simpl project's folder
2. Copy the contents of the `ts/main.ts` file to the `ts/main.ts` file of your project
3. Copy the `scss/views/parts/cookie.scss` file to the `scss/views/part` folder of your Simpl project's folder
4. Copy the import statement from within the `scss/main.scss` file to the `scss/main.scss` file of your project
5. Copy the `view/parts/cookie.phtml` file to the `view/part` folder of your project
6. Copy the line from the `view/parts/bottom.phtml` file to the `view/parts/bottom.phtml` file of your project, after where the footer partial is included
